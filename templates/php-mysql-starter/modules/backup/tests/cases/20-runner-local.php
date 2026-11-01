<?php

declare(strict_types=1);

use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Db\Importer;
use MGD\Backup\Db\NdjsonFile;
use MGD\Backup\Format\SecretKeyFile;

$tool = dirname(__DIR__, 2) . '/tools/mgd-backup.php';
$php = PHP_BINARY;

/**
 * Entschluesselt einen Backup-Ordner mit dem CLI-Werkzeug des Moduls.
 *
 * @return array{0:int,1:string,2:string,3:string} Exit-Code, stdout, stderr, Ausgabeordner
 */
$decrypt = static function (World $w, string $folder, string $tool, string $php): array {
    $keyFile = $w->dir . '/keys/primary.key';

    if (!is_file($keyFile)) {
        mkdir(dirname($keyFile), 0700, true);
        SecretKeyFile::write($keyFile, $w->recipient['secret'], null);
    }

    $out = $w->dir . '/out-' . bin2hex(random_bytes(4));
    [$code, $stdout, $stderr] = T::run([$php, $tool, 'decrypt', $folder, '--key-file=' . $keyFile, '--out=' . $out, '--signer=' . (string) $w->store->settings()['signing_fp']]);

    return [$code, $stdout, $stderr, $out];
};

// --- Voller Lauf, lokal, kleine Teile (mehrere DB- und Datei-Teile) ------------------------------------------------
$w = new World(rows: 1000, partBytes: 4096);
$w->enableLocal();
$w->store->updateSettings(['exclude_patterns' => "*.tmp\n"]);
$runId = $w->runner->enqueue('full', 'manual', 1);
$run = $w->runToEnd($runId);
T::check($run['status'] === 'completed' && $run['phase'] === 'done' && $run['active_slot'] === null, 'Lauf: full lokal endet als completed und gibt den Slot frei');
$parts = $w->store->parts($runId);
T::check(count(array_filter($parts, static fn (array $p): bool => $p['part_kind'] === 'db')) >= 2, 'Lauf: DB wird auf mehrere Teile verteilt (Teilgroesse greift)');
T::check(end($parts)['part_kind'] === 'manifest' && (int) $run['parts'] === count($parts), 'Lauf: Manifest ist der letzte Teil, Zaehler stimmen');
T::check($run['db_consistency'] === 'per_tick' && str_contains((string) $run['warnings_json'], 'db_not_atomic') === true, 'Lauf: ohne Lesesicht (SQLite) wird per_tick mit Warnung gemeldet');

$folder = $w->runDir($run);
$allPlain = true;

foreach (glob($folder . '/*.mgdb') ?: [] as $partFile) {
    $content = (string) file_get_contents($partFile);

    foreach (['Datei A mit Inhalt', 'Lorem ipsum', 'seite-1', 'schema_migrations', 'Titel', 'TOP-SECRET-CONFIG', 'testnutzer'] as $needle) {
        if (str_contains($content, $needle)) {
            $allPlain = false;
        }
    }

    T::check((fileperms($partFile) & 0777) === 0600, 'Lauf: Teildatei ' . basename($partFile) . ' hat Rechte 0600');
}

T::check($allPlain, 'Lauf: kein Klartext (Inhalte, Tabellennamen, Konfiguration) in den Teildateien');
T::check(!file_exists($folder . '/files.idx'), 'Lauf: der Dateiindex wird nach dem Lauf entfernt');

// Entschluesseln mit dem mitgelieferten CLI-Werkzeug und Inhalte vergleichen.
[$code, $stdout, $stderr, $out] = $decrypt($w, $folder, $tool, $php);
T::check($code === 0 && str_starts_with($stdout, 'OK'), 'Tool: decrypt liefert OK (Signer bestaetigt)');
T::check(is_file($out . '/db.ndjson.gz'), 'Tool: db.ndjson.gz wurde geschrieben');
T::check(file_get_contents($out . '/uploads/a.txt') === 'Datei A mit Inhalt', 'Tool: Datei a.txt kommt unveraendert zurueck');
T::check(hash_file('sha256', $out . '/uploads/sub/b.bin') === hash_file('sha256', $w->project . '/public/uploads/sub/b.bin'), 'Tool: 300-KB-Datei ueber mehrere Teile identisch');
T::check(file_exists($out . '/uploads/sub/leer.txt') && filesize($out . '/uploads/sub/leer.txt') === 0, 'Tool: leere Datei wird erhalten');
T::check(!file_exists($out . '/uploads/skip.tmp'), 'Dateien: Ausschlussmuster *.tmp greift');
T::check(!is_dir($out . '/config') && !file_exists($out . '/config.php'), 'Dateien: config/ ist in keinem Bereich');

// DB zurueckspielen in eine frische Datenbank mit gleichem Schema und Zeilen vergleichen.
$target = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$target->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, slug VARCHAR(80) NOT NULL, title TEXT, body TEXT, blob_data BLOB, price REAL)');
$target->exec('CREATE TABLE page_tags (page_id INTEGER NOT NULL, tag VARCHAR(40) NOT NULL, PRIMARY KEY (page_id, tag))');
$target->exec('CREATE TABLE schema_migrations (version VARCHAR(190) NOT NULL PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL)');
$target->exec('CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, email_hash CHAR(64))');
$target->exec("INSERT INTO pages (slug) VALUES ('wird-ersetzt')");
$target->exec("INSERT INTO login_attempts (email_hash) VALUES ('bleibt')");
$totals = (new Importer($target, ['login_attempts']))->import(NdjsonFile::events($out . '/db.ndjson.gz'));
T::check($totals['pages'] === 1000 && $totals['page_tags'] === 2000 && $totals['schema_migrations'] === 2, 'Round-Trip: Zeilenzahlen aus dem Backup stimmen');
T::check(T::tableDigest($w->pdo, 'pages', 'id') === T::tableDigest($target, 'pages', 'id'), 'Round-Trip: Tabelle pages ist Zeile fuer Zeile identisch (UTF-8, Binaer, NULL, Float)');
T::check(T::tableDigest($w->pdo, 'page_tags', 'page_id, tag') === T::tableDigest($target, 'page_tags', 'page_id, tag'), 'Round-Trip: Tabelle mit zusammengesetztem Schluessel identisch');
T::check((int) $target->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() === 1, 'Round-Trip: ausgeschlossene Tabelle wird weder gesichert noch ueberschrieben');
$gz = gzopen($out . "/db.ndjson.gz", "rb");
$plainDb = "";
while (!gzeof($gz)) {
    $plainDb .= gzread($gz, 65536);
}
gzclose($gz);
T::check(!str_contains($plainDb, "login_attempts") && !str_contains($plainDb, "should-not-be-backed-up") && str_contains($plainDb, "\"t\":\"pages\""), "Round-Trip: login_attempts steht nicht im Strom, pages schon");

// Manipulation: ein Byte in einem DB-Teil kippen.
$tamper = $w->dir . '/tampered';
mkdir($tamper, 0700);

foreach (glob($folder . '/*.mgdb') ?: [] as $partFile) {
    copy($partFile, $tamper . '/' . basename($partFile));
}

$dbPart = glob($tamper . '/*_001_db.mgdb')[0];
$bytes = (string) file_get_contents($dbPart);
$bytes[(int) (strlen($bytes) / 2)] = chr(ord($bytes[(int) (strlen($bytes) / 2)]) ^ 0x10);
file_put_contents($dbPart, $bytes);
$keyFile = $w->dir . '/keys/primary.key';
[$code, , $stderr] = T::run([$php, $tool, 'verify', $tamper, '--key-file=' . $keyFile, '--signer=' . $w->store->settings()['signing_fp']]);
T::check($code !== 0 && str_starts_with($stderr, 'FEHLER:'), 'Manipulation: gekipptes Byte im Teil wird vom Tool erkannt (Exit != 0)');
[$code] = T::run([$php, $tool, 'verify', $folder, '--key-file=' . $keyFile, '--signer=' . $w->store->settings()['signing_fp']]);
T::check($code === 0, 'Tool: verify des unveraenderten Ordners ist OK');
[$code, , $stderr] = T::run([$php, $tool, 'verify', $folder, '--key-file=' . $keyFile, '--signer=' . str_repeat('0', 16)]);
T::check($code !== 0, 'Tool: falscher erwarteter Signer wird abgelehnt');
[$code, , $stderr] = T::run([$php, $tool, 'verify', $folder, '--key-file=' . $keyFile]);
T::check($code !== 0 && str_contains($stderr, 'Signer'), 'Tool: ohne Signer-Angabe wird verify verweigert');

// Fehlender Teil.
unlink($tamper . '/' . basename($dbPart));
copy($folder . '/' . basename($dbPart), $dbPart);
unlink(glob($tamper . '/*_002_db.mgdb')[0]);
[$code] = T::run([$php, $tool, 'verify', $tamper, '--key-file=' . $keyFile, '--signer=' . $w->store->settings()['signing_fp']]);
T::check($code !== 0, 'Manipulation: fehlender Teil wird erkannt');

// Falscher Schluessel.
$wrong = T::keyPair();
mkdir($w->dir . '/wrongkeys', 0700);
SecretKeyFile::write($w->dir . '/wrongkeys/x.key', $wrong['secret'], null);
[$code] = T::run([$php, $tool, 'verify', $folder, '--key-file=' . $w->dir . '/wrongkeys/x.key', '--signer=' . $w->store->settings()['signing_fp']]);
T::check($code !== 0, 'Tool: falscher privater Schluessel entschluesselt nichts');

// --- DB-Lauf, Aufbewahrung und Abbruch -----------------------------------------------------------------------------
$w2 = new World(rows: 30);
$w2->enableLocal(2);
$ids = [];

for ($i = 0; $i < 4; $i++) {
    $w2->now = $w2->now->modify('+1 day');
    $id = $w2->runner->enqueue('db', 'schedule', null);
    $w2->runToEnd($id);
    $ids[] = $id;
}

$w2->runner->tick(new Budget(10)); // Loeschungen verarbeiten
$left = array_map(static fn (array $r): int => (int) $r['id'], $w2->store->runs());
T::check($left === [4, 3], 'Aufbewahrung: bei keep_count=2 bleiben nur die zwei neuesten Laeufe');
T::check(count(glob($w2->paths->root() . '/mgdbk_*') ?: []) === 2, 'Aufbewahrung: aelter Sicherungen sind auch lokal von der Platte geloescht');
$latest = $w2->store->run(4);
T::check($latest['kind'] === 'db' && $latest['status'] === 'completed', 'DB-Lauf: Art db endet ohne Dateiphase als completed');

// Hoechstalter: aelter als max_age_days faellt raus, die juengste je Art bleibt.
$w3 = new World(rows: 5);
$w3->enableLocal(10);
$w3->store->updateSettings(['max_age_days' => 7]);

foreach ([-40, -30, -2] as $offset) {
    $w3->now = new DateTimeImmutable('2026-10-27 12:00:00', new DateTimeZone('UTC'));
    $w3->now = $w3->now->modify($offset . ' days');
    $w3->runToEnd($w3->runner->enqueue('db', 'manual', 1));
}

$w3->now = new DateTimeImmutable('2026-10-27 12:00:00', new DateTimeZone('UTC'));
(new MGD\Backup\Retention($w3->store))->apply();
$w3->runner->tick(new Budget(10));
T::check(array_map(static fn (array $r): int => (int) $r['id'], $w3->store->runs()) === [3], 'Aufbewahrung: Hoechstalter entfernt alte Laeufe, der juengste bleibt');

$w4 = new World(rows: 5);
$w4->enableLocal(1);
$w4->runToEnd($w4->runner->enqueue('db', 'manual', 1));
$w4->now = $w4->now->modify('+400 days');
(new MGD\Backup\Retention($w4->store))->apply();
T::check((int) $w4->pdo->query("SELECT COUNT(*) FROM backup_run_destinations WHERE status = 'deleting'")->fetchColumn() === 0, 'Aufbewahrung: die letzte erfolgreiche Sicherung wird auch ueber das Hoechstalter hinaus nie geloescht');

// Abbruch.
$w5 = new World(rows: 3000, partBytes: 4096);
$w5->enableLocal();
$id = $w5->runner->enqueue('full', 'manual', 1);
$w5->runner->tick($w5->steps(4)); // nur wenige Schritte: der Lauf bleibt mitten in der DB-Phase stehen
T::check($w5->store->run($id)["status"] === "running" && $w5->store->run($id)["phase"] === "dumping_db" && count($w5->store->parts($id)) >= 1, "Abbruch: Vorbedingung, der Lauf steht mitten in der DB-Phase mit fertigen Teilen");
$w5->runner->requestCancel($id, 1);
$w5->runner->tick(new Budget(10));
$cancelled = $w5->store->run($id);
T::check($cancelled['status'] === 'cancelled' && $cancelled['active_slot'] === null && !is_dir($w5->runDir($cancelled)), 'Abbruch: Lauf wird abgebrochen und lokale Dateien werden entfernt');

// Fortsetzen nach Abbruch mitten im Teil: Teildatei ohne Zeile wird verworfen.
$w6 = new World(rows: 400, partBytes: 4096);
$w6->enableLocal();
$id = $w6->runner->enqueue('db', 'manual', 1);

for ($i = 0; $i < 3; $i++) {
    $w6->runner->tick($w6->steps(4));
}

$run6 = $w6->store->run($id);
T::check(in_array($run6["phase"], ["dumping_db"], true) && $run6["status"] === "running", "Fortsetzen: Vorbedingung, der Lauf wurde mitten in der DB-Phase unterbrochen");
$next = (int) json_decode((string) $run6['cursor_json'], true)['next_seq'];
file_put_contents($w6->runDir($run6) . sprintf('/%s_%03d_db.mgdb', $run6['folder_name'], $next), 'halbfertiger Teil');
$finished = $w6->runToEnd($id);
T::check($finished['status'] === 'completed', 'Fortsetzen: eine liegengebliebene halbe Teildatei wird verworfen und der Lauf endet sauber');
[$code] = T::run([$php, $tool, 'verify', $w6->runDir($finished), '--key-file=' . $w6->dir . '/none.key', '--signer=x']);
T::check($code !== 0, 'Tool: fehlende Schluesseldatei ist ein Fehler');

// Parallelsperre und Fehlerfaelle.
$w7 = new World(rows: 3);
T::throws(static fn () => $w7->runner->enqueue('db', 'manual', 1), BackupException::class, 'Start ohne aktives Ziel wird abgelehnt', 'no_destination_enabled');
$w7->enableLocal();
$w7->store->updateSettings(['primary_key_text' => null, 'primary_key_fp' => null]);
T::throws(static fn () => $w7->runner->enqueue('db', 'manual', 1), BackupException::class, 'Start ohne Schluessel wird abgelehnt', 'key_missing');
$w7->keys->setRecipient('primary', $w7->recipient['text']);
$id = $w7->runner->enqueue('db', 'manual', 1);
T::throws(static fn () => $w7->runner->enqueue('full', 'manual', 1), BackupException::class, 'Zweiter Lauf parallel wird abgelehnt (active_slot)', 'backup_already_active');
$lock = fopen($w7->paths->ensureRoot() . '/.runner.lock', 'c');
flock($lock, LOCK_EX);
T::check($w7->runner->tick(new Budget(5))['state'] === 'busy', 'Sperre: ein zweiter tick() bei gehaltener Sperre meldet busy und arbeitet nicht');
flock($lock, LOCK_UN);
fclose($lock);
T::check($w7->runner->tick(new Budget(5))['state'] === 'worked', 'Sperre: nach Freigabe arbeitet der tick() weiter');

// Zeitplan: faellig -> Lauf wird angelegt, naechster Termin gesetzt.
$w8 = new World(rows: 3);
$w8->enableLocal();
$w8->store->updateSettings(['schedule_interval' => 'daily', 'schedule_kind' => 'db', 'schedule_time' => '03:00']);
$w8->runner->tick(new Budget(0.0));
$next = (string) $w8->store->settings()['next_run_at'];
T::check($next === '2026-10-28 03:00:00' && $w8->store->activeRun() === null, 'Zeitplan: erster tick setzt nur den naechsten Termin');
$w8->now = new DateTimeImmutable('2026-10-28 03:05:00', new DateTimeZone('UTC'));
$w8->runner->tick(new Budget(30));
$scheduled = $w8->store->runs(5);
T::check(count($scheduled) === 1 && $scheduled[0]['trigger_type'] === 'schedule' && $scheduled[0]['status'] === 'completed', 'Zeitplan: faelliger Termin startet und beendet einen Lauf per Cron-Aufruf');
T::check($w8->store->settings()['next_run_at'] === '2026-10-29 03:00:00', 'Zeitplan: naechster Termin liegt am Folgetag (kein Nachholstau)');
T::check($w8->store->settings()['last_success_at'] !== null, 'Zeitplan: last_success_at wird gesetzt');

// Audit: nur IDs und Codes, keine Geheimnisse.
$auditJson = json_encode($w->audit);
T::check(str_contains((string) $auditJson, 'backup.completed') && !str_contains((string) $auditJson, $w->recipient['text']), 'Audit: Ereignisse vorhanden, ohne Schluessel oder Pfade');
