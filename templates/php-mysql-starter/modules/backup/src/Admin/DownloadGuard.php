<?php

declare(strict_types=1);

namespace MGD\Backup\Admin;

use MGD\Backup\BackupException;
use MGD\Backup\Paths;

/**
 * Entscheidet, welche Datei der authentifizierte Download ausliefern darf. Ausgeliefert wird nur eine Teildatei, die
 *   - zu einem fertigen Lauf (completed/partial) gehoert,
 *   - in der Datenbank als lokal vorhandener Teil (status ready) steht und deren Lauf ein lokales Ziel im Status done hat,
 *   - einen Namen nach Whitelist-Regex hat (nie aus der Anfrage uebernommen: die Anfrage nennt nur Lauf-Nr. und Teilnummer),
 *   - unter dem konfigurierten Backup-Ordner liegt (Paths::assertInside: kein Symlink, kein ".."),
 *   - und deren Groesse zur Datenbank passt.
 * Die Antwort bekommt Header gegen Sniffing und Caching.
 */
final class DownloadGuard
{
    public function __construct(private readonly Services $services)
    {
    }

    /** Gibt es fuer den Lauf ein lokales Ziel im Status done (und der Lauf ist fertig)? */
    public function localAvailable(int $runId): bool
    {
        $store = $this->services->store;
        $run = $store->run($runId);

        if ($run === null || !in_array((string) $run['status'], ['completed', 'partial'], true) || $run['local_deleted_at'] !== null) {
            return false;
        }

        foreach ($store->runDestinations($runId) as $rd) {
            $destination = $store->destination((int) $rd['destination_id']);

            if ($destination !== null && $destination['dest_type'] === 'local' && $rd['status'] === 'done') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $seq Teilnummer aus der Anfrage (nur Ziffern erlaubt)
     * @return array{path:string,name:string,size:int}|null null = nicht auslieferbar (der Aufrufer antwortet 404)
     */
    public function resolve(int $runId, string $seq): ?array
    {
        if ($runId < 1 || preg_match('/^\d{1,6}$/D', $seq) !== 1 || !$this->localAvailable($runId)) {
            return null;
        }

        $store = $this->services->store;
        $run = $store->run($runId);
        $part = $store->part($runId, (int) $seq);

        if ($run === null || $part === null || $part['status'] !== 'ready') {
            return null;
        }

        $name = (string) $part['file_name'];
        $folder = (string) $run['folder_name'];

        try {
            if (!Paths::isPartName($name) || !str_starts_with($name, $folder . '_')) {
                return null;
            }

            $path = $this->services->paths->assertInside($this->services->paths->partPath($folder, $name));
        } catch (BackupException) {
            return null;
        }

        if (!is_file($path) || is_link($path) || (int) filesize($path) !== (int) $part['cipher_bytes']) {
            return null;
        }

        return ['path' => $path, 'name' => $name, 'size' => (int) $part['cipher_bytes']];
    }

    /**
     * Antwort-Header. Der Dateiname stammt aus der Whitelist-Regex und braucht keine Maskierung.
     *
     * @return array<string,string>
     */
    public static function headers(string $name, int $size): array
    {
        return [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length' => (string) $size,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ];
    }
}
