<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use InvalidArgumentException;
use MGD\Starter\Core\Security\HtmlSanitizer;
use MGD\Starter\Core\Settings\SettingsValidator;

final class CreditInput
{
    public const CATEGORIES = [
        'ai' => 'KI-System', 'tool' => 'Werkzeug', 'library' => 'Bibliothek', 'framework' => 'Framework',
        'plugin' => 'Plugin', 'font' => 'Schrift', 'icon' => 'Icons', 'image' => 'Bilder', 'sound' => 'Sound',
        'service' => 'Dienst', 'other' => 'Sonstiges',
    ];

    public const COMMERCIAL_USE = [
        'yes' => 'Kommerziell erlaubt', 'no' => 'Nicht kommerziell', 'restricted' => 'Eingeschränkt', 'unknown' => 'Unbekannt',
    ];

    private const MAX_LINKS = 10;
    private const MAX_TAGS = 20;

    public function person(array $raw): array
    {
        return [
            'name' => $this->text($raw['name'] ?? '', 'Name', 150, true),
            'role' => $this->text($raw['role'] ?? '', 'Rolle', 150, true),
            'link_url' => $this->url((string) ($raw['link_url'] ?? ''), 'Link'),
            'sort_order' => $this->sortOrder($raw['sort_order'] ?? 0),
        ];
    }

    public function component(array $raw): array
    {
        $category = (string) ($raw['category'] ?? 'other');
        $commercial = (string) ($raw['commercial_use'] ?? 'unknown');
        $logo = trim((string) ($raw['logo_path'] ?? ''));

        if (!array_key_exists($category, self::CATEGORIES) || !array_key_exists($commercial, self::COMMERCIAL_USE)) {
            throw new InvalidArgumentException('Ungültige Kategorie oder Angabe zur kommerziellen Nutzung.');
        }

        if ($logo !== '' && !SettingsValidator::isLocalPath($logo)) {
            throw new InvalidArgumentException('Logo muss lokal unter /assets/ oder /uploads/ liegen (kein Hotlinking).');
        }

        return [
            'name' => $this->text($raw['name'] ?? '', 'Name', 150, true),
            'category' => $category,
            'logo_path' => $logo,
            'description' => $this->text($raw['description'] ?? '', 'Beschreibung', 2000),
            'provider_name' => $this->text($raw['provider_name'] ?? '', 'Anbieter', 150),
            'provider_info' => $this->text($raw['provider_info'] ?? '', 'Anbieterhinweis', 2000),
            'links' => $this->links($raw['links'] ?? []),
            'license' => $this->text($raw['license'] ?? '', 'Lizenz', 100),
            'tags' => $this->tags($raw['tags'] ?? []),
            'commercial_use' => $commercial,
            'attribution_required' => in_array($raw['attribution_required'] ?? false, [true, 1, '1', 'on'], true),
            'locally_embedded' => in_array($raw['locally_embedded'] ?? false, [true, 1, '1', 'on'], true),
            'version' => $this->text($raw['version'] ?? '', 'Version', 50),
            'notes' => $this->text($raw['notes'] ?? '', 'Notizen', 2000),
            'sort_order' => $this->sortOrder($raw['sort_order'] ?? 0),
        ];
    }

    /**
     * Links als Liste {label,url} oder als Text "Label | https://…" pro Zeile.
     *
     * @return list<array{label: string, url: string}>
     */
    public function links(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = array_map(static function (string $line): array {
                $parts = array_map('trim', explode('|', $line, 2));

                return count($parts) === 2 ? ['label' => $parts[0], 'url' => $parts[1]] : ['label' => $parts[0], 'url' => $parts[0]];
            }, array_filter(array_map('trim', preg_split('/\R/', $raw) ?: [])));
        }

        $links = [];

        foreach (is_array($raw) ? $raw : [] as $link) {
            $label = $this->text(is_array($link) ? ($link['label'] ?? '') : '', 'Link-Bezeichnung', 80, true);
            $url = $this->url((string) (is_array($link) ? ($link['url'] ?? '') : ''), 'Link "' . $label . '"');

            if ($url === '') {
                throw new InvalidArgumentException('Link "' . $label . '" braucht eine URL.');
            }

            $links[] = ['label' => $label, 'url' => $url];
        }

        if (count($links) > self::MAX_LINKS) {
            throw new InvalidArgumentException('Höchstens ' . self::MAX_LINKS . ' Links.');
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    public function tags(mixed $raw): array
    {
        $values = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : []);
        $tags = [];

        foreach ($values as $value) {
            $tag = is_string($value) ? trim($value) : '';

            if ($tag !== '' && mb_strlen($tag) <= 50 && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags, 0, self::MAX_TAGS);
    }

    private function text(mixed $raw, string $label, int $max, bool $required = false): string
    {
        $value = is_scalar($raw) ? trim((string) $raw) : '';

        if ($required && $value === '') {
            throw new InvalidArgumentException($label . ' ist erforderlich.');
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label . ' ist länger als ' . $max . ' Zeichen.');
        }

        return $value;
    }

    private function url(string $url, string $label): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (mb_strlen($url) > 500 || !HtmlSanitizer::isSafeUrl($url, ['http', 'https', 'mailto'])
            || preg_match('#^(https?://|mailto:|/)#i', $url) !== 1) {
            throw new InvalidArgumentException($label . ' muss eine http(s)-, mailto- oder relative URL sein.');
        }

        return $url;
    }

    private function sortOrder(mixed $raw): int
    {
        $value = is_scalar($raw) ? (string) $raw : '0';

        return preg_match('/^-?\d{1,6}$/', trim($value)) === 1 ? (int) $value : 0;
    }
}
