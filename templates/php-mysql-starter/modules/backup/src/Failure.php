<?php

declare(strict_types=1);

namespace MGD\Backup;

use MGD\Backup\Format\BackupFormatException;
use Throwable;

/**
 * Katalog der Fehlerkategorien. Es werden ausschliesslich diese Codes gespeichert und angezeigt, nie Roh-Texte von
 * Servern, curl oder PHP (dort koennten Pfade, Hostnamen oder Zugangsdaten stehen).
 */
final class Failure
{
    public const CODES = [
        'io_error', 'path_invalid', 'verify_failed', 'database_error', 'insufficient_space', 'no_destination_enabled',
        'key_missing', 'key_invalid', 'backup_already_active', 'cancelled', 'timeout_run', 'signing_key_unreadable', 'upload_failed', 'remote_delete_failed', 'auth_failed', 'tls_failed',
        'connection_failed', 'timeout', 'host_blocked', 'port_not_allowed', 'destination_unsupported', 'file_changed',
        'format_error', 'internal_error',
    ];

    private function __construct() {}

    /** Katalogcode zu einer beliebigen Ausnahme (nie der Text der Ausnahme). */
    public static function category(Throwable $e): string
    {
        if ($e instanceof BackupException) {
            return in_array($e->errorCode, self::CODES, true) ? $e->errorCode : 'internal_error';
        }
        if ($e instanceof BackupFormatException) {
            return $e->errorCode === BackupFormatException::WRITE_FAILED || $e->errorCode === BackupFormatException::OPEN_FAILED
                ? 'io_error'
                : 'format_error';
        }

        return 'internal_error';
    }

    public static function isKnown(?string $code): bool
    {
        return $code !== null && in_array($code, self::CODES, true);
    }

    /** CURLE_*-Nummer zu Katalogcode. Der curl-Text wird bewusst nicht ausgewertet. */
    public static function fromCurl(int $errno): string
    {
        return match (true) {
            in_array($errno, [6, 7, 52, 56], true) => 'connection_failed',
            $errno === 28 => 'timeout',
            in_array($errno, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91], true) => 'tls_failed',
            in_array($errno, [67, 9], true) => $errno === 67 ? 'auth_failed' : 'path_invalid',
            default => 'upload_failed',
        };
    }
}
