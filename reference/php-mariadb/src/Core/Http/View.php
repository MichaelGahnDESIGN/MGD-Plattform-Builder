<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Http;

final class View
{
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function page(string $title, string $body, bool $authenticated = false): string
    {
        $nav = $authenticated
            ? '<nav><a href="/">Dashboard</a><a href="/accounts">Accounts</a><a href="/audit">Audit</a><a href="/security">Security</a><a href="/jobs">Jobs</a></nav>'
            : '';

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
