<?php

declare(strict_types=1);

use MGD\Platform\Core\Jobs\Outbox;

$container = require dirname(__DIR__) . '/bootstrap.php';
$outbox = new Outbox($container['database']);

$jobs = $outbox->next(25);

if ($jobs === []) {
    fwrite(STDOUT, "No pending jobs.\n");
    exit(0);
}

foreach ($jobs as $job) {
    try {
        $payload = json_decode((string) $job['payload_json'], true, flags: JSON_THROW_ON_ERROR);

        fwrite(
            STDOUT,
            sprintf(
                "Processing #%d %s %s\n",
                (int) $job['id'],
                (string) $job['topic'],
                json_encode($payload, JSON_UNESCAPED_SLASHES)
            )
        );

        // Replace this switch with project-specific handlers.
        switch ((string) $job['topic']) {
            case 'demo.audit-export':
                break;

            default:
                throw new RuntimeException('No handler registered for topic: ' . $job['topic']);
        }

        $outbox->markDone((int) $job['id']);
    } catch (Throwable $error) {
        $outbox->markFailed((int) $job['id'], $error->getMessage());
        fwrite(STDERR, "Job failed: {$error->getMessage()}\n");
    }
}
