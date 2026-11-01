<?php

declare(strict_types=1);

use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Destinations\Ftp\CurlFtpClient;
use MGD\Backup\Destinations\FtpDriver;
use MGD\Backup\Destinations\HostPolicy;
use MGD\Backup\Format\SecretKeyFile;
use MGD\Backup\Paths;

$tool = dirname(__DIR__, 2) . '/tools/mgd-backup.php';
$php = PHP_BINARY;

// --- CurlFtpClient: Optionen ohne Verbindung pruefen ---------------------------------------------------------------
HostPolicy::$resolver = static fn (string $host): array => match ($host) {
    'ftp.example.org' => ['93.184.216.34'],
    'intern.example.org' => ['10.0.0.5'],
    'rebind.example.org' => ['93.184.216.34', '127.0.0.1'],
    'v6.example.org' => ['2606:2800:220:1:248:1893:25c8:1946'],
    default => [],
};
$client = new CurlFtpClient('ftp.example.org', 21, 'explicit', 'testnutzer', 'erfundenes-passwort');
$options = $client->options('/backups/x', false);
T::check($options[CURLOPT_SSL_VERIFYPEER] === true && $options[CURLOPT_SSL_VERIFYHOST] === 2, 'FTPS: Zertifikat und Hostname werden geprueft');
T::check($options[CURLOPT_USE_SSL] === CURLUSESSL_ALL && $options[CURLOPT_FTPSSLAUTH] === CURLFTPAUTH_TLS, 'FTPS explizit: TLS ist Pflicht (AUTH TLS), kein Rueckfall auf Klartext');
T::check($options[CURLOPT_SSLVERSION] === CURL_SSLVERSION_TLSv1_2, 'FTPS: TLS mindestens 1.2');
T::check($options[CURLOPT_FTP_SKIP_PASV_IP] === true, 'FTPS: PASV-Adresse des Servers wird ignoriert (Bounce/SSRF)');
T::check($options[CURLOPT_PROTOCOLS] === (CURLPROTO_FTP | CURLPROTO_FTPS) && $options[CURLOPT_FOLLOWLOCATION] === false, 'FTPS: nur FTP/FTPS, keine Weiterleitungen');
T::check($options[CURLOPT_RESOLVE] === ['ftp.example.org:21:93.184.216.34'], 'FTPS: die geprueften Adressen werden per CURLOPT_RESOLVE festgeschrieben');
T::check(!str_contains((string) $options[CURLOPT_URL], 'erfundenes-passwort') && !str_contains((string) $options[CURLOPT_URL], 'testnutzer') && $options[CURLOPT_USERPWD] === 'testnutzer:erfundenes-passwort', 'FTPS: Zugangsdaten stehen nicht in der URL');
T::check(str_starts_with((string) $options[CURLOPT_URL], 'ftp://ftp.example.org:21//backups/x'), 'FTPS: absoluter Pfad in der URL');

$implicit = (new CurlFtpClient('ftp.example.org', 990, 'implicit', 'u', 'p'))->options();
T::check(str_starts_with((string) $implicit[CURLOPT_URL], 'ftps://') && !isset($implicit[CURLOPT_FTPSSLAUTH]), 'FTPS implizit: ftps:// auf Port 990');
T::throws(static fn () => new CurlFtpClient('ftp.example.org', 21, 'plain', 'u', 'p'), BackupException::class, 'FTP im Klartext ist ausgeschlossen (Modus plain)', 'destination_unsupported');
T::throws(static fn () => (new CurlFtpClient('ftp.example.org', 2121, 'explicit', 'u', 'p'))->options(), BackupException::class, 'Port ausserhalb der Allowlist wird abgelehnt', 'port_not_allowed');
T::throws(static fn () => (new CurlFtpClient('intern.example.org', 21, 'explicit', 'u', 'p'))->options(), BackupException::class, 'Host mit privater Adresse wird blockiert (SSRF)', 'host_blocked');
T::throws(static fn () => (new CurlFtpClient('rebind.example.org', 21, 'explicit', 'u', 'p'))->options(), BackupException::class, 'Host mit gemischten Adressen (Rebinding) wird blockiert', 'host_blocked');
T::throws(static fn () => (new CurlFtpClient('127.0.0.1', 21, 'explicit', 'u', 'p'))->options(), BackupException::class, 'Loopback-IP wird blockiert', 'host_blocked');
T::throws(static fn () => (new CurlFtpClient('169.254.169.254', 21, 'explicit', 'u', 'p'))->options(), BackupException::class, 'Link-Local-/Metadaten-Adresse wird blockiert', 'host_blocked');
T::throws(static fn () => (new CurlFtpClient('localhost', 21, 'explicit', 'u', 'p'))->options(), BackupException::class, 'localhost wird blockiert', 'host_blocked');
T::throws(static fn () => (new CurlFtpClient('unbekannt.example.org', 21, 'explicit', 'u', 'p'))->options(), BackupException::class, 'Nicht aufloesbarer Host wird abgelehnt', 'host_blocked');
T::throws(static fn () => new CurlFtpClient('ftp.example.org', 21, 'explicit', 'a:b', 'p'), BackupException::class, 'Benutzername mit Doppelpunkt wird abgelehnt', 'auth_failed');
T::throws(static fn () => $client->options('/a/../b'), BackupException::class, 'Pfad mit .. wird abgelehnt', 'path_invalid');
T::throws(static fn () => $client->options("/a\"b"), BackupException::class, 'Pfad mit Anfuehrungszeichen wird abgelehnt (QUOTE-Injektion)', 'path_invalid');
T::check((new CurlFtpClient('intern.example.org', 21, 'explicit', 'u', 'p', new HostPolicy(true)))->options()[CURLOPT_RESOLVE] === ['intern.example.org:21:10.0.0.5'], 'allow_private_hosts (nur per config.php) erlaubt ein internes Ziel');
T::check(HostPolicy::isValidHost('ftp.example.org') && !HostPolicy::isValidHost('ftp.example.org/pfad') && !HostPolicy::isValidHost('user@host.example.org') && !HostPolicy::isValidHost('host:21'), 'Host-Syntax: nur Namen oder IPs ohne Pfad, Zugangsdaten und Port');

// --- FtpDriver gegen den Fake-Server --------------------------------------------------------------------------------
$fake = new FakeFtpClient();
$driver = new FtpDriver('/backups/site', $fake, 100000);
$driver->test();
T::check($fake->files === [] && isset($fake->dirs['/backups/site']), 'FtpDriver: Verbindungstest legt Basisordner an und raeumt die Testdatei weg');

$dir = T::tempDir();
$folder = 'mgdbk_20261027T120000Z_db_aabbccdd';
$name = $folder . '_001_db.mgdb';
file_put_contents($dir . '/' . $name, random_bytes(350000));
$ref = $driver->beginRun($folder);
T::check($ref === '/backups/site/' . $folder, 'FtpDriver: beginRun liefert die Ordnerreferenz unter base_path');

$fake->chunks = 0;
$fake->failAfterChunks = 2; // der dritte Abschnitt scheitert einmal
$progress = $driver->upload(['resume_state' => null, 'remote_ref' => null], $dir . '/' . $name, new Budget(30));
T::check($progress->paused && $progress->bytesDone === 200000 && !$progress->done, 'FtpDriver: Fehler nach Fortschritt pausiert den Upload (Offset bleibt erhalten)');
$progress = $driver->upload(['resume_state' => $progress->resumeState, 'remote_ref' => null], $dir . '/' . $name, new Budget(30));
T::check($progress->done && $progress->remoteRef === $ref . '/' . $name, 'FtpDriver: Fortsetzen ab Offset schliesst den Upload ab');
T::check(($fake->files[$progress->remoteRef] ?? '') === (string) file_get_contents($dir . '/' . $name), 'FtpDriver: Inhalt am Ziel ist byteweise gleich');
T::check(!in_array('put ' . $progress->remoteRef . '@0', array_slice($fake->log, array_search('put ' . $progress->remoteRef . '@200000', $fake->log, true)), true), 'FtpDriver: nach dem Fortsetzen wird nicht von vorn begonnen');
$driver->verify($progress->remoteRef, 350000);
T::throws(static fn () => $driver->verify($progress->remoteRef, 350001), BackupException::class, 'FtpDriver: falsche Groesse am Ziel faellt bei verify() auf', 'verify_failed');

$budgetEmpty = new Budget(0.0);
$fake->files = [];
$p = $driver->upload(['resume_state' => null, 'remote_ref' => null], $dir . '/' . $name, $budgetEmpty);
T::check($p->paused && $p->bytesDone === 100000, 'FtpDriver: bei leerem Budget wird mindestens ein Abschnitt gesendet und dann pausiert');

// Loeschen nur ueber gueltige, gespeicherte Referenzen.
$fake->files = ['/backups/site/' . $folder . '/' . $name => 'x', '/backups/site/fremd.txt' => 'bleibt', '/backups/andere/' . $folder . '/' . $name => 'bleibt auch'];
foreach (['/backups/site/fremd.txt', '/backups/andere/' . $folder . '/' . $name, '/backups/site/../fremd.txt', '/etc/passwd', '/backups/site/' . $folder . '/../x', $folder . '/' . $name, '/backups/site/mgdbk_zzz/' . $name] as $bad) {
    T::throws(static fn () => $driver->delete($bad), BackupException::class, 'FtpDriver: Loeschen verweigert fuer ' . $bad, 'path_invalid');
}

$driver->delete('/backups/site/' . $folder . '/' . $name);
T::check(!isset($fake->files['/backups/site/' . $folder . '/' . $name]) && isset($fake->files['/backups/site/fremd.txt']) && isset($fake->files['/backups/andere/' . $folder . '/' . $name]), 'FtpDriver: nur die referenzierte Datei wird geloescht, Fremdes bleibt');
T::throws(static fn () => $driver->deleteFolder('/backups/site'), BackupException::class, 'FtpDriver: base_path selbst ist kein loeschbarer Ordner', 'path_invalid');
T::throws(static fn () => $driver->deleteFolder('/backups/site/mein-ordner'), BackupException::class, 'FtpDriver: Ordner ohne mgdbk_-Namen wird nicht geloescht', 'path_invalid');
$driver->deleteFolder('/backups/site/' . $folder);
T::throws(static fn () => new FtpDriver('relativ/pfad', $fake), BackupException::class, 'FtpDriver: relativer base_path wird abgelehnt', 'path_invalid');
T::throws(static fn () => new FtpDriver('/a/../b', $fake), BackupException::class, 'FtpDriver: base_path mit .. wird abgelehnt', 'path_invalid');

// --- Kompletter Lauf auf ein FTPS-Ziel (Fake) -----------------------------------------------------------------------
$password = 'erfundenes-test-passwort-4711';
$w = new World(rows: 300, partBytes: 4096);
$w->enableFtp($password, 1);
$runId = $w->runner->enqueue('full', 'manual', 1);
$run = $w->runToEnd($runId);
T::check($run['status'] === 'completed', 'FTP-Lauf: endet als completed');
T::check(!is_dir($w->runDir($run)) && $run['local_deleted_at'] !== null, 'FTP-Lauf: bei reinem Remote-Ziel bleibt lokal nichts liegen');
$remote = array_filter($w->ftp->files, static fn (string $path): bool => str_starts_with($path, '/backups/' . $run['folder_name'] . '/'), ARRAY_FILTER_USE_KEY);
T::check(count($remote) === (int) $run['parts'], 'FTP-Lauf: jeder Teil (inkl. Manifest) liegt am Ziel');

$manifestName = null;

foreach ($w->store->parts($runId) as $part) {
    $path = '/backups/' . $run['folder_name'] . '/' . $part['file_name'];

    if (($w->ftp->files[$path] ?? null) === null || strlen($w->ftp->files[$path]) !== (int) $part['cipher_bytes'] || hash('sha256', $w->ftp->files[$path]) !== $part['cipher_sha256']) {
        T::check(false, 'FTP-Lauf: Teil ' . $part['file_name'] . ' am Ziel stimmt mit Groesse und SHA-256 ueberein');
    }
}


// Remote-Dateien in einen Ordner schreiben und mit dem CLI-Werkzeug pruefen/entschluesseln.
$restoreDir = $w->dir . '/from-remote';
mkdir($restoreDir, 0700);

foreach ($remote as $path => $content) {
    file_put_contents($restoreDir . '/' . basename($path), $content);
}

mkdir($w->dir . '/keys', 0700);
SecretKeyFile::write($w->dir . '/keys/p.key', $w->recipient['secret'], null);
[$code, $stdout] = T::run([$php, $tool, 'decrypt', $restoreDir, '--key-file=' . $w->dir . '/keys/p.key', '--out=' . $w->dir . '/out', '--signer=' . $w->store->settings()['signing_fp']]);
T::check($code === 0 && file_get_contents($w->dir . '/out/uploads/a.txt') === 'Datei A mit Inhalt', 'FTP-Lauf: die Dateien vom Ziel lassen sich mit dem CLI-Werkzeug entschluesseln');

// Geheimnisse: Passwort nie im Klartext in der Datenbank, im Lauf, im Audit oder in den Teilen.
$dump = '';

foreach (['backup_destinations', 'backup_runs', 'backup_run_destinations', 'backup_uploads', 'backup_settings', 'backup_parts'] as $table) {
    $dump .= json_encode($w->pdo->query('SELECT * FROM ' . $table)->fetchAll()) . "\n";
}

$dump .= json_encode($w->audit);
T::check(!str_contains($dump, $password), 'Geheimnisse: das FTP-Passwort steht nirgends im Klartext (DB, Lauf, Audit)');
T::check(str_starts_with((string) $w->store->destinations()[0]['secret_enc'], 'v1:'), 'Geheimnisse: das Zielpasswort ist verschluesselt gespeichert');
$signingRaw = $w->secrets->decrypt((string) $w->store->settings()['signing_secret_enc']);
T::check(!str_contains($dump, base64_encode($signingRaw)) && !str_contains($dump, bin2hex($signingRaw)), 'Geheimnisse: der Signaturschluessel steht nicht im Klartext in der Datenbank');

// Aufbewahrung am FTP-Ziel: keep_count=1 -> ein zweiter Lauf loescht den ersten nur ueber gespeicherte Referenzen.
$w->ftp->files['/backups/fremd.txt'] = 'fremde Datei';
$w->now = $w->now->modify('+1 day');
$second = $w->runToEnd($w->runner->enqueue('full', 'manual', 1));
$w->runner->tick(new Budget(10));
$left = array_keys($w->ftp->files);
T::check(count(array_filter($left, static fn (string $p): bool => str_contains($p, $run['folder_name']))) === 0, 'Aufbewahrung (FTP): der aelteste Lauf ist am Ziel geloescht');
T::check(count(array_filter($left, static fn (string $p): bool => str_contains($p, $second['folder_name']))) === (int) $second['parts'], 'Aufbewahrung (FTP): der neueste Lauf bleibt vollstaendig');
T::check(isset($w->ftp->files['/backups/fremd.txt']), 'Aufbewahrung (FTP): fremde Dateien im Zielordner werden nie angefasst');
T::check(!isset($w->ftp->dirs['/backups/' . $run['folder_name']]), 'Aufbewahrung (FTP): der leere Lauf-Ordner wird entfernt');
T::check($w->store->run($runId) === null, 'Aufbewahrung (FTP): der geloeschte Lauf verschwindet aus dem Verlauf');

// --- Fehlerfall: Ziel nicht erreichbar -------------------------------------------------------------------------------
$f = new World(rows: 20);
$f->enableLocal();
$f->enableFtp('anderes-erfundenes-passwort', 3);
$f->ftp->failUploads = 100000;
$id = $f->runner->enqueue('db', 'manual', 1);

for ($i = 0; $i < 20; $i++) {
    $f->runner->tick(new Budget(10));
    $r = $f->store->run($id);

    if (!in_array($r['status'], ['queued', 'running'], true)) {
        break;
    }

    $f->now = $f->now->modify('+3 hours'); // Wartezeit zwischen den Versuchen ablaufen lassen
}

$r = $f->store->run($id);
T::check($r['status'] === 'partial', 'Fehlerfall: lokales Ziel ok, FTP-Ziel dauerhaft defekt -> partial');
$rds = $f->store->runDestinations($id);
$ftpRd = array_values(array_filter($rds, static fn (array $x): bool => $x['status'] === 'failed'))[0] ?? null;
T::check($ftpRd !== null && $ftpRd['error_code'] === 'upload_failed', 'Fehlerfall: nur der Katalogcode upload_failed wird gespeichert');
T::check(!str_contains(json_encode($f->pdo->query('SELECT * FROM backup_run_destinations')->fetchAll()) . json_encode($f->pdo->query('SELECT * FROM backup_uploads')->fetchAll()), 'ftp.example.org'), 'Fehlerfall: kein Hostname in Lauf- und Upload-Zeilen');

$g = new World(rows: 10);
$g->enableFtp('x-erfundenes-passwort', 3);
$g->ftp->failConnect = true;
$g->ftp->failUploads = 100000;
$id = $g->runner->enqueue('db', 'manual', 1);

for ($i = 0; $i < 20; $i++) {
    $g->runner->tick(new Budget(10));

    if (!in_array($g->store->run($id)['status'], ['queued', 'running'], true)) {
        break;
    }

    $g->now = $g->now->modify('+3 hours');
}

T::check($g->store->run($id)['status'] === 'failed' && is_string($g->store->run($id)['error_code']), 'Fehlerfall: nur ein defektes Ziel -> Lauf failed mit Fehlercode');

// Zugangsdaten verschwinden aus dem Treiber, wenn der app.key nicht mehr passt.
$h = new World(rows: 3);
$h->enableFtp('pw-erfunden', 3);
$other = new MGD\Backup\Secrets('ein-ganz-anderer-app-key-0123456789');
$factory = new MGD\Backup\Destinations\DriverFactory($h->paths, $other);
T::throws(static fn () => $factory->make($h->store->destinations()[0]), BackupException::class, 'Zugangsdaten sind ohne den passenden app.key nicht lesbar', 'signing_key_unreadable');

// Nachruestbare Treiber (Marketplace): nur ueber register(), nicht fuer eingebaute Typen.
$custom = new MGD\Backup\Destinations\DriverFactory($h->paths, $h->secrets);
$custom->register('s3x', static fn (array $d): MGD\Backup\Destinations\DestinationDriver => new MGD\Backup\Destinations\LocalDriver($h->paths));
T::check(in_array('s3x', $custom->types(), true), 'Treiber-Interface: weitere Ziel-Typen lassen sich nachruesten');
T::throws(static fn () => $custom->register('ftp', static fn (array $d) => new MGD\Backup\Destinations\LocalDriver($h->paths)), BackupException::class, 'Treiber-Interface: eingebaute Typen lassen sich nicht ueberschreiben');
T::throws(static fn () => $custom->make(['dest_type' => 'dropbox']), BackupException::class, 'Unbekannte Ziel-Typen sind nicht verfuegbar (keine Dienste im Builder)', 'destination_unsupported');
