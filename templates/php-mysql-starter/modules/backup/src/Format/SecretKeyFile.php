<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use Throwable;

/**
 * Datei mit dem privaten Empfaengerschluessel (X25519, 32 Byte), Rechte 0600.
 *
 * Textformat (eine Zeile):
 *   mgdbk-sk1:plain:<base64url(32)>
 *   mgdbk-sk1:pw:<base64url(Blob)>   Blob = ops (u32 BE) | mem (u32 BE) | Salt (16) | Nonce (24) | secretbox(sk)
 * Passphrase: sodium_crypto_pwhash (Argon2id) und sodium_crypto_secretbox. Geschrieben wird mit SENSITIVE (ops 4, 1 GiB); die
 * Parameter stehen im Blob, gelesen werden auch aeltere Dateien (MODERATE) und alle Werte innerhalb der Grenzen.
 * Fehler bei falscher Passphrase tragen keine Details.
 */
final class SecretKeyFile
{
    private const PREFIX = 'mgdbk-sk1';

    private const MAX_FILE_BYTES = 4096;

    private const MAX_OPS = 16;

    private const MAX_MEM = 1073741824;

    private const MIN_MEM = 8192;

    /** Schreibt den Schluessel atomar (Temp-Datei 0600, dann rename); legt den Ordner bei Bedarf mit 0700 an. */
    public static function write(string $path, string $secretKey, ?string $passphrase, ?int $opsLimit = null, ?int $memLimit = null): void
    {
        if (strlen($secretKey) !== BackupFormat::SECRET_KEY_BYTES) {
            throw new BackupFormatException(BackupFormatException::BAD_KEY);
        }

        if ($passphrase === null || $passphrase === '') {
            $line = self::PREFIX.':plain:'.self::b64url($secretKey);
        } else {
            // Standard SENSITIVE; $opsLimit/$memLimit nur fuer schnelle Tests.
            $ops = $opsLimit ?? SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE;
            $mem = $memLimit ?? SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE;
            $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $derived = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $passphrase, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
            $box = sodium_crypto_secretbox($secretKey, $nonce, $derived);
            sodium_memzero($derived);
            $line = self::PREFIX.':pw:'.self::b64url(pack('NN', $ops, $mem).$salt.$nonce.$box);
        }

        $dir = dirname($path);
        $old = umask(0077);
        try {
            if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            $tmp = $path.'.tmp-'.bin2hex(random_bytes(6));
            $h = @fopen($tmp, 'xb');
            if ($h === false) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            $ok = fwrite($h, $line."\n") === strlen($line) + 1;
            $ok = $ok && fflush($h);
            fclose($h);
            if (! $ok || ! @chmod($tmp, 0600) || ! @rename($tmp, $path)) {
                @unlink($tmp);
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            @chmod($path, 0600);
        } finally {
            umask($old);
        }
    }

    /** Liest den Schluessel; die Datei darf fuer Gruppe/Andere nicht lesbar sein. */
    public static function read(string $path, ?string $passphrase): string
    {
        $perms = @fileperms($path);
        if ($perms === false || ! is_file($path)) {
            throw new BackupFormatException(BackupFormatException::OPEN_FAILED);
        }
        if (($perms & 0077) !== 0) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_PERMISSIONS);
        }
        $content = @file_get_contents($path, false, null, 0, self::MAX_FILE_BYTES + 1);
        if ($content === false || strlen($content) > self::MAX_FILE_BYTES) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
        }

        if (preg_match('/^'.self::PREFIX.':(plain|pw):([A-Za-z0-9_-]+)\s*$/D', $content, $m) !== 1) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
        }
        $blob = self::unb64url($m[2]);
        if ($blob === null) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
        }

        if ($m[1] === 'plain') {
            if (strlen($blob) !== BackupFormat::SECRET_KEY_BYTES) {
                throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
            }

            return $blob;
        }

        if ($passphrase === null || $passphrase === '') {
            throw new BackupFormatException(BackupFormatException::PASSPHRASE_REQUIRED);
        }
        $saltLen = SODIUM_CRYPTO_PWHASH_SALTBYTES;
        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($blob) !== 8 + $saltLen + $nonceLen + BackupFormat::SECRET_KEY_BYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
        }
        $params = unpack('Nops/Nmem', substr($blob, 0, 8));
        if ($params === false || $params['ops'] < 1 || $params['ops'] > self::MAX_OPS || $params['mem'] < self::MIN_MEM || $params['mem'] > self::MAX_MEM) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
        }
        $salt = substr($blob, 8, $saltLen);
        $nonce = substr($blob, 8 + $saltLen, $nonceLen);
        $box = substr($blob, 8 + $saltLen + $nonceLen);

        try {
            $derived = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $passphrase, $salt, $params['ops'], $params['mem'], SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        } catch (Throwable) {
            throw new BackupFormatException(BackupFormatException::KEY_FILE_FORMAT);
        }
        $secret = sodium_crypto_secretbox_open($box, $nonce, $derived);
        sodium_memzero($derived);
        if ($secret === false || strlen($secret) !== BackupFormat::SECRET_KEY_BYTES) {
            throw new BackupFormatException(BackupFormatException::BAD_PASSPHRASE);
        }

        return $secret;
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64url(string $text): ?string
    {
        $padded = strtr($text, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $raw = base64_decode($padded, true);

        return $raw === false ? null : $raw;
    }
}
