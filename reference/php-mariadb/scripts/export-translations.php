<?php

declare(strict_types=1);

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use MGD\Platform\Core\I18n\TranslationRegistry;

$container = require dirname(__DIR__) . '/bootstrap.php';
$database = $container['database'];

$locale = isset($argv[1]) && trim((string) $argv[1]) !== ''
    ? trim((string) $argv[1])
    : null;

$actor = new Actor(
    'translation-export-cli',
    'bootstrap',
    ['translations.export']
);

$registry = new TranslationRegistry(
    $database,
    new AuditLogger($database)
);

try {
    fwrite(STDOUT, $registry->exportJson($actor, $locale) . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, "Export failed: {$error->getMessage()}\n");
    exit(1);
}
