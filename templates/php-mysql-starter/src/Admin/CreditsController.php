<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Cms\CreditInput;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * Credits-Übersicht und Personen (Name, Rolle, Link, Sortierung).
 */
final class CreditsController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $credits = $this->app->credits();
        $people = [];
        $components = [];

        foreach ($credits->people() as $person) {
            $id = (int) $person['id'];
            $people[] = [
                View::e($person['name']),
                View::e($person['role']),
                View::e((string) $person['sort_order']),
                '<div class="row-actions">' . Ui::link('/admin/credits/people/' . $id . '/edit', 'Bearbeiten', 'button button-small')
                    . Form::action('/admin/credits/people/' . $id . '/delete', 'Löschen', 'Person entfernen?', 'button button-small button-danger') . '</div>',
            ];
        }

        foreach ($credits->components() as $component) {
            $id = $component['id'];
            $components[] = [
                View::e($component['name']),
                Ui::badge(CreditInput::CATEGORIES[$component['category']] ?? $component['category']),
                View::e($component['license']),
                Ui::chips($component['tags']),
                '<div class="row-actions">' . Ui::link('/admin/credits/components/' . $id . '/edit', 'Bearbeiten', 'button button-small')
                    . Form::action('/admin/credits/components/' . $id . '/delete', 'Löschen', 'Komponente entfernen?', 'button button-small button-danger') . '</div>',
            ];
        }

        $intro = $this->app->pages()->findBySlug('credits', false);
        $introLink = $intro !== null ? Ui::link('/admin/pages/' . (int) $intro['id'] . '/edit', 'Einleitungstext bearbeiten', 'button button-ghost') : '';

        return $this->page(
            'Credits',
            Ui::pageHeader('Credits', $introLink . Ui::link('/credits', 'Öffentliche Seite', 'button button-ghost'), 'Mitwirkende und verwendete Komponenten. Logos nur lokal – kein Hotlinking.')
                . '<section><div class="section-head"><h2>Personen &amp; Rollen</h2>' . Ui::link('/admin/credits/people/new', 'Person hinzufügen') . '</div>'
                . ($people === [] ? Ui::emptyState('Noch keine Personen eingetragen.') : Ui::table(['Name', 'Rolle', 'Sortierung', ''], $people)) . '</section>'
                . '<section><div class="section-head"><h2>Komponenten</h2>' . Ui::link('/admin/credits/components/new', 'Komponente hinzufügen') . '</div>'
                . ($components === [] ? Ui::emptyState('Noch keine Komponenten eingetragen.') : Ui::table(['Name', 'Kategorie', 'Lizenz', 'Tags', ''], $components)) . '</section>',
            $user,
            '/admin/credits'
        );
    }

    public function createPerson(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);

        if (!$request->isPost()) {
            return $this->personForm($user, '/admin/credits/people/new', []);
        }

        try {
            $data = (new CreditInput())->person($this->submitted($request));
            $id = $this->app->credits()->createPerson($data);
            $this->audit($user, 'credit.person.create', 'credit_person', $id, ['name' => $data['name']]);

            return $this->redirectWith('/admin/credits', 'success', 'Person hinzugefügt.');
        } catch (InvalidArgumentException $exception) {
            return $this->personForm($user, '/admin/credits/people/new', $this->submitted($request), $exception->getMessage());
        }
    }

    public function editPerson(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $id = (int) $params['id'];
        $person = $this->app->credits()->findPerson($id) ?? throw new HttpException(404, 'Person nicht gefunden.');
        $action = '/admin/credits/people/' . $id . '/edit';

        if (!$request->isPost()) {
            return $this->personForm($user, $action, $person);
        }

        try {
            $data = (new CreditInput())->person($this->submitted($request));
            $this->app->credits()->updatePerson($id, $data);
            $this->audit($user, 'credit.person.update', 'credit_person', $id, ['name' => $data['name']]);

            return $this->redirectWith('/admin/credits', 'success', 'Person gespeichert.');
        } catch (InvalidArgumentException $exception) {
            return $this->personForm($user, $action, $this->submitted($request), $exception->getMessage());
        }
    }

    public function deletePerson(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $person = $this->app->credits()->findPerson((int) $params['id']) ?? throw new HttpException(404, 'Person nicht gefunden.');
        $this->app->credits()->deletePerson((int) $person['id']);
        $this->audit($user, 'credit.person.delete', 'credit_person', (int) $person['id'], ['name' => $person['name']]);

        return $this->redirectWith('/admin/credits', 'success', 'Person entfernt.');
    }

    private function personForm(User $user, string $action, array $person, string $error = ''): Response
    {
        $body = Ui::pageHeader('Person')
            . ($error !== '' ? Ui::notice($error, 'danger') : '')
            . Form::open($action, ' class="stack"')
            . Form::text('name', 'Name', (string) ($person['name'] ?? ''), ' required maxlength="150"')
            . Form::text('role', 'Rolle', (string) ($person['role'] ?? ''), ' required maxlength="150"')
            . Form::text('link_url', 'Link (optional)', (string) ($person['link_url'] ?? ''), ' maxlength="500"')
            . Form::text('sort_order', 'Sortierung', (string) ($person['sort_order'] ?? '0'), '', 'number')
            . Form::submit('Speichern') . '</form>';

        return $this->page('Credits', $body, $user, '/admin/credits');
    }

    private function submitted(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'role' => $request->input('role'),
            'link_url' => $request->input('link_url'),
            'sort_order' => $request->input('sort_order', '0'),
        ];
    }
}
