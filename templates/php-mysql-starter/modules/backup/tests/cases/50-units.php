<?php

declare(strict_types=1);

use MGD\Backup\Admin\Lang;
use MGD\Backup\Admin\StepUp;
use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Db\Dumper;
use MGD\Backup\Db\Importer;
use MGD\Backup\Failure;
use MGD\Backup\Files\AreaPolicy;
use MGD\Backup\Files\Scanner;
use MGD\Backup\Format\BackupFormat;
use MGD\Backup\Format\BackupFormatException;
use MGD\Backup\Paths;
use MGD\Backup\Restore\Restorer;
use MGD\Backup\Schedule;
use MGD\Backup\Secrets;

// --- Texte: DE/EN deckungsgleich ---------------------------------------------------------------------------------
$de = Lang::keys('de');
$en = Lang::keys('en');
sort($de);
sort($en);
T::check($de === $en, 'Lang: Deutsch und Englisch haben dieselben Schluessel');
$missingCodes = array_filter(Failure::CODES, static fn (string $code): bool => !in_array('code_' . $code, $de, true));
T::check($missingCodes === [], 'Lang: jeder Fehlercode des Katalogs hat einen Text (' . implode(',', $missingCodes) . ')');
$german = new Lang('de');
$english = new Lang('en');
$samePlaceholders = true;

foreach ($de as $key) {
    $a = $german->t($key);
    $b = $english->t($key);
    preg_match_all('/\{[a-z]+\}/', $a, $pa);
    preg_match_all('/\{[a-z]+\}/', $b, $pb);

    if ($a === '' || $b === '' || $pa[0] !== $pb[0]) {
        $samePlaceholders = false;
    }
}

T::check($samePlaceholders, 'Lang: keine leeren Texte, gleiche Platzhalter in DE und EN');
T::check($german->t('run_title', ['id' => 7]) === 'Sicherung 7' && $english->t('run_title', ['id' => 7]) === 'Backup 7' && $english->t('gibt_es_nicht') === 'gibt_es_nicht', 'Lang: Platzhalter werden ersetzt, unbekannte Schluessel zeigen sich selbst');

// --- Zeitplan --------------------------------------------------------------------------------------------------
$utc = new DateTimeZone('UTC');
$at = static fn (string $s): DateTimeImmutable => new DateTimeImmutable($s, $utc);
T::check(Schedule::nextRun('daily', '03:00', 1, 1, $at('2026-10-27 12:00:00'), 'UTC')?->format('c') === '2026-10-28T03:00:00+00:00', 'Zeitplan: taeglich -> naechster Tag');
T::check(Schedule::nextRun('daily', '03:00', 1, 1, $at('2026-10-27 01:00:00'), 'UTC')?->format('c') === '2026-10-27T03:00:00+00:00', 'Zeitplan: taeglich -> heute, wenn die Zeit noch kommt');
T::check(Schedule::nextRun('weekly', '03:00', 7, 1, $at('2026-10-27 12:00:00'), 'UTC')?->format('c') === '2026-11-01T03:00:00+00:00', 'Zeitplan: woechentlich (Sonntag)');
T::check(Schedule::nextRun('monthly', '03:00', 1, 28, $at('2026-10-29 00:00:00'), 'UTC')?->format('c') === '2026-11-28T03:00:00+00:00', 'Zeitplan: monatlich am 28.');
T::check(Schedule::nextRun('off', '03:00', 1, 1, $at('2026-10-27 12:00:00')) === null && Schedule::nextRun('daily', '25:00', 1, 1, $at('2026-10-27 12:00:00')) === null, 'Zeitplan: aus bzw. ungueltige Uhrzeit -> kein Termin');
T::check(Schedule::nextRun('daily', '02:30', 1, 1, $at('2026-03-28 12:00:00'), 'Europe/Berlin')?->format('c') === '2026-03-29T01:30:00+00:00', 'Zeitplan: nicht existierende Ortszeit am Umstelltag (Maerz) wird nach vorn verschoben');
$october = Schedule::nextRun('daily', '02:30', 1, 1, $at('2026-10-24 12:00:00'), 'Europe/Berlin');
$afterOctober = Schedule::nextRun('daily', '02:30', 1, 1, $october, 'Europe/Berlin');
T::check($october !== null && $afterOctober !== null && $afterOctober->format('Y-m-d') === '2026-10-26', 'Zeitplan: doppelte Ortszeit im Oktober zaehlt nur einmal (je Tag ein Lauf)');

// --- Geheimnisse -----------------------------------------------------------------------------------------------
$secrets = new Secrets('ein-erfundener-app-key-0123456789');
$box = $secrets->encrypt('geheim-erfunden');
T::check($secrets->decrypt($box) === 'geheim-erfunden' && !str_contains($box, 'geheim'), 'Secrets: Round-Trip, Geheimtext enthaelt keinen Klartext');
T::check($secrets->encrypt('x') !== $secrets->encrypt('x'), 'Secrets: jede Verschluesselung hat ein eigenes Nonce');
T::throws(static fn () => (new Secrets('ein-anderer-app-key-erfunden-123456'))->decrypt($box), BackupException::class, 'Secrets: mit anderem app.key nicht lesbar', 'signing_key_unreadable');
T::throws(static fn () => $secrets->decrypt(substr($box, 0, -4)), BackupException::class, 'Secrets: abgeschnittener Geheimtext wird abgelehnt');
T::throws(static fn () => new Secrets('change-me'), BackupException::class, 'Secrets: der Platzhalter-Schluessel change-me ist verboten', 'key_missing');

// --- Pfade -----------------------------------------------------------------------------------------------------
$dir = T::tempDir();
$paths = new Paths($dir . '/root');
$paths->ensureRoot();
$folder = Paths::folderName('2026-10-27 12:00:00', 'full', '0123abcd-0000-4000-8000-000000000000');
T::check($folder === 'mgdbk_20261027T120000Z_full_0123abcd' && Paths::isFolderName($folder), 'Pfade: Ordnername nach Schema');
$paths->ensureRunDir($folder);
T::check((fileperms($paths->root() . '/' . $folder) & 0777) === 0700, 'Pfade: Lauf-Ordner mit Rechten 0700');
foreach ([$dir . '/root/../x', $dir . '/root/' . $folder . '/../../x', $dir . '/root/fremd', $dir . '/root/' . $folder . '/passwd', $dir . '/root/' . $folder . '/a/b', '/etc/passwd', ''] as $bad) {
    T::throws(static fn () => $paths->assertInside($bad), BackupException::class, 'Pfade: assertInside lehnt ab: ' . ($bad === '' ? '(leer)' : substr($bad, strlen($dir))), 'path_invalid');
}

symlink($dir, $paths->root() . '/mgdbk_20261027T120000Z_db_deadbeef');
T::throws(static fn () => $paths->assertInside($paths->root() . '/mgdbk_20261027T120000Z_db_deadbeef'), BackupException::class, 'Pfade: Symlink als Lauf-Ordner wird abgelehnt', 'path_invalid');
T::check($paths->folders() === [$folder], 'Pfade: folders() ignoriert Symlinks und Fremdnamen');
T::throws(static fn () => Paths::partFileName('../x', 1, 'db'), BackupException::class, 'Pfade: Teilname nur zu gueltigem Ordnernamen', 'path_invalid');

// --- Bereiche --------------------------------------------------------------------------------------------------
$project = $dir . '/project';

foreach (['public/uploads', 'config', 'storage/backups', 'public/assets', 'public/assets/custom'] as $sub) {
    mkdir($project . '/' . $sub, 0755, true);
}

symlink($project . '/config', $project . '/public/linked');
$areas = AreaPolicy::resolve([
    'uploads' => 'public/uploads', 'assets-all' => 'public/assets', 'config' => 'config', 'speicher' => 'storage/backups', 'wurzel' => '.', 'draussen' => $dir,
    'link' => 'public/linked', 'traversal' => 'public/../config', 'Gross' => 'public/uploads', 'fehlt' => 'public/gibt-es-nicht', 'absolut' => '/etc',
], $project, [$project . '/config', $project . '/storage']);
T::check(array_keys($areas) === ['assets-all', 'uploads'], 'Bereiche: nur gueltige Ordner im Projekt; config/storage/Wurzel/aussen/Symlink/../ fliegen raus (' . implode(',', array_keys($areas)) . ')');
T::check(AreaPolicy::parsePatterns("*.tmp\n  cache/*  \n../etc\n\nbad;chars\n" . str_repeat('x', 200)) === ['*.tmp', 'cache/*'], 'Bereiche: Ausschlussmuster werden bereinigt');

// --- Scanner ---------------------------------------------------------------------------------------------------
file_put_contents($project . '/public/uploads/ok.txt', 'ok');
file_put_contents($project . '/public/uploads/mit leerzeichen.txt', 'x');
file_put_contents($project . '/public/uploads/config.php', '<?php secret');
file_put_contents($project . '/public/uploads/.env', 'SECRET=1');
mkdir($project . '/public/uploads/.git');
file_put_contents($project . '/public/uploads/.git/HEAD', 'ref');
symlink($project . '/config', $project . '/public/uploads/link-dir');
symlink($project . '/public/uploads/ok.txt', $project . '/public/uploads/link-file');
link($project . '/public/uploads/ok.txt', $project . '/public/uploads/hard.txt');
$scanner = new Scanner($paths);
$stats = $scanner->buildIndex($folder, ['uploads' => $areas['uploads']], []);
$index = file($paths->indexPath($folder), FILE_IGNORE_NEW_LINES);
$names = array_map(static fn (string $line): string => explode("\t", $line)[0], $index);
T::check($names === [], 'Scanner: Hardlinks, Symlinks, Leerzeichen-Namen, config.php, .env und .git sind nicht im Index (' . implode(',', $names) . ')');
T::check($stats['skipped_links'] === 4 && $stats['skipped_unsafe'] === 1, 'Scanner: uebersprungene Dateien werden gezaehlt (links=' . $stats['skipped_links'] . ', unsicher=' . $stats['skipped_unsafe'] . ')');
T::check((fileperms($paths->indexPath($folder)) & 0777) === 0600, 'Scanner: Dateiindex hat Rechte 0600');

// --- Schritt-Up -----------------------------------------------------------------------------------------------
$w = new World(rows: 3);
$stepUp = new StepUp($w->store);
$hash = password_hash('richtig-erfunden', PASSWORD_DEFAULT);
T::check($stepUp->verify($hash, 'richtig-erfunden') === StepUp::OK, 'Step-up: richtiges Passwort');
T::check($stepUp->verify($hash, 'falsch') === StepUp::WRONG && $stepUp->verify(null, 'x') === StepUp::WRONG && $stepUp->verify($hash, '') === StepUp::WRONG, 'Step-up: falsches/leeres Passwort und fehlender Hash');
$stepUp->verify($hash, 'falsch');
$stepUp->verify($hash, 'falsch');
T::check($stepUp->verify($hash, 'richtig-erfunden') === StepUp::BLOCKED, 'Step-up: nach 5 Fehlversuchen gesperrt, auch mit richtigem Passwort');
$w->now = $w->now->modify('+61 minutes');
T::check($stepUp->verify($hash, 'richtig-erfunden') === StepUp::OK, 'Step-up: nach einer Stunde wieder frei');
$stepUp->verify($hash, 'falsch');
T::check($stepUp->verify($hash, 'richtig-erfunden') === StepUp::OK && (int) $w->store->settings()['stepup_fail_count'] === 0, 'Step-up: Erfolg setzt den Zaehler zurueck');

// --- Schluessel ---------------------------------------------------------------------------------------------
$other = T::keyPair();
T::throws(static fn () => $w->keys->setRecipient('emergency', $w->recipient['text']), BackupException::class, 'Schluessel: derselbe Schluessel darf nicht Haupt- und Notfallschluessel sein', 'key_invalid');
T::throws(static fn () => $w->keys->setRecipient('emergency', 'mgdbk-pk1:x'), BackupFormatException::class, 'Schluessel: ungueltige Textform wird abgelehnt');
T::throws(static fn () => $w->keys->setRecipient('boss', $other['text']), BackupException::class, 'Schluessel: unbekannte Rolle wird abgelehnt', 'key_invalid');
$w->keys->setRecipient('emergency', $other['text']);
T::check(count($w->keys->recipients()) === 2 && $w->keys->fingerprints() === [$w->recipient['fp'], $other['fp']], 'Schluessel: Haupt- und Notfallschluessel werden Empfaenger');
$w->keys->removeEmergency();
T::check(count($w->keys->recipients()) === 1, 'Schluessel: Notfallschluessel entfernbar');
$w->keys->ensureSigning();
$fp = $w->keys->signingFingerprint();
$w->keys->ensureSigning();
T::check($fp === $w->keys->signingFingerprint() && strlen($w->keys->signingSecret()) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'Schluessel: Signaturschluessel wird einmal erzeugt und bleibt stabil');

// --- Fehlerkatalog -----------------------------------------------------------------------------------------------
T::check(Failure::category(new RuntimeException('Passwort geheim /pfad/x')) === 'internal_error' && Failure::category(new BackupFormatException('bad_manifest')) === 'format_error', 'Fehler: fremde Ausnahmen werden zu Katalogcodes, ihr Text geht verloren');
T::check((new BackupException('io_error'))->getMessage() === 'io_error', 'Fehler: BackupException traegt nur den Code');
T::check(Failure::fromCurl(60) === 'tls_failed' && Failure::fromCurl(67) === 'auth_failed' && Failure::fromCurl(6) === 'connection_failed' && Failure::fromCurl(28) === 'timeout' && Failure::fromCurl(25) === 'upload_failed', 'Fehler: curl-Nummern werden Kategorien zugeordnet');

// --- Budget ----------------------------------------------------------------------------------------------------
$calls = 0;
$budget = new Budget(2.0, static function () use (&$calls): float {
    return (float) $calls++;
});
T::check(!$budget->exhausted() && $budget->exhausted(), 'Budget: laeuft mit der injizierten Uhr ab');
T::check(Budget::forCron(1)->remaining() >= 4.0 || (int) ini_get('max_execution_time') > 0, 'Budget: Mindestbudget 5 s ohne Zeitlimit');

// --- Dump/Import: Tabelle ohne Primaerschluessel, Ausschluesse --------------------------------------------------------
$pdo = T::pdo();
$pdo->exec('CREATE TABLE ohne_pk (a INTEGER, b TEXT)');
$pdo->exec('CREATE TABLE "weird-name" (a INTEGER)');
$dumper = new Dumper($pdo);
T::throws(static fn () => $dumper->plan(), BackupException::class, 'Dump: ein Tabellenname mit Sonderzeichen bricht ab statt still ausgelassen zu werden', 'database_error');
$pdo->exec('DROP TABLE "weird-name"');

for ($i = 0; $i < 450; $i++) {
    $pdo->exec('INSERT INTO ohne_pk VALUES (' . ($i % 7) . ", 'zeile-" . $i . "')");
}

$plan = $dumper->plan();
T::check(in_array('ohne_pk', $plan['tables'], true) && !in_array('backup_runs', $plan['tables'], true) && in_array('backup_runs', $plan['excluded'], true), 'Dump: backup_*-Tabellen sind ausgeschlossen');

$sink = $dir . '/ohnepk.mgdb';
$key = T::keyPair();
$writer = MGD\Backup\Format\PartWriter::open($sink, BackupFormat::KIND_DB, random_bytes(16), 1, [MGD\Backup\Format\RecipientKey::parse($key['text'])]);
$state = ['tables' => ['ohne_pk'], 'excluded' => [], 'i' => 0, 'last' => null, 'off' => 0, 'stats' => [], 'consistent' => true];
$result = $dumper->writeNextPart($writer, $state, new Budget(30), 1 << 30);
$writer->finish();
T::check($result['done'] && $result['rows'] === 450, 'Dump: Tabelle ohne Primaerschluessel wird ueber den Offset-Weg vollstaendig gelesen');
$reader = MGD\Backup\Format\PartReader::open($sink, $key['secret']);
$events = iterator_to_array((new MGD\Backup\Format\DbStreamReader($reader->chunks()))->events(), false);
T::check(count(array_filter($events, static fn (array $e): bool => $e['type'] === 'row')) === 450, 'Dump: alle 450 Zeilen stehen im Strom');

$target = T::pdo();
$target->exec('CREATE TABLE ohne_pk (a INTEGER, b TEXT)');
$target->exec("INSERT INTO ohne_pk VALUES (99, 'alt')");
$importer = new Importer($target);
$totals = $importer->import($events);
T::check($totals === ['ohne_pk' => 450] && (int) $target->query('SELECT COUNT(*) FROM ohne_pk')->fetchColumn() === 450, 'Import: vorhandene Zeilen werden ersetzt, Zeilenzahl stimmt');
$bad = [['type' => 'table', 'table' => 'backup_runs', 'columns' => ['id']], ['type' => 'end', 'table' => 'backup_runs', 'rows' => 0]];
T::throws(static fn () => (new Importer($target))->import($bad), BackupException::class, 'Import: backup_*-Tabellen werden nie beschrieben', 'database_error');
$bad = [['type' => 'table', 'table' => 'ohne_pk', 'columns' => ['a', 'zusatz']], ['type' => 'end', 'table' => 'ohne_pk', 'rows' => 0]];
T::throws(static fn () => (new Importer($target))->import($bad), BackupException::class, 'Import: unbekannte Spalte wird abgelehnt', 'database_error');

// --- Restorer: Dateien ---------------------------------------------------------------------------------------------
$plain = $dir . '/plain';
mkdir($plain . '/uploads/sub', 0700, true);
file_put_contents($plain . '/uploads/sub/neu.txt', 'neu');
file_put_contents($plain . '/uploads/ok.txt', 'aus dem Backup');
mkdir($plain . '/fremd', 0700);
file_put_contents($project . '/public/uploads/ok.txt', 'aktuell');
$restorer = new Restorer($pdo, ['uploads' => $areas['uploads']], [], []);
$summary = $restorer->preflight($plain);
T::check($summary['areas'] === ['uploads'] && $summary['skipped_areas'] === ['fremd'] && strlen($summary['code']) === 8, 'Restore: nur freigegebene Bereiche werden uebernommen, andere gemeldet');
$result = $restorer->restoreFiles($plain, '20261027T000000');
T::check(file_get_contents($areas['uploads'] . '/ok.txt') === 'aus dem Backup' && file_get_contents($areas['uploads'] . '/sub/neu.txt') === 'neu', 'Restore: Dateien liegen im Bereich');
T::check(is_dir($areas['uploads'] . '.pre-restore-20261027T000000') && file_get_contents($areas['uploads'] . '.pre-restore-20261027T000000/ok.txt') === 'aktuell', 'Restore: bisheriger Bereich bleibt als .pre-restore erhalten');
T::check((fileperms($areas['uploads'] . '/ok.txt') & 0777) === 0644, 'Restore: wiederhergestellte Dateien sind web-lesbar (0644)');

symlink('/etc/passwd', $plain . '/uploads/boese-link');
T::throws(static fn () => $restorer->restoreFiles($plain, '20261027T000001'), BackupException::class, 'Restore: ein Symlink im Klartext-Baum bricht vor dem Tausch ab', 'path_invalid');
T::check(!file_exists($areas['uploads'] . '.restore-20261027T000001'), 'Restore: das Staging wird nach einem Fehler entfernt');
T::throws(static fn () => $restorer->preflight($dir . '/gibt-es-nicht'), BackupException::class, 'Restore: fehlender Ordner wird abgelehnt', 'path_invalid');

// --- Aufraeumen verwaister Ordner: nur ohne Lauf-Zeile, nie ein Ordner eines bekannten Laufs -------------------------
$s = new World(rows: 3);
$s->enableLocal();
$known = $s->runToEnd($s->runner->enqueue('db', 'manual', 1));

for ($i = 0; $i < 210; $i++) {
    $folderName = sprintf('mgdbk_20200101T0000%02dZ_db_%s', $i % 60, bin2hex(random_bytes(4)));
    $s->pdo->prepare("INSERT INTO backup_runs (uuid, kind, trigger_type, status, folder_name, queued_at) VALUES (?, 'db', 'manual', 'completed', ?, '2020-01-01 00:00:00')")
        ->execute([MGD\Backup\Runner::uuid(), $folderName]);
}

$orphan = $s->paths->root() . '/mgdbk_20200101T000000Z_db_0badc0de';
mkdir($orphan, 0700);
file_put_contents($orphan . '/mgdbk_20200101T000000Z_db_0badc0de_001_db.mgdb', 'rest');
file_put_contents($orphan . '/fremd.txt', 'fremde Datei');
touch($orphan, time() - 3 * 86400);
touch($s->runDir($known), time() - 3 * 86400);
$s->runner->tick(new Budget(5));
T::check(is_dir($s->runDir($known)), 'Aufraeumen: der Ordner eines bekannten Laufs bleibt, auch wenn er alt ist und viele neuere Laeufe existieren');
T::check(!file_exists($orphan . '/mgdbk_20200101T000000Z_db_0badc0de_001_db.mgdb') && is_file($orphan . '/fremd.txt'), 'Aufraeumen: verwaiste Teile werden entfernt, fremde Dateien im Ordner bleiben liegen');
