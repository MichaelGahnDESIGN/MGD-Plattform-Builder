<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\Auth\ServicePrincipalAuth;
use MGD\Platform\Core\Auth\ServicePrincipalManager;
use MGD\Platform\Core\Database\Connection;
use MGD\Platform\Core\Database\MigrationRunner;
use MGD\Platform\Core\I18n\TranslationRegistry;
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

$migrations = new MigrationRunner(
    $database,
    dirname(__DIR__) . '/database/migrations'
);

$results = $migrations->migrate();

if (count($results) < 5) {
    throw new RuntimeException('Expected at least five migrations.');
}

$secondRun = $migrations->migrate();

foreach ($secondRun as [, $status]) {
    if ($status !== 'already-applied') {
        throw new RuntimeException('Migration runner is not idempotent.');
    }
}

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

foreach ([
    'accounts.suspend',
    'translations.manage',
    'service-principals.manage',
    'jobs.manage',
] as $requiredCapability) {
    if (!in_array($requiredCapability, $actor->capabilities, true)) {
        throw new RuntimeException('Admin capability missing: ' . $requiredCapability);
    }
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

$translations = new TranslationRegistry($database, $audit);
$translations->save(
    $actor,
    'dashboard.welcome',
    'de',
    'Willkommen',
    'published',
    'Smoke-test translation'
);

if ($translations->published('dashboard.welcome', 'de') !== 'Willkommen') {
    throw new RuntimeException('Published translation lookup failed.');
}

$principals = new ServicePrincipalManager($database, $audit);
$created = $principals->create(
    $actor,
    'Smoke Test Agent',
    ['content.read', 'support.case.read']
);

try {
    $principals->create(
        $actor,
        'Invalid Scope Agent',
        ['not-a-real.capability']
    );
    throw new RuntimeException('Unknown service-principal scope was accepted.');
} catch (InvalidArgumentException) {
    // Expected.
}

$principalAuth = new ServicePrincipalAuth($database);
$serviceActor = $principalAuth->authenticateBearer('Bearer ' . $created['token']);

if (!$serviceActor || $serviceActor->type !== 'service_principal') {
    throw new RuntimeException('Service-principal authentication failed.');
}

if (!in_array('support.case.read', $serviceActor->capabilities, true)) {
    throw new RuntimeException('Service-principal scopes were not loaded.');
}

$lastUsed = $database->prepare(
    'SELECT last_used_at FROM service_principals WHERE public_id = :public_id'
);
$lastUsed->execute(['public_id' => $created['public_id']]);

if (!$lastUsed->fetchColumn()) {
    throw new RuntimeException('Service-principal last_used_at was not recorded.');
}

$principals->revoke($actor, $created['public_id']);

if ($principalAuth->authenticateBearer('Bearer ' . $created['token']) !== null) {
    throw new RuntimeException('Revoked service-principal token still authenticates.');
}

try {
    $principals->rotate($actor, $created['public_id']);
    throw new RuntimeException('Revoked service principal was rotated/reactivated.');
} catch (InvalidArgumentException) {
    // Expected.
}

$outbox = new Outbox($database);
$outbox->enqueue('demo.audit-export', ['test' => true]);
$jobs = $outbox->claim('smoke-worker', 10);

if (count($jobs) !== 1 || $jobs[0]['topic'] !== 'demo.audit-export') {
    throw new RuntimeException('Outbox claim failed.');
}

$outbox->markDone((int) $jobs[0]['id']);

$outbox->enqueue('demo.stale-worker', ['test' => true]);
$staleClaim = $outbox->claim('worker-that-crashes', 10);
$staleJob = array_values(array_filter(
    $staleClaim,
    static fn (array $job): bool => $job['topic'] === 'demo.stale-worker'
))[0] ?? null;

if (!$staleJob) {
    throw new RuntimeException('Stale-worker test job was not initially claimed.');
}

$database->prepare(
    "UPDATE jobs_outbox
        SET locked_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 20 MINUTE)
      WHERE id = :id"
)->execute(['id' => (int) $staleJob['id']]);

$recovered = $outbox->claim('replacement-worker', 10);
$recoveredJob = array_values(array_filter(
    $recovered,
    static fn (array $job): bool => $job['topic'] === 'demo.stale-worker'
))[0] ?? null;

if (!$recoveredJob) {
    throw new RuntimeException('Stale processing job was not recovered.');
}

$outbox->markDone((int) $recoveredJob['id']);

$done = (int) $database->query("SELECT COUNT(*) FROM jobs_outbox WHERE status = 'done'")->fetchColumn();
if ($done !== 2) {
    throw new RuntimeException('Outbox completion failed.');
}

$outbox->enqueue('demo.must-fail', ['test' => true], null, 1);
$failedJobs = $outbox->claim('smoke-worker', 10);
$failedJob = array_values(array_filter(
    $failedJobs,
    static fn (array $job): bool => $job['topic'] === 'demo.must-fail'
))[0] ?? null;

if (!$failedJob) {
    throw new RuntimeException('Dead-letter test job was not claimed.');
}

if ($outbox->markFailed((int) $failedJob['id'], 'Expected smoke-test failure') !== 'dead') {
    throw new RuntimeException('Job did not enter dead-letter state.');
}

$deadCount = (int) $database->query("SELECT COUNT(*) FROM jobs_outbox WHERE status = 'dead'")->fetchColumn();
if ($deadCount !== 1) {
    throw new RuntimeException('Dead-letter state was not persisted.');
}

$outbox->retryDead((int) $failedJob['id']);

$pendingAgain = (int) $database->query(
    "SELECT COUNT(*) FROM jobs_outbox WHERE status = 'pending' AND attempts = 0"
)->fetchColumn();

if ($pendingAgain < 1) {
    throw new RuntimeException('Dead-letter retry failed.');
}

$auditCount = (int) $database->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
if ($auditCount < 5) {
    throw new RuntimeException('Expected audit events were not created.');
}

fwrite(STDOUT, "PHP/MariaDB 0.4 reference smoke test passed.\n");
