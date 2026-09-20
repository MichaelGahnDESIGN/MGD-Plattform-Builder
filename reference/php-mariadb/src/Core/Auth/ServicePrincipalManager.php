<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Auth;

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Support\Id;
use PDO;
use InvalidArgumentException;

final class ServicePrincipalManager
{
    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
    ) {
    }

    public function create(
        Actor $actor,
        string $name,
        array $scopes,
        ?\DateTimeImmutable $expiresAt = null,
        ?string $description = null,
    ): array {
        $name = trim($name);
        $scopes = array_values(array_unique(array_filter(array_map('trim', $scopes))));

        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        if ($scopes === []) {
            throw new InvalidArgumentException('At least one scope is required.');
        }

        foreach ($scopes as $scope) {
            if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9-]+){1,4}$/', $scope)) {
                throw new InvalidArgumentException('Invalid scope: ' . $scope);
            }
        }

        $publicId = Id::uuidV4();
        $token = 'mgd_' . bin2hex(random_bytes(32));

        $statement = $this->database->prepare(
            'INSERT INTO service_principals
                (public_id, name, description, token_hash, scopes_json, created_by_actor_id, expires_at, created_at)
             VALUES
                (:public_id, :name, :description, :token_hash, :scopes_json, :actor_id, :expires_at, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'public_id' => $publicId,
            'name' => $name,
            'description' => $description,
            'token_hash' => hash('sha256', $token),
            'scopes_json' => json_encode($scopes, JSON_THROW_ON_ERROR),
            'actor_id' => $actor->id,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        $this->audit->record(
            $actor,
            'service-principals.create',
            'service_principal',
            $publicId,
            ['scopes' => $scopes]
        );

        return [
            'public_id' => $publicId,
            'token' => $token,
        ];
    }

    public function rotate(Actor $actor, string $publicId): string
    {
        $token = 'mgd_' . bin2hex(random_bytes(32));

        $statement = $this->database->prepare(
            'UPDATE service_principals
                SET token_hash = :token_hash,
                    last_rotated_at = UTC_TIMESTAMP(),
                    revoked_at = NULL
              WHERE public_id = :public_id'
        );
        $statement->execute([
            'token_hash' => hash('sha256', $token),
            'public_id' => $publicId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Service principal not found.');
        }

        $this->audit->record(
            $actor,
            'service-principals.rotate',
            'service_principal',
            $publicId
        );

        return $token;
    }

    public function revoke(Actor $actor, string $publicId): void
    {
        $statement = $this->database->prepare(
            'UPDATE service_principals
                SET revoked_at = UTC_TIMESTAMP()
              WHERE public_id = :public_id
                AND revoked_at IS NULL'
        );
        $statement->execute(['public_id' => $publicId]);

        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Active service principal not found.');
        }

        $this->audit->record(
            $actor,
            'service-principals.revoke',
            'service_principal',
            $publicId
        );
    }

    public function list(): array
    {
        return $this->database->query(
            'SELECT public_id, name, description, scopes_json, expires_at, revoked_at, created_at, last_rotated_at, last_used_at
               FROM service_principals
              ORDER BY id DESC
              LIMIT 200'
        )->fetchAll();
    }
}
