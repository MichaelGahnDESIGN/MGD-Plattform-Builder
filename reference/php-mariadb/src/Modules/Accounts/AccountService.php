<?php

declare(strict_types=1);

namespace MGD\Platform\Modules\Accounts;

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\Permissions\Authorization;
use PDO;

final class AccountService
{
    public function __construct(
        private readonly PDO $database,
        private readonly Authorization $authorization,
        private readonly AuditLogger $audit,
    ) {
    }

    public function suspend(Actor $actor, string $accountId, string $reason): void
    {
        $this->authorization->requireCapability($actor, 'accounts.suspend');

        $statement = $this->database->prepare(
            'UPDATE accounts SET status = :status, updated_at = UTC_TIMESTAMP() WHERE public_id = :id'
        );

        $statement->execute([
            'status' => 'suspended',
            'id' => $accountId,
        ]);

        $this->audit->record(
            $actor,
            'accounts.suspend',
            'account',
            $accountId,
            ['reason' => $reason]
        );
    }
}
