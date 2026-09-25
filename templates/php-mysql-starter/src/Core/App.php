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
use MGD\Starter\Core\Auth\PasswordResets;
use MGD\Starter\Core\Mail\Mailer;
use MGD\Starter\Media\MediaRepository;
use MGD\Starter\Media\MediaStore;
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
use MGD\Starter\Core\License\LicenseIntegrity;
use MGD\Starter\Core\License\LicenseKey;
use MGD\Starter\Core\License\LicenseRepository;
use MGD\Starter\Core\License\LicenseService;
use MGD\Starter\Core\License\PoweredBy;
use MGD\Starter\Core\Module\ModuleManager;
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
    private ?LicenseService $licenses = null;
    private ?ModuleManager $modules = null;

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

    public function passwordResets(): PasswordResets
    {
        return new PasswordResets($this->databases->core(), $this->users(), $this->config->string('app.key', 'mgd-starter'));
    }

    public function mailer(): Mailer
    {
        $mail = $this->config->get('mail', []);

        return new Mailer(
            is_array($mail) ? $mail : [],
            $this->settings()->string('mail.from_address'),
            $this->settings()->string('mail.from_name') ?: $this->settings()->string('general.site_name')
        );
    }

    public function media(): MediaRepository
    {
        return new MediaRepository($this->databases->core());
    }

    public function mediaStore(): MediaStore
    {
        return new MediaStore(
            $this->media(),
            $this->config->path('uploads'),
            (int) $this->config->get('security.max_upload_bytes', MediaStore::DEFAULT_MAX_BYTES),
            $this->config->get('media.reencode_images', true) !== false
        );
    }

    public function pageInput(): PageInput
    {
        return new PageInput(
            $this->sanitizer(),
            maxProjectBytes: (int) $this->config->get('security.max_editor_project_bytes', PageInput::DEFAULT_MAX_PROJECT_BYTES)
        );
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

    public function licenses(): LicenseService
    {
        return $this->licenses ??= new LicenseService(
            new LicenseRepository($this->databases->core()),
            new LicenseKey(),
            LicenseService::hostFrom($this->config->string('app.url'), (string) ($_SERVER['HTTP_HOST'] ?? '')),
            $this->config->string('license.whitelabel_key'),
        );
    }

    public function licenseIntegrity(): LicenseIntegrity
    {
        return new LicenseIntegrity($this->config->path('root'));
    }

    /**
     * Pflicht-Label nach MGD-Lizenz. Entfällt nur mit gültiger Whitelabel-Lizenz.
     */
    public function poweredBy(): PoweredBy
    {
        return new PoweredBy($this->licenses()->isWhitelabel(), $this->settings()->string('license.powered_by_align'));
    }

    public function modules(): ModuleManager
    {
        return $this->modules ??= new ModuleManager($this, $this->pathOr('modules', 'modules'));
    }

    /**
     * Konfigurierter Pfad oder Standard relativ zum Projektordner (für ältere config.php ohne neue Einträge).
     */
    public function pathOr(string $name, string $relativeDefault): string
    {
        $configured = $this->config->get('paths.' . $name);

        return is_string($configured) && $configured !== ''
            ? $configured
            : $this->config->path('root') . '/' . $relativeDefault;
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
