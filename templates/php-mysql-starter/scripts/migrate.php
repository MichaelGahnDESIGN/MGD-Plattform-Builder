<?php

declare(strict_types=1);

use MGD\Starter\Install\Installer;

/*
 * Führt ausstehende Migrationen für Kern- und private Datenbank aus.
 * Aufruf: php scripts/migrate.php
 */

$app = require __DIR__ . '/cli-bootstrap.php';

try {
    foreach ((new Installer($app))->migrate() as $line) {
        echo $line, PHP_EOL;
    }
} catch (Throwable $exception) {
    cli_fail($exception->getMessage());
}
