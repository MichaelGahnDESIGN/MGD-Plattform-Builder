<?php

declare(strict_types=1);

namespace MGD\Starter\Core;

use MGD\Starter\Cms\CreditRepository;
use MGD\Starter\Cms\PageInput;
use MGD\Starter\Cms\PageRepository;
use MGD\Starter\Cms\PageTransfer;
use MGD\Starter\Cms\ReleaseNoteRepository;
use MGD\Starter\Core\Audit\AuditLogger;
use MGD\Starter\Core\Auth\Csrf;
use MGD\Starter\Core\Auth\LoginThrottle;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\SessionAuth;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Auth\UserRepository;
use MGD\Starter\Core\Database\Databases;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\RedirectException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\SecurityHeaders;
use MGD\Starter\Core\Security\HtmlSanitizer;
use MGD\Starter\Core\Settings\SettingsRegistry;
use MGD\Starter\Core\Settings\SettingsRepository;
use MGD\Starter\Core\Version\Version;
use MGD\Starter\Core\Version\VersionDisplay;
use MGD\Starter\Privacy\PrivateDataRepository;

/**
 * Kleiner Service-Container. Dienste werden erst bei Bedarf erzeugt.
 */
final class App
{
    private ?SettingsRegistry $registry = null;
    private ?SettingsRepository $settings = null;
    private ?AuditLogger $audit = null;
    private ?UserRepository $users = null;
    private ?SessionAuth $auth = null;
    private ?HtmlSanitizer $sanitizer = null;
    private ?PageRepository $pages = null;
    private ?PageTransfer $pageTransfer = null;
    private ?ReleaseNoteRepository $releaseNotes = null;
    private ?CreditRepository $credits = null;
    private ?Version $version = null;
    private ?PrivateDataRepository $privateData = null;

    public function __construct(
        public readonly Config $config,
        public readonly Databases $databases,
        public readonly SecurityHeaders $headers,
    ) {
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name(preg_replace('/[^A-Za-z0-9_]/', '', $this->config->string('app.session_name', 'mgd_starter_session')) ?: 'mgd_starter_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => ($this->config->string('app.base_path') ?: '') . '/',
            'secure' => $this->config->bool('security.session_secure', true),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public function registry(): SettingsRegistry
    {
        return $this->registry ??= new SettingsRegistry();
    }

    public function settings(): SettingsRepository
    {
        return $this->settings ??= new SettingsRepository($this->databases->core(), $this->registry());
    }

    public function audit(): AuditLogger
    {
        return $this->audit ??= new AuditLogger($this->databases->core());
    }

    public function users(): UserRepository
    {
        return $this->users ??= new UserRepository($this->databases->core());
    }

    public function auth(): SessionAuth
    {
        return $this->auth ??= new SessionAuth(
            $this->users(),
            new LoginThrottle($this->databases->core(), $this->config->string('app.key', 'mgd-starter'))
        );
    }

    public function sanitizer(): HtmlSanitizer
    {
        return $this->sanitizer ??= new HtmlSanitizer();
    }

    public function pageInput(): PageInput
    {
        return new PageInput($this->sanitizer());
    }

    public function pages(): PageRepository
    {
        return $this->pages ??= new PageRepository($this->databases->core());
    }

    public function pageTransfer(): PageTransfer
    {
        return $this->pageTransfer ??= new PageTransfer($this->pages(), $this->pageInput());
    }

    public function releaseNotes(): ReleaseNoteRepository
    {
        return $this->releaseNotes ??= new ReleaseNoteRepository($this->databases->core());
    }

    public function credits(): CreditRepository
    {
        return $this->credits ??= new CreditRepository($this->databases->core());
    }

    public function privateData(): PrivateDataRepository
    {
        return $this->privateData ??= new PrivateDataRepository($this->databases->private());
    }

    public function version(): Version
    {
        return $this->version ??= Version::fromFile($this->config->path('version_file'));
    }

    public function versionDisplay(): VersionDisplay
    {
        return new VersionDisplay($this->version(), $this->settings(), $this->locale());
    }

    public function locale(): string
    {
        return $this->config->string('app.locale', 'de') === 'en' ? 'en' : 'de';
    }

    public function currentUser(): ?User
    {
        return session_status() === PHP_SESSION_ACTIVE ? $this->auth()->currentUser() : null;
    }

    /**
     * Serverseitige Autorisierung für jede Backoffice-Route.
     */
    public function requireRole(Role $minimum): User
    {
        $user = $this->currentUser();

        if ($user === null) {
            throw new RedirectException('/login');
        }

        if (!$user->can($minimum)) {
            throw new HttpException(403, 'Für diesen Bereich fehlt die Berechtigung (mindestens ' . $minimum->label() . ').');
        }

        return $user;
    }

    public function requireCsrf(Request $request): void
    {
        if (!Csrf::verify($request->input('_csrf', ''))) {
            throw new HttpException(419, 'Das Formular ist abgelaufen. Bitte Seite neu laden und erneut versuchen.');
        }
    }
}
