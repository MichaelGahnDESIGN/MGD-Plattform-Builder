<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use HashContext;

/**
 * Setzt Datensaetze des Teils "files" (FileRecordReader) wieder zu Dateien zusammen und prueft sie:
 * Segmente muessen lueckenlos bei Offset 0 beginnen und aufeinander folgen, die Gesamtgroesse muss gleich bleiben,
 * das Ende muss die Groesse erreichen und die SHA-256-Summe muss stimmen.
 *
 * Mit $outDir werden die (unveraendert uebernommenen, weiterhin MGDF1-verschluesselten) Dateien unter
 * <outDir>/<pfad> geschrieben (Ordner 0700, Dateien 0600, nie ueberschreibend); ohne $outDir wird nur geprueft.
 * Der Aufrufer reicht die Teile in aufsteigender Teilnummer ein (eine Datei darf ueber Teile laufen).
 */
final class FileRecordAssembler
{
    /** @var array<string, array{total:int, written:int, hash:HashContext}> */
    private array $open = [];

    /** @var array<string, true> */
    private array $done = [];

    private int $bytes = 0;

    /** @var array<string,int> */
    private array $areaCounts = [];

    /** @var array<string,int> */
    private array $areaBytes = [];

    /** @var resource|null */
    private $handle;

    private ?string $handlePath = null;

    public function __construct(private readonly ?string $outDir = null)
    {
        if ($outDir !== null && (! is_dir($outDir) || is_link($outDir))) {
            throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
        }
    }

    /** @param  array<string,mixed>  $record  Ergebnis von FileRecordReader::records() */
    public function accept(array $record): void
    {
        $path = $record['path'] ?? null;
        if (! is_string($path) || ! BackupFormat::isSafeFilePath($path) || ! isset($record['type'])) {
            throw new BackupFormatException(BackupFormatException::BAD_PATH);
        }
        if (isset($this->done[$path])) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }

        if ($record['type'] === 1) {
            $this->segment($path, (int) $record['total'], (int) $record['offset'], (string) $record['data']);
        } elseif ($record['type'] === 2) {
            $this->end($path, (string) $record['sha256']);
        } else {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }
    }

    /**
     * Schliesst ab. Ohne $allowOpen muss jede Datei beendet sein.
     *
     * @return array{files:int, bytes:int, areas:array<string,array{count:int,bytes:int}>}
     */
    public function finish(bool $allowOpen = false): array
    {
        $this->closeHandle();
        if (! $allowOpen && $this->open !== []) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }

        $areas = [];
        foreach ($this->areaCounts as $area => $count) {
            $areas[$area] = ['count' => $count, 'bytes' => $this->areaBytes[$area]];
        }

        return ['files' => count($this->done), 'bytes' => $this->bytes, 'areas' => $areas];
    }

    /** @return list<string> noch nicht beendete Dateien */
    public function openPaths(): array
    {
        return array_map('strval', array_keys($this->open));
    }

    private function segment(string $path, int $total, int $offset, string $data): void
    {
        if (! isset($this->open[$path])) {
            if ($offset !== 0) {
                throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
            }
            $this->open[$path] = ['total' => $total, 'written' => 0, 'hash' => hash_init('sha256')];
            if ($this->outDir !== null) {
                $this->createFile($path);
            }
        }
        $state = &$this->open[$path];
        if ($state['total'] !== $total || $state['written'] !== $offset || $offset + strlen($data) > $total) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }

        if ($data !== '') {
            if ($this->outDir !== null) {
                $this->append($path, $data);
            }
            hash_update($state['hash'], $data);
            $state['written'] += strlen($data);
        }
    }

    private function end(string $path, string $expectedHex): void
    {
        $state = $this->open[$path] ?? null;
        if ($state === null || $state['written'] !== $state['total']
            || preg_match('/^[0-9a-f]{64}$/D', $expectedHex) !== 1
            || ! hash_equals($expectedHex, hash_final($state['hash']))) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }
        if ($this->handlePath === $path) {
            $this->closeHandle();
        }
        unset($this->open[$path]);
        $this->done[$path] = true;
        $this->bytes += $state['total'];

        $area = explode('/', $path, 2)[0];
        $this->areaCounts[$area] = ($this->areaCounts[$area] ?? 0) + 1;
        $this->areaBytes[$area] = ($this->areaBytes[$area] ?? 0) + $state['total'];
    }

    /** Legt Ordner (0700) und die leere Datei (0600, exklusiv) an. */
    private function createFile(string $path): void
    {
        $dir = (string) $this->outDir;
        $parts = explode('/', $path);
        array_pop($parts);
        $old = umask(0077);
        try {
            foreach ($parts as $part) {
                $dir .= '/'.$part;
                if (is_link($dir) || (file_exists($dir) && ! is_dir($dir))) {
                    throw new BackupFormatException(BackupFormatException::BAD_PATH);
                }
                if (! is_dir($dir) && ! @mkdir($dir, 0700) && ! is_dir($dir)) {
                    throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
                }
            }
            $handle = @fopen($this->outDir.'/'.$path, 'xb');
            if ($handle === false) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            fclose($handle);
        } finally {
            umask($old);
        }
    }

    private function append(string $path, string $data): void
    {
        if ($this->handlePath !== $path) {
            $this->closeHandle();
            $handle = @fopen($this->outDir.'/'.$path, 'ab');
            if ($handle === false) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            $this->handle = $handle;
            $this->handlePath = $path;
        }
        $len = strlen($data);
        for ($written = 0; $written < $len;) {
            $n = fwrite($this->handle, $written === 0 ? $data : substr($data, $written));
            if ($n === false || $n === 0) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            $written += $n;
        }
    }

    private function closeHandle(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        $this->handle = null;
        $this->handlePath = null;
    }

    public function __destruct()
    {
        $this->closeHandle();
    }
}
