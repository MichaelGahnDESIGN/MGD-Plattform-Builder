<?php

declare(strict_types=1);

/*
 * Wiederherstellung per Kommandozeile (es gibt bewusst keinen Web-Restore).
 *
 * Ablauf:
 *   1. Auf dem EIGENEN Rechner: php modules/backup/tools/mgd-backup.php decrypt <teile-ordner> --key-file=<schluessel> --out=<klartext-ordner> --signer=<fp>
 *      (prueft Signatur, Hashes und Zeilenzahlen; der private Schluessel bleibt dort)
 *   2. Den Klartext-Ordner auf den Server bringen (z. B. per FTPS) und Wartungsmodus einschalten.
 *   3. Trockenlauf:  php modules/backup/tools/restore.php <klartext-ordner>
 *      zeigt Tabellen/Zeilen/Bereiche und den Bestaetigungscode; es wird nichts geschrieben.
 *   4. Echt:         php modules/backup/tools/restore.php <klartext-ordner> --confirm=<code> [--only=db|files]
 *
 * Schutz: Der Trockenlauf liest alles durch, bevor geschrieben wird. Die Datenbank wird in einer Transaktion ersetzt
 * (bei Fehler Rollback); backup_*-Tabellen bleiben unberuehrt. Dateibereiche werden in einen Staging-Ordner kopiert und per
 * rename getauscht, der bisherige Bereich bleibt als <bereich>.pre-restore-<zeit> liegen. Ohne Wartungsmodus (Einstellung
 * maintenance.enabled) oder bei laufender Sicherung wird nicht geschrieben (--no-maintenance-check hebt nur die erste Pruefung auf).
 * Ausgabe nur Zaehler, nie Inhalte, Pfade ausserhalb des Projekts oder Zugangsdaten.
 */

use MGD\Backup\Admin\Services;
use MGD\Backup\BackupException;
use MGD\Backup\Failure;
use MGD\Backup\Files\AreaPolicy;
use MGD\Backup\Restore\Restorer;

$app = require dirname(__DIR__, 3) . '/scripts/cli-bootstrap.php';
require_once dirname(__DIR__) . '/module.php'; // registriert den Autoloader des Moduls

$positional = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
$dir = $positional[0] ?? '';

if ($dir === '' || cli_option($argv, 'help') !== null) {
    fwrite(STDERR, "Aufruf: php modules/backup/tools/restore.php <klartext-ordner> [--confirm=<code>] [--only=db|files] [--no-maintenance-check]\n");
    exit(2);
}

if (!extension_loaded('zlib')) {
    cli_fail('PHP-Erweiterung zlib fehlt.');
}

$only = cli_option($argv, 'only');

if ($only !== null && !in_array($only, ['db', 'files'], true)) {
    cli_fail('--only erlaubt nur db oder files.');
}

try {
    $services = Services::for($app);
    $env = $services->env;
    $areas = AreaPolicy::resolve($env->configuredAreas, $env->projectRoot, $env->forbiddenDirs);
    $restorer = new Restorer($app->databases->core(), $areas, $env->excludeTables, $env->migrations());
    $summary = $restorer->preflight($dir);
} catch (BackupException $exception) {
    cli_fail('Pruefung fehlgeschlagen (' . Failure::category($exception) . '). Es wurde nichts veraendert.');
} catch (Throwable $exception) {
    error_log('[mgd-backup] restore: ' . $exception::class);
    cli_fail('Pruefung fehlgeschlagen (internal_error). Es wurde nichts veraendert.');
}

echo 'Tabellen: ', $summary['tables'], ', Zeilen: ', $summary['rows'], PHP_EOL;
echo 'Dateibereiche: ', $summary['areas'] === [] ? '-' : implode(', ', $summary['areas']), PHP_EOL;

if ($summary['skipped_areas'] !== []) {
    echo 'Nicht freigegebene Eintraege (werden ignoriert): ', implode(', ', $summary['skipped_areas']), PHP_EOL;
}

$confirm = cli_option($argv, 'confirm');

if ($confirm === null) {
    echo 'Trockenlauf, es wurde nichts geschrieben. Zum Einspielen: --confirm=', $summary['code'], PHP_EOL;
    exit(0);
}

if (!hash_equals($summary['code'], $confirm)) {
    cli_fail('Der Bestaetigungscode stimmt nicht.');
}

if (cli_option($argv, 'no-maintenance-check') === null && !$app->settings()->bool('maintenance.enabled')) {
    cli_fail('Bitte zuerst den Wartungsmodus einschalten (Einstellungen > Wartung).');
}

if ($services->store->activeRun() !== null) {
    cli_fail('Es laeuft gerade eine Sicherung. Erst abbrechen oder abwarten.');
}

try {
    if ($only !== 'files' && is_file(rtrim($dir, '/') . '/db.ndjson.gz')) {
        $totals = $restorer->restoreDatabase($dir);
        echo 'Datenbank eingespielt: ', count($totals), ' Tabellen, ', array_sum($totals), ' Zeilen', PHP_EOL;
    }

    if ($only !== 'db' && $summary['areas'] !== []) {
        foreach ($restorer->restoreFiles($dir) as $name => $info) {
            echo 'Bereich ', $name, ': ', $info['files'], ' Dateien, bisheriger Ordner als ', $info['previous'], ' behalten', PHP_EOL;
        }
    }

    $app->audit()->record(null, 'backup.restored', 'backup', null, ['tables' => $summary['tables'], 'rows' => $summary['rows'], 'areas' => $summary['areas'], 'only' => $only]);
} catch (BackupException $exception) {
    cli_fail('Wiederherstellung fehlgeschlagen (' . Failure::category($exception) . ').');
} catch (Throwable $exception) {
    error_log('[mgd-backup] restore: ' . $exception::class);
    cli_fail('Wiederherstellung fehlgeschlagen (internal_error).');
}

echo 'Fertig. Wartungsmodus nach der Pruefung wieder ausschalten, alte .pre-restore-Ordner nach Kontrolle selbst loeschen.', PHP_EOL;
