<?php

declare(strict_types=1);

use MGD\Platform\Core\Support\Id;

$container = require dirname(__DIR__) . '/bootstrap.php';
$database = $container['database'];

$name = trim((string) getenv('MGD_SP_NAME'));
$scopes = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) getenv('MGD_SP_SCOPES'))
)));

if ($name === '') {
    fwrite(STDERR, "Set MGD_SP_NAME.\n");
    exit(1);
}

if ($scopes === []) {
    fwrite(STDERR, "Set MGD_SP_SCOPES to a comma-separated capability list.\n");
    exit(1);
}

$token = 'mgd_' . bin2hex(random_bytes(32));

$statement = $database->prepare(
    'INSERT INTO service_principals
        (public_id, name, token_hash, scopes_json, created_at)
     VALUES
        (:public_id, :name, :token_hash, :scopes_json, UTC_TIMESTAMP())'
);
$statement->execute([
    'public_id' => Id::uuidV4(),
    'name' => $name,
    'token_hash' => hash('sha256', $token),
    'scopes_json' => json_encode($scopes, JSON_THROW_ON_ERROR),
]);

fwrite(STDOUT, "Service principal created.\n");
fwrite(STDOUT, "Token (shown once): {$token}\n");
fwrite(STDOUT, "Store it securely. Only the hash is stored in MariaDB.\n");
