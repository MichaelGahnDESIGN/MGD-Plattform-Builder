<?php

declare(strict_types=1);

use MGD\Platform\Core\Database\Connection;

require __DIR__ . '/vendor/autoload.php';

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    throw new RuntimeException('Missing config.php. Copy config.example.php first.');
}

$config = require $configFile;
$database = Connection::create($config['database']);

return [
    'config' => $config,
    'database' => $database,
];
