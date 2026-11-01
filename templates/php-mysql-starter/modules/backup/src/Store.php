<?php

declare(strict_types=1);

namespace MGD\Backup;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

/**
 * Datenzugriff auf die backup_*-Tabellen (nur vorbereitete Statements, nur Spalten aus Whitelists). Alle Zeiten sind UTC im
 * Format 'Y-m-d H:i:s' und kommen aus PHP (nicht aus SQL), damit dieselben Abfragen auf MySQL/MariaDB und SQLite (Tests) laufen.
 */
final class Store
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    private const SETTINGS_COLUMNS = [
        'schedule_interval', 'schedule_kind', 'schedule_time', 'schedule_weekday', 'schedule_monthday', 'next_run_at', 'max_age_days',
        'part_size_mb', 'areas_json', 'exclude_patterns', 'primary_key_text', 'primary_key_fp', 'emergency_key_text', 'emergency_key_fp',
        'signing_secret_enc', 'signing_public', 'signing_fp', 'cron_token_hash', 'cron_fail_count', 'cron_fail_since',
        'stepup_fail_count', 'stepup_fail_since', 'last_tick_at', 'last_success_at', 'updated_by',
    ];

    private const DESTINATION_COLUMNS = [
        'dest_type', 'name', 'enabled', 'keep_count', 'config_json', 'secret_enc', 'status', 'last_test_at', 'last_test_ok', 'last_error_code',
    ];

    private const RUN_COLUMNS = [
        'status', 'phase', 'active_slot', 'started_at', 'heartbeat_at', 'finished_at', 'cancel_requested_at', 'cursor_json', 'db_consistency',
        'key_fps', 'signer_fp', 'app_version', 'db_tables', 'db_rows', 'file_count', 'files_bytes', 'cipher_bytes', 'parts', 'manifest_sha256',
        'error_code', 'warnings_json', 'local_deleted_at',
    ];

    private const RUN_DESTINATION_COLUMNS = ['status', 'remote_folder_ref', 'error_code', 'attempts', 'next_attempt_at', 'completed_at', 'delete_after'];

    private const UPLOAD_COLUMNS = ['status', 'attempts', 'next_attempt_at', 'remote_ref', 'resume_state', 'bytes_done', 'error_code', 'uploaded_at'];

    /** @var Closure():DateTimeImmutable */
    private readonly Closure $clock;

    /** @param (Closure():DateTimeImmutable)|null $clock */
    public function __construct(private readonly PDO $pdo, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function now(): DateTimeImmutable
    {
        return ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
    }

    public function nowString(): string
    {
        return $this->now()->format(self::DATE_FORMAT);
    }

    public static function toDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value, new DateTimeZone('UTC'));

        return $date === false ? null : $date;
    }

    public function transaction(Closure $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    // ------------------------------------------------------------------ Einstellungen

    /** @return array<string,mixed> */
    public function settings(): array
    {
        $row = $this->pdo->query('SELECT * FROM backup_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new BackupException('database_error');
        }

        return $row;
    }

    /** @param array<string,mixed> $fields */
    public function updateSettings(array $fields): void
    {
        $fields['updated_at'] = $this->nowString();
        $this->update('backup_settings', 1, $fields, [...self::SETTINGS_COLUMNS, 'updated_at']);
    }

    // ------------------------------------------------------------------ Ziele

    /** @return list<array<string,mixed>> */
    public function destinations(bool $onlyEnabled = false): array
    {
        $sql = 'SELECT * FROM backup_destinations' . ($onlyEnabled ? ' WHERE enabled = 1' : '') . ' ORDER BY id';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function destination(int $id): ?array
    {
        return $this->one('SELECT * FROM backup_destinations WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $fields */
    public function createDestination(array $fields, ?int $userId): int
    {
        $now = $this->nowString();
        $fields += ['enabled' => 0, 'keep_count' => 7, 'status' => 'unconfigured'];
        $values = $this->filter($fields, self::DESTINATION_COLUMNS);
        $values['created_by'] = $userId;
        $values['created_at'] = $now;
        $values['updated_at'] = $now;

        return $this->insert('backup_destinations', $values);
    }

    /** @param array<string,mixed> $fields */
    public function updateDestination(int $id, array $fields): void
    {
        $fields['updated_at'] = $this->nowString();
        $this->update('backup_destinations', $id, $fields, [...self::DESTINATION_COLUMNS, 'updated_at']);
    }

    public function deleteDestination(int $id): void
    {
        $this->pdo->prepare('DELETE FROM backup_destinations WHERE id = ?')->execute([$id]);
    }

    public function destinationInUse(int $id): bool
    {
        return $this->one('SELECT 1 AS used FROM backup_run_destinations WHERE destination_id = ? AND status IN (\'pending\', \'done\', \'deleting\') LIMIT 1', [$id]) !== null;
    }

    // ------------------------------------------------------------------ Laeufe

    /** @param array<string,mixed> $fields uuid, kind, trigger_type, folder_name, requested_by */
    public function createRun(array $fields): int
    {
        $values = [
            'uuid' => $fields['uuid'],
            'kind' => $fields['kind'],
            'trigger_type' => $fields['trigger_type'],
            'status' => 'queued',
            'phase' => 'queued',
            'requested_by' => $fields['requested_by'] ?? null,
            'folder_name' => $fields['folder_name'],
            'queued_at' => $this->nowString(),
            'active_slot' => 1,
        ];

        return $this->insert('backup_runs', $values);
    }

    /** @return array<string,mixed>|null */
    public function run(int $id): ?array
    {
        return $this->one('SELECT * FROM backup_runs WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function runByFolder(string $folder): ?array
    {
        return $this->one('SELECT * FROM backup_runs WHERE folder_name = ?', [$folder]);
    }

    /** Der aktive Lauf (queued/running) oder null. Hoechstens einer: active_slot ist eindeutig. */
    public function activeRun(): ?array
    {
        return $this->one('SELECT * FROM backup_runs WHERE active_slot = 1 ORDER BY id LIMIT 1', []);
    }

    /** @param array<string,mixed> $fields */
    public function updateRun(int $id, array $fields): void
    {
        $this->update('backup_runs', $id, $fields, self::RUN_COLUMNS);
    }

    /** @return list<array<string,mixed>> */
    public function runs(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));

        return $this->pdo->query('SELECT * FROM backup_runs ORDER BY id DESC LIMIT ' . $limit)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteRun(int $id): void
    {
        foreach ($this->parts($id) as $part) {
            $this->pdo->prepare('DELETE FROM backup_uploads WHERE part_id = ?')->execute([$part['id']]);
        }

        foreach (['backup_parts', 'backup_run_destinations'] as $table) {
            $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE run_id = ?')->execute([$id]);
        }

        $this->pdo->prepare('DELETE FROM backup_runs WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------------ Teile

    /** @return list<array<string,mixed>> */
    public function parts(int $runId): array
    {
        return $this->all('SELECT * FROM backup_parts WHERE run_id = ? ORDER BY seq', [$runId]);
    }

    /** @return array<string,mixed>|null */
    public function part(int $runId, int $seq): ?array
    {
        return $this->one('SELECT * FROM backup_parts WHERE run_id = ? AND seq = ?', [$runId, $seq]);
    }

    /** @param array<string,mixed> $fields */
    public function addPart(int $runId, array $fields): int
    {
        return $this->insert('backup_parts', [
            'run_id' => $runId,
            'seq' => $fields['seq'],
            'part_kind' => $fields['part_kind'],
            'file_name' => $fields['file_name'],
            'status' => 'ready',
            'plain_bytes' => $fields['plain_bytes'],
            'cipher_bytes' => $fields['cipher_bytes'],
            'cipher_sha256' => $fields['cipher_sha256'],
        ]);
    }

    public function markPartDeleted(int $partId): void
    {
        $this->pdo->prepare("UPDATE backup_parts SET status = 'deleted' WHERE id = ?")->execute([$partId]);
    }

    // ------------------------------------------------------------------ Ziel je Lauf

    /** @return list<array<string,mixed>> */
    public function runDestinations(int $runId): array
    {
        return $this->all('SELECT * FROM backup_run_destinations WHERE run_id = ? ORDER BY id', [$runId]);
    }

    /** @return array<string,mixed>|null */
    public function runDestination(int $runId, int $destinationId): ?array
    {
        return $this->one('SELECT * FROM backup_run_destinations WHERE run_id = ? AND destination_id = ?', [$runId, $destinationId]);
    }

    public function addRunDestination(int $runId, int $destinationId): int
    {
        return $this->insert('backup_run_destinations', ['run_id' => $runId, 'destination_id' => $destinationId, 'status' => 'pending']);
    }

    /** @param array<string,mixed> $fields */
    public function updateRunDestination(int $id, array $fields): void
    {
        $this->update('backup_run_destinations', $id, $fields, self::RUN_DESTINATION_COLUMNS);
    }

    /**
     * Fertige Laeufe (status completed/partial) mit ihrem Ziel-Status, neueste zuerst; Grundlage der Aufbewahrung.
     *
     * @return list<array<string,mixed>>
     */
    public function completedRunDestinations(int $destinationId): array
    {
        return $this->all(
            "SELECT rd.id AS rd_id, rd.run_id, rd.status AS rd_status, r.kind, r.finished_at, r.status AS run_status
               FROM backup_run_destinations rd JOIN backup_runs r ON r.id = rd.run_id
              WHERE rd.destination_id = ? AND rd.status = 'done' AND r.status IN ('completed', 'partial')
              ORDER BY r.finished_at DESC, r.id DESC",
            [$destinationId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function deletingRunDestinations(): array
    {
        return $this->all("SELECT * FROM backup_run_destinations WHERE status = 'deleting' ORDER BY id", []);
    }

    /** Fehlgeschlagene Ziele eines Laufs, deren bereits hochgeladene Teile noch aufzuraeumen sind. @return list<array<string,mixed>> */
    public function failedWithCleanup(): array
    {
        return $this->all("SELECT * FROM backup_run_destinations WHERE status = 'failed' AND delete_after IS NOT NULL ORDER BY id", []);
    }

    // ------------------------------------------------------------------ Uploads

    /** @return list<array<string,mixed>> */
    public function uploadsOfRun(int $runId): array
    {
        return $this->all(
            'SELECT u.*, p.seq, p.file_name, p.cipher_bytes, p.cipher_sha256 FROM backup_uploads u JOIN backup_parts p ON p.id = u.part_id
              WHERE p.run_id = ? ORDER BY p.seq, u.destination_id',
            [$runId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function uploadsOfRunDestination(int $runId, int $destinationId): array
    {
        return $this->all(
            'SELECT u.*, p.seq, p.file_name, p.cipher_bytes FROM backup_uploads u JOIN backup_parts p ON p.id = u.part_id
              WHERE p.run_id = ? AND u.destination_id = ? ORDER BY p.seq',
            [$runId, $destinationId]
        );
    }

    public function addUpload(int $partId, int $destinationId): int
    {
        return $this->insert('backup_uploads', ['part_id' => $partId, 'destination_id' => $destinationId, 'status' => 'pending']);
    }

    /** @param array<string,mixed> $fields */
    public function updateUpload(int $id, array $fields): void
    {
        $this->update('backup_uploads', $id, $fields, self::UPLOAD_COLUMNS);
    }

    // ------------------------------------------------------------------ Hilfen

    /**
     * @param list<mixed> $params
     * @return array<string,mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string,mixed>>
     */
    private function all(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,mixed> $fields
     * @param list<string> $allowed
     * @return array<string,mixed>
     */
    private function filter(array $fields, array $allowed): array
    {
        return array_intersect_key($fields, array_flip($allowed));
    }

    /** @param array<string,mixed> $values */
    private function insert(string $table, array $values): int
    {
        $columns = array_keys($values);

        foreach ($columns as $column) {
            if (preg_match('/^[a-z_0-9]{1,40}$/D', (string) $column) !== 1) {
                throw new BackupException('database_error');
            }
        }

        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        try {
            $this->pdo->prepare($sql)->execute($this->bind($values));
        } catch (PDOException) {
            throw new BackupException('database_error');
        }

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $fields
     * @param list<string> $allowed
     */
    private function update(string $table, int $id, array $fields, array $allowed): void
    {
        $values = $this->filter($fields, $allowed);

        if ($values === []) {
            return;
        }

        $sets = implode(', ', array_map(static fn (string $column): string => $column . ' = ?', array_keys($values)));

        try {
            $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $sets . ' WHERE id = ?')->execute([...$this->bind($values), $id]);
        } catch (PDOException) {
            throw new BackupException('database_error');
        }
    }

    /**
     * @param array<string,mixed> $values
     * @return list<mixed>
     */
    private function bind(array $values): array
    {
        return array_map(static fn (mixed $value): mixed => is_bool($value) ? (int) $value : $value, array_values($values));
    }
}
