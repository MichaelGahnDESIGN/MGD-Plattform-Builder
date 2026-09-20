<?php

declare(strict_types=1);

use MGD\Platform\Core\Database\MigrationRunner;

$container = require dirname(__DIR__) . '/bootstrap.php';

$runner = new MigrationRunner(
    $container['database'],
    dirname(__DIR__) . '/database/migrations'
);

try {
    $results = $runner->migrate();

    foreach ($results as [$version, $status]) {
        fwrite(STDOUT, sprintf("%-40s %s\n", $version, $status));
    }

    fwrite(STDOUT, "Migrations complete.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Migration failed: {$error->getMessage()}\n");
    exit(1);
}
