<?php

declare(strict_types=1);

use MGD\Backup\Format\SecretKeyFile;

/*
 * Kompatibilitaet mit dem Standalone-Werkzeug des MGD-Support-Hubs (gleiches Paketformat MGDB1).
 * Nur wenn MGD_HUB_TOOL auf die Datei tools/backup/mgd-backup.php des Hubs zeigt; sonst uebersprungen.
 *
 * Erwartung: Ein DB-Backup des Builders (Art db) laesst sich mit dem Hub-Werkzeug pruefen und entschluesseln. Ein Backup mit
 * Dateien (Art full) nutzt freie Bereichsnamen und Pfade (Obermenge des Hub-Formats); der Hub-Verifizierer kennt nur
 * uploads/erp mit Zahlenpfaden und weist solche Pakete ab. Das ist eine dokumentierte Abweichung.
 */
$hubTool = getenv('MGD_HUB_TOOL') ?: '';

if ($hubTool === '' || !is_file($hubTool)) {
    echo "skip Hub-Kompatibilitaet (MGD_HUB_TOOL nicht gesetzt)\n";

    return;
}

$php = PHP_BINARY;
$w = new World(rows: 200, partBytes: 4096);
$w->enableLocal();
$run = $w->runToEnd($w->runner->enqueue('db', 'manual', 1));
T::check($run['status'] === 'completed', 'Hub-Kompatibilitaet: Builder-DB-Backup erzeugt');
mkdir($w->dir . '/keys', 0700);
SecretKeyFile::write($w->dir . '/keys/p.key', $w->recipient['secret'], null);
$signer = (string) $w->store->settings()['signing_fp'];

[$code, $stdout, $stderr] = T::run([$php, $hubTool, 'verify', $w->runDir($run), '--key-file=' . $w->dir . '/keys/p.key', '--signer=' . $signer]);
echo "     hub verify: exit {$code}: " . trim(str_replace("\n", ' | ', $stdout . $stderr)) . "\n";
T::check($code === 0 && str_starts_with($stdout, 'OK'), 'Hub-Werkzeug: verify eines Builder-DB-Backups ist OK (Signatur, Hashes, Zeilenzahlen)');

[$code, $stdout, $stderr] = T::run([$php, $hubTool, 'decrypt', $w->runDir($run), '--key-file=' . $w->dir . '/keys/p.key', '--out=' . $w->dir . '/hub-out', '--signer=' . $signer]);
T::check($code === 0 && is_file($w->dir . '/hub-out/db.ndjson.gz'), 'Hub-Werkzeug: decrypt eines Builder-DB-Backups liefert db.ndjson.gz');
[$ownCode] = T::run([$php, dirname(__DIR__, 2) . '/tools/mgd-backup.php', 'decrypt', $w->runDir($run), '--key-file=' . $w->dir . '/keys/p.key', '--out=' . $w->dir . '/own-out', '--signer=' . $signer]);
$read = static function (string $file): string {
    $gz = gzopen($file, 'rb');
    $out = '';

    while (!gzeof($gz)) {
        $out .= gzread($gz, 65536);
    }

    gzclose($gz);

    return $out;
};
T::check($ownCode === 0 && $read($w->dir . '/hub-out/db.ndjson.gz') === $read($w->dir . '/own-out/db.ndjson.gz'), 'Hub-Werkzeug: entschluesselte Datenbank ist identisch mit der des Builder-Werkzeugs');

[$code] = T::run([$php, $hubTool, 'verify', $w->runDir($run), '--key-file=' . $w->dir . '/keys/p.key', '--signer=' . str_repeat('0', 16)]);
T::check($code !== 0, 'Hub-Werkzeug: falscher Signer wird abgelehnt');

// Bekannte Abweichung: Backups MIT Dateien (full) nutzen freie Pfade; der Hub-Verifizierer lehnt sie ab (nur Information).
$full = new World(rows: 20);
$full->enableLocal();
$fullRun = $full->runToEnd($full->runner->enqueue('full', 'manual', 1));
mkdir($full->dir . '/keys', 0700);
SecretKeyFile::write($full->dir . '/keys/p.key', $full->recipient['secret'], null);
[$fullCode, , $fullErr] = T::run([$php, $hubTool, 'verify', $full->runDir($fullRun), '--key-file=' . $full->dir . '/keys/p.key', '--signer=' . $full->store->settings()['signing_fp']]);
echo "     Hub-Werkzeug bei full (mit Dateien): exit {$fullCode} " . trim($fullErr) . " (erwartet: Abweichung, siehe Doku)\n";
T::check($fullCode === 1 && str_contains($fullErr, 'bad_path'), 'Hub-Werkzeug: Abweichung belegt - full-Backups mit freien Pfaden lehnt der Hub-Verifizierer ab (bad_path)');
