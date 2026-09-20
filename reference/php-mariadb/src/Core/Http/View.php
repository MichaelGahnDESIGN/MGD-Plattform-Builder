<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Http;

final class View
{
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function page(string $title, string $body, array $capabilities = []): string
    {
        $links = [
            ['/', 'Dashboard', null],
            ['/accounts', 'Accounts', 'accounts.suspend'],
            ['/audit', 'Audit', 'security.audit.read'],
            ['/security', 'Security', 'security.audit.read'],
            ['/jobs', 'Jobs', 'security.audit.read'],
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
</body>
</html>';
    }
}
