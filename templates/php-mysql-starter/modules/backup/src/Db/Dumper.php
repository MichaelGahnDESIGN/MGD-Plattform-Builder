<?php

declare(strict_types=1);

namespace MGD\Backup\Db;

use Closure;
use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Format\DbStreamWriter;
use MGD\Backup\Format\PartWriter;
use PDO;
use PDOException;
use Throwable;

/**
 * Schreibt die Datenbank teilweise (ein Teil je Aufruf) als gzip-NDJSON in einen PartWriter. Reines PDO, kein mysqldump,
 * kein exec(). Die Tabellenliste kommt aus dem Schema; nicht gesichert werden alle Tabellen mit Praefix backup_ und die
 * uebergebenen Ausschluesse. Generierte Spalten werden ausgelassen.
 *
 * Paginierung (Seiten zu 200 Zeilen), stabil ueber mehrere Teile und Aufrufe:
 *  - Primaerschluessel mit 1..n Spalten: Keyset ueber das Tupel aller PK-Spalten (`(a > x) OR (a = x AND b > y) ...`,
 *    sortiert nach allen PK-Spalten). Kein Offset, daher unempfindlich gegen parallele Schreibzugriffe.
 *  - Tabellen OHNE Primaerschluessel: Sortierung nach allen Spalten mit Offset-Fallback (nur stabil, solange sich die
 *    Tabelle nicht aendert; best effort).
 *
 * Der Zustand (JSON im Lauf): tables (feste Liste), excluded, i (Index der aktuellen Tabelle), last (letzter Schluessel),
 * off (Offset im Offset-Modus), stats (Tabelle => Zeilen), consistent.
 *
 * Konsistenz: beginSnapshot()/endSnapshot() klammern einen Cron-Aufruf. MySQL/MariaDB: eigene zweite Verbindung mit
 * START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY. Dauert der Dump laenger als ein Aufruf, ist er nur tabellenweise
 * konsistent (db_consistency = per_tick, mit Warnung). SQLite (Tests) hat keinen Snapshot.
 */
final class Dumper
{
    public const PAGE_ROWS = 200;

    private const NAME_REGEX = '/^[A-Za-z0-9_]{1,64}$/D';

    private bool $snapshotOpen = false;

    private ?PDO $snapshot = null;

    /** @var array<string,array{columns:list<string>,keys:list<string>}> */
    private array $meta = [];

    /**
     * @param list<string> $exclude zusaetzlich auszuschliessende Tabellen
     * @param (Closure():PDO)|null $snapshotFactory liefert eine NEUE Verbindung fuer die Lesesicht (nur MySQL/MariaDB)
     */
    public function __construct(private readonly PDO $pdo, private readonly array $exclude = [], private readonly ?Closure $snapshotFactory = null)
    {
    }

    public function isExcluded(string $table): bool
    {
        return self::isBackupTable($table) || in_array($table, $this->exclude, true);
    }

    /** @return list<string> die zusaetzlich zu backup_* ausgeschlossenen Tabellen */
    public function excludedNames(): array
    {
        return array_values($this->exclude);
    }

    public static function isBackupTable(string $table): bool
    {
        return str_starts_with($table, 'backup_');
    }

    public function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** Versionstext des Datenbankservers (fuer das Manifest). */
    public function serverVersion(): string
    {
        try {
            return $this->driver() === 'sqlite' ? 'sqlite ' . (string) $this->pdo->query('SELECT sqlite_version()')->fetchColumn()
                : (string) $this->pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /**
     * @return array{tables:list<string>,excluded:list<string>}
     */
    public function plan(): array
    {
        $sql = $this->driver() === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            : "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'";
        $names = array_map('strval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
        sort($names, SORT_STRING);

        $tables = [];
        $excluded = [];

        foreach ($names as $name) {
            if ($this->isExcluded($name)) {
                $excluded[] = $name;
            } elseif (preg_match(self::NAME_REGEX, $name) !== 1) {
                throw new BackupException('database_error'); // ungewöhnlicher Tabellenname: lieber abbrechen als still auslassen
            } else {
                $tables[] = $name;
            }
        }

        return ['tables' => $tables, 'excluded' => array_values(array_filter($excluded, static fn (string $n): bool => preg_match(self::NAME_REGEX, $n) === 1))];
    }

    /** @return array<string,mixed> */
    public function initialState(): array
    {
        $plan = $this->plan();

        return ['tables' => $plan['tables'], 'excluded' => $plan['excluded'], 'i' => 0, 'last' => null, 'off' => 0, 'stats' => [], 'consistent' => true];
    }

    /** Beginnt die konsistente Lesesicht fuer diesen Aufruf (idempotent). */
    public function beginSnapshot(): void
    {
        if ($this->snapshotOpen) {
            return;
        }

        $this->snapshotOpen = true;

        if ($this->driver() !== 'mysql' || $this->snapshotFactory === null) {
            return;
        }

        try {
            $connection = ($this->snapshotFactory)();
            $connection->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $connection->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        } catch (Throwable) {
            $this->snapshotOpen = false;

            throw new BackupException('database_error');
        }

        $this->snapshot = $connection;
    }

    public function endSnapshot(): void
    {
        $this->snapshotOpen = false;

        if ($this->snapshot === null) {
            return;
        }

        try {
            $this->snapshot->exec('COMMIT');
        } catch (Throwable) {
            // Eine nicht mehr offene Lesetransaktion ist harmlos.
        }

        $this->snapshot = null;
    }

    public function hasSnapshot(): bool
    {
        return $this->snapshot !== null;
    }

    /** Kann dieser Dumper eine konsistente Lesesicht aufbauen (MySQL/MariaDB mit zweiter Verbindung)? */
    public function supportsSnapshot(): bool
    {
        return $this->driver() === 'mysql' && $this->snapshotFactory !== null;
    }

    /** Grobe Groesse der Datenbank in Bytes (MySQL: information_schema, SQLite: Seiten x Seitengroesse); 0 bei Fehlern. */
    public function estimateBytes(): int
    {
        try {
            if ($this->driver() === 'sqlite') {
                return (int) $this->pdo->query('SELECT (SELECT page_count FROM pragma_page_count()) * (SELECT page_size FROM pragma_page_size())')->fetchColumn();
            }

            return (int) $this->pdo->query(
                'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            )->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Schreibt Tabellen ab dem Zustand in den Writer, bis die Teilgroesse (gemessen an den geschriebenen Chiffre-Bytes) oder
     * das Zeitbudget erreicht ist; mindestens eine Seite je Aufruf. Der DbStreamWriter wird beendet, der PartWriter NICHT
     * (finish() macht der Aufrufer).
     *
     * @param array<string,mixed> $state leer = Anfang
     * @return array{state:array<string,mixed>,done:bool,rows:int}
     */
    public function writeNextPart(PartWriter $writer, array $state, Budget $budget, int $limitBytes): array
    {
        if ($state === [] || !isset($state['tables'])) {
            $state = $this->initialState();
        }

        $ownSnapshot = !$this->snapshotOpen;

        if ($ownSnapshot) {
            $this->beginSnapshot();
        }

        try {
            return $this->write($writer, $state, $budget, $limitBytes);
        } catch (PDOException) {
            throw new BackupException('database_error');
        } finally {
            if ($ownSnapshot) {
                $this->endSnapshot();
            }
        }
    }

    /**
     * @param array<string,mixed> $state
     * @return array{state:array<string,mixed>,done:bool,rows:int}
     */
    private function write(PartWriter $writer, array $state, Budget $budget, int $limitBytes): array
    {
        $stream = new DbStreamWriter($writer->write(...));
        $tables = $state['tables'];
        $count = count($tables);
        $partRows = 0;
        $first = true;
        $reached = static fn (): bool => $budget->exhausted() || $writer->bytesWritten() >= $limitBytes;

        while ($state['i'] < $count) {
            if (!$first && $reached()) {
                break;
            }

            $first = false;
            $table = $tables[$state['i']];
            $meta = $this->tableMeta($table);
            $stream->beginTable($table, $meta['columns']);
            $state['stats'][$table] ??= 0;
            $tableDone = false;

            while (true) {
                $rows = $this->fetchPage($table, $meta, $state);
                $n = 0;

                foreach ($rows as $row) {
                    $values = [];

                    foreach ($meta['columns'] as $column) {
                        $values[] = self::value($row[$column] ?? null);
                    }

                    $stream->row($values);
                    $n++;

                    if ($meta['keys'] !== []) {
                        $state['last'] = array_map(static fn (string $k): mixed => $row[$k], $meta['keys']);
                    }
                }

                if ($meta['keys'] === []) {
                    $state['off'] += $n;
                }

                $state['stats'][$table] += $n;
                $partRows += $n;

                if ($n < self::PAGE_ROWS) {
                    $tableDone = true;
                    break;
                }

                if ($reached()) {
                    break;
                }
            }

            $stream->endTable();

            if ($tableDone) {
                $state['i']++;
                $state['last'] = null;
                $state['off'] = 0;
            }
        }

        $stream->finish();

        return ['state' => $state, 'done' => $state['i'] >= $count, 'rows' => $partRows];
    }

    /**
     * @param array{columns:list<string>,keys:list<string>} $meta
     * @param array<string,mixed> $state
     * @return list<array<string,mixed>>
     */
    private function fetchPage(string $table, array $meta, array $state): array
    {
        $columns = implode(', ', array_map($this->quote(...), $meta['columns']));
        $sql = 'SELECT ' . $columns . ' FROM ' . $this->quote($table);
        $params = [];

        if ($meta['keys'] !== []) {
            $keys = $meta['keys'];
            $last = $state['last'];

            if (is_array($last) && count($last) === count($keys)) {
                $alternatives = [];

                foreach ($keys as $i => $key) {
                    $terms = [];

                    for ($j = 0; $j < $i; $j++) {
                        $terms[] = $this->quote($keys[$j]) . ' = ?';
                        $params[] = $last[$j];
                    }

                    $terms[] = $this->quote($key) . ' > ?';
                    $params[] = $last[$i];
                    $alternatives[] = '(' . implode(' AND ', $terms) . ')';
                }

                $sql .= ' WHERE ' . implode(' OR ', $alternatives);
            }

            $sql .= ' ORDER BY ' . implode(', ', array_map($this->quote(...), $keys)) . ' LIMIT ' . self::PAGE_ROWS;
        } else {
            $sql .= ' ORDER BY ' . $columns . ' LIMIT ' . self::PAGE_ROWS . ' OFFSET ' . (int) $state['off'];
        }

        $statement = $this->reader()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Spalten (ohne generierte) und Schluesselspalten einer Tabelle.
     *
     * @return array{columns:list<string>,keys:list<string>}
     */
    private function tableMeta(string $table): array
    {
        if (isset($this->meta[$table])) {
            return $this->meta[$table];
        }

        $columns = [];
        $keys = [];

        if ($this->driver() === 'sqlite') {
            $info = $this->pdo->query('PRAGMA table_xinfo(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
            $primary = [];

            foreach ($info as $column) {
                if ((int) ($column['hidden'] ?? 0) >= 2) {
                    continue; // generierte Spalte
                }

                if ((int) ($column['hidden'] ?? 0) === 1) {
                    continue; // versteckte (virtuelle Tabelle)
                }

                $columns[] = (string) $column['name'];

                if ((int) $column['pk'] > 0) {
                    $primary[(int) $column['pk']] = (string) $column['name'];
                }
            }

            ksort($primary);
            $keys = array_values($primary);
        } else {
            $statement = $this->pdo->prepare(
                'SELECT COLUMN_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
            );
            $statement->execute([$table]);

            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (($column['GENERATION_EXPRESSION'] ?? '') === '' || $column['GENERATION_EXPRESSION'] === null) {
                    $columns[] = (string) $column['COLUMN_NAME'];
                }
            }

            $statement = $this->pdo->prepare(
                "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION"
            );
            $statement->execute([$table]);
            $keys = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        }

        foreach ($columns as $column) {
            if (preg_match(self::NAME_REGEX, $column) !== 1) {
                throw new BackupException('database_error');
            }
        }

        if ($columns === [] || ($keys !== [] && array_diff($keys, $columns) !== [])) {
            $keys = [];
        }

        return $this->meta[$table] = ['columns' => $columns, 'keys' => $keys];
    }

    /** Fliesskommazahlen verlustfrei (kuerzeste Darstellung), alles andere unveraendert (DbStreamWriter wandelt in Text). */
    private static function value(mixed $value): mixed
    {
        return is_float($value) ? (string) json_encode($value) : $value;
    }

    private function reader(): PDO
    {
        return $this->snapshot ?? $this->pdo;
    }

    private function quote(string $name): string
    {
        if (preg_match(self::NAME_REGEX, $name) !== 1) {
            throw new BackupException('database_error');
        }

        return $this->driver() === 'sqlite' ? '"' . $name . '"' : '`' . $name . '`';
    }
}
