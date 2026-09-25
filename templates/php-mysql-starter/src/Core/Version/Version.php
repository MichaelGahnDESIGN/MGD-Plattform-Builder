<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Version;

use RuntimeException;

/**
 * Versionsschema MAJOR.MINOR.PATCH:
 * MAJOR = Release-Linie, MINOR = neue Funktionen (Spiel/Editor), PATCH = Patches/Updates bestehender Funktionen.
 */
final class Version
{
    public const PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/';

    public function __construct(
        public readonly string $number,
        public readonly VersionStatus $status,
        public readonly string $releasedAt = '',
    ) {
        if (preg_match(self::PATTERN, $number) !== 1) {
            throw new RuntimeException('Ungültige Versionsnummer: ' . $number);
        }
    }

    public static function fromFile(string $file): self
    {
        $json = is_file($file) ? file_get_contents($file) : false;

        if ($json === false) {
            throw new RuntimeException('version.json nicht lesbar: ' . $file);
        }

        $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        $status = VersionStatus::tryFrom((string) ($data['status'] ?? ''));

        if (!is_array($data) || $status === null) {
            throw new RuntimeException('version.json enthält keinen gültigen Status.');
        }

        return new self((string) ($data['version'] ?? ''), $status, (string) ($data['released_at'] ?? ''));
    }

    public function label(bool $withStatus = true, string $locale = 'de'): string
    {
        return $withStatus ? $this->number . ' ' . $this->status->label($locale) : $this->number;
    }

    public function isNewerThan(string $other): bool
    {
        return version_compare($this->number, $other, '>');
    }
}
