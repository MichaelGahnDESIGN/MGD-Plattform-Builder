<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Database;

use PDO;

/**
 * Hält die Kernverbindung und die (optionale) private Verbindung.
 * Ohne eigene private Datenbank wird eine separate Verbindung zur Kerndatenbank
 * geöffnet, damit private Daten später ohne Codeänderung umziehen können.
 */
final class Databases
{
    private ?PDO $core = null;
    private ?PDO $private = null;

    public function __construct(
        private readonly array $coreConfig,
        private readonly ?array $privateConfig = null,
    ) {
    }

    public function core(): PDO
    {
        return $this->core ??= ConnectionFactory::create($this->coreConfig);
    }

    public function private(): PDO
    {
        return $this->private ??= ConnectionFactory::create($this->privateConfig ?? $this->coreConfig);
    }

    public function hasSeparatePrivateDatabase(): bool
    {
        return $this->privateConfig !== null;
    }
}
