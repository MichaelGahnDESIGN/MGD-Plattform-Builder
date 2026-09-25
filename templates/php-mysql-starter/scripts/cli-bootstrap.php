<?php

declare(strict_types=1);

/*
 * Gemeinsamer Einstieg für CLI-Skripte. Verweigert Aufrufe über den Webserver.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Liest --name=wert bzw. --flag aus $argv.
 */
function cli_option(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $argument) {
        if ($argument === '--' . $name) {
            return '1';
        }

        if (str_starts_with($argument, '--' . $name . '=')) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return $default;
}

function cli_fail(string $message): never
{
    fwrite(STDERR, 'Fehler: ' . $message . PHP_EOL);
    exit(1);
}

return require dirname(__DIR__) . '/src/bootstrap.php';
