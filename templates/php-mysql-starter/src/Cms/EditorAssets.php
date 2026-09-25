<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use MGD\Starter\Core\Settings\SettingsRepository;
use MGD\Starter\Core\View\View;

/**
 * Liefert die Skript-/Stylesheet-Pfade des gewählten CMS-Editors.
 * Lokal: public/assets/vendor/<editor>/… (siehe assets/vendor/README.md). CDN: HTTPS-URLs aus den Einstellungen.
 */
final class EditorAssets
{
    public const LOCAL_FILES = [
        'tinymce' => ['script' => 'vendor/tinymce/tinymce.min.js', 'style' => ''],
        'grapesjs' => ['script' => 'vendor/grapesjs/grapes.min.js', 'style' => 'vendor/grapesjs/css/grapes.min.css'],
        'quill' => ['script' => 'vendor/quill/quill.js', 'style' => 'vendor/quill/quill.snow.css'],
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly string $publicPath,
    ) {
    }

    public function editor(): string
    {
        return $this->settings->string('cms_editor.editor');
    }

    public function usesCdn(): bool
    {
        return $this->settings->string('cms_editor.delivery') === 'cdn';
    }

    /**
     * @return array{script: string, style: string, available: bool}
     */
    public function resolve(): array
    {
        $editor = $this->editor();

        if (!isset(self::LOCAL_FILES[$editor])) {
            return ['script' => '', 'style' => '', 'available' => false];
        }

        if ($this->usesCdn()) {
            $script = $this->settings->string('cms_editor.cdn_script_url');

            return ['script' => $script, 'style' => $this->settings->string('cms_editor.cdn_style_url'), 'available' => $script !== ''];
        }

        $files = self::LOCAL_FILES[$editor];
        $available = is_file($this->publicPath . '/assets/' . $files['script']);

        return [
            'script' => $available ? View::asset($files['script']) : '',
            'style' => $files['style'] !== '' && is_file($this->publicPath . '/assets/' . $files['style']) ? View::asset($files['style']) : '',
            'available' => $available,
        ];
    }
}
