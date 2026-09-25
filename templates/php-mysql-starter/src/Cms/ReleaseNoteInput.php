<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use DateTimeImmutable;
use InvalidArgumentException;
use MGD\Starter\Core\Version\Version;
use MGD\Starter\Core\Version\VersionStatus;

final class ReleaseNoteInput
{
    public const TYPES = [
        'feature' => 'Feature',
        'patch' => 'Patch',
        'fix' => 'Fix',
        'security' => 'Sicherheit',
        'breaking' => 'Breaking Change',
        'deploy' => 'Deployment',
    ];

    public const TYPE_VARIANTS = [
        'feature' => 'primary',
        'patch' => 'info',
        'fix' => 'success',
        'security' => 'danger',
        'breaking' => 'warning',
        'deploy' => 'neutral',
    ];

    public const AUDIENCES = [
        'frontend' => 'Frontend',
        'backoffice' => 'Backoffice',
        'editor' => 'Editor',
        'platform' => 'Plattform',
        'game' => 'Spiel',
        'api' => 'API',
    ];

    private const MAX_ITEMS = 50;
    private const MAX_ITEM_LENGTH = 500;

    /**
     * @return array{version: string, status: string, date: string, type: string, title: string,
     *               items: list<string>, audience: list<string>}
     */
    public function normalize(array $raw): array
    {
        $version = trim((string) ($raw['version'] ?? ''));
        $status = VersionStatus::tryFrom((string) ($raw['status'] ?? ''));
        $date = trim((string) ($raw['date'] ?? ''));
        $type = (string) ($raw['type'] ?? '');
        $title = trim((string) ($raw['title'] ?? ''));

        if (preg_match(Version::PATTERN, $version) !== 1) {
            throw new InvalidArgumentException('Version muss dem Schema MAJOR.MINOR.PATCH entsprechen.');
        }

        if ($status === null) {
            throw new InvalidArgumentException('Ungültiger Versionsstatus.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Datum muss im Format JJJJ-MM-TT sein.');
        }

        if (!array_key_exists($type, self::TYPES)) {
            throw new InvalidArgumentException('Ungültiger Typ.');
        }

        if ($title === '' || mb_strlen($title) > 190) {
            throw new InvalidArgumentException('Titel fehlt oder ist länger als 190 Zeichen.');
        }

        return [
            'version' => $version,
            'status' => $status->value,
            'date' => $date,
            'type' => $type,
            'title' => $title,
            'items' => $this->items($raw['items'] ?? []),
            'audience' => $this->audience($raw['audience'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private function items(mixed $raw): array
    {
        $lines = is_string($raw) ? (preg_split('/\R/', $raw) ?: []) : (is_array($raw) ? $raw : []);
        $items = [];

        foreach ($lines as $line) {
            $line = is_string($line) ? trim(ltrim(trim($line), '-* ')) : '';

            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) > self::MAX_ITEM_LENGTH) {
                throw new InvalidArgumentException('Ein Punkt ist länger als ' . self::MAX_ITEM_LENGTH . ' Zeichen.');
            }

            $items[] = $line;
        }

        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('Höchstens ' . self::MAX_ITEMS . ' Punkte pro Eintrag.');
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function audience(mixed $raw): array
    {
        $values = is_array($raw) ? array_filter($raw, 'is_string') : [];

        foreach ($values as $value) {
            if (!array_key_exists($value, self::AUDIENCES)) {
                throw new InvalidArgumentException('Unbekannte Zielgruppe: ' . $value);
            }
        }

        $audience = array_values(array_intersect(array_keys(self::AUDIENCES), $values));

        if ($audience === []) {
            throw new InvalidArgumentException('Mindestens eine Zielgruppe wählen.');
        }

        return $audience;
    }
}
