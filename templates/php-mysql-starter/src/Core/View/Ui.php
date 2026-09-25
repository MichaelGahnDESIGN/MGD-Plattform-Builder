<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

final class Ui
{
    public static function badge(string $text, string $variant = 'neutral'): string
    {
        $variant = preg_replace('/[^a-z0-9-]/', '', $variant) ?: 'neutral';

        return '<span class="badge badge-' . $variant . '">' . View::e($text) . '</span>';
    }

    /**
     * @param list<string> $tags
     */
    public static function chips(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        $html = '<ul class="chips">';

        foreach ($tags as $tag) {
            $html .= '<li class="chip">' . View::e($tag) . '</li>';
        }

        return $html . '</ul>';
    }

    public static function notice(string $message, string $kind = 'info'): string
    {
        $kind = in_array($kind, ['info', 'success', 'warning', 'danger'], true) ? $kind : 'info';
        $role = $kind === 'danger' ? 'alert' : 'status';

        return '<div class="notice notice-' . $kind . '" role="' . $role . '">' . View::e($message) . '</div>';
    }

    public static function emptyState(string $message): string
    {
        return '<p class="empty-state">' . View::e($message) . '</p>';
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows Zellen sind bereits escaptes HTML.
     */
    public static function table(array $headers, array $rows): string
    {
        $head = '';

        foreach ($headers as $header) {
            $head .= '<th scope="col">' . View::e($header) . '</th>';
        }

        $body = '';

        foreach ($rows as $row) {
            $body .= '<tr><td>' . implode('</td><td>', $row) . '</td></tr>';
        }

        return '<div class="table-wrap"><table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    public static function pageHeader(string $title, string $actionsHtml = '', string $intro = ''): string
    {
        $introHtml = $intro !== '' ? '<p class="lead">' . View::e($intro) . '</p>' : '';

        return '<div class="page-header"><div><h1>' . View::e($title) . '</h1>' . $introHtml . '</div>'
            . ($actionsHtml !== '' ? '<div class="page-actions">' . $actionsHtml . '</div>' : '') . '</div>';
    }

    public static function link(string $path, string $label, string $class = 'button'): string
    {
        return '<a class="' . View::e($class) . '" href="' . View::e(View::url($path)) . '">' . View::e($label) . '</a>';
    }
}
