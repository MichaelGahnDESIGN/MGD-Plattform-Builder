<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use InvalidArgumentException;
use JsonException;
use MGD\Starter\Core\Auth\User;

/**
 * Export/Import von CMS-Seiten als JSON.
 * Format: {"format":"mgd-cms-pages","format_version":1,"exported_at":"…","pages":[…]}
 * Seiten im Format "grapesjs" enthalten zusätzlich content_css und die Editor-Projektdaten (content_source).
 * Beim Import werden Seiten neu angelegt oder – falls der Slug existiert – als neue Revision gespeichert.
 * Inhalte werden dabei immer erneut bereinigt.
 */
final class PageTransfer
{
    public const FORMAT = 'mgd-cms-pages';
    public const FORMAT_VERSION = 1;
    private const MAX_PAGES = 1000;

    public function __construct(
        private readonly PageRepository $pages,
        private readonly PageInput $input,
    ) {
    }

    /**
     * @param list<int> $ids leere Liste = alle (ohne Papierkorb)
     */
    public function export(array $ids = [], bool $withRevisions = false): array
    {
        $rows = $this->pages->list();
        $selected = array_map('intval', $ids);
        $pages = [];

        foreach ($rows as $row) {
            if ($selected !== [] && !in_array((int) $row['id'], $selected, true)) {
                continue;
            }

            $page = $this->pages->find((int) $row['id']);

            if ($page === null) {
                continue;
            }

            $entry = $this->exportPage($page);

            if ($withRevisions) {
                $entry['revisions'] = $this->exportRevisions((int) $page['id']);
            }

            $pages[] = $entry;
        }

        return [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => gmdate('c'),
            'pages' => $pages,
        ];
    }

    public function decode(string $json, int $maxBytes): array
    {
        if (strlen($json) > $maxBytes) {
            throw new InvalidArgumentException('Import ist zu groß.');
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Import ist kein gültiges JSON.');
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Import muss ein JSON-Objekt sein.');
        }

        return $data;
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(array $data, ?User $actor): array
    {
        if (($data['format'] ?? null) !== self::FORMAT || (int) ($data['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('Unbekanntes Importformat (erwartet ' . self::FORMAT . ' v' . self::FORMAT_VERSION . ').');
        }

        $pages = $data['pages'] ?? null;

        if (!is_array($pages) || !array_is_list($pages) || count($pages) > self::MAX_PAGES) {
            throw new InvalidArgumentException('"pages" muss eine Liste mit höchstens ' . self::MAX_PAGES . ' Einträgen sein.');
        }

        $summary = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($pages as $index => $raw) {
            try {
                $summary[$this->importPage(is_array($raw) ? $raw : [], $actor)]++;
            } catch (InvalidArgumentException $exception) {
                $summary['errors'][] = 'Eintrag ' . ($index + 1) . ': ' . $exception->getMessage();
            }
        }

        return $summary;
    }

    private function importPage(array $raw, ?User $actor): string
    {
        $data = $this->input->normalize(array_intersect_key($raw, array_flip([
            'slug', 'title', 'page_type', 'status', 'content_format', 'content_source', 'content_html', 'content_css', 'meta_description',
        ])));
        $existing = $this->pages->findBySlug($data['slug'], false, true);

        if ($existing === null) {
            $this->pages->create($data, $actor, 'Importiert');

            return 'created';
        }

        $this->pages->update((int) $existing['id'], $data, $actor, 'Import');

        return 'updated';
    }

    private function exportPage(array $page): array
    {
        return [
            'slug' => $page['slug'],
            'title' => $page['title'],
            'page_type' => $page['page_type'],
            'status' => $page['status'],
            'content_format' => $page['content_format'],
            'content_source' => $page['content_source'],
            'content_html' => $page['content_html'],
            'content_css' => $page['content_css'] ?? null,
            'meta_description' => $page['meta_description'],
            'current_revision' => (int) $page['current_revision'],
            'updated_at' => $page['updated_at'],
        ];
    }

    private function exportRevisions(int $pageId): array
    {
        $revisions = [];

        foreach ($this->pages->revisions($pageId) as $summary) {
            $revision = $this->pages->revision((int) $summary['id']);

            if ($revision === null) {
                continue;
            }

            $revisions[] = [
                'revision_no' => (int) $revision['revision_no'],
                'title' => $revision['title'],
                'status' => $revision['status'],
                'content_format' => $revision['content_format'],
                'content_source' => $revision['content_source'],
                'content_html' => $revision['content_html'],
                'content_css' => $revision['content_css'] ?? null,
                'meta_description' => $revision['meta_description'],
                'author_label' => $revision['author_label'],
                'note' => $revision['note'],
                'created_at' => $revision['created_at'],
            ];
        }

        return $revisions;
    }
}
