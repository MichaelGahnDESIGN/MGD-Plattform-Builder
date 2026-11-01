<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

use MGD\Backup\BackupException;
use MGD\Backup\Budget;
use MGD\Backup\Destinations\Ftp\FtpClient;
use MGD\Backup\Paths;
use Throwable;

/**
 * FTPS-Ziel. Die Verbindung (TLS, HostPolicy, Ports) steckt im FtpClient; dieser Treiber kennt nur die Ablage:
 *
 *   <base_path>/<ordner des Laufs>/<teildatei>      (Namen nach BackupFormat::FOLDER_NAME_REGEX / PART_NAME_REGEX)
 *
 * Upload in Abschnitten innerhalb des Zeitbudgets; der Stand (Offset) steht im resume_state. Nach dem letzten Abschnitt
 * wird die Groesse am Ziel (SIZE) gegen die lokale Chiffregroesse geprueft.
 *
 * Loeschen nur fuer gespeicherte Referenzen, und nur wenn die Referenz GENAU aus base_path + gueltigem Ordnernamen
 * (+ gueltigem Teilnamen, beide mit Praefix mgdbk_) besteht. Es gibt kein Listing und kein Muster-Loeschen; Ordner werden
 * nur leer entfernt (nie rekursiv).
 */
final class FtpDriver implements DestinationDriver, PredictsPartRef
{
    /** Abschnitte, die nacheinander ohne Fortschritt scheitern duerfen, bevor der Upload als fehlgeschlagen zaehlt. */
    private const MAX_STALLS = 3;

    /** '' = Wurzel, sonst "/a/b" ohne Schlusszeichen. */
    private readonly string $base;

    public function __construct(string $basePath, private readonly FtpClient $client, private readonly int $chunkBytes = 8388608)
    {
        $this->base = self::cleanBase($basePath);
    }

    /** Normalisiert base_path: absolut, ohne ".", ".." und Steuerzeichen; "/" wird zu ''. */
    public static function cleanBase(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || preg_match('/[\x00-\x1f\x7f"\\\\]/', $path) === 1) {
            throw new BackupException('path_invalid');
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new BackupException('path_invalid');
            }
        }

        return $segments === [] ? '' : '/' . implode('/', $segments);
    }

    public function test(): void
    {
        $this->client->connect();
        $this->ensureBase();

        $name = 'mgdbk_test_' . bin2hex(random_bytes(6)) . '.txt';
        $remote = $this->base . '/' . $name;
        $local = tempnam(sys_get_temp_dir(), 'mgdbk');

        if ($local === false) {
            throw new BackupException('io_error');
        }

        $payload = "mgd backup test\n" . bin2hex(random_bytes(8)) . "\n";

        try {
            if (file_put_contents($local, $payload) !== strlen($payload)) {
                throw new BackupException('io_error');
            }

            $this->client->uploadChunk($local, $remote, 0, strlen($payload));

            if ($this->client->size($remote) !== strlen($payload)) {
                throw new BackupException('verify_failed');
            }

            $this->client->delete($remote);
        } catch (Throwable $e) {
            try {
                $this->client->delete($remote); // die eigene Testdatei nicht liegen lassen
            } catch (Throwable) {
            }

            throw $e;
        } finally {
            @unlink($local);
        }
    }

    public function beginRun(string $folder): string
    {
        if (!Paths::isFolderName($folder)) {
            throw new BackupException('path_invalid');
        }

        $this->ensureBase();
        $path = $this->base . '/' . $folder;
        $this->client->ensureDir($path);

        return $path;
    }

    public function upload(array $upload, string $path, Budget $budget): UploadProgress
    {
        if (!is_file($path) || is_link($path)) {
            throw new BackupException('io_error');
        }

        $total = (int) filesize($path);
        $remote = $this->partRef(basename($path));
        $chunk = max(1, $this->chunkBytes);

        [$offset, $stalls] = $this->resumePoint($upload['resume_state'] ?? null, $remote, $total);
        $first = true;

        while ($first || ($offset < $total && !$budget->exhausted())) {
            $first = false;

            try {
                $offset = $this->client->uploadChunk($path, $remote, $offset, $chunk);
                $stalls = 0;
            } catch (Throwable $e) {
                // Mit Fortschritt (jetzt oder frueher) bleibt der Stand erhalten; erst wiederholte Fehlschlaege ohne
                // Fortschritt gelten als gescheitert (Runner: Versuch zaehlen, Backoff, neu beginnen).
                if ($offset > 0 && ++$stalls < self::MAX_STALLS) {
                    return UploadProgress::paused($this->state($offset, $total, $stalls), $offset);
                }

                throw $e;
            }
        }

        if ($offset < $total) {
            return UploadProgress::paused($this->state($offset, $total, 0), $offset);
        }

        if ($this->client->size($remote) !== $total) {
            // Die unvollstaendige eigene Datei wegraeumen; der naechste Versuch beginnt bei 0.
            try {
                $this->client->delete($remote);
            } catch (Throwable) {
            }

            throw new BackupException('verify_failed');
        }

        return UploadProgress::done($remote, $total);
    }

    public function verify(string $remoteRef, int $cipherBytes): void
    {
        $ref = $this->assertPartRef($remoteRef);

        if ($this->client->size($ref) !== $cipherBytes) {
            throw new BackupException('verify_failed');
        }
    }

    public function delete(string $ref): void
    {
        $this->client->delete($this->assertPartRef($ref));
    }

    public function deleteFolder(string $ref): void
    {
        $this->client->deleteDir($this->assertFolderRef($ref));
    }

    // ------------------------------------------------------------------ Pfade

    private function ensureBase(): void
    {
        if ($this->base !== '') {
            $this->client->ensureDir($this->base);
        }
    }

    /** Referenz einer Teildatei aus ihrem lokalen Dateinamen (der Ordner ergibt sich aus dem Namen). */
    public function partRef(string $fileName): string
    {
        $folder = Paths::folderOfPart($fileName);

        if ($folder === null || !Paths::isFolderName($folder)) {
            throw new BackupException('path_invalid');
        }

        return $this->base . '/' . $folder . '/' . $fileName;
    }

    /** Eine Dateireferenz gilt nur als <base_path>/<ordner>/<teil> mit gueltigen mgdbk_-Namen. */
    private function assertPartRef(string $ref): string
    {
        $prefix = $this->base . '/';

        if (!str_starts_with($ref, $prefix)) {
            throw new BackupException('path_invalid');
        }

        $parts = explode('/', substr($ref, strlen($prefix)));

        if (count($parts) !== 2 || !Paths::isFolderName($parts[0]) || !Paths::isPartName($parts[1]) || !str_starts_with($parts[1], $parts[0] . '_')) {
            throw new BackupException('path_invalid');
        }

        return $ref;
    }

    /** Eine Ordnerreferenz gilt nur als <base_path>/<ordner> (oder der blosse Ordnername) mit gueltigem mgdbk_-Namen. */
    private function assertFolderRef(string $ref): string
    {
        if (Paths::isFolderName($ref)) {
            return $this->base . '/' . $ref;
        }

        $prefix = $this->base . '/';

        if (!str_starts_with($ref, $prefix) || !Paths::isFolderName(substr($ref, strlen($prefix)))) {
            throw new BackupException('path_invalid');
        }

        return $ref;
    }

    // ------------------------------------------------------------------ Wiederaufnahme

    private function state(int $offset, int $size, int $stalls): string
    {
        return json_encode(['offset' => $offset, 'size' => $size, 'stalls' => $stalls], JSON_THROW_ON_ERROR);
    }

    /**
     * Startpunkt des Uploads: aus dem gespeicherten Stand, aber nur wenn die Datei am Ziel dazu passt (mindestens der
     * gespeicherte Offset, hoechstens die Gesamtgroesse). Sonst von vorn (der erste Abschnitt ersetzt die Zieldatei).
     *
     * @return array{0:int,1:int} Offset und Zahl der bisherigen Fehlschlaege ohne Fortschritt
     */
    private function resumePoint(?string $raw, string $remote, int $total): array
    {
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($state) || !is_int($state['offset'] ?? null) || ($state['size'] ?? null) !== $total
            || $state['offset'] <= 0 || $state['offset'] > $total) {
            return [0, 0];
        }

        $remoteSize = $this->client->size($remote);

        if ($remoteSize === null || $remoteSize < $state['offset'] || $remoteSize > $total) {
            return [0, 0];
        }

        return [$remoteSize, max(0, (int) ($state['stalls'] ?? 0))];
    }
}
