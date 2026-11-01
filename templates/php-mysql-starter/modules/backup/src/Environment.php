<?php

declare(strict_types=1);

namespace MGD\Backup;

use Closure;

/**
 * Alles, was der Runner vom umgebenden Projekt braucht, ohne den Starter direkt zu kennen (damit Tests ohne Starter laufen):
 * Version, sicherbare Bereiche (Whitelist), Projektordner, gesperrte Ordner, auszuschliessende Tabellen und die Liste der
 * angewendeten Migrationen.
 */
final class Environment
{
    /**
     * @param array<string,string> $configuredAreas Bereichsname => Pfad (Whitelist aus config.php; absolut oder relativ zum Projektordner)
     * @param list<string> $forbiddenDirs Ordner, die in keinem Bereich liegen oder ihn enthalten duerfen
     * @param list<string> $excludeTables Tabellen, die nie gesichert werden (zusaetzlich zu backup_*)
     * @param Closure():list<string> $migrations
     */
    public function __construct(
        public readonly string $appVersion,
        public readonly array $configuredAreas,
        public readonly string $projectRoot,
        public readonly array $forbiddenDirs,
        public readonly array $excludeTables,
        private readonly Closure $migrations,
        public readonly int $minFreeBytes = 268435456,
        public readonly int $maxRunHours = 24,
        public readonly int $maxAttempts = 5,
        public readonly string $timezone = 'Europe/Berlin',
        public readonly ?int $partBytesOverride = null,
    ) {
    }

    /**
     * Angewendete Migrationen in einer fuers Manifest zulaessigen Schreibweise ([0-9A-Za-z_], hoechstens 200 Zeichen).
     *
     * @return list<string>
     */
    public function migrations(): array
    {
        $names = [];

        foreach (($this->migrations)() as $name) {
            $clean = substr((string) preg_replace('/[^0-9A-Za-z_]/', '_', (string) $name), 0, 200);

            if ($clean !== '') {
                $names[] = $clean;
            }
        }

        return $names;
    }
}
