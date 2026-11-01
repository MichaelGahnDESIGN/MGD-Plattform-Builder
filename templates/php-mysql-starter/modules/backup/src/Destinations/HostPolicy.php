<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

use Closure;

/**
 * Schutz vor Server-Side-Request-Forgery: Ein FTPS-Ziel darf nur auf oeffentliche Adressen zeigen (kein localhost, kein
 * Privatnetz, keine Link-Local- oder reservierten Bereiche). Der Name wird einmal aufgeloest; die geprueften Adressen werden
 * vom Client per CURLOPT_RESOLVE festgeschrieben (kein zweiter DNS-Aufruf, kein Rebinding-Fenster).
 *
 * Wer bewusst ein Ziel im eigenen Netz braucht, setzt in config/config.php 'backup' => ['allow_private_hosts' => true]
 * (nur per Dateizugriff, nicht im Backoffice).
 */
final class HostPolicy
{
    /** Test-Schalter: ersetzt die DNS-Aufloesung (Name => Liste von IPs). */
    public static ?Closure $resolver = null;

    public function __construct(private readonly bool $allowPrivate = false)
    {
    }

    /** Normalisiert einen Hostnamen (klein, ohne eckige Klammern und Punkt am Ende). */
    public static function normalize(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }

    /** Ist der Host syntaktisch ein gueltiger Name oder eine IP (ohne Pfad, Port, Zugangsdaten)? */
    public static function isValidHost(string $host): bool
    {
        $host = self::normalize($host);
        $bare = trim($host, '[]');

        if ($bare === '' || strlen($bare) > 253) {
            return false;
        }

        if (filter_var($bare, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/D', $host) === 1
            && str_contains($host, '.');
    }

    /**
     * Loest den Host auf und liefert die Adressen, wenn ALLE erlaubt sind; sonst null.
     *
     * @return list<string>|null
     */
    public function resolveAllowed(string $host): ?array
    {
        $host = self::normalize($host);
        $bare = trim($host, '[]');

        if (!self::isValidHost($host)) {
            return null;
        }

        if (filter_var($bare, FILTER_VALIDATE_IP) !== false) {
            $ips = [$bare];
        } else {
            $ips = self::$resolver !== null ? (self::$resolver)($host) : self::dns($host);
        }

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (!$this->isAllowedIp($ip)) {
                return null;
            }
        }

        return array_values($ips);
    }

    public function isAllowedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($this->allowPrivate) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && !self::isSpecial($ip);
    }

    /** Bereiche, die PHPs Flags nicht abdecken (IPv6 Loopback/Link-Local/ULA, IPv4-mapped). */
    private static function isSpecial(string $ip): bool
    {
        if (!str_contains($ip, ':')) {
            return str_starts_with($ip, '169.254.') || str_starts_with($ip, '100.64.');
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return true;
        }

        $lower = strtolower($ip);

        return $lower === '::1' || $lower === '::'
            || (ord($packed[0]) & 0xfe) === 0xfc                      // fc00::/7 (ULA)
            || (ord($packed[0]) === 0xfe && (ord($packed[1]) & 0xc0) === 0x80) // fe80::/10 (Link-Local)
            || str_starts_with($lower, '::ffff:');                  // IPv4-mapped: nicht durchlassen
    }

    /** @return list<string> */
    private static function dns(string $host): array
    {
        $ips = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            if (isset($record['ip'])) {
                $ips[] = (string) $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $ips[] = (string) $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
