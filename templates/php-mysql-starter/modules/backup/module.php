<?php

declare(strict_types=1);

use MGD\Backup\Admin\BackupController;
use MGD\Backup\Admin\CronEndpoint;
use MGD\Backup\Admin\Services;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\Http\Router;
use MGD\Starter\Core\Module\ModuleManifest;

/*
 * Backup-Modul: Routen im Backoffice (/admin/backup, nur Rolle Administration) und der Cron-Einstieg /backup-cron/<token>.
 * Die Klassen des Moduls liegen unter modules/backup/src/ (Namensraum MGD\Backup\), der Autoloader wird hier registriert.
 */
if (!class_exists(Services::class, false)) {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'MGD\\Backup\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));

        if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
            return;
        }

        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

return static function (Router $router, App $app, ModuleManifest $module): void {
    $controller = static fn (): BackupController => new BackupController($app);

    $router->get('/admin/backup', static fn (Request $request): Response => $controller()->index($request));
    $router->get('/admin/backup/run/{id}', static fn (Request $request, array $params): Response => $controller()->details($request, $params));
    $router->get('/admin/backup/download/{id}/{seq}', static fn (Request $request, array $params): Response => $controller()->download($request, $params));

    foreach ([
        '/admin/backup/run' => 'run',
        '/admin/backup/cancel' => 'cancel',
        '/admin/backup/settings' => 'saveSettings',
        '/admin/backup/key' => 'saveKey',
        '/admin/backup/destination' => 'saveDestination',
        '/admin/backup/destination/test' => 'testDestination',
        '/admin/backup/destination/delete' => 'deleteDestination',
        '/admin/backup/token' => 'newToken',
        '/admin/backup/run/delete' => 'deleteRun',
    ] as $path => $method) {
        $router->post($path, static fn (Request $request): Response => $controller()->{$method}($request));
    }

    // Cron-Einstieg ohne Sitzung: authentifiziert nur ueber das Token (siehe CronEndpoint).
    $router->get('/backup-cron/{token}', static fn (Request $request, array $params): Response => (new CronEndpoint(Services::for($app)))->handle($request, (string) ($params['token'] ?? '')));
};
