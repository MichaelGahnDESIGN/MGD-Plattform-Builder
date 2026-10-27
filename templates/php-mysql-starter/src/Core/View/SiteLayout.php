<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

use MGD\Starter\Core\App;

/**
 * Öffentliches Layout: Header, Navigation, Footer mit Rechtslinks, Cookie-Box.
 */
final class SiteLayout
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array{description?: string, path?: string, landing?: bool, status?: int} $options
     */
    public function render(string $pageTitle, string $body, array $options = []): string
    {
        $settings = $this->app->settings();
        $toggle = new ThemeToggle($settings);
        $version = $this->app->versionDisplay();
        $isLanding = (bool) ($options['landing'] ?? false);
        $landing = $isLanding ? $version->render('landing_public') : '';
        $canonicalBase = rtrim($settings->string('seo.canonical_base'), '/');
        $ogImage = $settings->string('seo.og_image');

        return (new Document($this->app))->render([
            'title' => $this->title($pageTitle),
            'area' => 'site',
            'description' => ($options['description'] ?? '') ?: $settings->string('seo.meta_description'),
            'robots' => $settings->string('seo.robots') === 'noindex' ? 'noindex, nofollow' : 'index, follow',
            'canonical' => $canonicalBase !== '' && isset($options['path']) ? $canonicalBase . View::url($options['path']) : '',
            'og_image' => $ogImage !== '' ? ($canonicalBase !== '' ? $canonicalBase : '') . View::url($ogImage) : '',
            'loader' => true,
            'body' => $this->header($toggle, $version->render('public_header'))
                . '<main id="main" class="site-main">' . $landing . $body
                    . ($isLanding ? '<div class="container">' . $this->app->poweredBy()->render('landing_public') . '</div>' : '')
                    . '</main>'
                . $this->footer($toggle, $version->render('public_footer'))
                . $toggle->render('floating')
                . $this->cookieBox(),
        ]);
    }

    private function title(string $pageTitle): string
    {
        $settings = $this->app->settings();
        $site = $settings->string('seo.site_title') ?: $settings->string('general.site_name');

        if ($pageTitle === '' || $pageTitle === $site) {
            return $site;
        }

        return strtr($settings->string('seo.title_pattern') ?: '{page} · {site}', ['{page}' => $pageTitle, '{site}' => $site]);
    }

    private function header(ThemeToggle $toggle, string $versionHtml): string
    {
        $settings = $this->app->settings();
        $slugs = array_filter(array_map('trim', explode(',', $settings->string('general.header_pages'))));
        $nav = '';

        foreach ($this->app->pages()->publishedTitles($slugs) as $slug => $title) {
            $href = $slug === 'home' ? '/' : '/seite/' . $slug;
            $nav .= '<li><a href="' . View::e(View::url($href)) . '">' . View::e($title) . '</a></li>';
        }

        return '<header class="site-header"><div class="container site-header__inner">'
            . '<a class="site-brand" href="' . View::e(View::url('/')) . '"><strong>' . View::e($settings->string('general.site_name')) . '</strong>'
            . '<span class="site-tagline">' . View::e($settings->string('general.tagline')) . '</span></a>'
            . '<nav class="site-nav" aria-label="Hauptnavigation"><ul>' . $nav . '</ul></nav>'
            . '<div class="site-header__tools">' . $versionHtml . $toggle->render('header') . '</div>'
            . '</div></header>';
    }

    private function footer(ThemeToggle $toggle, string $versionHtml): string
    {
        $settings = $this->app->settings();
        $links = '';

        foreach ($this->app->pages()->publishedTitles($settings->list('legal.footer_slugs')) as $slug => $title) {
            $links .= '<li><a href="' . View::e(View::url('/seite/' . $slug)) . '">' . View::e($title) . '</a></li>';
        }

        if ($settings->bool('release_notes.public_enabled') && $settings->bool('release_notes.footer_link')) {
            $links .= '<li><a href="' . View::e(View::url('/release-notes')) . '">Release Notes</a></li>';
        }

        if ($settings->bool('credits.public_enabled') && $settings->bool('credits.footer_link')) {
            $links .= '<li><a href="' . View::e(View::url('/credits')) . '">Credits</a></li>';
        }

        if ($settings->bool('cookie_box.enabled')) {
            $links .= '<li><button type="button" class="link-button" data-cookie-settings>Cookie-Hinweis</button></li>';
        }

        return '<footer class="site-footer"><div class="container site-footer__inner">'
            . '<nav aria-label="Rechtliches"><ul class="footer-links">' . $links . '</ul></nav>'
            . $this->withdrawalButton()
            . '<div class="site-footer__meta"><span>' . View::e($settings->string('general.footer_text')) . '</span>'
            . $versionHtml . $toggle->render('footer') . '</div>'
            . $this->app->poweredBy()->render('public_footer')
            . '</div></footer>';
    }

    private function withdrawalButton(): string
    {
        if (!$this->app->settings()->bool('legal.withdrawal_button')) {
            return '';
        }

        $snippet = $this->app->pages()->findBySlug('widerrufs-button-text');
        $label = $snippet !== null ? trim(strip_tags((string) $snippet['content_html'])) : '';

        return '<a class="button button-secondary withdrawal-button" href="' . View::e(View::url('/seite/widerruf')) . '">'
            . View::e($label !== '' ? $label : 'Vertrag widerrufen') . '</a>';
    }

    private function cookieBox(): string
    {
        $settings = $this->app->settings();

        if (!$settings->bool('cookie_box.enabled')) {
            return '';
        }

        $snippet = $this->app->pages()->findBySlug($settings->string('cookie_box.snippet_slug'));
        $text = $snippet !== null
            ? (string) $snippet['content_html']
            : '<p>Diese Website verwendet nur technisch notwendige Speicherungen.</p>';
        $policy = $settings->string('cookie_box.policy_slug');
        $buttons = $settings->bool('cookie_box.essential_only')
            ? '<button type="button" class="button" data-consent="essential">Verstanden</button>'
            : '<button type="button" class="button button-secondary" data-consent="essential">Nur essenzielle</button>'
                . '<button type="button" class="button" data-consent="all">Alle akzeptieren</button>';

        return '<aside id="cookie-box" class="cookie-box" hidden aria-label="Cookie-Hinweis">'
            . '<div class="cookie-box__text prose">' . $text . '</div>'
            . '<div class="cookie-box__actions">'
            . ($policy !== '' ? '<a href="' . View::e(View::url('/seite/' . $policy)) . '">Mehr erfahren</a>' : '')
            . $buttons . '</div></aside>';
    }
}
