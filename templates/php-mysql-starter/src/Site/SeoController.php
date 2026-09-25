<?php

declare(strict_types=1);

namespace MGD\Starter\Site;

use MGD\Starter\Core\App;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\View;

final class SeoController
{
    public function __construct(private readonly App $app)
    {
    }

    public function sitemap(Request $request): Response
    {
        $base = $this->baseUrl();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        if ($this->app->settings()->string('seo.robots') !== 'noindex') {
            foreach ($this->app->pages()->publishedForSitemap() as $page) {
                $path = $page['slug'] === 'home' ? '/' : '/seite/' . $page['slug'];
                $lastmod = View::date((string) $page['updated_at'], 'Y-m-d');
                $xml .= '<url><loc>' . htmlspecialchars($base . View::url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
                    . ($lastmod !== '' ? '<lastmod>' . $lastmod . '</lastmod>' : '') . '</url>';
            }
        }

        return Response::text($xml . '</urlset>', 'application/xml; charset=utf-8');
    }

    public function robots(Request $request): Response
    {
        if ($this->app->settings()->string('seo.robots') === 'noindex') {
            return Response::text("User-agent: *\nDisallow: /\n");
        }

        return Response::text(
            "User-agent: *\nDisallow: " . View::url('/admin') . "\nDisallow: " . View::url('/login') . "\n\nSitemap: "
            . $this->baseUrl() . View::url('/sitemap.xml') . "\n"
        );
    }

    private function baseUrl(): string
    {
        $configured = rtrim($this->app->settings()->string('seo.canonical_base'), '/');

        if ($configured !== '') {
            return $configured;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host) === 1 ? $host : 'localhost';
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';

        return ($https ? 'https://' : 'http://') . $host;
    }
}
