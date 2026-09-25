<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Http;

use MGD\Platform\Core\Support\Version;

final class View
{
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Pflicht-Label nach MGD-Lizenz (siehe MGD-Lizenz.md). Nicht entfernen oder verändern.
     */
    public static function poweredBy(): string
    {
        return '<p class="mgd-powered-by"><a href="https://michael-gahn.de" target="_blank" rel="noopener">'
            . '<span>powered by:</span>'
            . '<img src="/brand/mgd-logo-light.svg" alt="Michael Gahn DESIGN" width="152" height="20"></a></p>';
    }

    public static function page(string $title, string $body, array $capabilities = []): string
    {
        $links = [
            ['/', 'Dashboard', null],
            ['/accounts', 'Accounts', 'accounts.suspend'],
            ['/translations', 'Translations', 'translations.read'],
            ['/service-principals', 'Agents / API', 'service-principals.read'],
            ['/audit', 'Audit', 'security.audit.read'],
            ['/security', 'Security', 'security.audit.read'],
            ['/jobs', 'Jobs', 'jobs.read'],
        ];

        $nav = '';

        if ($capabilities !== []) {
            $items = [];

            foreach ($links as [$href, $label, $required]) {
                if ($required === null || in_array($required, $capabilities, true)) {
                    $items[] = '<a href="' . self::e($href) . '">' . self::e($label) . '</a>';
                }
            }

            $nav = '<nav>' . implode('', $items) . '</nav>';
        }

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . self::e($title) . ' · MGD Platform Reference</title>
<link rel="stylesheet" href="/app.css">
</head>
<body>
<header><strong>MGD Platform Reference</strong>' . $nav . '</header>
<main>' . $body . '</main>
<footer class="muted">MGD Platform Reference · Version ' . self::e(Version::label()) . self::poweredBy() . '</footer>
</body>
</html>';
    }
}
