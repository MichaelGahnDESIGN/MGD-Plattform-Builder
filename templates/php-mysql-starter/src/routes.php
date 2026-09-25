<?php

declare(strict_types=1);

use MGD\Starter\Admin\AccountController;
use MGD\Starter\Admin\AuditController;
use MGD\Starter\Admin\AuthController;
use MGD\Starter\Admin\CodeEditorController;
use MGD\Starter\Admin\CreditComponentsController;
use MGD\Starter\Admin\CreditsController;
use MGD\Starter\Admin\DashboardController;
use MGD\Starter\Admin\FileLocationsController;
use MGD\Starter\Admin\MediaController;
use MGD\Starter\Admin\PasswordResetController;
use MGD\Starter\Admin\LicenseController;
use MGD\Starter\Admin\ModulesController;
use MGD\Starter\Admin\PageRevisionsController;
use MGD\Starter\Admin\PagesController;
use MGD\Starter\Admin\PageTransferController;
use MGD\Starter\Admin\PageTrashController;
use MGD\Starter\Admin\ReleaseNotesController;
use MGD\Starter\Admin\SettingsController;
use MGD\Starter\Admin\UpdaterController;
use MGD\Starter\Admin\UsersController;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Http\Router;
use MGD\Starter\Install\WebInstallController;
use MGD\Starter\Site\SeoController;
use MGD\Starter\Site\SiteController;

/*
 * Routen. Jede Backoffice-Aktion prüft Rolle (und bei POST CSRF) im Controller selbst.
 */
return static function (Router $router, App $app): void {
    $site = new SiteController($app);
    $seo = new SeoController($app);
    $router->get('/', [$site, 'home']);
    $router->get('/seite/{slug}', [$site, 'page']);
    $router->get('/release-notes', [$site, 'releaseNotes']);
    $router->get('/credits', [$site, 'credits']);
    $router->get('/sitemap.xml', [$seo, 'sitemap']);
    $router->get('/robots.txt', [$seo, 'robots']);

    $install = new WebInstallController($app);
    $router->get('/install', [$install, 'show']);
    $router->post('/install', [$install, 'run']);

    $auth = new AuthController($app);
    $router->get('/login', [$auth, 'showLogin']);
    $router->post('/login', [$auth, 'login']);
    $router->post('/logout', [$auth, 'logout']);

    $reset = new PasswordResetController($app);
    $router->get('/passwort-vergessen', [$reset, 'showRequest']);
    $router->post('/passwort-vergessen', [$reset, 'sendRequest']);
    $router->get('/passwort-zuruecksetzen', [$reset, 'showReset']);
    $router->post('/passwort-zuruecksetzen', [$reset, 'reset']);

    $router->get('/admin', [new DashboardController($app), 'index']);

    $account = new AccountController($app);
    $router->get('/admin/account', [$account, 'index']);
    $router->post('/admin/account', [$account, 'index']);
    $router->get('/admin/account/password', [$account, 'password']);
    $router->post('/admin/account/password', [$account, 'password']);

    $users = new UsersController($app);
    $router->get('/admin/users', [$users, 'index']);
    $router->get('/admin/users/new', [$users, 'create']);
    $router->post('/admin/users/new', [$users, 'create']);
    $router->get('/admin/users/{id}/edit', [$users, 'edit']);
    $router->post('/admin/users/{id}/edit', [$users, 'edit']);
    $router->post('/admin/users/{id}/password', [$users, 'resetPassword']);
    $router->post('/admin/users/{id}/delete', [$users, 'delete']);

    $media = new MediaController($app);
    $router->get('/admin/media', [$media, 'index']);
    $router->post('/admin/media/upload', [$media, 'upload']);
    $router->post('/admin/media/{id}/edit', [$media, 'update']);
    $router->post('/admin/media/{id}/delete', [$media, 'delete']);

    $pages = new PagesController($app);
    $router->get('/admin/pages', [$pages, 'index']);
    $router->get('/admin/pages/new', [$pages, 'create']);
    $router->post('/admin/pages/new', [$pages, 'create']);
    $router->get('/admin/pages/{id}/edit', [$pages, 'edit']);
    $router->post('/admin/pages/{id}/edit', [$pages, 'edit']);
    $router->post('/admin/pages/{id}/delete', [$pages, 'delete']);

    $trash = new PageTrashController($app);
    $router->get('/admin/pages/trash', [$trash, 'index']);
    $router->post('/admin/pages/{id}/restore', [$trash, 'restore']);
    $router->post('/admin/pages/{id}/purge', [$trash, 'purge']);

    $revisions = new PageRevisionsController($app);
    $router->get('/admin/pages/{id}/revisions', [$revisions, 'index']);
    $router->get('/admin/revisions/{id}', [$revisions, 'show']);
    $router->post('/admin/revisions/{id}/restore', [$revisions, 'restore']);

    $transfer = new PageTransferController($app);
    $router->get('/admin/pages/transfer', [$transfer, 'index']);
    $router->post('/admin/pages/export', [$transfer, 'export']);
    $router->post('/admin/pages/import', [$transfer, 'import']);

    $settings = new SettingsController($app);
    $router->get('/admin/settings', [$settings, 'index']);
    $router->post('/admin/settings', [$settings, 'save']);
    $router->post('/admin/settings/design-reset', [$settings, 'resetDesign']);

    $notes = new ReleaseNotesController($app);
    $router->get('/admin/release-notes', [$notes, 'index']);
    $router->get('/admin/release-notes/new', [$notes, 'create']);
    $router->post('/admin/release-notes/new', [$notes, 'create']);
    $router->post('/admin/release-notes/sync', [$notes, 'sync']);
    $router->get('/admin/release-notes/{id}/edit', [$notes, 'edit']);
    $router->post('/admin/release-notes/{id}/edit', [$notes, 'edit']);
    $router->post('/admin/release-notes/{id}/delete', [$notes, 'delete']);

    $credits = new CreditsController($app);
    $router->get('/admin/credits', [$credits, 'index']);
    $router->get('/admin/credits/people/new', [$credits, 'createPerson']);
    $router->post('/admin/credits/people/new', [$credits, 'createPerson']);
    $router->get('/admin/credits/people/{id}/edit', [$credits, 'editPerson']);
    $router->post('/admin/credits/people/{id}/edit', [$credits, 'editPerson']);
    $router->post('/admin/credits/people/{id}/delete', [$credits, 'deletePerson']);

    $components = new CreditComponentsController($app);
    $router->get('/admin/credits/components/new', [$components, 'create']);
    $router->post('/admin/credits/components/new', [$components, 'create']);
    $router->get('/admin/credits/components/{id}/edit', [$components, 'edit']);
    $router->post('/admin/credits/components/{id}/edit', [$components, 'edit']);
    $router->post('/admin/credits/components/{id}/delete', [$components, 'delete']);

    $router->get('/admin/files', [new FileLocationsController($app), 'index']);

    $code = new CodeEditorController($app);
    $router->get('/admin/code', [$code, 'index']);
    $router->get('/admin/code/{lang}', [$code, 'edit']);
    $router->post('/admin/code/{lang}', [$code, 'edit']);

    $updater = new UpdaterController($app);
    $router->get('/admin/updater', [$updater, 'index']);
    $router->post('/admin/updater/check', [$updater, 'check']);

    $router->get('/admin/audit', [new AuditController($app), 'index']);

    $license = new LicenseController($app);
    $router->get('/admin/license', [$license, 'index']);
    $router->post('/admin/license/whitelabel', [$license, 'saveWhitelabel']);
    $router->post('/admin/license/whitelabel/remove', [$license, 'removeWhitelabel']);

    $modules = new ModulesController($app);
    $router->get('/admin/modules', [$modules, 'index']);
    $router->post('/admin/modules/toggle', [$modules, 'toggle']);
    $router->post('/admin/modules/license', [$modules, 'saveLicense']);
};
