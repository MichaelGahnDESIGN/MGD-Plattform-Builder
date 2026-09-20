<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\Database\Connection;
use MGD\Platform\Core\Jobs\Outbox;
use MGD\Platform\Core\Permissions\Authorization;
use MGD\Platform\Core\Permissions\CapabilityRepository;
use MGD\Platform\Modules\Accounts\AccountService;

require dirname(__DIR__) . '/vendor/autoload.php';

$database = Connection::create([
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MGD_TEST_DB_HOST') ?: '127.0.0.1',
        getenv('MGD_TEST_DB_PORT') ?: '3306',
        getenv('MGD_TEST_DB_NAME') ?: 'mgd_platform'
    ),
    'user' => getenv('MGD_TEST_DB_USER') ?: 'mgd',
    'password' => getenv('MGD_TEST_DB_PASSWORD') ?: 'mgdpass',
]);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Could not read schema.sql');
}

$database->exec($schema);

$account = $database->prepare(
    'INSERT INTO accounts (public_id, email, password_hash, status)
     VALUES (:public_id, :email, :password_hash, :status)'
);
$account->execute([
    'public_id' => '11111111-1111-4111-8111-111111111111',
    'email' => 'admin@example.test',
    'password_hash' => password_hash('test-password-only', PASSWORD_DEFAULT),
    'status' => 'active',
]);
$accountId = (int) $database->lastInsertId();

$database->prepare(
    "INSERT INTO account_roles (account_id, role_id)
     SELECT :account_id, id FROM roles WHERE role_key = 'admin'"
)->execute(['account_id' => $accountId]);

$capabilities = new CapabilityRepository($database);
$actor = new Actor(
    '11111111-1111-4111-8111-111111111111',
    'account',
    $capabilities->forAccountId($accountId)
);

if (!in_array('accounts.suspend', $actor->capabilities, true)) {
    throw new RuntimeException('Admin capability resolution failed.');
}

$audit = new AuditLogger($database);
$authorization = new Authorization();
$accounts = new AccountService($database, $authorization, $audit);

$target = $database->prepare(
    'INSERT INTO accounts (public_id, email, password_hash, status)
     VALUES (:public_id, :email, :password_hash, :status)'
);
$target->execute([
    'public_id' => '22222222-2222-4222-8222-222222222222',
    'email' => 'user@example.test',
    'password_hash' => password_hash('test-password-only', PASSWORD_DEFAULT),
    'status' => 'active',
]);

$accounts->suspend(
    $actor,
    '22222222-2222-4222-8222-222222222222',
    'Automated reference smoke test'
);

$status = $database->query(
    "SELECT status FROM accounts WHERE public_id = '22222222-2222-4222-8222-222222222222'"
)->fetchColumn();

if ($status !== 'suspended') {
    throw new RuntimeException('Account suspension did not persist.');
}

$auditCount = (int) $database->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
if ($auditCount < 1) {
    throw new RuntimeException('Audit event was not created.');
}

$outbox = new Outbox($database);
$outbox->enqueue('demo.audit-export', ['test' => true]);
$jobs = $outbox->next(10);

if (count($jobs) !== 1 || $jobs[0]['topic'] !== 'demo.audit-export') {
    throw new RuntimeException('Outbox enqueue/read failed.');
}

$outbox->markDone((int) $jobs[0]['id']);

$done = (int) $database->query("SELECT COUNT(*) FROM jobs_outbox WHERE status = 'done'")->fetchColumn();
if ($done !== 1) {
    throw new RuntimeException('Outbox completion failed.');
}

fwrite(STDOUT, "PHP/MariaDB reference smoke test passed.\n");
