<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\Auth\Csrf;
use MGD\Platform\Core\Auth\ServicePrincipalAuth;
use MGD\Platform\Core\Auth\SessionAuth;
use MGD\Platform\Core\Http\View;
use MGD\Platform\Core\Jobs\Outbox;
use MGD\Platform\Core\Permissions\Authorization;
use MGD\Platform\Core\Permissions\CapabilityRepository;
use MGD\Platform\Core\Security\SecurityEventLogger;
use MGD\Platform\Modules\Accounts\AccountService;

$container = require dirname(__DIR__) . '/bootstrap.php';
$config = $container['config'];
$database = $container['database'];

session_name('mgd_reference_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool) ($config['app']['session_secure'] ?? false),
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

$capabilityRepository = new CapabilityRepository($database);
$auth = new SessionAuth($database, $capabilityRepository);
$serviceAuth = new ServicePrincipalAuth($database);
$authorization = new Authorization();
$audit = new AuditLogger($database);
$security = new SecurityEventLogger($database);
$outbox = new Outbox($database);
$accounts = new AccountService($database, $authorization, $audit);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function requireActor(SessionAuth $auth): Actor
{
    $actor = $auth->currentActor();

    if (!$actor) {
        redirect('/login');
    }

    return $actor;
}

function requireCapability(Authorization $authorization, Actor $actor, string $capability): void
{
    try {
        $authorization->requireCapability($actor, $capability);
    } catch (RuntimeException) {
        http_response_code(403);
        echo View::page(
            'Forbidden',
            '<div class="panel"><h1>403</h1><p>This account does not have the required capability.</p></div>',
            $actor->capabilities
        );
        exit;
    }
}

function requireCsrf(): void
{
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo View::page(
            'Invalid request',
            '<div class="panel"><h1>Request expired</h1><p>The CSRF token is missing or invalid. Reload the page and try again.</p></div>'
        );
        exit;
    }
}

function queryCount(PDO $database, string $table, string $where = '1=1'): int
{
    $allowed = ['accounts', 'audit_events', 'security_events', 'jobs_outbox'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported table.');
    }

    return (int) $database->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
}

if ($path === '/api/me' && $method === 'GET') {
    $actor = $serviceAuth->authenticateBearer($_SERVER['HTTP_AUTHORIZATION'] ?? null)
        ?? $auth->currentActor();

    if (!$actor) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthorized'], JSON_THROW_ON_ERROR);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'actor_id' => $actor->id,
        'actor_type' => $actor->type,
        'capabilities' => $actor->capabilities,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit;
}

if ($path === '/login' && $method === 'GET') {
    if ($auth->currentActor()) {
        redirect('/');
    }

    $token = View::e(Csrf::token());
    $body = '<div class="panel" style="max-width:460px;margin:60px auto">
        <h1>Sign in</h1>
        <p class="muted">Reference backoffice login</p>
        <form method="post" action="/login">
            <input type="hidden" name="_csrf" value="' . $token . '">
            <label>Email<input type="email" name="email" autocomplete="username" required></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit">Sign in</button>
        </form>
    </div>';

    echo View::page('Sign in', $body);
    exit;
}

if ($path === '/login' && $method === 'POST') {
    requireCsrf();

    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $actor = $auth->attempt($email, $password);

    if (!$actor) {
        $security->record(
            'auth.login.failed',
            'medium',
            null,
            ['email_hash' => hash('sha256', mb_strtolower(trim($email)))]
        );

        http_response_code(401);
        $body = '<div class="panel" style="max-width:460px;margin:60px auto">
            <h1>Sign in</h1>
            <p class="error">Login failed.</p>
            <p><a href="/login">Try again</a></p>
        </div>';
        echo View::page('Sign in', $body);
        exit;
    }

    $audit->record($actor, 'auth.login', 'session', session_id());
    redirect('/');
}

if ($path === '/logout' && $method === 'POST') {
    $actor = requireActor($auth);
    requireCsrf();
    $audit->record($actor, 'auth.logout', 'session', session_id());
    $auth->logout();
    redirect('/login');
}

$actor = requireActor($auth);

if ($path === '/' && $method === 'GET') {
    $cards = [
        ['Accounts', queryCount($database, 'accounts')],
        ['Audit events', queryCount($database, 'audit_events')],
        ['Security events', queryCount($database, 'security_events')],
        ['Pending jobs', queryCount($database, 'jobs_outbox', "status = 'pending'")],
    ];

    $cardHtml = '';
    foreach ($cards as [$label, $value]) {
        $cardHtml .= '<section class="panel"><span class="muted">' . View::e($label) . '</span><h2>' . (int) $value . '</h2></section>';
    }

    $capabilityHtml = '';
    foreach ($actor->capabilities as $capability) {
        $capabilityHtml .= '<span class="badge">' . View::e($capability) . '</span>';
    }

    $body = '<h1>Dashboard</h1>
        <p class="muted">Authenticated as <code>' . View::e($actor->id) . '</code></p>
        <div class="grid">' . $cardHtml . '</div>
        <div class="panel"><h2>Capabilities</h2>' . $capabilityHtml . '</div>
        <div class="panel"><h2>Agent/API test</h2><p>Service principals can call <code>GET /api/me</code> with a Bearer token.</p></div>
        <form method="post" action="/logout">
            <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
            <button type="submit">Sign out</button>
        </form>';

    echo View::page('Dashboard', $body, $actor->capabilities);
    exit;
}

if ($path === '/accounts' && $method === 'GET') {
    requireCapability($authorization, $actor, 'accounts.suspend');

    $rows = $database->query(
        'SELECT public_id, email, status, last_login_at, created_at
           FROM accounts
          ORDER BY id DESC
          LIMIT 100'
    )->fetchAll();

    $html = '<h1>Accounts</h1><div class="panel"><table><thead><tr><th>Email</th><th>Status</th><th>Last login</th><th>Action</th></tr></thead><tbody>';

    foreach ($rows as $row) {
        $action = '';

        if ($row['status'] === 'active' && $row['public_id'] !== $actor->id) {
            $action = '<form class="inline" method="post" action="/accounts/suspend">
                <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
                <input type="hidden" name="account_id" value="' . View::e((string) $row['public_id']) . '">
                <input type="hidden" name="reason" value="Reference admin action">
                <button class="danger" type="submit">Suspend</button>
            </form>';
        }

        $html .= '<tr><td>' . View::e((string) $row['email']) . '</td><td>' . View::e((string) $row['status']) . '</td><td>' . View::e((string) ($row['last_login_at'] ?? 'never')) . '</td><td>' . $action . '</td></tr>';
    }

    $html .= '</tbody></table></div>';
    echo View::page('Accounts', $html, $actor->capabilities);
    exit;
}

if ($path === '/accounts/suspend' && $method === 'POST') {
    requireCapability($authorization, $actor, 'accounts.suspend');
    requireCsrf();

    $accountId = trim((string) ($_POST['account_id'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($accountId === '' || $reason === '') {
        http_response_code(422);
        echo View::page('Invalid account action', '<div class="panel"><p>Account and reason are required.</p></div>', $actor->capabilities);
        exit;
    }

    if ($accountId === $actor->id) {
        http_response_code(422);
        echo View::page('Invalid account action', '<div class="panel"><p>The reference UI does not allow self-suspension.</p></div>', $actor->capabilities);
        exit;
    }

    $accounts->suspend($actor, $accountId, $reason);
    redirect('/accounts');
}

if ($path === '/audit' && $method === 'GET') {
    requireCapability($authorization, $actor, 'security.audit.read');

    $rows = $database->query(
        'SELECT actor_id, actor_type, action_name, resource_type, resource_id, created_at
           FROM audit_events
          ORDER BY id DESC
          LIMIT 100'
    )->fetchAll();

    $html = '<h1>Audit log</h1><div class="panel"><table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Resource</th></tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr><td>' . View::e((string) $row['created_at']) . '</td><td>' . View::e((string) $row['actor_type']) . '<br><code>' . View::e((string) $row['actor_id']) . '</code></td><td>' . View::e((string) $row['action_name']) . '</td><td>' . View::e((string) $row['resource_type']) . ' ' . View::e((string) ($row['resource_id'] ?? '')) . '</td></tr>';
    }

    $html .= '</tbody></table></div>';
    echo View::page('Audit', $html, $actor->capabilities);
    exit;
}

if ($path === '/security' && $method === 'GET') {
    requireCapability($authorization, $actor, 'security.audit.read');

    $rows = $database->query(
        'SELECT event_type, severity, actor_id, ip_address, created_at
           FROM security_events
          ORDER BY id DESC
          LIMIT 100'
    )->fetchAll();

    $html = '<h1>Security events</h1><div class="panel"><table><thead><tr><th>Time</th><th>Severity</th><th>Event</th><th>Actor / IP</th></tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr><td>' . View::e((string) $row['created_at']) . '</td><td><span class="badge">' . View::e((string) $row['severity']) . '</span></td><td>' . View::e((string) $row['event_type']) . '</td><td>' . View::e((string) ($row['actor_id'] ?? 'anonymous')) . '<br>' . View::e((string) ($row['ip_address'] ?? '')) . '</td></tr>';
    }

    $html .= '</tbody></table></div>';
    echo View::page('Security', $html, $actor->capabilities);
    exit;
}

if ($path === '/jobs' && $method === 'GET') {
    requireCapability($authorization, $actor, 'security.audit.read');

    $rows = $database->query(
        'SELECT id, topic, status, attempts, available_at, processed_at
           FROM jobs_outbox
          ORDER BY id DESC
          LIMIT 100'
    )->fetchAll();

    $html = '<h1>Jobs / Outbox</h1>
        <form method="post" action="/jobs/demo" class="panel">
            <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
            <p>Create a harmless demo job, then process it with <code>php scripts/worker.php</code>.</p>
            <button type="submit">Create demo job</button>
        </form>
        <div class="panel"><table><thead><tr><th>ID</th><th>Topic</th><th>Status</th><th>Attempts</th><th>Available</th></tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr><td>' . (int) $row['id'] . '</td><td>' . View::e((string) $row['topic']) . '</td><td>' . View::e((string) $row['status']) . '</td><td>' . (int) $row['attempts'] . '</td><td>' . View::e((string) $row['available_at']) . '</td></tr>';
    }

    $html .= '</tbody></table></div>';
    echo View::page('Jobs', $html, $actor->capabilities);
    exit;
}

if ($path === '/jobs/demo' && $method === 'POST') {
    requireCapability($authorization, $actor, 'security.audit.read');
    requireCsrf();

    $outbox->enqueue('demo.audit-export', [
        'requested_by' => $actor->id,
        'requested_at' => gmdate(DATE_ATOM),
    ]);
    $audit->record($actor, 'jobs.demo.enqueue', 'job');

    redirect('/jobs');
}

http_response_code(404);
echo View::page(
    'Not found',
    '<div class="panel"><h1>404</h1><p>The requested reference route does not exist.</p></div>',
    $actor->capabilities
);
