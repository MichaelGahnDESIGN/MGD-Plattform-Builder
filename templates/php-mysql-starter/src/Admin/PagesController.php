<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Cms\PageInput;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class PagesController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Moderator);
        $query = mb_substr($request->query('q'), 0, 100);
        $type = $request->query('type');
        $canEdit = $user->can(Role::Editor);
        $rows = [];

        foreach ($this->app->pages()->list($query, $type) as $page) {
            $id = (int) $page['id'];
            $actions = $canEdit
                ? Ui::link('/admin/pages/' . $id . '/edit', 'Bearbeiten', 'button button-small')
                    . Ui::link('/admin/pages/' . $id . '/revisions', 'Revisionen', 'button button-small button-ghost')
                : '';
            $rows[] = [
                '<a href="' . View::e(View::url($page['slug'] === 'home' ? '/' : '/seite/' . $page['slug'])) . '">' . View::e($page['title']) . '</a>',
                '<code>' . View::e($page['slug']) . '</code>',
                Ui::badge(PageInput::TYPES[$page['page_type']] ?? $page['page_type']),
                Ui::badge(PageInput::STATUSES[$page['status']] ?? $page['status'], $page['status'] === 'published' ? 'success' : 'warning'),
                View::e((string) $page['current_revision']),
                View::e(View::date($page['updated_at'], 'd.m.Y H:i')),
                '<div class="row-actions">' . $actions . '</div>',
            ];
        }

        $filter = '<form method="get" class="filter-bar" action="' . View::e(View::url('/admin/pages')) . '">'
            . Form::text('q', 'Suche', $query, ' type="search" placeholder="Titel oder Slug"')
            . Form::select('type', 'Typ', ['' => 'Alle Typen', ...PageInput::TYPES], $type)
            . Form::submit('Filtern', 'button button-secondary') . '</form>';
        $actions = $canEdit ? Ui::link('/admin/pages/new', 'Neue Seite') : '';

        return $this->page(
            'CMS-Seiten',
            Ui::pageHeader('CMS-Seiten', $actions, 'Seiten, Rechtstexte und Snippets. Jede Speicherung erzeugt eine Revision.')
                . $filter . ($rows === [] ? Ui::emptyState('Keine Seiten gefunden.') : Ui::table(
                    ['Titel', 'Slug', 'Typ', 'Status', 'Rev.', 'Geändert', ''],
                    $rows
                )),
            $user,
            '/admin/pages'
        );
    }

    public function create(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);

        if (!$request->isPost()) {
            return $this->form($user, '/admin/pages/new', 'Neue Seite', []);
        }

        try {
            $data = $this->validated($request);
            $id = $this->app->pages()->create($data, $user, $request->input('note') ?: 'Erstellt');
            $this->audit($user, 'page.create', 'page', $id, ['slug' => $data['slug']]);

            return $this->redirectWith('/admin/pages/' . $id . '/edit', 'success', 'Seite angelegt.');
        } catch (InvalidArgumentException $exception) {
            return $this->form($user, '/admin/pages/new', 'Neue Seite', $this->submitted($request), $exception->getMessage());
        }
    }

    public function edit(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $id = (int) $params['id'];
        $page = $this->app->pages()->find($id) ?? throw new HttpException(404, 'Seite nicht gefunden.');
        $action = '/admin/pages/' . $id . '/edit';

        if (!$request->isPost()) {
            return $this->form($user, $action, 'Seite bearbeiten', $page);
        }

        try {
            $data = $this->validated($request);
            $revision = $this->app->pages()->update($id, $data, $user, $request->input('note'));
            $this->audit($user, 'page.update', 'page', $id, ['slug' => $data['slug'], 'revision' => $revision]);

            return $this->redirectWith($action, 'success', 'Gespeichert als Revision ' . $revision . '.');
        } catch (InvalidArgumentException $exception) {
            return $this->form($user, $action, 'Seite bearbeiten', [...$page, ...$this->submitted($request)], $exception->getMessage());
        }
    }

    public function delete(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $id = (int) $params['id'];
        $page = $this->app->pages()->find($id) ?? throw new HttpException(404, 'Seite nicht gefunden.');
        $this->app->pages()->softDelete($id, $user);
        $this->audit($user, 'page.trash', 'page', $id, ['slug' => $page['slug']]);

        return $this->redirectWith('/admin/pages', 'success', 'Seite in den Papierkorb verschoben. Sie kann dort wiederhergestellt werden.');
    }

    private function form(User $user, string $action, string $title, array $page, string $error = ''): Response
    {
        $form = (new PageForm($this->app))->render($action, $page, $error);
        $id = (int) ($page['id'] ?? 0);
        $actions = $id > 0
            ? Ui::link('/admin/pages/' . $id . '/revisions', 'Revisionen', 'button button-ghost')
                . Form::action('/admin/pages/' . $id . '/delete', 'In den Papierkorb', 'Seite in den Papierkorb verschieben?')
            : '';

        return $this->page($title, Ui::pageHeader($title, $actions) . $form['html'], $user, '/admin/pages', $form['assets']);
    }

    private function validated(Request $request): array
    {
        if ($this->app->settings()->bool('cms_editor.revision_note_required') && trim($request->input('note')) === '') {
            throw new InvalidArgumentException('Bitte eine Änderungsnotiz angeben.');
        }

        return $this->app->pageInput()->normalize($this->submitted($request));
    }

    private function submitted(Request $request): array
    {
        return [
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'page_type' => $request->input('page_type'),
            'status' => $request->input('status'),
            'content_format' => $request->input('content_format', 'html'),
            'content_html' => $request->input('content'),
            'content_source' => $request->input('content'),
            'meta_description' => $request->input('meta_description'),
        ];
    }
}
