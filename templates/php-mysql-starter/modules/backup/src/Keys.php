<?php

declare(strict_types=1);

namespace MGD\Backup;

use MGD\Backup\Format\ManifestCodec;
use MGD\Backup\Format\RecipientKey;

/**
 * Schluessel des Moduls. Auf dem Server liegen NUR oeffentliche Empfaengerschluessel (Haupt- und optional Notfallschluessel,
 * Textform mgdbk-pk1:...) und der Ed25519-Signaturschluessel des Servers (verschluesselt, siehe Secrets). Der private
 * Empfaengerschluessel entsteht lokal mit `php modules/backup/tools/mgd-backup.php keygen` und beruehrt den Server nie.
 */
final class Keys
{
    public const ROLES = ['primary', 'emergency'];

    public function __construct(private readonly Store $store, private readonly Secrets $secrets)
    {
    }

    /**
     * Empfaenger fuer neue Pakete: Hauptschluessel (Pflicht) und Notfallschluessel (optional).
     *
     * @return list<RecipientKey>
     */
    public function recipients(): array
    {
        $settings = $this->store->settings();
        $recipients = [];

        foreach (['primary_key_text', 'emergency_key_text'] as $column) {
            $text = (string) ($settings[$column] ?? '');

            if ($text !== '') {
                $recipients[] = RecipientKey::parse($text);
            }
        }

        if ($recipients === []) {
            throw new BackupException('key_missing');
        }

        return $recipients;
    }

    /** @return list<string> Fingerabdruecke (16 Hex) der aktuellen Empfaenger */
    public function fingerprints(): array
    {
        $settings = $this->store->settings();

        return array_values(array_filter([(string) ($settings['primary_key_fp'] ?? ''), (string) ($settings['emergency_key_fp'] ?? '')], static fn (string $fp): bool => $fp !== ''));
    }

    /**
     * Setzt einen Empfaengerschluessel aus der Textform. Gleicher Schluessel fuer beide Rollen ist nicht erlaubt.
     *
     * @return string Fingerabdruck
     * @throws \MGD\Backup\Format\BackupFormatException bei falschem Format oder falscher Pruefsumme
     */
    public function setRecipient(string $role, string $text): string
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new BackupException('key_invalid');
        }

        $key = RecipientKey::parse(trim($text));
        $settings = $this->store->settings();
        $otherFp = (string) ($settings[$role === 'primary' ? 'emergency_key_fp' : 'primary_key_fp'] ?? '');

        if ($otherFp !== '' && hash_equals($otherFp, $key->fingerprint())) {
            throw new BackupException('key_invalid');
        }

        $prefix = $role === 'primary' ? 'primary' : 'emergency';
        $this->store->updateSettings([$prefix . '_key_text' => $key->toText(), $prefix . '_key_fp' => $key->fingerprint()]);

        return $key->fingerprint();
    }

    public function removeEmergency(): void
    {
        $this->store->updateSettings(['emergency_key_text' => null, 'emergency_key_fp' => null]);
    }

    /** Erzeugt den Signaturschluessel beim ersten Bedarf. */
    public function ensureSigning(): void
    {
        $settings = $this->store->settings();

        if ((string) ($settings['signing_secret_enc'] ?? '') !== '') {
            $this->signingSecret(); // lesbar?

            return;
        }

        $pair = sodium_crypto_sign_keypair();
        $public = sodium_crypto_sign_publickey($pair);
        $this->store->updateSettings([
            'signing_secret_enc' => $this->secrets->encrypt(sodium_crypto_sign_secretkey($pair)),
            'signing_public' => base64_encode($public),
            'signing_fp' => ManifestCodec::signerFingerprint($public),
        ]);
        sodium_memzero($pair);
    }

    /** 64 Byte Ed25519-Geheimschluessel. @throws BackupException signing_key_unreadable */
    public function signingSecret(): string
    {
        $secret = $this->secrets->decrypt((string) ($this->store->settings()['signing_secret_enc'] ?? ''));

        if (strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new BackupException('signing_key_unreadable');
        }

        return $secret;
    }

    public function signingPublic(): string
    {
        $raw = base64_decode((string) ($this->store->settings()['signing_public'] ?? ''), true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new BackupException('signing_key_unreadable');
        }

        return $raw;
    }

    public function signingFingerprint(): string
    {
        return ManifestCodec::signerFingerprint($this->signingPublic());
    }
}
