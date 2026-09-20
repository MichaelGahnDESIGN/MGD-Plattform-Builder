<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Audit;

use MGD\Platform\Core\Auth\Actor;
use PDO;

final class AuditLogger
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function record(
        Actor $actor,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $metadata = []
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO audit_events
                (actor_id, actor_type, action_name, resource_type, resource_id, metadata_json, created_at)
             VALUES
                (:actor_id, :actor_type, :action_name, :resource_type, :resource_id, :metadata_json, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'actor_id' => $actor->id,
            'actor_type' => $actor->type,
            'action_name' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
