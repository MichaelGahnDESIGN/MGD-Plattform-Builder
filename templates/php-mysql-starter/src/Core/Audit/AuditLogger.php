<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Audit;

use MGD\Starter\Core\Auth\User;
use PDO;

final class AuditLogger
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function record(
        ?User $actor,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $metadata = [],
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO audit_log
                (actor_user_id, actor_label, action_name, resource_type, resource_id, metadata_json, created_at)
             VALUES
                (:actor_user_id, :actor_label, :action_name, :resource_type, :resource_id, :metadata_json, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'actor_user_id' => $actor?->id,
            'actor_label' => $actor !== null ? mb_substr($actor->displayName, 0, 120) : 'system',
            'action_name' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    public function recent(int $limit = 100, string $resourceType = ''): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, actor_label, action_name, resource_type, resource_id, metadata_json, created_at FROM audit_log';
        $params = [];

        if ($resourceType !== '') {
            $sql .= ' WHERE resource_type = :resource_type';
            $params['resource_type'] = $resourceType;
        }

        $statement = $this->database->prepare($sql . ' ORDER BY id DESC LIMIT ' . $limit);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
