<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Cms\EditorAssets;
use MGD\Starter\Cms\PageInput;
use MGD\Starter\Core\App;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * Formular für CMS-Seiten inkl. Einbindung des gewählten Editors (lokal/CDN, Fallback Textfeld).
 */
final class PageForm
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return array{html: string, assets: array{scripts: list<string>, styles: list<string>}}
     */
    public function render(string $action, array $page, string $error = ''): array
    {
        $editorAssets = new EditorAssets($this->app->settings(), $this->app->config->path('public'));
        $editor = $editorAssets->editor();
        $format = (string) ($page['content_format'] ?? ($editor === 'markdown' ? 'markdown' : 'html'));
        $content = $format === 'markdown' ? (string) ($page['content_source'] ?? '') : (string) ($page['content_html'] ?? '');
        $resolved = $editorAssets->resolve();
        $useRichEditor = $format === 'html' && $resolved['available'];
        $assets = ['scripts' => [View::asset('js/editor-loader.js')], 'styles' => []];

        if ($useRichEditor && $editorAssets->usesCdn()) {
            $this->app->headers->allowScriptOrigin($resolved['script']);
            $this->app->headers->allowStyleOrigin($resolved['style']);
        }

        $noteRequired = $this->app->settings()->bool('cms_editor.revision_note_required');
        $editorAttributes = ' id="page-content" rows="20" class="code-input"'
            . ' data-editor="' . View::e($useRichEditor ? $editor : 'plain') . '"'
            . ' data-editor-script="' . View::e($useRichEditor ? $resolved['script'] : '') . '"'
            . ' data-editor-style="' . View::e($useRichEditor ? $resolved['style'] : '') . '"';
        $hint = $format === 'html' && !$resolved['available'] && isset(EditorAssets::LOCAL_FILES[$editor])
            ? Ui::notice('Editor "' . $editor . '" ist nicht verfügbar (Dateien fehlen unter /assets/vendor oder keine CDN-URL). Es wird ein einfaches Textfeld verwendet.', 'warning')
            : '';

        $html = ($error !== '' ? Ui::notice($error, 'danger') : '') . $hint
            . Form::open($action, ' class="stack page-form" data-editor-form')
            . '<div class="grid-2">'
            . Form::text('title', 'Titel', (string) ($page['title'] ?? ''), ' required maxlength="200"')
            . Form::text('slug', 'Slug (URL)', (string) ($page['slug'] ?? ''), ' required maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*"')
            . Form::select('page_type', 'Typ', PageInput::TYPES, (string) ($page['page_type'] ?? 'page'))
            . Form::select('status', 'Status', PageInput::STATUSES, (string) ($page['status'] ?? 'draft'))
            . Form::select('content_format', 'Format', ['html' => 'HTML', 'markdown' => 'Markdown'], $format)
            . Form::text('meta_description', 'Meta-Beschreibung', (string) ($page['meta_description'] ?? ''), ' maxlength="300"')
            . '</div>'
            . Form::textarea('content', 'Inhalt', $content, $editorAttributes)
            . '<p class="muted editor-status" data-editor-status></p>'
            . Form::text('note', 'Änderungsnotiz (Revision)', '', ' maxlength="300"' . ($noteRequired ? ' required' : ''))
            . '<p class="muted">HTML wird beim Speichern serverseitig bereinigt (z. B. werden Skripte und Event-Attribute entfernt).</p>'
            . Form::submit('Speichern') . '</form>';

        if ($useRichEditor && $resolved['style'] !== '') {
            $assets['styles'][] = $resolved['style'];
        }

        return ['html' => $html, 'assets' => $assets];
    }
}
