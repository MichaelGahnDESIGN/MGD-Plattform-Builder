<?php

declare(strict_types=1);

namespace MGD\Backup\Files;

use MGD\Backup\BackupException;
use MGD\Backup\Format\BackupFormat;

/**
 * Sicherbare Dateibereiche. Bereiche gibt es nur aus einer Whitelist (config.php, Schluessel backup.areas); im Backoffice
 * lassen sich nur diese Bereiche an- und abwaehlen, nie freie Pfade eintragen. Jeder Bereich muss ein Ordner UNTER dem
 * Projektordner sein und darf weder Geheimnisse (config/), Laufzeitdaten (storage/) noch das Backup-Ziel selbst enthalten
 * oder sein.
 */
final class AreaPolicy
{
    /** Dateien, die in keinem Bereich je gesichert werden (Geheimnisse, Versionsverwaltung). */
    public const ALWAYS_EXCLUDE = ['.git/*', '*/.git/*', 'config.php', '*/config.php', '.env', '*/.env'];

    private function __construct() {}

    /**
     * @param array<mixed> $configured Name => Pfad (absolut oder relativ zum Projektordner)
     * @param list<string> $forbidden Ordner, die nie in einem Bereich liegen oder ihn enthalten duerfen (config, storage ...)
     * @return array<string,string> Name => aufgeloester absoluter Pfad (nur gueltige, existierende Bereiche)
     */
    public static function resolve(array $configured, string $root, array $forbidden): array
    {
        $rootReal = realpath($root);

        if ($rootReal === false) {
            throw new BackupException('path_invalid');
        }

        $forbiddenReal = [];

        foreach ($forbidden as $path) {
            $real = realpath($path);

            if ($real !== false) {
                $forbiddenReal[] = $real;
            }
        }

        $areas = [];

        foreach ($configured as $name => $path) {
            if (!is_string($name) || preg_match(BackupFormat::AREA_NAME_REGEX, $name) !== 1 || !is_string($path) || $path === '') {
                continue;
            }

            $absolute = $path[0] === '/' ? $path : $rootReal . '/' . $path;

            if (preg_match('#(^|/)\.\.(/|$)#', $absolute) === 1 || is_link($absolute)) {
                continue;
            }

            $real = realpath($absolute);

            if ($real === false || !is_dir($real) || $real === $rootReal || !str_starts_with($real, $rootReal . '/')) {
                continue;
            }

            foreach ($forbiddenReal as $bad) {
                if ($real === $bad || str_starts_with($real, $bad . '/') || str_starts_with($bad, $real . '/')) {
                    continue 2;
                }
            }

            $areas[$name] = $real;
        }

        ksort($areas);

        return $areas;
    }

    /** Passt der relative Pfad (Bereich/Datei) auf eines der Ausschlussmuster (fnmatch, auch auf den Dateinamen)? */
    public static function excluded(string $relative, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }

            if (fnmatch($pattern, $relative) || fnmatch($pattern, basename($relative))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ausschlussmuster aus Freitext (eine Zeile je Muster): getrimmt, ohne leere Zeilen, hoechstens 50 Muster zu je 120 Zeichen,
     * nur harmlose Zeichen. Ungueltige Zeilen werden verworfen.
     *
     * @return list<string>
     */
    public static function parsePatterns(string $text): array
    {
        $patterns = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || strlen($line) > 120 || preg_match('#^[A-Za-z0-9._*?/\- ]+$#', $line) !== 1 || str_contains($line, '..')) {
                continue;
            }

            $patterns[$line] = true;

            if (count($patterns) >= 50) {
                break;
            }
        }

        return array_keys($patterns);
    }
}
