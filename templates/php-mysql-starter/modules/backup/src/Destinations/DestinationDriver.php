<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

use MGD\Backup\Budget;

/**
 * Schnittstelle eines Sicherungsziels. Alle Methoden werfen bei Fehlern eine BackupException mit Katalogcode; der Runner
 * uebernimmt davon nur die Kategorie (Failure::category), nie den Text. Treiber duerfen keine Geheimnisse, URLs oder Pfade in
 * Ausnahmetexte schreiben.
 *
 * Weitere Treiber (z. B. aus dem Marketplace) implementieren dieses Interface und werden ueber DriverFactory::register()
 * angemeldet. Im Builder-Repository selbst gibt es nur die Treiber "local" und "ftp" (FTPS/FTP ueber ext-curl).
 */
interface DestinationDriver
{
    /** Verbindung pruefen (Testdatei hochladen, Groesse pruefen, wieder loeschen). */
    public function test(): void;

    /** Legt den Ordner des Laufs am Ziel an und liefert seine Referenz (wird in backup_run_destinations.remote_folder_ref gespeichert). */
    public function beginRun(string $folder): string;

    /**
     * Laedt die lokale Teildatei hoch. Kehrt spaetestens beim Ende des Budgets zurueck: done = fertig (remoteRef gesetzt),
     * paused = fortsetzbar (resumeState fuer den naechsten Aufruf).
     *
     * @param array{resume_state:?string,remote_ref:?string} $upload
     */
    public function upload(array $upload, string $path, Budget $budget): UploadProgress;

    /** Prueft die abgelegte Datei gegen die lokale Groesse; wirft bei Abweichung. */
    public function verify(string $remoteRef, int $cipherBytes): void;

    /** Loescht eine einzelne Datei NUR anhand einer gespeicherten Referenz. */
    public function delete(string $ref): void;

    /** Loescht den Ordner eines Laufs NUR anhand einer gespeicherten Referenz (nur leer, nie rekursiv). */
    public function deleteFolder(string $ref): void;
}
