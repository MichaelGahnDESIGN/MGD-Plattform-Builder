<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\UserRepository;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\View;

/**
 * Wiederverwendbare Bausteine der Benutzerverwaltung (Filter, Felder, Seitennavigation).
 */
final class UserViews
{
    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $options = [];

        foreach (array_reverse(Role::cases()) as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    /**
     * @param array{q: string, role: string, status: string} $filters
     */
    public static function filter(string $path, array $filters): string
    {
        return '<form method="get" class="filter-bar" action="' . View::e(View::url($path)) . '">'
            . Form::text('q', 'Suche', $filters['q'], ' type="search" placeholder="Name oder E-Mail"')
            . Form::select('role', 'Rolle', ['' => 'Alle Rollen', ...self::roleOptions()], $filters['role'])
            . Form::select('status', 'Status', ['' => 'Alle', ...UserRepository::STATUSES], $filters['status'])
            . Form::submit('Filtern', 'button button-secondary') . '</form>';
    }

    public static function profileFields(array $values, bool $withStatus = false): string
    {
        return '<div class="grid-2">'
            . Form::text('display_name', 'Anzeigename', (string) ($values['display_name'] ?? ''), ' required maxlength="120"')
            . Form::text('email', 'E-Mail', (string) ($values['email'] ?? ''), ' required maxlength="254" autocomplete="off"', 'email')
            . Form::select('role', 'Rolle', self::roleOptions(), (string) ($values['role'] ?? Role::Editor->value))
            . ($withStatus ? Form::select('status', 'Status', UserRepository::STATUSES, (string) ($values['status'] ?? 'active')) : '')
            . '</div>';
    }

    public static function meta(array $user): string
    {
        return '<dl class="meta-list"><dt>Angelegt</dt><dd>' . View::e(View::date($user['created_at'] ?? null, 'd.m.Y H:i')) . '</dd>'
            . '<dt>Letzter Login</dt><dd>' . View::e(View::date($user['last_login_at'] ?? null, 'd.m.Y H:i') ?: '–') . '</dd>'
            . '<dt>Passwort geändert</dt><dd>' . View::e(View::date($user['password_changed_at'] ?? null, 'd.m.Y H:i') ?: '–') . '</dd>'
            . '<dt>Einmal-Passwort aktiv</dt><dd>' . ((int) ($user['must_change_password'] ?? 0) === 1 ? 'ja' : 'nein') . '</dd></dl>';
    }

    /**
     * @param array<string, string> $filters
     */
    public static function pagination(string $path, array $filters, int $page, int $total, int $perPage): string
    {
        $pages = (int) max(1, ceil($total / $perPage));

        if ($pages <= 1) {
            return '<p class="muted">' . $total . ' Konto/Konten</p>';
        }

        $link = static function (int $target, string $label) use ($path, $filters): string {
            $query = http_build_query(array_filter([...$filters, 'page' => $target > 1 ? (string) $target : '']));

            return '<a class="button button-small button-ghost" href="' . View::e(View::url($path . ($query !== '' ? '?' . $query : ''))) . '">' . View::e($label) . '</a>';
        };

        return '<nav class="row-actions" aria-label="Seiten">'
            . ($page > 1 ? $link($page - 1, '← Zurück') : '')
            . '<span class="muted">Seite ' . $page . ' von ' . $pages . ' (' . $total . ' Konten)</span>'
            . ($page < $pages ? $link($page + 1, 'Weiter →') : '')
            . '</nav>';
    }
}
