<?php

declare(strict_types=1);

use MGD\Starter\Core\App;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\RedirectException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\Http\Router;
use MGD\Starter\Core\View\SiteLayout;
use MGD\Starter\Core\View\View;
use MGD\Starter\Site\MaintenanceGate;

/*
 * Front-Controller. Alle Anfragen laufen über diese Datei (siehe .htaccess).
 */

// Lokaler PHP-Testserver (php -S … public/index.php): vorhandene statische Dateien direkt ausliefern.
if (PHP_SAPI === 'cli-server') {
    $staticFile = realpath(__DIR__ . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

    if ($staticFile !== false && is_file($staticFile) && str_starts_with($staticFile, __DIR__ . DIRECTORY_SEPARATOR)
        && pathinfo($staticFile, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

$root = dirname(__DIR__);

try {
    /** @var App $app */
    $app = require $root . '/src/bootstrap.php';
} catch (Throwable $exception) {
    error_log('[mgd-starter] bootstrap: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo str_contains($exception->getMessage(), 'config.php')
        ? $exception->getMessage()
        : 'Die Anwendung konnte nicht gestartet werden. Details stehen im Server-Log.';
    exit;
}

$errorPage = static function (App $app, int $status, string $message): Response {
    $body = '<div class="container"><article class="page"><h1>' . View::e((string) $status) . '</h1><p>' . View::e($message) . '</p>'
        . '<p><a href="' . View::e(View::url('/')) . '">Zur Startseite</a></p></article></div>';

    try {
        return Response::html((new SiteLayout($app))->render('Fehler ' . $status, $body), $status);
    } catch (Throwable) {
        return Response::text($status . ' – ' . $message, 'text/plain; charset=utf-8', $status);
    }
};

$app->startSession();
$request = Request::fromGlobals($app->config->string('app.base_path'));
$router = new Router();
(require $root . '/src/routes.php')($router, $app);
$app->modules()->boot($router);

try {
    $response = (new MaintenanceGate($app))->check($request)
        ?? $router->dispatch($request)
        ?? $errorPage($app, 404, 'Seite nicht gefunden.');
} catch (RedirectException $redirect) {
    $response = Response::redirect($redirect->location);
} catch (HttpException $exception) {
    $response = $errorPage($app, $exception->status, $exception->getMessage());
} catch (Throwable $exception) {
    error_log('[mgd-starter] ' . $exception::class . ': ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());
    $response = $errorPage($app, 500, 'Ein interner Fehler ist aufgetreten.');
}

$app->headers->send();
$response->send();
