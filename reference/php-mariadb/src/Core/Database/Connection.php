<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Database;

use PDO;

final class Connection
{
    public static function create(array $config): PDO
    {
        return new PDO(
            $config['dsn'],
            $config['user'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
