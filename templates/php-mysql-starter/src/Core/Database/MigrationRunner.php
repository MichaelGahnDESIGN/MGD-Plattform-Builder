<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $directory,
        private readonly string $scope,
    ) {
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();
        $applied = $this->appliedMigrations();
        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $result = [];

        foreach ($files as $file) {
            $version = $this->scope . '/' . basename($file, '.sql');
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException('Migration nicht lesbar: ' . $file);
            }

            $checksum = hash('sha256', $sql);

            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version], $checksum)) {
                    throw new RuntimeException('Bereits angewendete Migration wurde verändert: ' . $version);
                }

                $result[] = [$version, 'already-applied'];
                continue;
            }

            foreach (self::splitStatements($sql) as $statement) {
                $this->database->exec($statement);
            }

            $this->record($version, $checksum);
            $result[] = [$version, 'applied'];
        }

        return $result;
    }

    /**
     * Zerlegt SQL an Semikolons am Zeilenende. Migrationen dürfen daher keine
     * Semikolons am Zeilenende innerhalb von Zeichenketten enthalten.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $lines = array_filter($lines, static fn (string $line): bool => !str_starts_with(ltrim($line), '--'));
        $parts = preg_split('/;\s*(?:\R|$)/', implode("\n", $lines)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private function ensureMigrationTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function appliedMigrations(): array
    {
        $rows = $this->database->query('SELECT version, checksum FROM schema_migrations')->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['version']] = (string) $row['checksum'];
        }

        return $result;
    }

    private function record(string $version, string $checksum): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO schema_migrations (version, checksum, applied_at)
             VALUES (:version, :checksum, UTC_TIMESTAMP())'
        );
        $statement->execute(['version' => $version, 'checksum' => $checksum]);
    }
}
