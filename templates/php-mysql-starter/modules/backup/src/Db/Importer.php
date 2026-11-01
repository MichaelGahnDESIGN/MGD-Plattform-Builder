<?php

declare(strict_types=1);

namespace MGD\Backup\Db;

use MGD\Backup\BackupException;
use PDO;
use PDOException;

/**
 * Spielt die Ereignisse des DB-Stroms (Format\DbStreamReader::events() bzw. Db\NdjsonFile::events()) tabellenweise per
 * vorbereitetem INSERT ein. Gedacht fuer den CLI-Restore und fuer Tests.
 *
 *  - Die Fremdschluesselpruefung ist waehrend des Imports aus und wird danach (auch bei Fehlern) wieder eingeschaltet.
 *  - backup_*-Tabellen und ausgeschlossene Tabellen werden nie beschrieben; kommen sie im Strom vor, ist das ein Fehler
 *    (die backup_*-Zeilen bleiben so unberuehrt).
 *  - Beim ersten Auftreten einer Tabelle im Import werden ihre vorhandenen Zeilen geloescht (replace); weitere Teile
 *    derselben Tabelle haengen an.
 *  - Es werden nur Tabellen und Spalten geschrieben, die im Ziel existieren (sonst database_error).
 *
 * Der Restore laeuft im Wartungsmodus und ersetzt die Daten ohnehin vollstaendig; es gibt daher keine Gesamttransaktion.
 */
final class Importer
{
    private const SQLITE_MAX_PARAMS = 899;

    private const MYSQL_MAX_PARAMS = 60000;

    private const BATCH_BYTES = 1048576;

    private const BATCH_MAX_ROWS = 1000;

    private const NAME_REGEX = '/^[A-Za-z0-9_]{1,64}$/D';

    /** @var array<string,int> */
    private array $totals = [];

    /** @var array<string,true> */
    private array $seen = [];

    private ?string $table = null;

    /** @var list<string> */
    private array $columns = [];

    /** @var list<list<?string>> */
    private array $batch = [];

    private int $batchBytes = 0;

    /** @param list<string> $exclude */
    public function __construct(private readonly PDO $pdo, private readonly array $exclude = [])
    {
    }

    /**
     * @param iterable<array<string,mixed>> $events
     * @return array<string,int> Tabelle => Zeilen
     */
    public function import(iterable $events): array
    {
        $this->setForeignKeys(false);

        try {
            foreach ($events as $event) {
                match ($event['type'] ?? null) {
                    'table' => $this->beginTable((string) $event['table'], $event['columns']),
                    'row' => $this->addRow($event['values']),
                    'end' => $this->endTable((string) $event['table']),
                    default => throw new BackupException('database_error'),
                };
            }

            if ($this->table !== null) {
                throw new BackupException('database_error');
            }
        } catch (PDOException) {
            throw new BackupException('database_error');
        } finally {
            $this->table = null;
            $this->batch = [];
            $this->setForeignKeys(true);
        }

        return $this->totals;
    }

    /** @param list<string> $columns */
    private function beginTable(string $table, array $columns): void
    {
        if ($this->table !== null || Dumper::isBackupTable($table) || in_array($table, $this->exclude, true) || preg_match(self::NAME_REGEX, $table) !== 1) {
            throw new BackupException('database_error');
        }

        $existing = $this->columnsOf($table);

        if ($existing === [] || array_diff($columns, $existing) !== []) {
            throw new BackupException('database_error');
        }

        if (!isset($this->seen[$table])) {
            $this->seen[$table] = true;
            $this->totals[$table] = 0;
            $this->pdo->exec('DELETE FROM ' . $this->quote($table));
        }

        $this->table = $table;
        $this->columns = $columns;
        $this->batch = [];
        $this->batchBytes = 0;
    }

    /** @param list<?string> $values */
    private function addRow(array $values): void
    {
        if ($this->table === null || count($values) !== count($this->columns)) {
            throw new BackupException('database_error');
        }

        foreach ($values as $value) {
            $this->batchBytes += $value === null ? 1 : strlen($value);
        }

        $this->batch[] = $values;

        if (count($this->batch) >= $this->maxRows() || $this->batchBytes >= self::BATCH_BYTES) {
            $this->flush();
        }
    }

    private function endTable(string $table): void
    {
        if ($this->table !== $table) {
            throw new BackupException('database_error');
        }

        $this->flush();
        $this->table = null;
        $this->columns = [];
    }

    private function flush(): void
    {
        if ($this->batch === [] || $this->table === null) {
            return;
        }

        $columnCount = count($this->columns);
        $row = '(' . implode(', ', array_fill(0, $columnCount, '?')) . ')';
        $sql = 'INSERT INTO ' . $this->quote($this->table) . ' (' . implode(', ', array_map($this->quote(...), $this->columns)) . ') VALUES '
            . implode(', ', array_fill(0, count($this->batch), $row));
        $params = [];

        foreach ($this->batch as $values) {
            foreach ($values as $value) {
                $params[] = $value;
            }
        }

        $this->pdo->prepare($sql)->execute($params);
        $this->totals[$this->table] += count($this->batch);
        $this->batch = [];
        $this->batchBytes = 0;
    }

    private function maxRows(): int
    {
        $columns = max(1, count($this->columns));
        $max = $this->driver() === 'sqlite' ? self::SQLITE_MAX_PARAMS : self::MYSQL_MAX_PARAMS;

        return max(1, min(self::BATCH_MAX_ROWS, intdiv($max, $columns)));
    }

    /** @return list<string> */
    private function columnsOf(string $table): array
    {
        if ($this->driver() === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA table_xinfo(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);

            return array_values(array_map(static fn (array $r): string => (string) $r['name'], array_filter($rows, static fn (array $r): bool => (int) ($r['hidden'] ?? 0) === 0)));
        }

        $statement = $this->pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $statement->execute([$table]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function setForeignKeys(bool $on): void
    {
        if ($this->driver() === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ' . ($on ? 'ON' : 'OFF'));

            return;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = ' . ($on ? '1' : '0'));
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function quote(string $name): string
    {
        if (preg_match(self::NAME_REGEX, $name) !== 1) {
            throw new BackupException('database_error');
        }

        return $this->driver() === 'sqlite' ? '"' . $name . '"' : '`' . $name . '`';
    }
}
