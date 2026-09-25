<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Update;

use InvalidArgumentException;
use JsonException;
use MGD\Starter\Core\Version\Version;
use MGD\Starter\Core\Version\VersionStatus;
use RuntimeException;

/**
 * Prüft ein Update-Manifest (HTTPS, JSON {version, status, notes_url}).
 * Installiert niemals automatisch.
 */
final class UpdateChecker
{
    private const TIMEOUT_SECONDS = 5;
    private const MAX_BYTES = 65536;

    /**
     * @return array{newer: bool, version: string, status: string, notes_url: string}
     */
    public function check(string $manifestUrl, Version $current): array
    {
        $parts = parse_url($manifestUrl);

        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('Manifest-URL muss eine HTTPS-URL sein.');
        }

        $data = $this->decode($this->fetch($manifestUrl));
        $version = (string) ($data['version'] ?? '');
        $status = VersionStatus::tryFrom((string) ($data['status'] ?? ''));
        $notesUrl = (string) ($data['notes_url'] ?? '');

        if (preg_match(Version::PATTERN, $version) !== 1 || $status === null) {
            throw new RuntimeException('Manifest enthält keine gültige Version oder keinen gültigen Status.');
        }

        if ($notesUrl !== '' && !str_starts_with($notesUrl, 'https://')) {
            $notesUrl = '';
        }

        return [
            'newer' => version_compare($version, $current->number, '>'),
            'version' => $version,
            'status' => $status->value,
            'notes_url' => $notesUrl,
        ];
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
