<?php

declare(strict_types=1);

namespace MGD\Backup\Files;

use MGD\Backup\BackupException;
use MGD\Backup\Format\BackupFormat;
use MGD\Backup\Paths;

/**
 * Erstellt den Dateiindex <ordner>/files.idx (0600) aus den gewaehlten Bereichen: je Zeile `<bereich>/<relativer pfad>\t<groesse>`.
 * Aufgenommen werden nur regulaere Dateien mit sicherem Pfad (BackupFormat::isSafeFilePath). Nichts wird still ausgelassen:
 * Symlinks und Hardlinks (nlink > 1) zaehlen als skipped_links, unsichere Namen als skipped_unsafe, nicht lesbare Dateien
 * als skipped_unreadable (der Lauf endet dann als partial mit Warnung). Muster aus AreaPolicy::ALWAYS_EXCLUDE und die
 * Einstellung "Ausschluesse" gelten fuer den Pfad innerhalb des Bereichs und fuer den Dateinamen. Die Zeilen sind stabil
 * sortiert; der Packer liest den Index per Byte-Offset.
 *
 * Bekannte Grenze: Der Scan ist ein Durchgang ohne Zeitbudget (bei sehr vielen Dateien kann er ein Zeitlimit des Hosters
 * ueberschreiten; dann Bereiche verkleinern oder Ausschluesse setzen).
 */
final class Scanner
{
    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @param array<string,string> $areas Bereich => absoluter Ordner (AreaPolicy::resolve)
     * @param list<string> $patterns Ausschlussmuster
     * @return array{files:int,bytes:int,skipped_links:int,skipped_unsafe:int,skipped_unreadable:int}
     */
    public function buildIndex(string $folder, array $areas, array $patterns): array
    {
        $this->paths->ensureRunDir($folder);
        $indexPath = $this->paths->assertInside($this->paths->indexPath($folder));

        if (file_exists($indexPath) || is_link($indexPath)) {
            @unlink($indexPath);
        }

        $old = umask(0177);
        $handle = @fopen($indexPath, 'xb');
        umask($old);

        if ($handle === false) {
            throw new BackupException('io_error');
        }

        $patterns = array_merge(AreaPolicy::ALWAYS_EXCLUDE, $patterns);
        $stats = ['files' => 0, 'bytes' => 0, 'skipped_links' => 0, 'skipped_unsafe' => 0, 'skipped_unreadable' => 0];

        try {
            foreach ($areas as $name => $base) {
                $this->walk($handle, $name, $base, '', $patterns, $stats);
            }

            if (!fflush($handle)) {
                throw new BackupException('io_error');
            }
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($indexPath);

            throw $e;
        }

        fclose($handle);
        @chmod($indexPath, 0600);

        return $stats;
    }

    /**
     * @param resource $handle
     * @param list<string> $patterns
     * @param array<string,int> $stats
     */
    private function walk($handle, string $area, string $base, string $relativeDir, array $patterns, array &$stats): void
    {
        $dir = $relativeDir === '' ? $base : $base . '/' . $relativeDir;
        $names = scandir($dir);

        if ($names === false) {
            $stats['skipped_unreadable']++;

            return;
        }

        sort($names, SORT_STRING);

        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $relative = $relativeDir === '' ? $name : $relativeDir . '/' . $name;
            $full = $base . '/' . $relative;
            $indexed = $area . '/' . $relative;

            if (is_link($full)) {
                $stats['skipped_links']++;

                continue;
            }

            if (AreaPolicy::excluded($relative, $patterns)) {
                continue;
            }

            if (is_dir($full)) {
                $this->walk($handle, $area, $base, $relative, $patterns, $stats);

                continue;
            }

            $stat = is_file($full) ? @stat($full) : false;

            if ($stat === false || !is_readable($full)) {
                $stats['skipped_unreadable']++;

                continue;
            }

            if ((int) $stat['nlink'] > 1) {
                $stats['skipped_links']++;

                continue;
            }

            if (!BackupFormat::isSafeFilePath($indexed)) {
                $stats['skipped_unsafe']++;

                continue;
            }

            $line = $indexed . "\t" . (int) $stat['size'] . "\n";

            if (fwrite($handle, $line) !== strlen($line)) {
                throw new BackupException('io_error');
            }

            $stats['files']++;
            $stats['bytes'] += (int) $stat['size'];
        }
    }
}
