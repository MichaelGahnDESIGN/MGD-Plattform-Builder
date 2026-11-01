<?php

declare(strict_types=1);

namespace MGD\Backup;

/**
 * Verschluesselt Zugangsdaten und den Signaturschluessel in der Datenbank (libsodium secretbox). Der Schluessel wird mit
 * einem festen Kontext aus dem app.key der config.php abgeleitet; ohne diesen Wert sind die Spalten wertlos. Das ist
 * keine Ende-zu-Ende-Sicherheit: wer config.php UND die Datenbank hat, kann die Werte lesen. Die Backup-Pakete selbst
 * sind davon unabhaengig (Public-Key-Verschluesselung, der private Schluessel liegt nie auf dem Server).
 */
final class Secrets
{
    private const PREFIX = 'v1:';

    private readonly string $key;

    public function __construct(string $appKey, string $context = 'mgd-backup-module-v1')
    {
        if (strlen($appKey) < 16 || $appKey === 'change-me') {
            throw new BackupException('key_missing');
        }

        $this->key = sodium_crypto_generichash($context . "\0" . $appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    /** @throws BackupException signing_key_unreadable, wenn der Wert nicht (mehr) mit diesem app.key lesbar ist */
    public function decrypt(string $encoded): string
    {
        $raw = str_starts_with($encoded, self::PREFIX) ? base64_decode(substr($encoded, strlen(self::PREFIX)), true) : false;

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new BackupException('signing_key_unreadable');
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key
        );

        if ($plain === false) {
            throw new BackupException('signing_key_unreadable');
        }

        return $plain;
    }
}
