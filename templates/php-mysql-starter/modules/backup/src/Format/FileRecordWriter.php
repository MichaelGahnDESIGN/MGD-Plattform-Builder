<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use Closure;

/**
 * Schreibt den Klartext des Teils "files": Datensaetze, die die MGDF1-Chiffredateien unveraendert transportieren.
 *   typ=1 Segment: [u8 1][u16 pfadlen][pfad][u64 gesamt][u64 offset][u32 seglen][bytes]
 *   typ=2 Ende:    [u8 2][u16 pfadlen][pfad][32 Byte sha256 der ganzen Datei]
 * Alle Zahlen Big Endian. Eine Datei darf ueber mehrere Segmente und mehrere Teile laufen; jede Datei beginnt mit
 * einem Segment (auch eine leere Datei: seglen 0) und endet mit genau einem Ende-Datensatz.
 * Pfade muessen BackupFormat::FILE_PATH_REGEX entsprechen (Zip-Slip-Schutz), auch beim Schreiben.
 */
final class FileRecordWriter
{
    /** @param  Closure(string):void  $sink  z. B. PartWriter::write(...) */
    public function __construct(private readonly Closure $sink) {}

    public function segment(string $path, int $total, int $offset, string $bytes): void
    {
        $length = strlen($bytes);
        if (! BackupFormat::isSafeFilePath($path)) {
            throw new BackupFormatException(BackupFormatException::BAD_PATH);
        }
        if ($total < 0 || $offset < 0 || $length > BackupFormat::MAX_SEGMENT_BYTES || $offset + $length > $total) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }
        ($this->sink)(chr(1).pack('n', strlen($path)).$path.pack('J', $total).pack('J', $offset).pack('N', $length));
        if ($length > 0) {
            ($this->sink)($bytes);
        }
    }

    /** @param  string  $sha256Raw  32 Byte binaer */
    public function end(string $path, string $sha256Raw): void
    {
        if (! BackupFormat::isSafeFilePath($path)) {
            throw new BackupFormatException(BackupFormatException::BAD_PATH);
        }
        if (strlen($sha256Raw) !== 32) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }
        ($this->sink)(chr(2).pack('n', strlen($path)).$path.$sha256Raw);
    }
}
