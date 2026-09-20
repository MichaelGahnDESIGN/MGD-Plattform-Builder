<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Permissions;

use PDO;

final class CapabilityRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function forAccountId(int $accountId): array
    {
        $statement = $this->database->prepare(
            'SELECT DISTINCT c.capability_key
               FROM capabilities c
               JOIN role_capabilities rc ON rc.capability_id = c.id
               JOIN account_roles ar ON ar.role_id = rc.role_id
              WHERE ar.account_id = :account_id
              ORDER BY c.capability_key'
        );

        $statement->execute(['account_id' => $accountId]);

        return array_values(array_map(
            static fn (array $row): string => (string) $row['capability_key'],
            $statement->fetchAll()
        ));
    }
}
