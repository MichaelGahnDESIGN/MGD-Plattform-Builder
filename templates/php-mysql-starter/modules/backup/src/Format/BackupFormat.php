<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

/**
 * Konstanten des Backup-Formats MGDB1 (ADR-0033). Keine Laravel-Abhaengigkeit: nur PHP, ext-sodium, ext-zlib.
 *
 * Teildatei: Kennung | Version | Teilart | Run-UUID | Teilnummer (BE) | Empfaengerzahl | je Empfaenger
 * [8 Byte Fingerabdruck][80 Byte crypto_box_seal(K, pk)] | secretstream-Header | Bloecke [u32 Laenge][Chiffre].
 */
final class BackupFormat
{
    public const MAGIC = 'MGDB1';

    public const VERSION = 1;

    public const KIND_MANIFEST = 1;

    public const KIND_DB = 2;

    public const KIND_FILES = 3;

    public const KIND_KEYS = 4;

    /** Teilart (Zahl) => Name wie im Dateinamen und in backup_parts.kind. */
    public const KIND_NAMES = [
        self::KIND_MANIFEST => 'manifest',
        self::KIND_DB => 'db',
        self::KIND_FILES => 'files',
        self::KIND_KEYS => 'keys',
    ];

    /** Klartextblock; Teil des Dateiformats, nicht konfigurierbar. */
    public const BLOCK_SIZE = 65536;

    /** Maximale Zahl der Empfaenger je Teil (Haupt- und Notfallschluessel, Reserve). */
    public const MAX_RECIPIENTS = 4;

    public const RUN_UUID_BYTES = 16;

    /** Kennung 5 + Version 1 + Teilart 1 + Run-UUID 16 + Teilnummer 4 + Empfaengerzahl 1. */
    public const FIXED_HEAD_BYTES = 28;

    public const FINGERPRINT_BYTES = 8;

    public const SEALED_KEY_BYTES = 80; // 32 Byte Schluessel + 48 Byte crypto_box_seal-Overhead

    public const SECRET_KEY_BYTES = 32;

    public const PUBLIC_KEY_BYTES = 32;

    /** Authentifizierungs-Overhead je secretstream-Block. */
    public const BLOCK_OVERHEAD = 17;

    /** Obergrenze einer NDJSON-Zeile im DB-Strom (Schutz vor Speicherueberlauf beim Lesen). */
    public const MAX_DB_LINE_BYTES = 134217728;

    /** Obergrenze eines Datei-Segments (Schutz beim Lesen und Schreiben). */
    public const MAX_SEGMENT_BYTES = 4194304;

    /** Obergrenze des Klartexts der Teile manifest und keys beim Lesen. */
    public const MAX_SMALL_PART_BYTES = 16777216;

    /**
     * Pfade im Datei-Teil (Zip-Slip-Schutz): <bereich>/<segment>[/<segment>...] mit festem Zeichensatz.
     * Das ist eine Obermenge der Pfadregel des MGD-Support-Hubs (uploads|erp)/<zahl>/<zahl>/<uuid>: Dort erzeugte Pfade bleiben gueltig.
     */
    public const FILE_PATH_REGEX = '#^[a-z0-9_-]{1,32}(?:/[A-Za-z0-9._-]{1,255})+$#D';

    /** Bereichsname im Manifest (files.areas) und erstes Pfadsegment. */
    public const AREA_NAME_REGEX = '/^[a-z0-9_-]{1,32}$/D';

    /** Ordnername: mgdbk_<JJJJMMTT>T<HHMMSS>Z_<kind>_<uuid8>. */
    public const FOLDER_NAME_REGEX = '/^mgdbk_\d{8}T\d{6}Z_(db|full)_[0-9a-f]{8}$/D';

    /** Dateiname: <ordner>_<seq3>_<teilart>.mgdb. */
    public const PART_NAME_REGEX = '/^mgdbk_\d{8}T\d{6}Z_(db|full)_[0-9a-f]{8}_\d{3}_(manifest|db|files|keys)\.mgdb$/D';

    /** Felder des Teils keys (JSON-Objekt). */
    public const KEYS_FIELDS = ['APP_KEY', 'APP_PREVIOUS_KEYS', 'UPLOADS_KEY', 'UPLOADS_PREVIOUS_KEYS'];

    private function __construct() {}

    public static function isKnownKind(int $kind): bool
    {
        return isset(self::KIND_NAMES[$kind]);
    }

    /** Fingerabdruck eines oeffentlichen Schluessels: erste 8 Byte von SHA-256 als 16 Hex-Zeichen. */
    public static function fingerprint(string $publicKeyRaw): string
    {
        return bin2hex(self::fingerprintRaw($publicKeyRaw));
    }

    /** Wie fingerprint(), aber binaer (8 Byte), wie im Dateikopf. */
    public static function fingerprintRaw(string $publicKeyRaw): string
    {
        return substr(hash('sha256', $publicKeyRaw, true), 0, self::FINGERPRINT_BYTES);
    }

    /** Teilname (z. B. 'db') zu Teilart (Zahl) oder null. */
    public static function kindFromName(string $name): ?int
    {
        $kind = array_search($name, self::KIND_NAMES, true);

        return $kind === false ? null : (int) $kind;
    }

    /** Ist der Pfad ein erlaubter Datei-Teil-Pfad (Regex, kein '..', kein absoluter Pfad, kein NUL, kein Backslash)? */
    public static function isSafeFilePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 255) {
            return false;
        }
        if (str_contains($path, "\0") || str_contains($path, '..') || str_contains($path, '\\') || $path[0] === '/') {
            return false;
        }

        if (preg_match(self::FILE_PATH_REGEX, $path) !== 1) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
