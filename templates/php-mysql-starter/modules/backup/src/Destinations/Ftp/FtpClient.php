<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations\Ftp;

/**
 * Schmale Schnittstelle zum FTPS-Server. Alle Pfade sind absolut ("/a/b"); alle Methoden werfen bei Fehlern eine
 * BackupException mit Katalogcode (nie Roh-Text, URL oder Passwort). Die einzige echte Umsetzung ist CurlFtpClient;
 * Tests setzen einen Fake ein.
 */
interface FtpClient
{
    /** Verbindung aufbauen, TLS aushandeln und anmelden (wirft bei Fehlern). */
    public function connect(): void;

    /** Legt das Verzeichnis samt fehlender Elternverzeichnisse an (existiert es schon, passiert nichts). */
    public function ensureDir(string $path): void;

    /**
     * Sendet hoechstens $maxBytes der lokalen Datei ab $offset. Bei $offset = 0 wird die Zieldatei neu angelegt (ersetzt),
     * sonst angehaengt (APPE). Liefert den neuen Stand (Offset nach dem Abschnitt).
     */
    public function uploadChunk(string $localPath, string $remotePath, int $offset, int $maxBytes): int;

    /** Groesse der Datei am Ziel (SIZE) oder null, wenn sie nicht existiert. */
    public function size(string $remotePath): ?int;

    /** Loescht eine Datei; eine bereits fehlende Datei ist kein Fehler. */
    public function delete(string $remotePath): void;

    /** Loescht ein (leeres) Verzeichnis; ein bereits fehlendes ist kein Fehler. Nie rekursiv. */
    public function deleteDir(string $path): void;
}
