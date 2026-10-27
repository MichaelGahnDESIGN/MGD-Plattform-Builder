<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Settings;

final class SettingsRegistry
{
    /** @var array<string, string> */
    private readonly array $categories;
    /** @var array<string, array> */
    private readonly array $settings;
    /** @var list<string> */
    private readonly array $palette;

    public function __construct(?array $definitions = null)
    {
        $definitions ??= require __DIR__ . '/definitions.php';
        $this->categories = $definitions['categories'];
        $this->palette = $definitions['palette'];
        $indexed = [];

        foreach ($definitions['settings'] as $definition) {
            $indexed[$definition['key']] = $definition;
        }

        $this->settings = $indexed;
    }

    /**
     * @return array<string, string>
     */
    public function categories(): array
    {
        return $this->categories;
    }

    public function categoryLabel(string $category): string
    {
        return $this->categories[$category] ?? $category;
    }

    public function has(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    public function definition(string $key): ?array
    {
        return $this->settings[$key] ?? null;
    }

    /**
     * @return array<string, array>
     */
    public function all(): array
    {
        return $this->settings;
    }

    /**
     * @return list<string> Farbnamen der Design-Palette (primary, surface, …)
     */
    public function palette(): array
    {
        return $this->palette;
    }

    public function defaultValue(string $key): mixed
    {
        return $this->settings[$key]['default'] ?? null;
    }

    /**
     * Serverseitige Suche/Filter (Fallback ohne JavaScript).
     * Alle Suchbegriffe müssen im Schlüssel, Label, Beschreibung, Kategorie oder den Keywords vorkommen.
     *
     * @return array<string, list<array>> gruppiert nach Kategorie
     */
    public function search(string $query, string $category = ''): array
    {
        $terms = array_filter(preg_split('/\s+/', mb_strtolower(trim($query))) ?: []);
        $grouped = [];

        foreach ($this->settings as $definition) {
            if ($category !== '' && $definition['category'] !== $category) {
                continue;
            }

            $haystack = $this->searchText($definition);

            foreach ($terms as $term) {
                if (!str_contains($haystack, $term)) {
                    continue 2;
                }
            }

            $grouped[$definition['category']][] = $definition;
        }

        return array_intersect_key(array_replace(array_fill_keys(array_keys($this->categories), []), $grouped), $grouped);
    }

    public function searchText(array $definition): string
    {
        return mb_strtolower(implode(' ', [
            $definition['key'],
            $definition['label'],
            $definition['description'],
            $this->categoryLabel($definition['category']),
            implode(' ', $definition['keywords']),
        ]));
    }
}
