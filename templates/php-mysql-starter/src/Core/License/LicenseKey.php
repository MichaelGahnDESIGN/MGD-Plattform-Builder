<?php

declare(strict_types=1);

namespace MGD\Starter\Core\License;

/**
 * Prüft Lizenzschlüssel im Format "MGD1.<payload>.<signatur>" (Base64url).
 * Die Signatur ist Ed25519 über "MGD1.<payload>". Nur der Lizenzgeber besitzt
 * den privaten Schlüssel; hier liegt ausschließlich der öffentliche Schlüssel.
 */
final class LicenseKey
{
    public const PREFIX = 'MGD1';
    public const PUBLIC_KEY = 'sdAeDUBPX7wKB5RoSu+miN5u8sXZO3A9Tl0B1E1toO8=';
    private const MAX_LENGTH = 4096;

    public function __construct(private readonly string $publicKey = self::PUBLIC_KEY)
    {
    }

    public function parse(string $key): ?LicenseGrant
    {
        $key = trim($key);

        if ($key === '' || strlen($key) > self::MAX_LENGTH || !function_exists('sodium_crypto_sign_verify_detached')) {
            return null;
        }

        $parts = explode('.', $key);

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return null;
        }

        $signature = self::base64UrlDecode($parts[2]);
        $publicKey = base64_decode($this->publicKey, true);

        if ($signature === null || $publicKey === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        if (!sodium_crypto_sign_verify_detached($signature, $parts[0] . '.' . $parts[1], $publicKey)) {
            return null;
        }

        $payload = json_decode((string) self::base64UrlDecode($parts[1]), true);

        return is_array($payload) ? LicenseGrant::fromPayload($payload) : null;
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
