<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

use MGD\Starter\Core\App;

/**
 * HTML-Grundgerüst: Head, Theme-Skript (Nonce, kein Flackern), Design-Variablen, Ladebildschirm.
 */
final class Document
{
    private const THEME_SCRIPT = "(function(){var d=document.documentElement,m=d.getAttribute('data-theme-default')||'system';"
        . "try{if(d.getAttribute('data-theme-user')==='1'){var s=localStorage.getItem('mgd-theme');"
        . "if(s==='light'||s==='dark'||s==='system'){m=s;}}}catch(e){}"
        . "var t=m;if(m==='system'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}"
        . "d.setAttribute('data-theme',t);d.setAttribute('data-theme-mode',m);d.classList.add('js');"
        . "if(d.getAttribute('data-loader')==='1'){d.classList.add('is-loading');}})();";

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array{title: string, body: string, area?: string, description?: string, canonical?: string,
     *              robots?: string, og_image?: string, scripts?: list<string>, styles?: list<string>,
     *              body_class?: string, loader?: bool} $options
     */
    public function render(array $options): string
    {
        $settings = $this->app->settings();
        $area = $options['area'] ?? 'site';
        $nonce = View::e($this->app->headers->nonce());
        $mode = $settings->string('theme.default_mode');
        $loader = ($options['loader'] ?? false) && $settings->bool('loader.enabled');
        $serverTheme = in_array($mode, ['light', 'dark'], true) ? ' data-theme="' . $mode . '"' : '';

        return '<!doctype html>' . "\n"
            . '<html lang="' . View::e($this->app->locale()) . '"' . $serverTheme
            . ' data-theme-default="' . View::e($mode) . '"'
            . ' data-theme-user="' . ($settings->bool('theme.allow_user_choice') ? '1' : '0') . '"'
            . ' data-loader="' . ($loader ? '1' : '0') . '"'
            . ' data-loader-min="' . $settings->int('loader.min_duration_ms') . '">'
            . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . View::e($options['title']) . '</title>'
            . $this->meta($options)
            . '<script nonce="' . $nonce . '">' . self::THEME_SCRIPT . '</script>'
            . $this->styles($area, $options['styles'] ?? [])
            . '<style nonce="' . $nonce . '">' . (new DesignCss($settings))->render() . '</style>'
            . '</head><body class="area-' . View::e($area) . ' ' . View::e($options['body_class'] ?? '') . '">'
            . '<a class="skip-link" href="#main">Zum Inhalt springen</a>'
            . ($loader ? $this->loader() : '')
            . $options['body']
            . $this->scripts($area, $loader, $options['scripts'] ?? [])
            . '</body></html>';
    }

    private function meta(array $options): string
    {
        $html = '';

        if (($options['description'] ?? '') !== '') {
            $html .= '<meta name="description" content="' . View::e($options['description']) . '">';
            $html .= '<meta property="og:description" content="' . View::e($options['description']) . '">';
        }

        $html .= '<meta name="robots" content="' . View::e($options['robots'] ?? 'noindex, nofollow') . '">';
        $html .= '<meta property="og:title" content="' . View::e($options['title']) . '">';

        if (($options['canonical'] ?? '') !== '') {
            $html .= '<link rel="canonical" href="' . View::e($options['canonical']) . '">';
        }

        if (($options['og_image'] ?? '') !== '') {
            $html .= '<meta property="og:image" content="' . View::e($options['og_image']) . '">';
        }

        return $html . '<meta name="color-scheme" content="light dark">';
    }

    /**
     * @param list<string> $extra bereits aufgelöste URLs
     */
    private function styles(string $area, array $extra): string
    {
        $files = ['css/tokens.css', 'css/theme-light.css', 'css/theme-dark.css', 'css/site.css', 'css/powered-by.css'];

        if ($area !== 'site') {
            $files[] = 'css/admin.css';
        }

        if ($area === 'site') {
            $files[] = 'custom/custom.css';
        }

        $html = '';

        foreach ($files as $file) {
            $html .= '<link rel="stylesheet" href="' . View::e(View::asset($file)) . '">';
        }

        foreach ($extra as $url) {
            $html .= '<link rel="stylesheet" href="' . View::e($url) . '">';
        }

        return $html;
    }

    /**
     * @param list<string> $extra bereits aufgelöste URLs
     */
    private function scripts(string $area, bool $loader, array $extra): string
    {
        $files = ['js/theme.js'];

        if ($loader) {
            $files[] = 'js/loader.js';
        }

        if ($area === 'site') {
            $files[] = 'js/cookie-box.js';
            $files[] = 'custom/custom.js';
        } else {
            $files[] = 'js/admin.js';
        }

        $html = '';

        foreach ($files as $file) {
            $html .= '<script src="' . View::e(View::asset($file)) . '" defer></script>';
        }

        foreach ($extra as $url) {
            $html .= '<script src="' . View::e($url) . '" defer></script>';
        }

        return $html;
    }

    private function loader(): string
    {
        $settings = $this->app->settings();
        $style = $settings->string('loader.style');
        $logo = $settings->string('loader.logo_path');
        $visual = match (true) {
            $style === 'logo' && $logo !== '' => '<img class="site-loader__logo" src="' . View::e(View::url($logo)) . '" alt="">',
            $style === 'bar' => '<span class="site-loader__bar"></span>',
            default => '<span class="site-loader__spinner"></span>',
        };

        return '<div class="site-loader site-loader--' . View::e($style) . '" role="status" aria-live="polite">'
            . '<div class="site-loader__inner">' . $visual
            . '<p class="site-loader__text">' . View::e($settings->string('loader.text')) . '</p></div></div>';
    }
}
