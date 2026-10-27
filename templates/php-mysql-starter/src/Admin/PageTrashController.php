<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class PageTrashController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $rows = [];

        foreach ($this->app->pages()->list('', '', true) as $page) {
            $id = (int) $page['id'];
            $purge = $user->can(Role::Admin)
                ? Form::action('/admin/pages/' . $id . '/purge', 'Endgültig löschen', 'Seite "' . $page['title'] . '" inkl. aller Revisionen endgültig löschen? Dies kann nicht rückgängig gemacht werden.', 'button button-small button-danger')
                : '';
            $rows[] = [
                View::e($page['title']),
                '<code>' . View::e($page['slug']) . '</code>',
                View::e(View::date($page['deleted_at'], 'd.m.Y H:i')),
                '<div class="row-actions">' . Form::action('/admin/pages/' . $id . '/restore', 'Wiederherstellen', '', 'button button-small') . $purge . '</div>',
            ];
        }

        return $this->page(
            'Papierkorb',
            Ui::pageHeader('Papierkorb', '', 'Gelöschte Seiten können wiederhergestellt werden. Endgültiges Löschen ist nur für Admins möglich.')
                . ($rows === [] ? Ui::emptyState('Der Papierkorb ist leer.') : Ui::table(['Titel', 'Slug', 'Gelöscht am', ''], $rows)),
            $user,
            '/admin/pages/trash'
        );
    }

    public function restore(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $id = (int) $params['id'];
        $page = $this->app->pages()->find($id, true) ?? throw new HttpException(404, 'Seite nicht gefunden.');
        $this->app->pages()->restore($id);
        $this->audit($user, 'page.restore', 'page', $id, ['slug' => $page['slug']]);

        return $this->redirectWith('/admin/pages/trash', 'success', 'Seite wiederhergestellt.');
    }

    public function purge(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $id = (int) $params['id'];
        $page = $this->app->pages()->find($id, true) ?? throw new HttpException(404, 'Seite nicht gefunden.');

        try {
            $this->app->pages()->purge($id);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWith('/admin/pages/trash', 'danger', $exception->getMessage());
        }

        $this->audit($user, 'page.purge', 'page', $id, ['slug' => $page['slug'], 'title' => $page['title']]);

        return $this->redirectWith('/admin/pages/trash', 'success', 'Seite endgültig gelöscht.');
    }
}
