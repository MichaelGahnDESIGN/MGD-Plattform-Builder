<?php

declare(strict_types=1);

/*
 * Exportiert CMS-Seiten als JSON.
 * Aufruf: php scripts/export-pages.php [--out=datei.json] [--with-revisions] [--ids=1,2,3]
 * Ohne --out wird nach storage/exports/ geschrieben.
 */

$app = require __DIR__ . '/cli-bootstrap.php';
$ids = array_values(array_filter(array_map('intval', explode(',', (string) cli_option($argv, 'ids', '')))));
$out = cli_option($argv, 'out', $app->config->path('exports') . '/cms-pages-' . gmdate('Ymd-His') . '.json');

try {
    $data = $app->pageTransfer()->export($ids, cli_option($argv, 'with-revisions') === '1');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (file_put_contents((string) $out, $json . PHP_EOL, LOCK_EX) === false) {
        cli_fail('Datei konnte nicht geschrieben werden: ' . $out);
    }
} catch (Throwable $exception) {
    cli_fail($exception->getMessage());
}

$app->audit()->record(null, 'page.export', 'page', null, ['count' => count($data['pages']), 'source' => 'cli']);
echo count($data['pages']), ' Seite(n) exportiert nach ', $out, PHP_EOL;
