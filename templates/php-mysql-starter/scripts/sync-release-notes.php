<?php

declare(strict_types=1);

use MGD\Starter\Cms\ReleaseNoteSync;

/*
 * Synchronisiert release-notes.json in die Datenbank (Upsert nach Version + Titel).
 * Aufruf: php scripts/sync-release-notes.php [--file=pfad/zur/release-notes.json]
 */

$app = require __DIR__ . '/cli-bootstrap.php';
$file = cli_option($argv, 'file', $app->config->path('release_notes_file'));

try {
    $summary = (new ReleaseNoteSync($app->releaseNotes()))->syncFile((string) $file);
} catch (Throwable $exception) {
    cli_fail($exception->getMessage());
}

$app->audit()->record(null, 'release_note.sync', 'release_note', null, [...$summary, 'errors' => count($summary['errors']), 'source' => 'cli']);
printf("Neu: %d, aktualisiert: %d, unverändert: %d\n", $summary['created'], $summary['updated'], $summary['unchanged']);

foreach ($summary['errors'] as $error) {
    fwrite(STDERR, $error . PHP_EOL);
}

exit($summary['errors'] === [] ? 0 : 2);
