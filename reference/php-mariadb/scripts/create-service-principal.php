<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\Auth\ServicePrincipalManager;

$container = require dirname(__DIR__) . '/bootstrap.php';
$database = $container['database'];

$name = trim((string) getenv('MGD_SP_NAME'));
$scopes = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) getenv('MGD_SP_SCOPES'))
)));
$description = trim((string) getenv('MGD_SP_DESCRIPTION'));

if ($name === '') {
    fwrite(STDERR, "Set MGD_SP_NAME.\n");
    exit(1);
}

if ($scopes === []) {
    fwrite(STDERR, "Set MGD_SP_SCOPES to a comma-separated capability list.\n");
    exit(1);
}

$bootstrapActor = new Actor(
    'bootstrap-cli',
    'bootstrap',
    ['service-principals.manage']
);

$manager = new ServicePrincipalManager(
    $database,
    new AuditLogger($database)
);

try {
    $created = $manager->create(
        $bootstrapActor,
        $name,
        $scopes,
        null,
        $description !== '' ? $description : null,
    );

    fwrite(STDOUT, "Service principal created.\n");
    fwrite(STDOUT, "Public ID: {$created['public_id']}\n");
    fwrite(STDOUT, "Token (shown once): {$created['token']}\n");
    fwrite(STDOUT, "Store it securely. Only the hash is stored in MariaDB.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Could not create service principal: {$error->getMessage()}\n");
    exit(1);
}
