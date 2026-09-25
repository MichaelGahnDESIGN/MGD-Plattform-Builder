<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Update;

use InvalidArgumentException;
use JsonException;
use MGD\Starter\Core\Version\Version;
use MGD\Starter\Core\Version\VersionStatus;
use RuntimeException;

/**
 * Prüft ein Update-Manifest per HTTPS. Installiert niemals automatisch.
 * Die Anfrage trägt ?channel=<kanal>. Akzeptiert werden zwei Formate:
 *   flach:   {"version": "1.2.0", "status": "stable", "notes_url": "https://…"}
 *   Kanäle:  {"channels": {"stable": {…}, "beta": {…}}}  (fehlt der Kanal, gilt "stable")
 */
final class UpdateChecker
{
    public const DEFAULT_CHANNELS = ['stable', 'beta', 'alpha', 'lts'];
    public const FALLBACK_CHANNEL = 'stable';
    private const TIMEOUT_SECONDS = 5;
    private const MAX_BYTES = 65536;

    /**
     * @param list<string> $channels erlaubte Kanäle (aus der Einstellungsdefinition updater.channel)
     */
    public function __construct(private readonly array $channels = self::DEFAULT_CHANNELS)
    {
    }

    /**
     * @return array{newer: bool, version: string, status: string, notes_url: string, channel: string}
     */
    public function check(string $manifestUrl, Version $current, string $channel = self::FALLBACK_CHANNEL): array
    {
        if (!in_array($channel, $this->channels, true)) {
            throw new InvalidArgumentException('Unbekannter Update-Kanal: ' . $channel);
        }

        $url = self::buildUrl($manifestUrl, $channel);

        return self::parseManifest($this->decode($this->fetch($url)), $channel, $current);
    }

    /**
     * Hängt channel=<kanal> an und behält vorhandene Query-Parameter bei (Fragment wird entfernt).
     */
    public static function buildUrl(string $manifestUrl, string $channel): string
    {
        $parts = parse_url($manifestUrl);

        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Manifest-URL muss eine HTTPS-URL sein.');
        }

        parse_str($parts['query'] ?? '', $query);
        $query['channel'] = $channel;
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return 'https://' . $parts['host'] . $port . ($parts['path'] ?? '/') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Reine Funktion (ohne Netzwerk): wählt den Kanal-Eintrag und validiert ihn.
     *
     * @return array{newer: bool, version: string, status: string, notes_url: string, channel: string}
     */
    public static function parseManifest(array $data, string $channel, Version $current): array
    {
        [$entry, $resolvedChannel] = self::selectEntry($data, $channel);
        $version = (string) ($entry['version'] ?? '');
        $status = VersionStatus::tryFrom((string) ($entry['status'] ?? ''));
        $notesUrl = (string) ($entry['notes_url'] ?? '');

        if (preg_match(Version::PATTERN, $version) !== 1 || $status === null) {
            throw new RuntimeException('Manifest enthält keine gültige Version oder keinen gültigen Status.');
        }

        if ($notesUrl !== '' && (!str_starts_with($notesUrl, 'https://') || filter_var($notesUrl, FILTER_VALIDATE_URL) === false)) {
            $notesUrl = '';
        }

        return [
            'newer' => version_compare($version, $current->number, '>'),
            'version' => $version,
            'status' => $status->value,
            'notes_url' => $notesUrl,
            'channel' => $resolvedChannel,
        ];
    }

    /**
     * @return array{0: array, 1: string}
     */
    private static function selectEntry(array $data, string $channel): array
    {
        if (!array_key_exists('channels', $data)) {
            return [$data, $channel];
        }

        $channels = $data['channels'];

        if (!is_array($channels)) {
            throw new RuntimeException('Manifest: "channels" muss ein Objekt sein.');
        }

        foreach ([$channel, self::FALLBACK_CHANNEL] as $candidate) {
            if (isset($channels[$candidate]) && is_array($channels[$candidate])) {
                return [$channels[$candidate], $candidate];
            }
        }

        throw new RuntimeException('Manifest enthält weder den Kanal "' . $channel . '" noch "' . self::FALLBACK_CHANNEL . '".');
    }

    private function fetch(string $url): string
    {
        if (function_exists('curl_init')) {
            return $this->fetchWithCurl($url);
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Weder cURL noch allow_url_fopen sind verfügbar.');
        }

        $context = stream_context_create([
            'http' => ['timeout' => self::TIMEOUT_SECONDS, 'follow_location' => 0, 'header' => "Accept: application/json\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);

        if ($body === false) {
            throw new RuntimeException('Manifest konnte nicht geladen werden.');
        }

        return $body;
    }

    private function fetchWithCurl(string $url): string
    {
        $handle = curl_init($url);
        $buffer = '';
        curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer): int {
                $buffer .= $chunk;

                return strlen($buffer) > self::MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($ok === false || $status !== 200) {
            throw new RuntimeException('Manifest konnte nicht geladen werden (HTTP ' . $status . ').');
        }

        return $buffer;
    }

    private function decode(string $body): array
    {
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Manifest ist zu groß.');
        }

        try {
            $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Manifest ist kein gültiges JSON.');
        }

        if (!is_array($data)) {
            throw new RuntimeException('Manifest muss ein JSON-Objekt sein.');
        }

        return $data;
    }
}
