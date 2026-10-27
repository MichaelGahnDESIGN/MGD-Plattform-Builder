<?php

declare(strict_types=1);

/*
 * PSR-4-Autoloader ohne Composer: MGD\Starter\ => src/
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'MGD\\Starter\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
