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

final class PageRevisionsController extends AdminController
{
    public function index(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $page = $this->app->pages()->find((int) $params['id'], true) ?? throw new HttpException(404, 'Seite nicht gefunden.');
        $rows = [];

        foreach ($this->app->pages()->revisions((int) $page['id']) as $revision) {
            $current = (int) $revision['revision_no'] === (int) $page['current_revision'];
            $rows[] = [
                View::e((string) $revision['revision_no']) . ($current ? ' ' . Ui::badge('aktuell', 'success') : ''),
                View::e($revision['title']),
                View::e($revision['author_label']),
                View::e($revision['note']),
                View::e(View::date($revision['created_at'], 'd.m.Y H:i')),
                '<div class="row-actions">' . Ui::link('/admin/revisions/' . (int) $revision['id'], 'Ansehen', 'button button-small button-ghost')
                    . ($current ? '' : Form::action('/admin/revisions/' . (int) $revision['id'] . '/restore', 'Wiederherstellen', 'Diese Revision als neue Fassung wiederherstellen?', 'button button-small'))
                    . '</div>',
            ];
        }

        return $this->page(
            'Revisionen',
            Ui::pageHeader('Revisionen: ' . $page['title'], Ui::link('/admin/pages/' . (int) $page['id'] . '/edit', 'Zur Seite', 'button button-ghost'),
                'Wiederherstellen legt eine neue Revision an – die Historie bleibt vollständig erhalten.')
                . Ui::table(['Nr.', 'Titel', 'Autor', 'Notiz', 'Datum', ''], $rows),
            $user,
            '/admin/pages'
        );
    }

    public function show(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $revision = $this->app->pages()->revision((int) $params['id']) ?? throw new HttpException(404, 'Revision nicht gefunden.');
        $source = $revision['content_format'] === 'markdown'
            ? '<h2>Markdown-Quelle</h2><pre class="code-view">' . View::e((string) $revision['content_source']) . '</pre>'
            : '';

        return $this->page(
            'Revision ' . $revision['revision_no'],
            Ui::pageHeader(
                'Revision ' . $revision['revision_no'] . ': ' . $revision['title'],
                Ui::link('/admin/pages/' . (int) $revision['page_id'] . '/revisions', 'Alle Revisionen', 'button button-ghost')
                . Form::action('/admin/revisions/' . (int) $revision['id'] . '/restore', 'Wiederherstellen', 'Diese Revision wiederherstellen?')
            )
            . '<p class="muted">' . View::e($revision['author_label']) . ' · ' . View::e(View::date($revision['created_at'], 'd.m.Y H:i'))
            . ' · ' . View::e($revision['note']) . '</p>'
            . '<h2>Vorschau</h2><div class="card prose">' . $revision['content_html'] . '</div>'
            . '<h2>HTML</h2><pre class="code-view">' . View::e((string) $revision['content_html']) . '</pre>' . $source,
            $user,
            '/admin/pages'
        );
    }

    public function restore(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $revision = $this->app->pages()->revision((int) $params['id']) ?? throw new HttpException(404, 'Revision nicht gefunden.');

        try {
            $newRevision = $this->app->pages()->restoreRevision((int) $revision['id'], $user);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWith('/admin/pages/' . (int) $revision['page_id'] . '/revisions', 'danger', $exception->getMessage());
        }

        $this->audit($user, 'page.revision.restore', 'page', (int) $revision['page_id'], [
            'from_revision' => (int) $revision['revision_no'],
            'new_revision' => $newRevision,
        ]);

        return $this->redirectWith('/admin/pages/' . (int) $revision['page_id'] . '/revisions', 'success', 'Revision wiederhergestellt (neue Revision ' . $newRevision . ').');
    }
}
