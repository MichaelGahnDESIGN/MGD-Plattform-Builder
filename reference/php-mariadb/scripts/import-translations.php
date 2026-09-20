<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\I18n\TranslationRegistry;

$container = require dirname(__DIR__) . '/bootstrap.php';
$database = $container['database'];

$file = $argv[1] ?? null;

if (!is_string($file) || $file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/import-translations.php path/to/translations.json\n");
    exit(1);
}

$json = file_get_contents($file);

if ($json === false) {
    fwrite(STDERR, "Could not read translation file.\n");
    exit(1);
}

$actor = new Actor(
    'translation-import-cli',
    'bootstrap',
    ['translations.import', 'translations.manage']
);

$registry = new TranslationRegistry(
    $database,
    new AuditLogger($database)
);

try {
    $count = $registry->importJson($actor, $json);
    fwrite(STDOUT, "Imported {$count} translation(s) as draft.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Import failed: {$error->getMessage()}\n");
    exit(1);
}
