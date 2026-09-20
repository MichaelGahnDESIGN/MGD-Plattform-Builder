<?php

declare(strict_types=1);

use MGD\Platform\Core\Support\Id;

$container = require dirname(__DIR__) . '/bootstrap.php';
$database = $container['database'];

$email = mb_strtolower(trim((string) getenv('MGD_ADMIN_EMAIL')));
$password = (string) getenv('MGD_ADMIN_PASSWORD');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set MGD_ADMIN_EMAIL to a valid email address.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Set MGD_ADMIN_PASSWORD to at least 12 characters.\n");
    exit(1);
}

$database->beginTransaction();

try {
    $statement = $database->prepare(
        'INSERT INTO accounts (public_id, email, password_hash, status)
         VALUES (:public_id, :email, :password_hash, :status)'
    );
    $statement->execute([
        'public_id' => Id::uuidV4(),
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'active',
    ]);

    $accountId = (int) $database->lastInsertId();

    $role = $database->prepare(
        "INSERT INTO account_roles (account_id, role_id)
         SELECT :account_id, id FROM roles WHERE role_key = 'admin'"
    );
    $role->execute(['account_id' => $accountId]);

    if ($role->rowCount() !== 1) {
        throw new RuntimeException('Admin role is missing. Import database/schema.sql first.');
    }

    $database->commit();
    fwrite(STDOUT, "Admin account created for {$email}.\n");
} catch (Throwable $error) {
    $database->rollBack();
    fwrite(STDERR, "Could not create admin: {$error->getMessage()}\n");
    exit(1);
}
