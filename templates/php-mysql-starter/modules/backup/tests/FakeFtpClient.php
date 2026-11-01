<?php

declare(strict_types=1);

use MGD\Backup\BackupException;
use MGD\Backup\Destinations\Ftp\FtpClient;

/** Server im Speicher: Verzeichnisse und Dateien als Arrays, mit einstellbaren Fehlern. Kein Netzwerk. */
final class FakeFtpClient implements FtpClient
{
    /** @var array<string,true> */
    public array $dirs = [];

    /** @var array<string,string> Pfad => Inhalt */
    public array $files = [];

    /** @var list<string> Protokoll der Aufrufe (Methode Pfad) */
    public array $log = [];

    /** Zahl der noch scheiternden uploadChunk()-Aufrufe (danach laeuft alles). */
    public int $failUploads = 0;

    /** Nach so vielen erfolgreichen Abschnitten scheitert der naechste Aufruf einmal (0 = aus). */
    public int $failAfterChunks = 0;

    public bool $failConnect = false;

    public bool $failDelete = false;

    public int $chunks = 0;

    public function connect(): void
    {
        $this->log[] = 'connect';

        if ($this->failConnect) {
            throw new BackupException('connection_failed');
        }
    }

    public function ensureDir(string $path): void
    {
        $this->log[] = 'mkdir ' . $path;
        $current = '';

        foreach (array_filter(explode('/', $path), static fn (string $s): bool => $s !== '') as $segment) {
            $current .= '/' . $segment;
            $this->dirs[$current] = true;
        }
    }

    public function uploadChunk(string $localPath, string $remotePath, int $offset, int $maxBytes): int
    {
        $this->log[] = 'put ' . $remotePath . '@' . $offset;

        if ($this->failUploads > 0) {
            $this->failUploads--;

            throw new BackupException('upload_failed');
        }

        if ($this->failAfterChunks > 0 && $this->chunks >= $this->failAfterChunks) {
            $this->failAfterChunks = 0;

            throw new BackupException('connection_failed');
        }

        $data = (string) file_get_contents($localPath, false, null, $offset, $maxBytes);

        if ($offset === 0) {
            $this->files[$remotePath] = $data;
        } else {
            if (strlen($this->files[$remotePath] ?? '') !== $offset) {
                throw new BackupException('upload_failed');
            }

            $this->files[$remotePath] .= $data;
        }

        $this->chunks++;

        return $offset + strlen($data);
    }

    public function size(string $remotePath): ?int
    {
        return isset($this->files[$remotePath]) ? strlen($this->files[$remotePath]) : null;
    }

    public function delete(string $remotePath): void
    {
        $this->log[] = 'dele ' . $remotePath;

        if ($this->failDelete) {
            throw new BackupException('remote_delete_failed');
        }

        unset($this->files[$remotePath]);
    }

    public function deleteDir(string $path): void
    {
        $this->log[] = 'rmd ' . $path;

        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, $path . '/')) {
                throw new BackupException('remote_delete_failed'); // nie rekursiv
            }
        }

        unset($this->dirs[$path]);
    }
}
