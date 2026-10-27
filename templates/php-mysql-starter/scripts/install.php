<?php

declare(strict_types=1);

use MGD\Starter\Install\Installer;

/*
 * Installation: Migrationen, Standardeinstellungen, Rechtstexte, Credits, Release Notes, erster Admin.
 *
 * Aufruf:
 *   php scripts/install.php --admin-email=admin@example.org --admin-name="Admin"
 * Das Passwort wird interaktiv abgefragt (ohne Anzeige) oder aus der Umgebungsvariable
 * MGD_ADMIN_PASSWORD gelesen. Niemals als Argument übergeben (landet sonst in der Shell-History).
 * Ohne --admin-email wird kein Admin angelegt (z. B. für erneute Ausführung).
 */

$app = require __DIR__ . '/cli-bootstrap.php';

function read_password(): string
{
    $fromEnv = getenv('MGD_ADMIN_PASSWORD');

    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }

    fwrite(STDOUT, 'Admin-Passwort (mind. 12 Zeichen): ');
    $interactive = function_exists('posix_isatty') && posix_isatty(STDIN);

    if ($interactive) {
        shell_exec('stty -echo');
    }

    $password = rtrim((string) fgets(STDIN), "\r\n");

    if ($interactive) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }

    return $password;
}

$email = cli_option($argv, 'admin-email');
$admin = null;

if ($email !== null) {
    $name = cli_option($argv, 'admin-name', 'Administrator');
    $password = read_password();
    $admin = ['email' => $email, 'name' => (string) $name, 'password' => $password];
}

try {
    foreach ((new Installer($app))->install($admin) as $line) {
        echo $line, PHP_EOL;
    }
} catch (Throwable $exception) {
    cli_fail($exception->getMessage());
}

echo 'Fertig.', PHP_EOL;
