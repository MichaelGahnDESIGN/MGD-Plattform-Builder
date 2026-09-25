<?php

declare(strict_types=1);

namespace MGD\Starter\Site;

use MGD\Starter\Cms\CreditInput;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\SiteLayout;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * Öffentliche Seiten: Startseite, CMS-Seiten, Release Notes, Credits.
 */
final class SiteController
{
    public function __construct(private readonly App $app)
    {
    }

    public function home(Request $request): Response
    {
        return $this->renderPage('home', '/', true);
    }

    public function page(Request $request, array $params): Response
    {
        $slug = (string) $params['slug'];

        if ($slug === 'home') {
            return Response::redirect('/');
        }

        return $this->renderPage($slug, '/seite/' . $slug);
    }

    public function releaseNotes(Request $request): Response
    {
        if (!$this->app->settings()->bool('release_notes.public_enabled')) {
            throw new HttpException(404, 'Seite nicht gefunden.');
        }

        $timeline = (new ReleaseNotesTimeline($this->app->locale()))->render($this->app->releaseNotes()->grouped('frontend'), null, false);

        return Response::html((new SiteLayout($this->app))->render(
            'Release Notes',
            '<div class="container"><article class="page">' . Ui::pageHeader('Release Notes', '', 'Neuigkeiten und Änderungen, neueste Version zuerst.')
                . $timeline . '</article></div>',
            ['path' => '/release-notes']
        ));
    }

    public function credits(Request $request): Response
    {
        if (!$this->app->settings()->bool('credits.public_enabled')) {
            throw new HttpException(404, 'Seite nicht gefunden.');
        }

        $intro = $this->app->pages()->findBySlug('credits');
        $people = '';

        foreach ($this->app->credits()->people() as $person) {
            $name = $person['link_url'] !== ''
                ? '<a href="' . View::e($person['link_url']) . '" rel="noopener noreferrer">' . View::e($person['name']) . '</a>'
                : View::e($person['name']);
            $people .= '<li><span class="credit-role">' . View::e($person['role']) . '</span><span class="credit-name">' . $name . '</span></li>';
        }

        $body = '<div class="container"><article class="page">'
            . '<h1>' . View::e($intro['title'] ?? 'Credits') . '</h1>'
            . ($intro !== null ? '<div class="prose">' . $intro['content_html'] . '</div>' : '')
            . ($people !== '' ? '<section><h2>Mitwirkende</h2><ul class="credit-people">' . $people . '</ul></section>' : '')
            . $this->components() . '</article></div>';

        return Response::html((new SiteLayout($this->app))->render('Credits', $body, ['path' => '/credits']));
    }

    private function components(): string
    {
        $grouped = [];

        foreach ($this->app->credits()->components() as $component) {
            $grouped[$component['category']][] = $component;
        }

        $html = '';
        $showLogos = $this->app->settings()->bool('credits.show_logos');

        foreach (CreditInput::CATEGORIES as $category => $label) {
            if (!isset($grouped[$category])) {
                continue;
            }

            $cards = '';

            foreach ($grouped[$category] as $component) {
                $cards .= (new CreditCard())->render($component, $showLogos);
            }

            $html .= '<section><h2>' . View::e($label) . '</h2><div class="credit-grid">' . $cards . '</div></section>';
        }

        return $html;
    }

    private function renderPage(string $slug, string $path, bool $landing = false): Response
    {
        $page = $this->app->pages()->findBySlug($slug);

        if ($page === null || $page['page_type'] === 'snippet') {
            throw new HttpException(404, 'Seite nicht gefunden.');
        }

        $body = '<div class="container"><article class="page page--' . View::e($page['page_type']) . '">'
            . '<h1>' . View::e($page['title']) . '</h1>'
            . '<div class="prose">' . $page['content_html'] . '</div>'
            . ($page['page_type'] === 'legal' ? '<p class="muted page-updated">Stand: ' . View::e(View::date($page['updated_at'])) . '</p>' : '')
            . '</article></div>';

        return Response::html((new SiteLayout($this->app))->render(
            $landing ? '' : (string) $page['title'],
            $body,
            ['description' => (string) $page['meta_description'], 'path' => $path, 'landing' => $landing]
        ));
    }
}
