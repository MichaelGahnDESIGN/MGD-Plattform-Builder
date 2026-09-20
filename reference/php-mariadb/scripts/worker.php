<?php

declare(strict_types=1);

use MGD\Platform\Core\Jobs\JobHandlerRegistry;
use MGD\Platform\Core\Jobs\Outbox;

$container = require dirname(__DIR__) . '/bootstrap.php';
$outbox = new Outbox($container['database']);
$handlers = new JobHandlerRegistry();

$handlers->register('demo.audit-export', static function (array $payload): void {
    if (!isset($payload['requested_by'])) {
        throw new RuntimeException('demo.audit-export requires requested_by.');
    }

    // Replace this demonstration with a real export handler in a project.
});

$workerId = gethostname() . ':' . getmypid();
$jobs = $outbox->claim($workerId, 25);

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
                "Processing #%d %s attempt %d/%d %s\n",
                (int) $job['id'],
                (string) $job['topic'],
                (int) $job['attempts'] + 1,
                (int) $job['max_attempts'],
                json_encode($payload, JSON_UNESCAPED_SLASHES)
            )
        );

        $handlers->handle((string) $job['topic'], $payload);
        $outbox->markDone((int) $job['id']);
    } catch (Throwable $error) {
        $state = $outbox->markFailed((int) $job['id'], $error->getMessage());
        fwrite(STDERR, "Job failed; next state: {$state}; {$error->getMessage()}\n");
    }
}
