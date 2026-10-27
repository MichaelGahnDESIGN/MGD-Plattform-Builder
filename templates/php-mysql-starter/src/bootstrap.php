<?php

declare(strict_types=1);

use MGD\Starter\Core\App;
use MGD\Starter\Core\Config;
use MGD\Starter\Core\Database\Databases;
use MGD\Starter\Core\Http\SecurityHeaders;
use MGD\Starter\Core\View\View;

require __DIR__ . '/autoload.php';

if (PHP_VERSION_ID < 80200) {
    throw new RuntimeException('PHP 8.2 oder neuer ist erforderlich.');
}

$config = Config::load(dirname(__DIR__) . '/config/config.php');
$private = $config->get('db.private');
date_default_timezone_set($config->string('app.timezone', 'Europe/Berlin'));
View::setBasePath($config->string('app.base_path'));

$app = new App(
    $config,
    new Databases((array) $config->get('db.core', []), is_array($private) ? $private : null),
    new SecurityHeaders($config->bool('security.hsts'))
);

// Optionale Projekt-Hooks (nur Admins mit allow_php_editor können sie im Backoffice bearbeiten).
$hooks = $config->get('paths.custom_php');

if (is_string($hooks) && is_file($hooks)) {
    require $hooks;
}

return $app;
