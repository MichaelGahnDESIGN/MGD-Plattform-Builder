<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

use MGD\Starter\Core\Settings\SettingsRepository;
use MGD\Starter\Core\Settings\SettingsValidator;

/**
 * Erzeugt CSS-Custom-Properties aus den Design-Einstellungen (hell + dunkel).
 * Werte werden vor der Ausgabe erneut streng validiert.
 */
final class DesignCss
{
    private const FONT_PATTERN = '/^[A-Za-z0-9 ,"\'-]+$/';

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function render(): string
    {
        $light = $this->variables('light');
        $dark = $this->variables('dark');
        $radius = max(0, min(32, $this->settings->int('design.radius')));
        $font = $this->settings->string('design.font_family');
        $font = preg_match(self::FONT_PATTERN, $font) === 1 ? $font : 'system-ui, sans-serif';
        $common = '--radius:' . $radius . 'px;--font-family:' . $font . ';';

        return $this->fontFace()
            . ':root{' . $common . '}'
            . ':root,:root[data-theme="light"]{' . $light . '}'
            . ':root[data-theme="dark"]{' . $dark . '}'
            . '@media (prefers-color-scheme: dark){:root:not([data-theme]){' . $dark . '}}';
    }

    private function variables(string $mode): string
    {
        $css = '';

        foreach ($this->settings->registry()->palette() as $name) {
            $value = $this->settings->string('design.' . $mode . '.' . $name);

            if (preg_match(SettingsValidator::COLOR_PATTERN, $value) === 1) {
                $css .= '--color-' . str_replace('_', '-', $name) . ':' . $value . ';';
            }
        }

        return $css;
    }

    private function fontFace(): string
    {
        $file = $this->settings->string('design.font_file');

        if ($file === '' || !SettingsValidator::isLocalPath($file) || !str_ends_with($file, '.woff2')) {
            return '';
        }

        return '@font-face{font-family:"Site Font";src:url("' . View::url($file) . '") format("woff2");font-display:swap;}';
    }
}
