<?php

declare(strict_types=1);

/*
 * FTP-Deployment (FTPS bevorzugt). Standard ist ein Probelauf (Dry-Run).
 *
 * Aufruf:
 *   php scripts/ftp-deploy.php                 Probelauf: zeigt, was hochgeladen würde
 *   php scripts/ftp-deploy.php --execute       Hochladen per FTPS (explizites TLS)
 * Optionen:
 *   --insecure       unverschlüsseltes FTP erlauben, falls FTPS nicht möglich ist (nicht empfohlen)
 *   --with-config    config/config.php mit hochladen (enthält Geheimnisse!)
 *   --with-custom    custom.css/custom.js/hooks.php überschreiben (sonst bleiben Server-Änderungen erhalten)
 *   --with-scripts   scripts/ mit hochladen (nur sinnvoll mit SSH/CLI auf dem Server)
 *   --config=pfad    alternative Konfigurationsdatei
 * Zugangsdaten: Abschnitt "ftp" in config.php oder Umgebungsvariablen
 *   MGD_FTP_HOST, MGD_FTP_PORT, MGD_FTP_USER, MGD_FTP_PASSWORD, MGD_FTP_REMOTE_PATH
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const DEPLOY_ROOTS = ['public', 'src', 'config', 'database', 'custom', 'storage', '.htaccess', 'VERSION', 'version.json', 'release-notes.json', 'template.json'];

function option(array $argv, string $name): ?string
{
    foreach ($argv as $argument) {
        if ($argument === '--' . $name) {
            return '1';
        }

        if (str_starts_with($argument, '--' . $name . '=')) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return null;
}

function fail(string $message): never
{
    fwrite(STDERR, 'Fehler: ' . $message . PHP_EOL);
    exit(1);
}

function ftp_settings(array $argv, string $root): array
{
    $configFile = option($argv, 'config') ?? $root . '/config/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    $ftp = is_array($config['ftp'] ?? null) ? $config['ftp'] : [];
    $value = static fn (string $env, string $key, string $default = ''): string => (string) (getenv($env) ?: ($ftp[$key] ?? $default));

    $settings = [
        'host' => $value('MGD_FTP_HOST', 'host'),
        'port' => (int) $value('MGD_FTP_PORT', 'port', '21'),
        'user' => $value('MGD_FTP_USER', 'user'),
        'password' => $value('MGD_FTP_PASSWORD', 'password'),
        'remote_path' => rtrim($value('MGD_FTP_REMOTE_PATH', 'remote_path', '/'), '/'),
        'passive' => ($ftp['passive'] ?? true) !== false,
    ];

    foreach (['host', 'user', 'password'] as $required) {
        if ($settings[$required] === '' || $settings[$required] === 'change-me') {
            fail('FTP-Zugangsdaten unvollständig (' . $required . '). config.php oder MGD_FTP_* setzen.');
        }
    }

    if (str_contains($settings['remote_path'], 'change-me')) {
        fail('ftp.remote_path ist noch nicht gesetzt.');
    }

    return $settings;
}

function is_excluded(string $relative, array $argv): bool
{
    $name = basename($relative);

    if ($name === '.DS_Store' || str_ends_with($name, '.md') || preg_match('#(^|/)(\.git|tests|node_modules)(/|$)#', $relative) === 1) {
        return true;
    }

    if ($relative === 'config/config.php') {
        return option($argv, 'with-config') === null;
    }

    if (in_array($relative, ['public/assets/custom/custom.css', 'public/assets/custom/custom.js', 'custom/hooks.php'], true)) {
        return option($argv, 'with-custom') === null;
    }

    if (preg_match('#^(public/uploads|storage)/#', $relative) === 1) {
        return !in_array($name, ['.htaccess', '.gitkeep'], true);
    }

    return false;
}

/**
 * @return list<string> relative Pfade
 */
function collect_files(string $root, array $argv): array
{
    $roots = DEPLOY_ROOTS;

    if (option($argv, 'with-scripts') !== null) {
        $roots[] = 'scripts';
    }

    $files = [];

    foreach ($roots as $entry) {
        $path = $root . '/' . $entry;

        if (is_file($path)) {
            $files[] = $entry;
            continue;
        }

        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }

    $files = array_values(array_filter($files, static fn (string $relative): bool => !is_excluded($relative, $argv)));
    sort($files);

    return $files;
}

function connect(array $settings, bool $insecure): FTP\Connection
{
    if (!extension_loaded('ftp')) {
        fail('Die PHP-Erweiterung "ftp" ist nicht installiert.');
    }

    $connection = function_exists('ftp_ssl_connect') ? @ftp_ssl_connect($settings['host'], $settings['port'], 20) : false;
    $secure = $connection !== false;

    if ($connection !== false && !@ftp_login($connection, $settings['user'], $settings['password'])) {
        $connection = false;
        $secure = false;
    }

    if ($connection === false) {
        if (!$insecure) {
            fail('FTPS-Verbindung fehlgeschlagen. Nur mit --insecure wird unverschlüsseltes FTP versucht.');
        }

        fwrite(STDERR, "Warnung: unverschlüsseltes FTP – Zugangsdaten werden im Klartext übertragen.\n");
        $connection = @ftp_connect($settings['host'], $settings['port'], 20);

        if ($connection === false || !@ftp_login($connection, $settings['user'], $settings['password'])) {
            fail('FTP-Anmeldung fehlgeschlagen.');
        }
    }

    ftp_pasv($connection, $settings['passive']);
    echo 'Verbunden (' . ($secure ? 'FTPS' : 'FTP') . ') mit ' . $settings['host'] . PHP_EOL;

    return $connection;
}

function ensure_remote_dir(FTP\Connection $connection, string $directory, array &$known): void
{
    $current = '';

    foreach (array_filter(explode('/', $directory), 'strlen') as $segment) {
        $current .= '/' . $segment;

        if (isset($known[$current])) {
            continue;
        }

        if (!@ftp_chdir($connection, $current)) {
            if (@ftp_mkdir($connection, $current) === false) {
                fail('Verzeichnis konnte nicht angelegt werden: ' . $current);
            }
        }

        $known[$current] = true;
    }
}

$root = dirname(__DIR__);
$files = collect_files($root, $argv);
$bytes = array_sum(array_map(static fn (string $f): int => (int) filesize($root . '/' . $f), $files));

if (option($argv, 'execute') === null) {
    foreach ($files as $file) {
        echo '  ', $file, PHP_EOL;
    }

    printf("Probelauf: %d Dateien (%.1f KB). Mit --execute hochladen.\n", count($files), $bytes / 1024);
    exit(0);
}

$settings = ftp_settings($argv, $root);
$connection = connect($settings, option($argv, 'insecure') !== null);
$known = [];
$uploaded = 0;

foreach ($files as $file) {
    $remote = $settings['remote_path'] . '/' . $file;
    ensure_remote_dir($connection, dirname($remote), $known);

    if (!@ftp_put($connection, $remote, $root . '/' . $file, FTP_BINARY)) {
        fail('Upload fehlgeschlagen: ' . $file);
    }

    $uploaded++;
    echo '  ↑ ', $file, PHP_EOL;
}

ftp_close($connection);
printf("Fertig: %d Dateien hochgeladen.\n", $uploaded);
