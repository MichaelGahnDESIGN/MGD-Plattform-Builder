<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Jobs;

use PDO;

final class Outbox
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function enqueue(string $topic, array $payload, ?\DateTimeImmutable $availableAt = null): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO jobs_outbox
                (topic, payload_json, status, available_at, attempts, created_at)
             VALUES
                (:topic, :payload_json, :status, :available_at, 0, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'topic' => $topic,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'available_at' => ($availableAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function next(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $statement = $this->database->prepare(
            "SELECT id, topic, payload_json, attempts
               FROM jobs_outbox
              WHERE status = 'pending'
                AND available_at <= UTC_TIMESTAMP()
              ORDER BY id
              LIMIT {$limit}"
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function markDone(int $id): void
    {
        $statement = $this->database->prepare(
            "UPDATE jobs_outbox
                SET status = 'done', processed_at = UTC_TIMESTAMP()
              WHERE id = :id"
        );
        $statement->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $statement = $this->database->prepare(
            "UPDATE jobs_outbox
                SET attempts = attempts + 1,
                    last_error = :error,
                    available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL LEAST(POW(2, attempts + 1), 60) MINUTE)
              WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'error' => mb_substr($error, 0, 1000),
        ]);
    }
}
