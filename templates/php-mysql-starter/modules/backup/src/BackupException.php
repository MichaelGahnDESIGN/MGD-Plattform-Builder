<?php

declare(strict_types=1);

namespace MGD\Backup;

use RuntimeException;

/**
 * Fehler des Backup-Moduls mit festem Katalog-Code (siehe Failure::CODES). Die Meldung ist immer nur der Code,
 * nie ein Pfad, eine URL oder ein Geheimnis.
 */
class BackupException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
