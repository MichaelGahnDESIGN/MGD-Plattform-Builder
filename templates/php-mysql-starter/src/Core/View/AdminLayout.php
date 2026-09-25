<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;

/**
 * Backoffice-Layout. Menüpunkte werden nur angezeigt, wenn die Rolle reicht –
 * die eigentliche Prüfung erfolgt zusätzlich serverseitig in jedem Controller.
 */
final class AdminLayout
{
    private const NAVIGATION = [
        ['Übersicht', [['/admin', 'Dashboard', Role::Moderator], ['/admin/account', 'Mein Konto', Role::Moderator]]],
        ['Inhalte', [
            ['/admin/pages', 'CMS-Seiten', Role::Moderator],
            ['/admin/pages/trash', 'Papierkorb', Role::Editor],
            ['/admin/pages/transfer', 'Import / Export', Role::Editor],
            ['/admin/media', 'Medien', Role::Editor],
        ]],
        ['Einstellungen', [
            ['/admin/settings', 'Einstellungen', Role::Admin],
            ['/admin/users', 'Benutzer', Role::Admin],
            ['/admin/release-notes', 'Release Notes', Role::Editor],
            ['/admin/credits', 'Credits', Role::Editor],
            ['/admin/files', 'Dateispeicherorte', Role::Admin],
            ['/admin/code', 'Code-Editoren', Role::Admin],
            ['/admin/updater', 'Updater', Role::Admin],
            ['/admin/audit', 'Audit-Log', Role::Admin],
            ['/admin/modules', 'Module', Role::Admin],
            ['/admin/license', 'Lizenz', Role::Moderator],
        ]],
    ];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array{scripts?: list<string>, styles?: list<string>} $assets
     */
    public function render(string $title, string $body, User $user, string $active = '', array $assets = []): string
    {
        $this->app->headers->allowInlineStyles();
        $settings = $this->app->settings();
        $toggle = new ThemeToggle($settings);
        $siteName = $settings->string('general.site_name');

        return (new Document($this->app))->render([
            'title' => $title . ' · Backoffice · ' . $siteName,
            'area' => 'admin',
            'scripts' => $assets['scripts'] ?? [],
            'styles' => $assets['styles'] ?? [],
            'body' => '<div class="admin-shell">'
                . '<aside class="admin-sidebar"><a class="admin-brand" href="' . View::e(View::url('/admin')) . '">'
                . View::e($siteName) . '<small>Backoffice</small></a>' . $this->navigation($user, $active) . '</aside>'
                . '<div class="admin-content"><header class="admin-topbar"><span>' . View::e($user->displayName)
                . ' · ' . View::e($user->role->label()) . '</span><div class="admin-topbar__tools">'
                . Ui::link('/', 'Website ansehen', 'button button-ghost')
                . $toggle->render('settings')
                . Form::open('/logout', ' class="inline-form"') . Form::submit('Abmelden', 'button button-secondary') . '</form>'
                . '</div></header>'
                . '<main id="main" class="admin-main">' . Flash::render() . $this->licenseNotice($user) . $body . '</main>'
                . '<footer class="admin-footer">' . $this->app->versionDisplay()->render('backoffice_footer')
                . $this->app->poweredBy()->render('backoffice_footer') . '</footer>'
                . '</div></div>',
        ]);
    }

    /**
     * Warnt Admins, wenn Lizenztext, Label oder Logos verändert wurden (ohne Whitelabel-Lizenz).
     */
    private function licenseNotice(User $user): string
    {
        if (!$user->can(Role::Admin) || $this->app->licenses()->isWhitelabel()) {
            return '';
        }

        $violations = $this->app->licenseIntegrity()->violations();

        if ($violations === []) {
            return '';
        }

        return Ui::notice('Lizenzverstoß: Folgende Dateien des Pflicht-Labels bzw. der MGD-Lizenz wurden verändert oder fehlen: '
            . implode(', ', $violations) . '. Bitte wiederherstellen oder eine Whitelabel-Lizenz hinterlegen.', 'danger');
    }

    private function navigation(User $user, string $active): string
    {
        $html = '<nav aria-label="Backoffice">';

        foreach (self::NAVIGATION as [$group, $items]) {
            $links = '';

            foreach ($items as [$href, $label, $role]) {
                if (!$user->can($role)) {
                    continue;
                }

                $current = $href === $active ? ' aria-current="page"' : '';
                $links .= '<li><a href="' . View::e(View::url($href)) . '"' . $current . '>' . View::e($label) . '</a></li>';
            }

            if ($links !== '') {
                $html .= '<p class="admin-nav__group">' . View::e($group) . '</p><ul>' . $links . '</ul>';
            }
        }

        $moduleLinks = '';

        foreach ($this->app->modules()->menu() as $item) {
            if ($user->can($item['role'])) {
                $current = $item['href'] === $active ? ' aria-current="page"' : '';
                $moduleLinks .= '<li><a href="' . View::e(View::url($item['href'])) . '"' . $current . '>' . View::e($item['label']) . '</a></li>';
            }
        }

        if ($moduleLinks !== '') {
            $html .= '<p class="admin-nav__group">Module</p><ul>' . $moduleLinks . '</ul>';
        }

        return $html . '</nav>';
    }

    /**
     * Minimales Layout für Login und Installer (ohne Navigation).
     */
    public function bare(string $title, string $body, string $toggleLocation = 'login'): string
    {
        $this->app->headers->allowInlineStyles();

        return (new Document($this->app))->render([
            'title' => $title . ' · ' . $this->app->settings()->string('general.site_name'),
            'area' => 'login',
            'body' => '<main id="main" class="auth-main"><div class="auth-card">' . Flash::render() . $body
                . '<div class="auth-card__footer">' . $this->app->versionDisplay()->render('login')
                . (new ThemeToggle($this->app->settings()))->render($toggleLocation) . '</div>'
                . $this->app->poweredBy()->render('login') . '</div></main>',
        ]);
    }
}
