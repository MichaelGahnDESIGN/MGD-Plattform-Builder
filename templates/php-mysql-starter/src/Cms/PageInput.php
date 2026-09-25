<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use InvalidArgumentException;
use JsonException;
use MGD\Starter\Core\Security\CssSanitizer;
use MGD\Starter\Core\Security\HtmlSanitizer;

/**
 * Validiert und normalisiert Seitendaten aus Formularen und Importen.
 * HTML wird nie vertraut: es wird immer serverseitig bereinigt.
 * Format "grapesjs": HTML (bereinigt) + CSS (bereinigt, auf .page-content begrenzt)
 * + optionale Projektdaten des Editors (gültiges JSON, Größe begrenzt) in content_source.
 */
final class PageInput
{
    public const TYPES = ['page' => 'Seite', 'legal' => 'Rechtliches', 'snippet' => 'Snippet'];
    public const STATUSES = ['draft' => 'Entwurf', 'published' => 'Veröffentlicht'];
    public const FORMATS = ['html', 'markdown', 'grapesjs'];
    public const DEFAULT_MAX_PROJECT_BYTES = 2097152;
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly MarkdownRenderer $markdown = new MarkdownRenderer(),
        private readonly CssSanitizer $css = new CssSanitizer(),
        private readonly int $maxProjectBytes = self::DEFAULT_MAX_PROJECT_BYTES,
    ) {
    }

    /**
     * @return array{slug: string, title: string, page_type: string, status: string, content_format: string,
     *               content_source: ?string, content_html: string, content_css: ?string, meta_description: string}
     */
    public function normalize(array $raw): array
    {
        $slug = strtolower(trim((string) ($raw['slug'] ?? '')));
        $title = trim((string) ($raw['title'] ?? ''));
        $type = (string) ($raw['page_type'] ?? $raw['type'] ?? 'page');
        $status = (string) ($raw['status'] ?? 'draft');
        $format = (string) ($raw['content_format'] ?? 'html');
        $meta = trim((string) ($raw['meta_description'] ?? ''));

        if (preg_match(self::SLUG_PATTERN, $slug) !== 1 || strlen($slug) > 120) {
            throw new InvalidArgumentException('Ungültiger Slug (nur a–z, 0–9 und Bindestriche, max. 120 Zeichen).');
        }

        if ($title === '' || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('Titel fehlt oder ist länger als 200 Zeichen.');
        }

        if (!array_key_exists($type, self::TYPES) || !array_key_exists($status, self::STATUSES)) {
            throw new InvalidArgumentException('Ungültiger Seitentyp oder Status.');
        }

        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException('Ungültiges Inhaltsformat.');
        }

        if (mb_strlen($meta) > 300) {
            throw new InvalidArgumentException('Meta-Beschreibung ist länger als 300 Zeichen.');
        }

        return [
            'slug' => $slug,
            'title' => $title,
            'page_type' => $type,
            'status' => $status,
            'meta_description' => $meta,
            ...$this->content($format, $raw),
        ];
    }

    /**
     * @return array{content_format: string, content_source: ?string, content_html: string, content_css: ?string}
     */
    private function content(string $format, array $raw): array
    {
        if ($format === 'markdown') {
            $source = (string) ($raw['content_source'] ?? $raw['content'] ?? '');

            return [
                'content_format' => 'markdown',
                'content_source' => $source,
                'content_html' => $this->sanitizer->sanitize($this->markdown->render($source)),
                'content_css' => null,
            ];
        }

        $html = (string) ($raw['content_html'] ?? $raw['content'] ?? '');

        if ($format === 'grapesjs') {
            $css = $this->css->sanitize((string) ($raw['content_css'] ?? ''));

            return [
                'content_format' => 'grapesjs',
                'content_source' => $this->projectData((string) ($raw['content_source'] ?? '')),
                'content_html' => $this->sanitizer->sanitize($html),
                'content_css' => $css === '' ? null : $css,
            ];
        }

        return [
            'content_format' => 'html',
            'content_source' => null,
            'content_html' => $this->sanitizer->sanitize($html),
            'content_css' => null,
        ];
    }

    /**
     * Projektdaten des Editors: optional, sonst gültiges JSON-Objekt innerhalb der Größenbegrenzung.
     */
    private function projectData(string $source): ?string
    {
        $source = trim($source);

        if ($source === '') {
            return null;
        }

        if (strlen($source) > $this->maxProjectBytes) {
            throw new InvalidArgumentException('Editor-Projektdaten sind zu groß (max. ' . $this->maxProjectBytes . ' Bytes).');
        }

        try {
            $decoded = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Editor-Projektdaten sind kein gültiges JSON.');
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Editor-Projektdaten müssen ein JSON-Objekt sein.');
        }

        return $source;
    }
}
