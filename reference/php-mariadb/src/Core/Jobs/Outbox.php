<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Jobs;

use PDO;
use RuntimeException;

final class Outbox
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function enqueue(
        string $topic,
        array $payload,
        ?\DateTimeImmutable $availableAt = null,
        int $maxAttempts = 5,
    ): void {
        $maxAttempts = max(1, min($maxAttempts, 25));

        $statement = $this->database->prepare(
            'INSERT INTO jobs_outbox
                (topic, payload_json, status, available_at, attempts, max_attempts, created_at)
             VALUES
                (:topic, :payload_json, :status, :available_at, 0, :max_attempts, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'topic' => $topic,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'available_at' => ($availableAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'max_attempts' => $maxAttempts,
        ]);
    }

    public function claim(string $workerId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));
        $workerId = trim($workerId);

        if ($workerId === '') {
            throw new RuntimeException('Worker ID is required.');
        }

        $this->database->beginTransaction();

        try {
            $rows = $this->database->query(
                "SELECT id, topic, payload_json, attempts, max_attempts
                   FROM jobs_outbox
                  WHERE (
                        status = 'pending'
                        OR (
                            status = 'processing'
                            AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                        )
                    )
                    AND available_at <= UTC_TIMESTAMP()
                  ORDER BY id
                  LIMIT {$limit}
                  FOR UPDATE SKIP LOCKED"
            )->fetchAll();

            if ($rows !== []) {
                $update = $this->database->prepare(
                    "UPDATE jobs_outbox
                        SET status = 'processing',
                            locked_at = UTC_TIMESTAMP(),
                            locked_by = :worker_id
                      WHERE id = :id"
                );

                foreach ($rows as $row) {
                    $update->execute([
                        'worker_id' => $workerId,
                        'id' => (int) $row['id'],
                    ]);
                }
            }

            $this->database->commit();

            return $rows;
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $error;
        }
    }

    public function markDone(int $id): void
    {
        $statement = $this->database->prepare(
            "UPDATE jobs_outbox
                SET status = 'done',
                    processed_at = UTC_TIMESTAMP(),
                    locked_at = NULL,
                    locked_by = NULL
              WHERE id = :id"
        );
        $statement->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $error): string
    {
        $statement = $this->database->prepare(
            'SELECT attempts, max_attempts FROM jobs_outbox WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $job = $statement->fetch();

        if (!$job) {
            throw new RuntimeException('Job not found.');
        }

        $nextAttempt = (int) $job['attempts'] + 1;
        $maxAttempts = (int) $job['max_attempts'];
        $message = mb_substr($error, 0, 1000);

        if ($nextAttempt >= $maxAttempts) {
            $update = $this->database->prepare(
                "UPDATE jobs_outbox
                    SET attempts = :attempts,
                        status = 'dead',
                        last_error = :error,
                        failed_at = UTC_TIMESTAMP(),
                        locked_at = NULL,
                        locked_by = NULL
                  WHERE id = :id"
            );
            $update->execute([
                'attempts' => $nextAttempt,
                'error' => $message,
                'id' => $id,
            ]);

            return 'dead';
        }

        $delayMinutes = min(2 ** $nextAttempt, 60);
        $availableAt = (new \DateTimeImmutable())
            ->modify('+' . $delayMinutes . ' minutes')
            ->format('Y-m-d H:i:s');

        $update = $this->database->prepare(
            "UPDATE jobs_outbox
                SET attempts = :attempts,
                    status = 'pending',
                    last_error = :error,
                    available_at = :available_at,
                    locked_at = NULL,
                    locked_by = NULL
              WHERE id = :id"
        );
        $update->execute([
            'attempts' => $nextAttempt,
            'error' => $message,
            'available_at' => $availableAt,
            'id' => $id,
        ]);

        return 'retry';
    }

    public function retryDead(int $id): void
    {
        $statement = $this->database->prepare(
            "UPDATE jobs_outbox
                SET status = 'pending',
                    attempts = 0,
                    available_at = UTC_TIMESTAMP(),
                    last_error = NULL,
                    failed_at = NULL,
                    locked_at = NULL,
                    locked_by = NULL
              WHERE id = :id
                AND status = 'dead'"
        );
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Dead-letter job not found.');
        }
    }
}
