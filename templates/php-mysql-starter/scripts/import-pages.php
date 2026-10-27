<?php

declare(strict_types=1);

/*
 * Importiert CMS-Seiten aus JSON (Format mgd-cms-pages v1). Bestehende Slugs erhalten eine neue Revision.
 * HTML wird immer serverseitig bereinigt.
 * Aufruf: php scripts/import-pages.php --file=export.json
 */

$app = require __DIR__ . '/cli-bootstrap.php';
$file = cli_option($argv, 'file');

if ($file === null || !is_file($file)) {
    cli_fail('Bitte --file=pfad/zur/datei.json angeben.');
}

try {
    $transfer = $app->pageTransfer();
    $maxBytes = (int) $app->config->get('security.max_import_bytes', 5242880);
    $summary = $transfer->import($transfer->decode((string) file_get_contents($file), $maxBytes), null);
} catch (Throwable $exception) {
    cli_fail($exception->getMessage());
}

$app->audit()->record(null, 'page.import', 'page', null, ['created' => $summary['created'], 'updated' => $summary['updated'], 'errors' => count($summary['errors']), 'source' => 'cli']);
printf("Neu: %d, aktualisiert: %d, Fehler: %d\n", $summary['created'], $summary['updated'], count($summary['errors']));

foreach ($summary['errors'] as $error) {
    fwrite(STDERR, $error . PHP_EOL);
}

exit($summary['errors'] === [] ? 0 : 2);
