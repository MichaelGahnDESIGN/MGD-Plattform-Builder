<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Jobs\Handlers;

use RuntimeException;

final class DemoAuditExportHandler
{
    public function __invoke(array $payload): void
    {
        if (!isset($payload['requested_by'])) {
            throw new RuntimeException('demo.audit-export requires requested_by.');
        }

        // Demonstration only.
        // A real project would generate and persist or deliver the export here.
    }
}
