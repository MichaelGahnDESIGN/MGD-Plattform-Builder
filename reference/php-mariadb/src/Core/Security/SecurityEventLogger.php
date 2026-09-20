<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Security;

use PDO;

final class SecurityEventLogger
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function record(
        string $type,
        string $severity,
        ?string $actorId = null,
        array $metadata = []
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO security_events
                (event_type, severity, actor_id, ip_address, metadata_json, created_at)
             VALUES
                (:event_type, :severity, :actor_id, :ip_address, :metadata_json, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'event_type' => $type,
            'severity' => $severity,
            'actor_id' => $actorId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
