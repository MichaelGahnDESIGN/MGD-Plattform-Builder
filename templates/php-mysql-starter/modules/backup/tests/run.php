<?php

declare(strict_types=1);

/*
 * Testlauf des Backup-Moduls.
 *
 *   php modules/backup/tests/run.php            alle Faelle
 *   php modules/backup/tests/run.php format     nur Faelle, deren Dateiname "format" enthaelt
 *
 * Optional: MGD_HUB_TOOL=/pfad/zu/mgd-backup.php (das Standalone-Werkzeug des MGD-Support-Hubs) prueft zusaetzlich die
 * Kompatibilitaet von Builder-Backups mit dem Hub-Werkzeug. TMPDIR bestimmt den Ort fuer temporaere Ordner.
 */

require __DIR__ . '/support.php';
require __DIR__ . '/FakeFtpClient.php';
require __DIR__ . '/World.php';

foreach (['sodium', 'zlib', 'pdo_sqlite', 'curl'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "FAIL: PHP-Erweiterung {$extension} fehlt\n");
        exit(1);
    }
}

$filter = $argv[1] ?? '';
$cases = glob(__DIR__ . '/cases/*.php') ?: [];
sort($cases, SORT_STRING);

try {
    foreach ($cases as $case) {
        if ($filter !== '' && !str_contains(basename($case), $filter)) {
            continue;
        }

        echo "--- " . basename($case) . "\n";
        require $case;
    }
} finally {
    T::cleanup();
}

echo "\n" . T::$passed . " Pruefungen bestanden\n";
