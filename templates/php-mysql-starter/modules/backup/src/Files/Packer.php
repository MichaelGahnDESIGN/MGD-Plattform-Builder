<?php

declare(strict_types=1);

namespace MGD\Backup\Files;

use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Format\BackupFormat;
use MGD\Backup\Format\FileRecordWriter;
use MGD\Backup\Format\PartWriter;
use MGD\Backup\Paths;

/**
 * Schreibt Datei-Teile: liest den Index (files.idx) ab einer Byte-Position und kopiert die Dateien als Segmente in den
 * (verschluesselten) Teil. Eine Datei darf ueber mehrere Teile laufen. Am Dateiende steht die SHA-256-Summe der ganzen Datei;
 * sie wird beim Wiederaufnehmen aus dem schon geschriebenen Praefix neu berechnet (kein Hash-Zustand im Zustand).
 * Fehlende (oder inzwischen als Symlink/Nicht-Datei vorgefundene) Dateien zaehlen als skipped_missing; wurde eine Datei
 * mitten im Teil veraendert oder gekuerzt, bricht der Lauf mit file_changed ab.
 *
 * Zustand: pos (Byte-Offset der aktuellen Indexzeile), offset (schon geschriebene Bytes dieser Datei), areas (Bereich =>
 * count/bytes), skipped_missing, files, bytes.
 */
final class Packer
{
    private const SEGMENT_BYTES = 1048576;

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @param list<string> $areaNames
     * @return array<string,mixed>
     */
    public function initialState(array $areaNames = []): array
    {
        $areas = ['uploads' => ['count' => 0, 'bytes' => 0], 'erp' => ['count' => 0, 'bytes' => 0]];

        foreach ($areaNames as $name) {
            $areas[$name] = ['count' => 0, 'bytes' => 0];
        }

        return ['pos' => 0, 'offset' => 0, 'areas' => $areas, 'skipped_missing' => 0, 'files' => 0, 'bytes' => 0];
    }

    /**
     * @param array<string,string> $areas Bereich => absoluter Ordner
     * @param array<string,mixed> $state
     * @return array{state:array<string,mixed>,done:bool}
     */
    public function writeNextPart(PartWriter $writer, string $indexPath, array $areas, array $state, Budget $budget, int $limitBytes): array
    {
        $records = new FileRecordWriter($writer->write(...));
        $reached = static fn (): bool => $budget->exhausted() || $writer->bytesWritten() >= $limitBytes;

        $index = @fopen($this->paths->assertInside($indexPath), 'rb');

        if ($index === false) {
            throw new BackupException('io_error');
        }

        $done = false;

        try {
            fseek($index, (int) $state['pos']);
            $first = true;

            while (true) {
                if (!$first && $reached()) {
                    break;
                }

                $start = (int) ftell($index);
                $line = fgets($index);

                if ($line === false) {
                    $done = true;
                    break;
                }

                $first = false;
                [$path] = explode("\t", rtrim($line, "\n"), 2);

                if (!BackupFormat::isSafeFilePath($path)) {
                    throw new BackupException('path_invalid');
                }

                if ($this->packFile($path, $areas, $records, $state, $reached)) {
                    $state['pos'] = (int) ftell($index);
                    $state['offset'] = 0;
                } else {
                    $state['pos'] = $start; // dieselbe Zeile im naechsten Teil fortsetzen (offset ist gesetzt)
                }
            }
        } finally {
            fclose($index);
        }

        if (!$done && (int) $state['offset'] === 0) {
            clearstatcache(true, $indexPath);
            $done = (int) $state['pos'] >= (int) filesize($indexPath);
        }

        return ['state' => $state, 'done' => $done];
    }

    /**
     * Packt eine Datei ab $state['offset']. true = vollstaendig geschrieben (oder uebersprungen), false = im naechsten Teil fortsetzen.
     *
     * @param array<string,string> $areas
     * @param array<string,mixed> $state
     * @param callable():bool $reached
     */
    private function packFile(string $path, array $areas, FileRecordWriter $records, array &$state, callable $reached): bool
    {
        [$areaName, $rest] = explode('/', $path, 2);
        $base = $areas[$areaName] ?? null;

        if ($base === null) {
            throw new BackupException('path_invalid');
        }

        $full = $base . '/' . $rest;
        $offset = (int) $state['offset'];
        $handle = (!is_link($full) && is_file($full)) ? @fopen($full, 'rb') : false;

        if ($handle === false) {
            if ($offset > 0) {
                throw new BackupException('file_changed'); // mitten in der Datei verschwunden
            }

            $state['skipped_missing']++;

            return true;
        }

        try {
            $stat = fstat($handle);
            $total = $stat === false ? -1 : (int) $stat['size'];

            if ($total < 0 || $offset > $total) {
                throw new BackupException('file_changed');
            }

            $hash = hash_init('sha256');

            for ($read = 0; $read < $offset;) {
                $chunk = fread($handle, min(self::SEGMENT_BYTES, $offset - $read));

                if ($chunk === false || $chunk === '') {
                    throw new BackupException('file_changed');
                }

                hash_update($hash, $chunk);
                $read += strlen($chunk);
            }

            if ($total === 0) {
                $records->segment($path, 0, 0, '');
            }

            while ($offset < $total) {
                $chunk = fread($handle, min(self::SEGMENT_BYTES, $total - $offset));

                if ($chunk === false || $chunk === '') {
                    throw new BackupException('file_changed');
                }

                $records->segment($path, $total, $offset, $chunk);
                hash_update($hash, $chunk);
                $offset += strlen($chunk);

                if ($offset < $total && $reached()) {
                    $state['offset'] = $offset;

                    return false;
                }
            }

            $records->end($path, hash_final($hash, true));
        } finally {
            fclose($handle);
        }

        $state['areas'][$areaName]['count'] = ($state['areas'][$areaName]['count'] ?? 0) + 1;
        $state['areas'][$areaName]['bytes'] = ($state['areas'][$areaName]['bytes'] ?? 0) + $total;
        $state['files']++;
        $state['bytes'] += $total;

        return true;
    }
}
