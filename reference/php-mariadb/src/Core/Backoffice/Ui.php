<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Backoffice;

use MGD\Platform\Core\Http\View;

final class Ui
{
    public static function badge(string $text): string
    {
        return '<span class="badge">' . View::e($text) . '</span>';
    }

    public static function notice(string $message, string $kind = 'ok'): string
    {
        $class = $kind === 'error' ? 'error' : 'ok';

        return '<div class="' . $class . '">' . View::e($message) . '</div>';
    }

    public static function emptyState(string $message): string
    {
        return '<p class="muted">' . View::e($message) . '</p>';
    }

    public static function table(array $headers, array $rows): string
    {
        $head = '';

        foreach ($headers as $header) {
            $head .= '<th>' . View::e((string) $header) . '</th>';
        }

        $body = '';

        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($row as $cell) {
                $body .= '<td>' . $cell . '</td>';
            }
            $body .= '</tr>';
        }

        return '<div class="panel"><table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }
}
