<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $directory,
    ) {
    }

    public function migrate(): array
    {
        $this->ensureMigrationTable();

        $applied = $this->appliedMigrations();
        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $result = [];

        foreach ($files as $file) {
            $version = basename($file, '.sql');
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException('Could not read migration: ' . $file);
            }

            $checksum = hash('sha256', $sql);

            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version], $checksum)) {
                    throw new RuntimeException('Applied migration changed: ' . $version);
                }

                $result[] = [$version, 'already-applied'];
                continue;
            }

            if ($version === '0001_baseline' && $this->tableExists('accounts')) {
                $this->record($version, $checksum);
                $result[] = [$version, 'adopted-existing-schema'];
                continue;
            }

            $this->database->exec($sql);
            $this->record($version, $checksum);
            $result[] = [$version, 'applied'];
        }

        return $result;
    }

    private function ensureMigrationTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function appliedMigrations(): array
    {
        $rows = $this->database
            ->query('SELECT version, checksum FROM schema_migrations ORDER BY version')
            ->fetchAll();

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
        $statement->execute([
            'version' => $version,
            'checksum' => $checksum,
        ]);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*)
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
