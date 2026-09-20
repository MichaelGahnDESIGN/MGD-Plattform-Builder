<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\Auth\Csrf;
use MGD\Platform\Core\Auth\ServicePrincipalAuth;
use MGD\Platform\Core\Auth\ServicePrincipalManager;
use MGD\Platform\Core\Auth\SessionAuth;
use MGD\Platform\Core\Backoffice\Ui;
use MGD\Platform\Core\Http\View;
use MGD\Platform\Core\I18n\TranslationRegistry;
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
$translations = new TranslationRegistry($database, $audit);
$servicePrincipals = new ServicePrincipalManager($database, $audit);

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
    $allowed = [
        'accounts',
        'audit_events',
        'security_events',
        'jobs_outbox',
        'translations',
        'service_principals',
    ];

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
        echo View::page(
            'Sign in',
            '<div class="panel" style="max-width:460px;margin:60px auto">
                <h1>Sign in</h1>
                <p class="error">Login failed.</p>
                <p><a href="/login">Try again</a></p>
            </div>'
        );
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
        ['Translations', queryCount($database, 'translations')],
        ['Service principals', queryCount($database, 'service_principals', 'revoked_at IS NULL')],
        ['Audit events', queryCount($database, 'audit_events')],
        ['Security events', queryCount($database, 'security_events')],
        ['Pending jobs', queryCount($database, 'jobs_outbox', "status = 'pending'")],
        ['Dead jobs', queryCount($database, 'jobs_outbox', "status = 'dead'")],
    ];

    $cardHtml = '';
    foreach ($cards as [$label, $value]) {
        $cardHtml .= '<section class="panel"><span class="muted">' . View::e($label) . '</span><h2>' . (int) $value . '</h2></section>';
    }

    $capabilityHtml = '';
    foreach ($actor->capabilities as $capability) {
        $capabilityHtml .= Ui::badge($capability);
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

    $tableRows = [];

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

        $tableRows[] = [
            View::e((string) $row['email']),
            Ui::badge((string) $row['status']),
            View::e((string) ($row['last_login_at'] ?? 'never')),
            $action,
        ];
    }

    echo View::page(
        'Accounts',
        '<h1>Accounts</h1>' . Ui::table(['Email', 'Status', 'Last login', 'Action'], $tableRows),
        $actor->capabilities
    );
    exit;
}

if ($path === '/accounts/suspend' && $method === 'POST') {
    requireCapability($authorization, $actor, 'accounts.suspend');
    requireCsrf();

    $accountId = trim((string) ($_POST['account_id'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($accountId === '' || $reason === '' || $accountId === $actor->id) {
        http_response_code(422);
        echo View::page(
            'Invalid account action',
            '<div class="panel"><p>The account action is invalid.</p></div>',
            $actor->capabilities
        );
        exit;
    }

    $accounts->suspend($actor, $accountId, $reason);
    redirect('/accounts');
}

if ($path === '/translations' && $method === 'GET') {
    requireCapability($authorization, $actor, 'translations.read');

    $rows = [];
    foreach ($translations->list() as $row) {
        $rows[] = [
            '<code>' . View::e((string) $row['translation_key']) . '</code>',
            View::e((string) ($row['locale'] ?? '')),
            View::e((string) ($row['value_text'] ?? '')),
            Ui::badge((string) ($row['status'] ?? 'missing')),
            View::e((string) ($row['updated_at'] ?? '')),
        ];
    }

    $form = '';

    if (in_array('translations.manage', $actor->capabilities, true)) {
        $form = '<form method="post" action="/translations/save" class="panel">
            <h2>Add or update translation</h2>
            <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
            <label>Key<input name="translation_key" placeholder="dashboard.welcome" required></label>
            <label>Locale<input name="locale" placeholder="de" required></label>
            <label>Value<input name="value" required></label>
            <label>Description<input name="description"></label>
            <label>Status
                <select name="status">
                    <option value="draft">draft</option>
                    <option value="published">published</option>
                </select>
            </label>
            <button type="submit">Save translation</button>
        </form>';
    }

    echo View::page(
        'Translations',
        '<h1>Translations</h1>' . $form . Ui::table(['Key', 'Locale', 'Value', 'Status', 'Updated'], $rows),
        $actor->capabilities
    );
    exit;
}

if ($path === '/translations/save' && $method === 'POST') {
    requireCapability($authorization, $actor, 'translations.manage');
    requireCsrf();

    try {
        $translations->save(
            $actor,
            (string) ($_POST['translation_key'] ?? ''),
            (string) ($_POST['locale'] ?? ''),
            (string) ($_POST['value'] ?? ''),
            (string) ($_POST['status'] ?? 'draft'),
            trim((string) ($_POST['description'] ?? '')) ?: null,
        );
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo View::page('Invalid translation', Ui::notice($error->getMessage(), 'error'), $actor->capabilities);
        exit;
    }

    redirect('/translations');
}

if ($path === '/service-principals' && $method === 'GET') {
    requireCapability($authorization, $actor, 'service-principals.read');

    $oneTimeToken = $_SESSION['new_service_principal_token'] ?? null;
    unset($_SESSION['new_service_principal_token']);

    $notice = is_string($oneTimeToken)
        ? '<div class="ok"><strong>Token shown once:</strong><br><code>' . View::e($oneTimeToken) . '</code><br>Store it securely now.</div>'
        : '';

    $form = '';

    if (in_array('service-principals.manage', $actor->capabilities, true)) {
        $form = '<form method="post" action="/service-principals/create" class="panel">
            <h2>Create service principal</h2>
            <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
            <label>Name<input name="name" required></label>
            <label>Description<input name="description"></label>
            <label>Scopes<input name="scopes" placeholder="content.read,support.case.read" required></label>
            <label>Expires at (optional)<input type="datetime-local" name="expires_at"></label>
            <button type="submit">Create principal</button>
        </form>';
    }

    $rows = [];

    foreach ($servicePrincipals->list() as $row) {
        $scopes = json_decode((string) $row['scopes_json'], true) ?: [];
        $scopeHtml = implode(' ', array_map(static fn ($scope) => Ui::badge((string) $scope), $scopes));
        $state = $row['revoked_at'] ? 'revoked' : (($row['expires_at'] && strtotime((string) $row['expires_at']) < time()) ? 'expired' : 'active');
        $actions = '';

        if (in_array('service-principals.manage', $actor->capabilities, true) && $state !== 'revoked') {
            $actions = '<form class="inline" method="post" action="/service-principals/rotate">
                <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
                <input type="hidden" name="public_id" value="' . View::e((string) $row['public_id']) . '">
                <button type="submit">Rotate</button>
            </form>
            <form class="inline" method="post" action="/service-principals/revoke">
                <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
                <input type="hidden" name="public_id" value="' . View::e((string) $row['public_id']) . '">
                <button class="danger" type="submit">Revoke</button>
            </form>';
        }

        $rows[] = [
            View::e((string) $row['name']) . '<br><code>' . View::e((string) $row['public_id']) . '</code>',
            $scopeHtml,
            Ui::badge($state),
            View::e((string) ($row['last_used_at'] ?? 'never')),
            $actions,
        ];
    }

    echo View::page(
        'Agents / API',
        '<h1>Agents / API</h1>' . $notice . $form . Ui::table(['Principal', 'Scopes', 'State', 'Last used', 'Actions'], $rows),
        $actor->capabilities
    );
    exit;
}

if ($path === '/service-principals/create' && $method === 'POST') {
    requireCapability($authorization, $actor, 'service-principals.manage');
    requireCsrf();

    try {
        $expires = trim((string) ($_POST['expires_at'] ?? ''));
        $created = $servicePrincipals->create(
            $actor,
            (string) ($_POST['name'] ?? ''),
            explode(',', (string) ($_POST['scopes'] ?? '')),
            $expires !== '' ? new DateTimeImmutable($expires) : null,
            trim((string) ($_POST['description'] ?? '')) ?: null,
        );
        $_SESSION['new_service_principal_token'] = $created['token'];
    } catch (Throwable $error) {
        http_response_code(422);
        echo View::page('Invalid service principal', Ui::notice($error->getMessage(), 'error'), $actor->capabilities);
        exit;
    }

    redirect('/service-principals');
}

if ($path === '/service-principals/rotate' && $method === 'POST') {
    requireCapability($authorization, $actor, 'service-principals.manage');
    requireCsrf();

    try {
        $_SESSION['new_service_principal_token'] = $servicePrincipals->rotate(
            $actor,
            (string) ($_POST['public_id'] ?? '')
        );
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo View::page('Rotation failed', Ui::notice($error->getMessage(), 'error'), $actor->capabilities);
        exit;
    }

    redirect('/service-principals');
}

if ($path === '/service-principals/revoke' && $method === 'POST') {
    requireCapability($authorization, $actor, 'service-principals.manage');
    requireCsrf();

    try {
        $servicePrincipals->revoke($actor, (string) ($_POST['public_id'] ?? ''));
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo View::page('Revoke failed', Ui::notice($error->getMessage(), 'error'), $actor->capabilities);
        exit;
    }

    redirect('/service-principals');
}

if ($path === '/audit' && $method === 'GET') {
    requireCapability($authorization, $actor, 'security.audit.read');

    $rows = $database->query(
        'SELECT actor_id, actor_type, action_name, resource_type, resource_id, created_at
           FROM audit_events
          ORDER BY id DESC
          LIMIT 100'
    )->fetchAll();

    $tableRows = [];
    foreach ($rows as $row) {
        $tableRows[] = [
            View::e((string) $row['created_at']),
            View::e((string) $row['actor_type']) . '<br><code>' . View::e((string) $row['actor_id']) . '</code>',
            View::e((string) $row['action_name']),
            View::e((string) $row['resource_type']) . ' ' . View::e((string) ($row['resource_id'] ?? '')),
        ];
    }

    echo View::page('Audit', '<h1>Audit log</h1>' . Ui::table(['Time', 'Actor', 'Action', 'Resource'], $tableRows), $actor->capabilities);
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

    $tableRows = [];
    foreach ($rows as $row) {
        $tableRows[] = [
            View::e((string) $row['created_at']),
            Ui::badge((string) $row['severity']),
            View::e((string) $row['event_type']),
            View::e((string) ($row['actor_id'] ?? 'anonymous')) . '<br>' . View::e((string) ($row['ip_address'] ?? '')),
        ];
    }

    echo View::page('Security', '<h1>Security events</h1>' . Ui::table(['Time', 'Severity', 'Event', 'Actor / IP'], $tableRows), $actor->capabilities);
    exit;
}

if ($path === '/jobs' && $method === 'GET') {
    requireCapability($authorization, $actor, 'jobs.read');

    $rows = $database->query(
        'SELECT id, topic, status, attempts, max_attempts, available_at, last_error
           FROM jobs_outbox
          ORDER BY id DESC
          LIMIT 100'
    )->fetchAll();

    $form = '';

    if (in_array('jobs.manage', $actor->capabilities, true)) {
        $form = '<form method="post" action="/jobs/demo" class="panel">
            <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
            <p>Create a harmless demo job, then process it with <code>php scripts/worker.php</code>.</p>
            <button type="submit">Create demo job</button>
        </form>';
    }

    $tableRows = [];

    foreach ($rows as $row) {
        $actions = '';

        if ($row['status'] === 'dead' && in_array('jobs.manage', $actor->capabilities, true)) {
            $actions = '<form class="inline" method="post" action="/jobs/retry">
                <input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">
                <input type="hidden" name="job_id" value="' . (int) $row['id'] . '">
                <button type="submit">Retry</button>
            </form>';
        }

        $tableRows[] = [
            (string) (int) $row['id'],
            View::e((string) $row['topic']),
            Ui::badge((string) $row['status']),
            (int) $row['attempts'] . ' / ' . (int) $row['max_attempts'],
            View::e((string) ($row['last_error'] ?? '')),
            $actions,
        ];
    }

    echo View::page(
        'Jobs',
        '<h1>Jobs / Outbox</h1>' . $form . Ui::table(['ID', 'Topic', 'State', 'Attempts', 'Last error', 'Actions'], $tableRows),
        $actor->capabilities
    );
    exit;
}

if ($path === '/jobs/demo' && $method === 'POST') {
    requireCapability($authorization, $actor, 'jobs.manage');
    requireCsrf();

    $outbox->enqueue('demo.audit-export', [
        'requested_by' => $actor->id,
        'requested_at' => gmdate(DATE_ATOM),
    ]);
    $audit->record($actor, 'jobs.demo.enqueue', 'job');

    redirect('/jobs');
}

if ($path === '/jobs/retry' && $method === 'POST') {
    requireCapability($authorization, $actor, 'jobs.manage');
    requireCsrf();

    try {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $outbox->retryDead($jobId);
        $audit->record($actor, 'jobs.retry', 'job', (string) $jobId);
    } catch (RuntimeException $error) {
        http_response_code(422);
        echo View::page('Retry failed', Ui::notice($error->getMessage(), 'error'), $actor->capabilities);
        exit;
    }

    redirect('/jobs');
}

http_response_code(404);
echo View::page(
    'Not found',
    '<div class="panel"><h1>404</h1><p>The requested reference route does not exist.</p></div>',
    $actor->capabilities
);
