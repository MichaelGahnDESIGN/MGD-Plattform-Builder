<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Database;

use InvalidArgumentException;
use PDO;

final class ConnectionFactory
{
    public static function create(array $config): PDO
    {
        foreach (['host', 'name', 'user'] as $required) {
            if (!isset($config[$required]) || !is_string($config[$required]) || $config[$required] === '') {
                throw new InvalidArgumentException('Datenbank-Konfiguration unvollständig: ' . $required);
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) ($config['port'] ?? 3306),
            $config['name'],
            (string) ($config['charset'] ?? 'utf8mb4')
        );

        $pdo = new PDO($dsn, $config['user'], (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
