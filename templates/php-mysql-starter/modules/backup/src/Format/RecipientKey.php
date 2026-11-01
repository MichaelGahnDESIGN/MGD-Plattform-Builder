<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

/**
 * Oeffentlicher Empfaengerschluessel (X25519, 32 Byte).
 * Textformat: mgdbk-pk1:<base64url(32 Byte, ohne Padding)>:<8 Hex Pruefsumme>
 * Pruefsumme = erste 4 Byte von SHA-256 des rohen Schluessels. Fingerabdruck = erste 8 Byte (16 Hex) von SHA-256(pk).
 */
final class RecipientKey
{
    public const PREFIX = 'mgdbk-pk1';

    private function __construct(private readonly string $raw) {}

    public static function fromRaw(string $raw): self
    {
        if (strlen($raw) !== BackupFormat::PUBLIC_KEY_BYTES) {
            throw new BackupFormatException(BackupFormatException::RECIPIENT_KEY_FORMAT);
        }

        return new self($raw);
    }

    public static function parse(string $text): self
    {
        $text = trim($text);
        if (preg_match('/^'.self::PREFIX.':([A-Za-z0-9_-]{43}):([0-9a-fA-F]{8})$/D', $text, $m) !== 1) {
            throw new BackupFormatException(BackupFormatException::RECIPIENT_KEY_FORMAT);
        }

        $raw = base64_decode(strtr($m[1], '-_', '+/').'=', true);
        // Strikte, kanonische Kodierung: die Rueckkodierung muss den Text exakt ergeben.
        if ($raw === false || strlen($raw) !== BackupFormat::PUBLIC_KEY_BYTES || self::b64url($raw) !== $m[1]) {
            throw new BackupFormatException(BackupFormatException::RECIPIENT_KEY_FORMAT);
        }
        if (! hash_equals(self::checksum($raw), strtolower($m[2]))) {
            throw new BackupFormatException(BackupFormatException::RECIPIENT_KEY_CHECKSUM);
        }

        return new self($raw);
    }

    public function raw(): string
    {
        return $this->raw;
    }

    /** 16 Hex-Zeichen. */
    public function fingerprint(): string
    {
        return BackupFormat::fingerprint($this->raw);
    }

    public function toText(): string
    {
        return self::PREFIX.':'.self::b64url($this->raw).':'.self::checksum($this->raw);
    }

    private static function checksum(string $raw): string
    {
        return bin2hex(substr(hash('sha256', $raw, true), 0, 4));
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
