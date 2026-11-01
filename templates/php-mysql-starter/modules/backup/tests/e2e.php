<?php

declare(strict_types=1);

/*
 * End-to-End-Test des Backup-Moduls gegen den echten Starter und eine MySQL/MariaDB-Testdatenbank (lokaler Webserver
 * php -S auf 127.0.0.1, kein Zugriff auf fremde Server). Nicht Teil von `npm test`, weil eine Datenbank noetig ist.
 *
 *   MGD_TEST_DB_HOST=127.0.0.1 MGD_TEST_DB_PORT=3306 MGD_TEST_DB_NAME=mgd_backup_e2e \
 *   MGD_TEST_DB_USER=mgd MGD_TEST_DB_PASSWORD=mgdpass php modules/backup/tests/e2e.php
 *
 * Die Datenbank muss LEER sein (der Test installiert den Starter hinein). Mit MGD_E2E_RESET=1 werden vorhandene Tabellen
 * vorher geloescht - nur fuer Wegwerf-Datenbanken verwenden. Alle Passwoerter, Schluessel und Hosts im Test sind erfunden.
 */

use MGD\Backup\Format\SecretKeyFile;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Config;
use MGD\Starter\Core\Database\Databases;
use MGD\Starter\Core\Http\SecurityHeaders;
use MGD\Starter\Install\Installer;

require __DIR__ . '/support.php';

if (!extension_loaded('curl') || !extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "FAIL: curl und pdo_mysql werden gebraucht\n");
    exit(1);
}

$db = [
    'host' => getenv('MGD_TEST_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('MGD_TEST_DB_PORT') ?: 3306),
    'name' => getenv('MGD_TEST_DB_NAME') ?: 'mgd_backup_e2e',
    'user' => getenv('MGD_TEST_DB_USER') ?: 'mgd',
    'password' => getenv('MGD_TEST_DB_PASSWORD') ?: 'mgdpass',
    'charset' => 'utf8mb4',
];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if ($existing !== []) {
    if (getenv('MGD_E2E_RESET') !== '1') {
        fwrite(STDERR, "FAIL: Testdatenbank ist nicht leer (MGD_E2E_RESET=1 setzt sie zurueck)\n");
        exit(1);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($existing as $table) {
        $pdo->exec('DROP TABLE `' . $table . '`');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// --- Projekt aus der Vorlage aufbauen -------------------------------------------------------------------------
$starter = dirname(__DIR__, 3);
$work = T::tempDir('mgdbk-e2e-');
$project = $work . '/project';
$copy = static function (string $from, string $to) use (&$copy): void {
    mkdir($to, 0755, true);

    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'config.php' || $entry === 'install.lock') {
            continue;
        }

        is_dir($from . '/' . $entry) ? $copy($from . '/' . $entry, $to . '/' . $entry) : copy($from . '/' . $entry, $to . '/' . $entry);
    }
};
$copy($starter, $project);
$port = random_int(38000, 38900);
$base = 'http://127.0.0.1:' . $port;
file_put_contents($project . '/config/config.php', '<?php return array_replace_recursive(require __DIR__ . "/config.example.php", ' . var_export([
    'app' => ['key' => bin2hex(random_bytes(32)), 'url' => $base, 'environment' => 'testing'],
    'db' => ['core' => $db, 'private' => null],
    'security' => ['session_secure' => false],
    'install' => ['token' => 'e2e-install-token-not-used'],
], true) . ');');
@mkdir($project . '/public/uploads/2026', 0755, true);
file_put_contents($project . '/public/uploads/bild.txt', 'Originalinhalt Bild');
file_put_contents($project . '/public/uploads/2026/doc.txt', 'Originalinhalt Dokument');
file_put_contents($project . '/public/uploads/trash.tmp', 'Muell');

require_once $project . "/src/autoload.php";
$config = Config::load($project . "/config/config.php");
$app = new App($config, new Databases($db), new SecurityHeaders());
$adminPassword = 'e2e-admin-passwort-erfunden-1';
$editorPassword = 'e2e-editor-passwort-erfunden-1';
(new Installer($app))->install(['email' => 'admin@example.test', 'name' => 'E2E Admin', 'password' => $adminPassword]);
$app->users()->create('editor@example.test', 'E2E Editor', $editorPassword, Role::Editor);
$admin = $app->users()->findActive((int) $pdo->query("SELECT id FROM users WHERE email = 'admin@example.test'")->fetchColumn());
$module = $app->modules()->discover()['backup'] ?? null;
T::check($module !== null && $module->requiresStarter !== '' && $app->modules()->isCompatible($module), 'Modul: backup wird gefunden und ist mit dem Starter kompatibel');
$app->modules()->enable($module, $admin);
T::check($app->modules()->isEnabled('backup') && (int) $pdo->query("SELECT COUNT(*) FROM backup_settings")->fetchColumn() === 1, 'Modul: Aktivieren fuehrt die Migration aus (backup_settings angelegt)');
T::check((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version = 'module/backup/001_backup'")->fetchColumn() === 1, 'Modul: Migration ist in schema_migrations vermerkt');

// --- Webserver -----------------------------------------------------------------------------------------------
$server = proc_open([PHP_BINARY, '-d', 'opcache.lockfile_path=' . $work, '-S', '127.0.0.1:' . $port, '-t', $project . '/public', $project . '/public/index.php'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $work . '/server.log', 'w'], 2 => ['file', $work . '/server.err', 'w']], $pipes, $project);
register_shutdown_function(static function () use ($server): void {
    if (is_resource($server)) {
        proc_terminate($server);
    }
});

for ($i = 0; $i < 150; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);

    if ($c !== false) {
        fclose($c);
        break;
    }

    usleep(100000);
}

/**
 * @return array{status:int,headers:array<string,string>,body:string}
 */
$http = static function (string $jar, string $method, string $path, array $form = []) use ($base): array {
    $ch = curl_init($base . $path);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 60, CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            return strlen($line);
        },
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }

    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    return ['status' => $status, 'headers' => $headers, 'body' => $body];
};
$csrf = static function (string $html): string {
    preg_match('/name="_csrf" value="([0-9a-f]{64})"/', $html, $m);

    return $m[1] ?? '';
};
$login = static function (string $jar, string $email, string $password) use ($http, $csrf): array {
    $page = $http($jar, 'GET', '/login');

    return $http($jar, 'POST', '/login', ['_csrf' => $csrf($page['body']), 'email' => $email, 'password' => $password]);
};
/** POST mit frischem CSRF-Token von /admin/backup. */
$post = static function (string $jar, string $path, array $form) use ($http, $csrf): array {
    $page = $http($jar, 'GET', '/admin/backup');

    return $http($jar, 'POST', $path, ['_csrf' => $csrf($page['body'])] + $form);
};
$flash = static function (string $jar) use ($http): string {
    return html_entity_decode(strip_tags($http($jar, 'GET', '/admin/backup')['body']), ENT_QUOTES);
};

$adminJar = $work . '/admin.jar';
$editorJar = $work . '/editor.jar';
$anonJar = $work . '/anon.jar';

// --- Zugriff -------------------------------------------------------------------------------------------------
$r = $http($anonJar, 'GET', '/admin/backup');
T::check($r['status'] === 303 && str_ends_with($r['headers']['location'] ?? '', '/login'), 'Zugriff: ohne Anmeldung -> Weiterleitung zum Login');
T::check($http($anonJar, 'GET', '/admin/backup/download/1/1')['status'] === 303, 'Zugriff: Download ohne Anmeldung -> Login');
T::check($http($anonJar, 'POST', '/admin/backup/run', ['kind' => 'db'])['status'] === 303, 'Zugriff: POST ohne Anmeldung -> Login');
$r = $login($editorJar, 'editor@example.test', $editorPassword);
T::check($r['status'] === 303, 'Zugriff: Redakteur kann sich anmelden');
T::check($http($editorJar, 'GET', '/admin/backup')['status'] === 403, 'Zugriff: Redaktion bekommt 403 auf /admin/backup (nur Administration)');
T::check($http($editorJar, 'GET', '/admin/backup/download/1/1')['status'] === 403, 'Zugriff: Redaktion bekommt 403 auf den Download');
T::check($post($editorJar, '/admin/backup/run', ['kind' => 'db'])['status'] === 403, 'Zugriff: Redaktion kann keinen Lauf starten (403)');
$r = $login($adminJar, 'admin@example.test', $adminPassword);
T::check($r['status'] === 303, 'Zugriff: Administrator meldet sich an');
$page = $http($adminJar, 'GET', '/admin/backup');
T::check($page['status'] === 200 && str_contains($page['body'], 'Verschlüsselte Sicherungen') && str_contains($page['body'], 'aria-labelledby="bk-status"'), 'Seite: Backup-Uebersicht wird fuer Administration angezeigt');
T::check(str_contains($page['body'], 'mgd-backup.php keygen') && str_contains($page['body'], 'Datenschutz'), 'Seite: Hinweis auf Schluesselerzeugung und Datenschutz vorhanden');
T::check(str_contains($page['headers']['content-security-policy'] ?? '', "default-src 'self'") || isset($page['headers']['content-security-policy']), 'Seite: Content-Security-Policy des Starters aktiv');
// Barrierefreiheit: jedes sichtbare Formularfeld hat ein Label, es gibt genau eine h1 und benannte Abschnitte.
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8">' . $page['body']);
$xpath = new DOMXPath($dom);
$unlabeled = 0;

foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="checkbox")] | //select | //textarea') as $field) {
    $labelled = $xpath->query('ancestor::label', $field)->length > 0 || $field->getAttribute('aria-label') !== '';

    if (!$labelled) {
        $unlabeled++;
    }
}

T::check($unlabeled === 0, 'Barrierefreiheit: alle Formularfelder haben ein Label (ohne Label: ' . $unlabeled . ')');
T::check($xpath->query('//main//h1')->length === 1 && $xpath->query('//section[@aria-labelledby]')->length >= 6, 'Barrierefreiheit: genau eine h1, Abschnitte tragen aria-labelledby');
T::check($xpath->query('//form[.//input[@name="_csrf"]]')->length >= 5, 'Seite: Formulare tragen ein CSRF-Token');
$noCsrf = $http($adminJar, 'POST', '/admin/backup/run', ['kind' => 'db']);
T::check($noCsrf['status'] === 419, 'CSRF: POST ohne Token wird mit 419 abgelehnt');
$badCsrf = $http($adminJar, 'POST', '/admin/backup/run', ['kind' => 'db', '_csrf' => str_repeat('0', 64)]);
T::check($badCsrf['status'] === 419, 'CSRF: POST mit falschem Token wird mit 419 abgelehnt');

// --- Schluessel, Step-up -------------------------------------------------------------------------------------
$pair = T::keyPair();
$r = $post($adminJar, '/admin/backup/key', ['role' => 'primary', 'public_key' => $pair['text'], 'current_password' => 'falsches-passwort']);
T::check($r['status'] === 303 && $pdo->query('SELECT primary_key_fp FROM backup_settings')->fetchColumn() === null, 'Step-up: falsches Passwort speichert keinen Schluessel');
T::check(str_contains($flash($adminJar), 'Das Passwort ist falsch'), 'Step-up: Fehlermeldung wird angezeigt');
$post($adminJar, '/admin/backup/key', ['role' => 'primary', 'public_key' => 'mgdbk-pk1:ungueltig:00000000', 'current_password' => $adminPassword]);
T::check($pdo->query('SELECT primary_key_fp FROM backup_settings')->fetchColumn() === null && str_contains($flash($adminJar), 'Schlüssel ist ungültig'), 'Schluessel: ungueltiges Format wird abgelehnt');
$post($adminJar, '/admin/backup/key', ['role' => 'primary', 'public_key' => $pair['text'], 'current_password' => $adminPassword]);
T::check($pdo->query('SELECT primary_key_fp FROM backup_settings')->fetchColumn() === $pair['fp'], 'Schluessel: oeffentlicher Schluessel wird mit Passwort gespeichert');
T::check(str_contains($http($adminJar, 'GET', '/admin/backup')['body'], $pair['fp']), 'Schluessel: Fingerabdruck wird angezeigt');
$audit = json_encode($pdo->query("SELECT action_name, metadata_json FROM audit_log WHERE action_name LIKE 'backup.%'")->fetchAll());
T::check(str_contains((string) $audit, 'backup.stepup_failed') && str_contains((string) $audit, 'backup.key_changed'), 'Audit: Fehlversuch und Schluesselwechsel stehen im Audit-Log');

// --- Ziele ----------------------------------------------------------------------------------------------------
$post($adminJar, '/admin/backup/destination', ['id' => '0', 'dest_type' => 'local', 'name' => 'Lokal', 'keep_count' => '2', 'enabled' => '1', 'current_password' => $adminPassword]);
$local = $pdo->query("SELECT * FROM backup_destinations WHERE dest_type = 'local'")->fetch();
T::check($local !== false && (int) $local['enabled'] === 1 && $local['status'] === 'ok', 'Ziel: lokales Ziel wird angelegt und ist aktiv');
$post($adminJar, '/admin/backup/destination', ['id' => '0', 'dest_type' => 'local', 'name' => 'Lokal 2', 'keep_count' => '2', 'current_password' => $adminPassword]);
T::check((int) $pdo->query("SELECT COUNT(*) FROM backup_destinations WHERE dest_type = 'local'")->fetchColumn() === 1, 'Ziel: es gibt hoechstens ein lokales Ziel');

$ftpPassword = 'ftp-passwort-erfunden-9876';
$ftpForm = ['id' => '0', 'dest_type' => 'ftp', 'name' => 'Fern', 'keep_count' => '3', 'enabled' => '1', 'host' => 'ftp.example.org', 'port' => '21', 'mode' => 'explicit', 'username' => 'testnutzer', 'password' => $ftpPassword, 'base_path' => '/backups', 'current_password' => $adminPassword];

foreach ([['host' => '127.0.0.1'], ['host' => 'localhost'], ['host' => '10.0.0.5'], ['host' => '169.254.169.254'], ['host' => 'ftp.example.org/pfad'], ['port' => '2121'], ['base_path' => '/a/../b'], ['base_path' => 'relativ'], ['username' => 'a:b']] as $override) {
    $before = (int) $pdo->query("SELECT COUNT(*) FROM backup_destinations")->fetchColumn();
    $post($adminJar, '/admin/backup/destination', $override + $ftpForm);
    T::check((int) $pdo->query("SELECT COUNT(*) FROM backup_destinations")->fetchColumn() === $before, 'Ziel: ungueltige Eingabe wird abgelehnt (' . array_key_first($override) . '=' . array_values($override)[0] . ')');
}

$post($adminJar, '/admin/backup/destination', ['host' => '93.184.216.34'] + $ftpForm); // oeffentliche IP-Adresse: wird nur gespeichert, nie kontaktiert
$ftp = $pdo->query("SELECT * FROM backup_destinations WHERE dest_type = 'ftp'")->fetch();
T::check($ftp !== false && (int) $ftp['enabled'] === 0 && $ftp['status'] === 'unconfigured', 'Ziel: ein ungetestetes FTPS-Ziel wird nie sofort aktiv');
T::check(!str_contains((string) $ftp['config_json'], $ftpPassword) && !str_contains((string) $ftp['secret_enc'], $ftpPassword) && str_starts_with((string) $ftp['secret_enc'], 'v1:'), 'Ziel: Passwort ist in der Datenbank nur verschluesselt');
$body = $http($adminJar, 'GET', '/admin/backup?edit=' . $ftp['id'])['body'];
T::check(!str_contains($body, $ftpPassword) && !str_contains($body, (string) $ftp['secret_enc']) && str_contains($body, 'testnutzer'), 'Ziel: Passwort und Geheimtext werden nie ausgegeben');
$post($adminJar, '/admin/backup/destination', ['id' => (string) $ftp['id'], 'host' => '93.184.216.35', 'password' => ''] + $ftpForm);
$ftp2 = $pdo->query("SELECT * FROM backup_destinations WHERE dest_type = 'ftp'")->fetch();
T::check($ftp2['secret_enc'] === null && (int) $ftp2['enabled'] === 0, 'Ziel: Serverwechsel ohne neues Passwort verwirft das gespeicherte Passwort');
$post($adminJar, '/admin/backup/destination/delete', ['id' => (string) $ftp['id'], 'current_password' => $adminPassword]);
T::check((int) $pdo->query("SELECT COUNT(*) FROM backup_destinations WHERE dest_type = 'ftp'")->fetchColumn() === 0, 'Ziel: ungenutztes Ziel laesst sich loeschen');

// --- Einstellungen -------------------------------------------------------------------------------------------
$settingsForm = ['schedule_interval' => 'daily', 'schedule_kind' => 'full', 'schedule_time' => '03:00', 'schedule_weekday' => '7', 'schedule_monthday' => '1', 'max_age_days' => '60', 'part_size_mb' => '8', 'areas' => ['uploads', 'fremd-bereich', '../etc'], 'exclude_patterns' => "*.tmp\n../boese\n", 'current_password' => $adminPassword];
$post($adminJar, '/admin/backup/settings', ['max_age_days' => '3'] + $settingsForm);
T::check($pdo->query('SELECT schedule_interval FROM backup_settings')->fetchColumn() === 'off', 'Einstellungen: ungueltiger Wert (Hoechstalter 3) wird abgelehnt');
$post($adminJar, '/admin/backup/settings', $settingsForm);
$settings = $pdo->query('SELECT * FROM backup_settings')->fetch();
T::check($settings['schedule_interval'] === 'daily' && $settings['next_run_at'] !== null, 'Einstellungen: Zeitplan wird gespeichert, naechster Lauf berechnet');
T::check($settings['areas_json'] === '["uploads"]', 'Einstellungen: Bereiche nur aus der Whitelist (fremde und ../-Namen verworfen)');
T::check($settings['exclude_patterns'] === '*.tmp', 'Einstellungen: unsichere Ausschlussmuster werden verworfen');

// --- Lauf (Web: anlegen, Cron: abarbeiten) ---------------------------------------------------------------------
$post($adminJar, '/admin/backup/run', ['kind' => 'full']);
$run = $pdo->query('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 1')->fetch();
T::check($run !== false && $run['status'] === 'queued' && $run['trigger_type'] === 'manual', 'Lauf: "Jetzt sichern" legt einen wartenden Lauf an');
$post($adminJar, '/admin/backup/run', ['kind' => 'db']);
T::check((int) $pdo->query('SELECT COUNT(*) FROM backup_runs')->fetchColumn() === 1 && str_contains($flash($adminJar), 'läuft bereits'), 'Lauf: ein zweiter Start waehrend eines aktiven Laufs wird abgelehnt');

$cron = [PHP_BINARY, $project . '/modules/backup/tools/cron.php'];
[$code, $out] = T::run($cron);
T::check($code === 0 && str_starts_with($out, 'ok'), 'Cron-CLI: Aufruf arbeitet den Lauf ab (' . trim($out) . ')');

for ($i = 0; $i < 20 && in_array((string) $pdo->query('SELECT status FROM backup_runs ORDER BY id DESC LIMIT 1')->fetchColumn(), ['queued', 'running'], true); $i++) {
    T::run($cron);
}

$run = $pdo->query('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 1')->fetch();
T::check($run['status'] === 'completed' || $run['status'] === 'partial', 'Lauf: nach den Cron-Aufrufen fertig (Status ' . $run['status'] . ')');
T::check($run['db_consistency'] === 'snapshot', 'Lauf: MariaDB-Dump mit konsistenter Lesesicht (snapshot)');
$parts = $pdo->query('SELECT * FROM backup_parts WHERE run_id = ' . (int) $run['id'] . ' ORDER BY seq')->fetchAll();
$folder = $project . '/storage/backups/mgdbk/' . $run['folder_name'];
T::check(is_dir($folder) && (fileperms($folder) & 0777) === 0700 && count($parts) >= 3, 'Lauf: lokale Ablage mit Rechten 0700 und mindestens DB-, Datei- und Manifest-Teil');

// --- Download -------------------------------------------------------------------------------------------------
$dbPart = $parts[0];
$r = $http($adminJar, 'GET', '/admin/backup/download/' . $run['id'] . '/' . $dbPart['seq']);
T::check($r['status'] === 200 && hash('sha256', $r['body']) === $dbPart['cipher_sha256'], 'Download: Teil kommt unveraendert an (SHA-256 gleich)');
T::check(($r['headers']['x-content-type-options'] ?? '') === 'nosniff' && str_contains($r['headers']['cache-control'] ?? '', 'no-store') && ($r['headers']['content-type'] ?? '') === 'application/octet-stream', 'Download: nosniff, no-store, octet-stream');
T::check(($r['headers']['content-security-policy'] ?? '') === 'sandbox', 'Download: Content-Security-Policy sandbox');
T::check(str_contains($r['headers']['content-disposition'] ?? '', 'attachment; filename="' . $dbPart['file_name'] . '"') && (int) ($r['headers']['content-length'] ?? 0) === (int) $dbPart['cipher_bytes'], 'Download: Content-Disposition und Content-Length stimmen');
T::check(!str_contains($r['body'], 'Originalinhalt') && !str_contains($r['body'], 'admin@example.test'), 'Download: kein Klartext im Teil');
foreach (['/admin/backup/download/' . $run['id'] . '/999', '/admin/backup/download/999/1', '/admin/backup/download/' . $run['id'] . '/1abc', '/admin/backup/download/' . $run['id'] . '/..%2F..%2Fconfig', '/admin/backup/download/' . $run['id'] . '/-1'] as $path) {
    T::check($http($adminJar, 'GET', $path)['status'] === 404, 'Download: ungueltiger Teil/Lauf liefert 404 (' . $path . ')');
}

$pdo->exec("UPDATE backup_parts SET file_name = '../../config/config.php' WHERE id = " . (int) $dbPart['id']);
T::check($http($adminJar, 'GET', '/admin/backup/download/' . $run['id'] . '/' . $dbPart['seq'])['status'] === 404, 'Download: manipulierter Dateiname in der Datenbank wird nicht ausgeliefert (Whitelist-Regex)');
$pdo->exec("UPDATE backup_parts SET file_name = " . $pdo->quote($dbPart['file_name']) . " WHERE id = " . (int) $dbPart['id']);
$detail = $http($adminJar, 'GET', '/admin/backup/run/' . $run['id']);
T::check($detail['status'] === 200 && str_contains($detail['body'], 'download/' . $run['id'] . '/1'), 'Details: Teile und Download-Links werden angezeigt');

// --- Entschluesseln mit dem CLI-Werkzeug und Vergleich ------------------------------------------------------------
mkdir($work . '/keys', 0700);
SecretKeyFile::write($work . '/keys/p.key', $pair['secret'], null);
$signer = (string) $pdo->query('SELECT signing_fp FROM backup_settings')->fetchColumn();
[$code, $out, $err] = T::run([PHP_BINARY, $project . '/modules/backup/tools/mgd-backup.php', 'decrypt', $folder, '--key-file=' . $work . '/keys/p.key', '--out=' . $work . '/plain', '--signer=' . $signer]);
T::check($code === 0, 'Tool: decrypt des Server-Backups gelingt (' . trim($err) . ')');
T::check(file_get_contents($work . '/plain/uploads/bild.txt') === 'Originalinhalt Bild' && file_get_contents($work . '/plain/uploads/2026/doc.txt') === 'Originalinhalt Dokument' && !file_exists($work . '/plain/uploads/trash.tmp'), 'Tool: Dateien stimmen, Ausschluss *.tmp greift');
T::check(!is_dir($work . '/plain/config') && !file_exists($work . '/plain/config.php'), 'Dateien: config/ ist nicht im Backup');
$digest = static fn (PDO $p, string $table, string $order): string => T::tableDigest($p, '`' . $table . '`', $order);
$beforePages = $digest($pdo, 'pages', 'id');
$beforeUsers = $digest($pdo, 'users', 'id');
$stream = '';
$gz = gzopen($work . '/plain/db.ndjson.gz', 'rb');

while (!gzeof($gz)) {
    $stream .= gzread($gz, 65536);
}

gzclose($gz);
T::check(!str_contains($stream, '"t":"backup_') && !str_contains($stream, '"t":"login_attempts"') && str_contains($stream, '"t":"users"'), 'DB: backup_*-Tabellen und login_attempts sind nicht im Dump');

// --- Cron per URL ---------------------------------------------------------------------------------------------
$r = $post($adminJar, '/admin/backup/token', ['current_password' => 'falsch']);
T::check($pdo->query('SELECT cron_token_hash FROM backup_settings')->fetchColumn() === null, 'Cron-Token: ohne richtiges Passwort wird keines erzeugt');
$r = $post($adminJar, '/admin/backup/token', ['current_password' => $adminPassword]);
preg_match('#/backup-cron/([0-9a-f]{48})#', $r['body'], $m);
$token = $m[1] ?? '';
T::check($r['status'] === 200 && $token !== '' && str_contains($r['headers']['cache-control'] ?? '', 'no-store'), 'Cron-Token: wird einmal angezeigt (no-store)');
T::check($pdo->query('SELECT cron_token_hash FROM backup_settings')->fetchColumn() === hash('sha256', $token) && !str_contains(json_encode($pdo->query('SELECT * FROM backup_settings')->fetch()), $token), 'Cron-Token: in der Datenbank nur als SHA-256');
T::check(!str_contains($http($adminJar, 'GET', '/admin/backup')['body'], $token), 'Cron-Token: danach nirgends mehr sichtbar');
$r = $http($anonJar, 'GET', '/backup-cron/' . $token);
T::check($r['status'] === 200 && str_starts_with($r['body'], 'ok ') && !str_contains($r['body'], $project), 'Cron-URL: richtiges Token -> 200, nur Zustand in der Antwort');
T::check($http($anonJar, 'POST', '/backup-cron/' . $token)['status'] !== 200, 'Cron-URL: POST wird nicht akzeptiert');
$wrongStatus = [];

for ($i = 0; $i < 11; $i++) {
    $wrongStatus[] = $http($anonJar, 'GET', '/backup-cron/' . bin2hex(random_bytes(24)))['status'];
}

T::check(array_slice($wrongStatus, 0, 10) === array_fill(0, 10, 404) && $wrongStatus[10] === 429, 'Cron-URL: falsche Tokens 404, nach 10 Fehlversuchen 429');
T::check($http($anonJar, 'GET', '/backup-cron/' . $token)['status'] === 429, 'Cron-URL: waehrend der Sperre wird auch das richtige Token nicht geprueft (429)');
$pdo->exec('UPDATE backup_settings SET cron_fail_count = 0, cron_fail_since = NULL');
T::check($http($anonJar, 'GET', '/backup-cron/' . $token)['status'] === 200, 'Cron-URL: nach Ablauf der Sperre funktioniert das Token wieder');

// --- Step-up-Sperre -------------------------------------------------------------------------------------------
$pdo->exec('UPDATE backup_settings SET stepup_fail_count = 0, stepup_fail_since = NULL');

for ($i = 0; $i < 5; $i++) {
    $post($adminJar, '/admin/backup/key', ['role' => 'emergency', 'public_key' => $pair['text'], 'current_password' => 'falsch-' . $i]);
}

$post($adminJar, '/admin/backup/key', ['role' => 'emergency', 'public_key' => T::keyPair()['text'], 'current_password' => $adminPassword]);
T::check($pdo->query('SELECT emergency_key_fp FROM backup_settings')->fetchColumn() === null && str_contains($flash($adminJar), 'Zu viele Fehlversuche'), 'Step-up: nach 5 Fehlversuchen ist die Bestaetigung gesperrt (auch mit richtigem Passwort)');
$pdo->exec('UPDATE backup_settings SET stepup_fail_count = 0, stepup_fail_since = NULL');

// --- Restore (CLI) --------------------------------------------------------------------------------------------
$restore = [PHP_BINARY, $project . '/modules/backup/tools/restore.php'];
$pdo->exec("UPDATE pages SET title = 'VERAENDERT NACH BACKUP'");
$pdo->exec("DELETE FROM audit_log WHERE action_name = 'auth.login'");
file_put_contents($project . '/public/uploads/bild.txt', 'NACH BACKUP VERAENDERT');
unlink($project . '/public/uploads/2026/doc.txt');
file_put_contents($project . '/public/uploads/neu-nach-backup.txt', 'neu');
[$code, $out] = T::run([...$restore, $work . '/plain']);
preg_match('/--confirm=([0-9a-f]{8})/', $out, $m);
$confirm = $m[1] ?? '';
T::check($code === 0 && $confirm !== '' && $digest($pdo, 'pages', 'id') !== $beforePages, 'Restore: Trockenlauf zeigt den Bestaetigungscode und schreibt nichts');
[$code] = T::run([...$restore, $work . '/plain', '--confirm=00000000']);
T::check($code !== 0, 'Restore: falscher Bestaetigungscode bricht ab');
[$code, , $err] = T::run([...$restore, $work . '/plain', '--confirm=' . $confirm]);
T::check($code !== 0 && str_contains($err, 'Wartungsmodus') && $digest($pdo, 'pages', 'id') !== $beforePages, 'Restore: ohne Wartungsmodus wird nichts geschrieben');
[$code, $out] = T::run([...$restore, $work . '/plain', '--confirm=' . $confirm, '--no-maintenance-check']);
T::check($code === 0, 'Restore: mit Bestaetigung und ohne Wartungspruefung laeuft durch (' . trim($out) . ')');
T::check($digest($pdo, 'pages', 'id') === $beforePages && $digest($pdo, 'users', 'id') === $beforeUsers, 'Restore: Tabellen sind wieder Zeile fuer Zeile identisch mit dem Backup');
T::check((int) $pdo->query('SELECT COUNT(*) FROM backup_runs')->fetchColumn() >= 1 && (int) $pdo->query('SELECT COUNT(*) FROM backup_settings')->fetchColumn() === 1, 'Restore: backup_*-Tabellen bleiben unberuehrt');
T::check(file_get_contents($project . '/public/uploads/bild.txt') === 'Originalinhalt Bild' && file_get_contents($project . '/public/uploads/2026/doc.txt') === 'Originalinhalt Dokument', 'Restore: Dateien sind wiederhergestellt');
T::check(count(glob($project . '/public/uploads.pre-restore-*') ?: []) === 1 && is_file(glob($project . '/public/uploads.pre-restore-*')[0] . '/neu-nach-backup.txt'), 'Restore: der bisherige Dateiordner bleibt als .pre-restore-Ordner erhalten');
T::check((int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action_name = 'backup.restored'")->fetchColumn() === 1, 'Restore: Audit-Eintrag backup.restored');

// Restore mit manipuliertem Klartext (Datei fehlt / Tabelle unbekannt) bricht vor dem Schreiben ab.
mkdir($work . '/evil', 0700);
$evil = gzopen($work . '/evil/db.ndjson.gz', 'wb');
gzwrite($evil, '{"t":"gibt_es_nicht","c":["id"]}' . "\n" . '{"r":["1"]}' . "\n" . '{"e":"gibt_es_nicht","n":1}' . "\n");
gzclose($evil);
[$code] = T::run([...$restore, $work . '/evil']);
T::check($code !== 0 && $digest($pdo, 'pages', 'id') === $beforePages, 'Restore: unbekannte Tabelle im Dump wird schon im Trockenlauf abgelehnt');
$evil = gzopen($work . '/evil/db.ndjson.gz', 'wb');
gzwrite($evil, '{"t":"backup_runs","c":["id"]}' . "\n" . '{"e":"backup_runs","n":0}' . "\n");
gzclose($evil);
[$code] = T::run([...$restore, $work . '/evil']);
T::check($code !== 0, 'Restore: ein Dump mit backup_*-Tabelle wird abgelehnt');

// --- Loeschen --------------------------------------------------------------------------------------------------
$post($adminJar, '/admin/backup/run/delete', ['run_id' => (string) $run['id'], 'scope' => 'local', 'current_password' => 'falsch']);
T::check(is_dir($folder), 'Loeschen: ohne richtiges Passwort bleibt die Sicherung');
$post($adminJar, '/admin/backup/run/delete', ['run_id' => (string) $run['id'], 'scope' => 'local', 'current_password' => $adminPassword]);
T::run($cron);
T::check(!is_dir($folder), 'Loeschen: lokale Dateien sind nach dem naechsten Cron-Aufruf entfernt');
T::check($http($adminJar, 'GET', '/admin/backup/download/' . $run['id'] . '/' . $dbPart['seq'])['status'] === 404, 'Download: geloeschte Sicherung ist nicht mehr abrufbar (404)');

// --- Alles zusammen: keine Geheimnisse in Antworten, Datenbank, Server-Log -------------------------------------
$logs = (string) file_get_contents($work . '/server.log') . (string) file_get_contents($work . '/server.err');
$dump = json_encode([$pdo->query('SELECT * FROM backup_settings')->fetchAll(), $pdo->query('SELECT * FROM backup_destinations')->fetchAll(), $pdo->query('SELECT * FROM backup_runs')->fetchAll(), $pdo->query('SELECT * FROM audit_log')->fetchAll()]);
T::check(!str_contains($logs . $dump, $ftpPassword) && !str_contains($logs . $dump, $adminPassword) && !str_contains($logs . $dump, $token), 'Geheimnisse: Passwoerter und Token stehen weder in Server-Log, Datenbank noch Audit-Log');
T::check(!str_contains($logs, 'Fatal error') && !str_contains($logs, 'Warning:') && !str_contains($logs, 'Deprecated'), 'Server-Log: keine PHP-Fehler oder Warnungen');

proc_terminate($server);
T::cleanup();
echo "\n" . T::$passed . " Pruefungen bestanden\n";
