<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Auth;

use PDO;

final class ServicePrincipalAuth
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function authenticateBearer(?string $authorizationHeader): ?Actor
    {
        if (!is_string($authorizationHeader) || !preg_match('/^Bearer\s+(.+)$/i', trim($authorizationHeader), $match)) {
            return null;
        }

        $token = trim($match[1]);
        if ($token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        $statement = $this->database->prepare(
            'SELECT id, public_id, scopes_json
               FROM service_principals
              WHERE token_hash = :token_hash
                AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
              LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $principal = $statement->fetch();

        if (!$principal) {
            return null;
        }

        $touch = $this->database->prepare(
            'UPDATE service_principals SET last_used_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $touch->execute(['id' => (int) $principal['id']]);

        $scopes = json_decode((string) $principal['scopes_json'], true, flags: JSON_THROW_ON_ERROR);

        return new Actor(
            (string) $principal['public_id'],
            'service_principal',
            is_array($scopes) ? array_values($scopes) : []
        );
    }
}
