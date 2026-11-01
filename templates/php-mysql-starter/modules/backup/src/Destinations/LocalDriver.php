<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Paths;

/**
 * Lokales Ziel: der lokale Ordner (Paths::root) IST das Ziel. upload() ist ein Nullschritt (der Teil liegt schon da),
 * verify() prueft Groesse. Referenzen sind Ordner- bzw. Dateinamen (nie Pfade); delete()/deleteFolder() pruefen den
 * Namens-Regex und assertInside.
 */
final class LocalDriver implements DestinationDriver
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function test(): void
    {
        $root = $this->paths->ensureRoot();

        if (!is_dir($root) || !is_writable($root)) {
            throw new BackupException('io_error');
        }
    }

    public function beginRun(string $folder): string
    {
        $this->paths->ensureRunDir($folder);

        return $folder;
    }

    public function upload(array $upload, string $path, Budget $budget): UploadProgress
    {
        $path = $this->paths->assertInside($path);

        if (!is_file($path) || is_link($path)) {
            throw new BackupException('io_error');
        }

        return UploadProgress::done(basename($path), (int) filesize($path));
    }

    public function verify(string $remoteRef, int $cipherBytes): void
    {
        $folder = Paths::folderOfPart($remoteRef);

        if ($folder === null) {
            throw new BackupException('path_invalid');
        }

        $path = $this->paths->assertInside($this->paths->partPath($folder, $remoteRef));

        if (!is_file($path) || is_link($path) || (int) filesize($path) !== $cipherBytes) {
            throw new BackupException('verify_failed');
        }
    }

    /** $ref = Dateiname einer Teildatei; der Ordner ergibt sich aus dem Namen. */
    public function delete(string $ref): void
    {
        $folder = Paths::folderOfPart($ref);

        if ($folder === null || !Paths::isFolderName($folder)) {
            throw new BackupException('path_invalid');
        }

        $this->paths->deleteFile($this->paths->partPath($folder, $ref));
    }

    /** $ref = Ordnername des Laufs. */
    public function deleteFolder(string $ref): void
    {
        if (!Paths::isFolderName($ref)) {
            throw new BackupException('path_invalid');
        }

        $this->paths->deleteFolder($ref);
    }
}
