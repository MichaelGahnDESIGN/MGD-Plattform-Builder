<?php

declare(strict_types=1);

namespace MGD\Backup\Restore;

use MGD\Backup\BackupException;
use MGD\Backup\Db\Dumper;
use MGD\Backup\Db\Importer;
use MGD\Backup\Db\NdjsonFile;
use MGD\Backup\Format\BackupFormat;
use PDO;

/**
 * Spielt ein ENTSCHLUESSELTES Backup (Ausgabe von `mgd-backup.php decrypt`) wieder ein. Es gibt keinen Web-Restore und der
 * private Schluessel muss dafuer nie auf den Server: Pruefen und Entschluesseln passiert auf dem eigenen Rechner (Signatur,
 * Hashes, Zeilenzahlen), erst der Klartext-Ordner geht an den Server.
 *
 * Reihenfolge und Schutz:
 *  1. preflight(): liest die ganze Datenbankdatei trocken durch (Struktur, Zeilenzahlen je Tabelle), prueft, dass jede Tabelle
 *     und Spalte im Ziel existiert, keine backup_*-Tabelle beschrieben wird und die Migrationen zusammenpassen. Es wird nichts
 *     geschrieben; Fehler hier hinterlassen Datenbank und Dateien unveraendert.
 *  2. restoreDatabase(): ersetzt die Zeilen aller gesicherten Tabellen in EINER Transaktion (bei Fehler Rollback).
 *     backup_*-Tabellen bleiben unberuehrt.
 *  3. restoreFiles(): kopiert jeden Bereich zuerst in einen Staging-Ordner neben dem Bereich (nur Pfade nach Whitelist, keine
 *     Symlinks) und tauscht ihn dann per rename gegen den aktuellen Bereich. Der bisherige Ordner bleibt als
 *     <bereich>.pre-restore-<zeit> liegen und wird nie automatisch geloescht.
 */
final class Restorer
{
    /**
     * @param array<string,string> $areas Bereich => aufgeloester Zielordner (AreaPolicy::resolve)
     * @param list<string> $excludeTables
     * @param list<string> $currentMigrations angewendete Migrationen der laufenden Installation (Schreibweise wie im Manifest)
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $areas,
        private readonly array $excludeTables,
        private readonly array $currentMigrations = [],
    ) {
    }

    /**
     * @return array{tables:int,rows:int,table_rows:array<string,int>,code:string,areas:list<string>,skipped_areas:list<string>}
     */
    public function preflight(string $dir, bool $checkSchema = true): array
    {
        $dir = rtrim($dir, '/');

        if (!is_dir($dir) || is_link($dir)) {
            throw new BackupException('path_invalid');
        }

        $dbFile = $dir . '/db.ndjson.gz';
        $tableRows = [];

        if (is_file($dbFile)) {
            $columnsOf = [];

            foreach (NdjsonFile::events($dbFile) as $event) {
                if ($event['type'] === 'table') {
                    $table = (string) $event['table'];

                    if (Dumper::isBackupTable($table) || in_array($table, $this->excludeTables, true)) {
                        throw new BackupException('database_error');
                    }

                    $columnsOf[$table] ??= $this->columns($table);

                    if ($columnsOf[$table] === [] || array_diff($event['columns'], $columnsOf[$table]) !== []) {
                        throw new BackupException('database_error');
                    }
                } elseif ($event['type'] === 'end') {
                    $tableRows[$event['table']] = ($tableRows[$event['table']] ?? 0) + $event['rows'];
                }
            }

            if ($checkSchema) {
                $this->assertSameMigrations($tableRows, $dbFile);
            }
        }

        $found = [];
        $skipped = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'db.ndjson.gz' || $entry === 'keys.env') {
                continue;
            }

            if (is_dir($dir . '/' . $entry) && !is_link($dir . '/' . $entry)) {
                if (isset($this->areas[$entry])) {
                    $found[] = $entry;
                } else {
                    $skipped[] = $entry;
                }
            } else {
                $skipped[] = $entry;
            }
        }

        sort($found);
        sort($skipped);

        return [
            'tables' => count($tableRows),
            'rows' => array_sum($tableRows),
            'table_rows' => $tableRows,
            'code' => is_file($dbFile) ? substr((string) hash_file('sha256', $dbFile), 0, 8) : substr(hash('sha256', implode(',', $found)), 0, 8),
            'areas' => $found,
            'skipped_areas' => $skipped,
        ];
    }

    /**
     * Ersetzt die Zeilen aller Tabellen aus der Sicherung. Nur nach erfolgreichem preflight() aufrufen.
     *
     * @return array<string,int> Tabelle => Zeilen
     */
    public function restoreDatabase(string $dir): array
    {
        $file = rtrim($dir, '/') . '/db.ndjson.gz';
        $transactional = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        if ($transactional) {
            $this->pdo->beginTransaction();
        }

        try {
            $totals = (new Importer($this->pdo, $this->excludeTables))->import(NdjsonFile::events($file));

            if ($transactional && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transactional && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e instanceof BackupException ? $e : new BackupException('database_error');
        }

        return $totals;
    }

    /**
     * Tauscht die Dateibereiche aus. Der bisherige Bereich bleibt als <bereich>.pre-restore-<zeit> erhalten.
     *
     * @return array<string,array{files:int,previous:string}>
     */
    public function restoreFiles(string $dir, ?string $stamp = null): array
    {
        $dir = rtrim($dir, '/');
        $stamp ??= gmdate('Ymd\THis');
        $result = [];

        // Erst alle Bereiche in Staging-Ordner kopieren (nichts Bestehendes wird angefasst) ...
        $staged = [];

        try {
            foreach ($this->areas as $name => $target) {
                if (!is_dir($dir . '/' . $name) || is_link($dir . '/' . $name)) {
                    continue;
                }

                $staging = $target . '.restore-' . $stamp;

                if (file_exists($staging) || is_link($staging)) {
                    throw new BackupException('io_error');
                }

                $staged[$name] = [$staging, 0]; // vor dem Kopieren eintragen, damit ein Abbruch es aufraeumt
                $count = $this->copyTree($dir . '/' . $name, $staging, $name);
                $staged[$name] = [$staging, $count];
            }
        } catch (\Throwable $e) {
            foreach ($staged as [$staging]) {
                self::removeTree($staging);
            }

            throw $e instanceof BackupException ? $e : new BackupException('io_error');
        }

        // ... dann tauschen.
        foreach ($staged as $name => [$staging, $count]) {
            $target = $this->areas[$name];
            $previous = $target . '.pre-restore-' . $stamp;

            if (!@rename($target, $previous) || !@rename($staging, $target)) {
                @rename($previous, $target); // Rueckweg, falls nur der erste Schritt klappte

                throw new BackupException('io_error');
            }

            $result[$name] = ['files' => $count, 'previous' => basename($previous)];
        }

        return $result;
    }

    // ------------------------------------------------------------------ intern

    /** @return list<string> */
    private function columns(string $table): array
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/D', $table) !== 1) {
            return [];
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA table_xinfo("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC);

            return array_values(array_map(static fn (array $r): string => (string) $r['name'], array_filter($rows, static fn (array $r): bool => (int) ($r['hidden'] ?? 0) === 0)));
        }

        $statement = $this->pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $statement->execute([$table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Die Migrationen in der Sicherung (Tabelle schema_migrations) muessen zur laufenden Installation passen, sonst wuerde
     * die Datenbank mit einem anderen Schemastand ueberschrieben. Bei Abweichung: Abbruch vor jedem Schreiben.
     *
     * @param array<string,int> $tableRows
     */
    private function assertSameMigrations(array $tableRows, string $dbFile): void
    {
        if (!isset($tableRows['schema_migrations']) || $this->currentMigrations === []) {
            return;
        }

        $backup = [];
        $inTable = false;
        $versionIndex = null;

        foreach (NdjsonFile::events($dbFile) as $event) {
            if ($event['type'] === 'table') {
                $inTable = $event['table'] === 'schema_migrations';
                $versionIndex = $inTable ? array_search('version', $event['columns'], true) : null;
            } elseif ($event['type'] === 'row' && $inTable && $versionIndex !== null && $versionIndex !== false) {
                $backup[] = (string) preg_replace('/[^0-9A-Za-z_]/', '_', (string) $event['values'][$versionIndex]);
            }
        }

        sort($backup);
        $current = $this->currentMigrations;
        sort($current);

        if ($backup !== $current) {
            throw new BackupException('format_error');
        }
    }

    /** Kopiert den Klartext-Baum eines Bereichs nach $staging (Rechte 0644/0755). Gibt die Zahl der Dateien zurueck. */
    private function copyTree(string $source, string $staging, string $area): int
    {
        $old = umask(0022);

        try {
            if (!@mkdir($staging, 0755)) {
                throw new BackupException('io_error');
            }

            return $this->copyDir($source, $staging, $area);
        } finally {
            umask($old);
        }
    }

    private function copyDir(string $source, string $target, string $relative): int
    {
        $count = 0;

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $from = $source . '/' . $entry;
            $to = $target . '/' . $entry;
            $path = $relative . '/' . $entry;

            if (is_link($from) || !BackupFormat::isSafeFilePath($path)) {
                throw new BackupException('path_invalid');
            }

            if (is_dir($from)) {
                if (!@mkdir($to, 0755)) {
                    throw new BackupException('io_error');
                }

                $count += $this->copyDir($from, $to, $path);
            } elseif (is_file($from)) {
                if (!@copy($from, $to)) {
                    throw new BackupException('io_error');
                }

                @chmod($to, 0644);
                $count++;
            } else {
                throw new BackupException('path_invalid');
            }
        }

        return $count;
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeTree($path . '/' . $entry);
            }
        }

        @rmdir($path);
    }
}
