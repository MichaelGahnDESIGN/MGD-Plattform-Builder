<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Moderator);
        $pages = $this->app->pages();
        $cards = [
            ['CMS-Seiten', count($pages->list()), '/admin/pages'],
            ['Im Papierkorb', count($pages->list('', '', true)), '/admin/pages/trash'],
            ['Release Notes', count($this->app->releaseNotes()->all()), '/admin/release-notes'],
            ['Komponenten in Credits', $this->app->credits()->componentCount(), '/admin/credits'],
        ];
        $html = '';

        foreach ($cards as [$label, $count, $href]) {
            $html .= '<a class="stat-card" href="' . View::e(View::url($href)) . '"><span class="stat-card__value">'
                . View::e((string) $count) . '</span><span class="stat-card__label">' . View::e($label) . '</span></a>';
        }

        $warnings = '';

        if ($this->app->settings()->bool('maintenance.enabled')) {
            $warnings .= Ui::notice('Der Wartungsmodus ist aktiv. Besucher sehen eine Wartungsmeldung.', 'warning');
        }

        if ($this->app->config->string('app.key') === 'change-me') {
            $warnings .= Ui::notice('app.key in config.php ist noch nicht gesetzt.', 'danger');
        }

        return $this->page(
            'Dashboard',
            Ui::pageHeader('Willkommen, ' . $user->displayName, '', 'Rolle: ' . $user->role->label())
                . $this->app->versionDisplay()->render('landing_private')
                . $warnings . '<div class="stat-grid">' . $html . '</div>',
            $user,
            '/admin'
        );
    }
}
