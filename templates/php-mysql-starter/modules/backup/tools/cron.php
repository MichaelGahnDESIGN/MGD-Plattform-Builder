<?php

declare(strict_types=1);

/*
 * CLI-Cron-Einstieg des Backup-Moduls (bevorzugt, wenn der Hoster Cronjobs per PHP-CLI erlaubt; kein Token noetig).
 *
 *   php modules/backup/tools/cron.php                  arbeitet faellige Laeufe ab (ein Zeitbudget je Aufruf)
 *   php modules/backup/tools/cron.php --enqueue=full   legt zusaetzlich einen Lauf an (full oder db)
 *   php modules/backup/tools/cron.php --status         zeigt den Zustand, ohne zu arbeiten
 *
 * Ausgabe nur Zustand und Zaehler, nie Pfade oder Zugangsdaten. Exit 0 = ok, 1 = Fehler.
 */

use MGD\Backup\Admin\Services;
use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Failure;

$app = require dirname(__DIR__, 3) . '/scripts/cli-bootstrap.php';

require_once dirname(__DIR__) . '/module.php'; // registriert den Autoloader des Moduls

if (!extension_loaded('sodium') || !extension_loaded('zlib')) {
    cli_fail('PHP-Erweiterung sodium oder zlib fehlt.');
}

if (!$app->modules()->isEnabled('backup')) {
    cli_fail('Das Backup-Modul ist nicht aktiviert (Backoffice > Einstellungen > Module).');
}

try {
    $services = Services::for($app);
    $runner = $services->runner;

    if (cli_option($argv, 'status') !== null) {
        $settings = $services->store->settings();
        $active = $services->store->activeRun();
        echo 'letzter Cron-Aufruf: ', $settings['last_tick_at'] ?? '-', PHP_EOL;
        echo 'letzte erfolgreiche Sicherung: ', $settings['last_success_at'] ?? '-', PHP_EOL;
        echo 'aktiver Lauf: ', $active !== null ? '#' . $active['id'] . ' ' . $active['phase'] : '-', PHP_EOL;
        exit(0);
    }

    $enqueue = cli_option($argv, 'enqueue');

    if ($enqueue !== null) {
        $id = $runner->enqueue($enqueue === 'db' ? 'db' : 'full', 'cli', null);
        echo 'Lauf #', $id, ' angelegt', PHP_EOL;
    }

    $result = $runner->tick(Budget::forCron($services->tickSeconds()));
    echo 'ok ', $result['state'], $result['run_id'] !== null ? ' #' . $result['run_id'] . ' ' . $result['phase'] : '', PHP_EOL;
} catch (BackupException $exception) {
    cli_fail('Backup: ' . Failure::category($exception));
} catch (Throwable $exception) {
    error_log('[mgd-backup] cron: ' . $exception::class);
    cli_fail('Backup: ' . Failure::category($exception));
}
