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

/**
 * Verwendete Komponenten: KI-Systeme, Tools, Plugins, Bibliotheken, Schriften, Icons usw.
 */
final class CreditComponentsController extends AdminController
{
    public function create(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);

        if (!$request->isPost()) {
            return $this->form($user, '/admin/credits/components/new', ['commercial_use' => 'unknown', 'category' => 'library']);
        }

        try {
            $data = (new CreditInput())->component($this->submitted($request));
            $id = $this->app->credits()->createComponent($data);
            $this->audit($user, 'credit.component.create', 'credit_component', $id, ['name' => $data['name']]);

            return $this->redirectWith('/admin/credits', 'success', 'Komponente hinzugefügt.');
        } catch (InvalidArgumentException $exception) {
            return $this->form($user, '/admin/credits/components/new', $this->submitted($request), $exception->getMessage());
        }
    }

    public function edit(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $id = (int) $params['id'];
        $component = $this->app->credits()->findComponent($id) ?? throw new HttpException(404, 'Komponente nicht gefunden.');
        $action = '/admin/credits/components/' . $id . '/edit';

        if (!$request->isPost()) {
            return $this->form($user, $action, $component);
        }

        try {
            $data = (new CreditInput())->component($this->submitted($request));
            $this->app->credits()->updateComponent($id, $data);
            $this->audit($user, 'credit.component.update', 'credit_component', $id, ['name' => $data['name']]);

            return $this->redirectWith('/admin/credits', 'success', 'Komponente gespeichert.');
        } catch (InvalidArgumentException $exception) {
            return $this->form($user, $action, $this->submitted($request), $exception->getMessage());
        }
    }

    public function delete(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $component = $this->app->credits()->findComponent((int) $params['id']) ?? throw new HttpException(404, 'Komponente nicht gefunden.');
        $this->app->credits()->deleteComponent($component['id']);
        $this->audit($user, 'credit.component.delete', 'credit_component', $component['id'], ['name' => $component['name']]);

        return $this->redirectWith('/admin/credits', 'success', 'Komponente entfernt.');
    }

    private function form(User $user, string $action, array $component, string $error = ''): Response
    {
        $links = $component['links'] ?? '';
        $linksText = is_array($links)
            ? implode("\n", array_map(static fn (array $l): string => ($l['label'] ?? '') . ' | ' . ($l['url'] ?? ''), array_filter($links, 'is_array')))
            : (string) $links;
        $tags = $component['tags'] ?? '';
        $body = Ui::pageHeader('Komponente')
            . ($error !== '' ? Ui::notice($error, 'danger') : '')
            . Form::open($action, ' class="stack"') . '<div class="grid-2">'
            . Form::text('name', 'Name', (string) ($component['name'] ?? ''), ' required maxlength="150"')
            . Form::select('category', 'Kategorie', CreditInput::CATEGORIES, (string) ($component['category'] ?? 'other'))
            . Form::text('logo_path', 'Logo/Icon (lokaler Pfad)', (string) ($component['logo_path'] ?? ''), ' placeholder="/assets/… oder /uploads/…" maxlength="300"')
            . Form::text('version', 'Version', (string) ($component['version'] ?? ''), ' maxlength="50"')
            . Form::text('provider_name', 'Anbieter', (string) ($component['provider_name'] ?? ''), ' maxlength="150"')
            . Form::text('license', 'Lizenz (SPDX, z. B. MIT)', (string) ($component['license'] ?? ''), ' maxlength="100"')
            . Form::select('commercial_use', 'Kommerzielle Nutzung', CreditInput::COMMERCIAL_USE, (string) ($component['commercial_use'] ?? 'unknown'))
            . Form::text('sort_order', 'Sortierung', (string) ($component['sort_order'] ?? '0'), '', 'number')
            . '</div>'
            . Form::textarea('description', 'Beschreibung', (string) ($component['description'] ?? ''), ' rows="3"')
            . Form::textarea('provider_info', 'Anbieterhinweis / Pflichtangabe zur Namensnennung', (string) ($component['provider_info'] ?? ''), ' rows="3"')
            . Form::textarea('links', 'Links (eine Zeile pro Link: Bezeichnung | https://…)', $linksText, ' rows="4"')
            . Form::text('tags', 'Tags (kommagetrennt, z. B. MIT, Kommerziell erlaubt, Attribution erforderlich)', is_array($tags) ? implode(', ', $tags) : (string) $tags)
            . Form::checkbox('attribution_required', 'Namensnennung erforderlich', (bool) ($component['attribution_required'] ?? false))
            . Form::checkbox('locally_embedded', 'Lokal eingebettet', (bool) ($component['locally_embedded'] ?? false))
            . Form::textarea('notes', 'Interne Notizen', (string) ($component['notes'] ?? ''), ' rows="3"')
            . Form::submit('Speichern') . '</form>';

        return $this->page('Credits', $body, $user, '/admin/credits');
    }

    private function submitted(Request $request): array
    {
        $fields = ['name', 'category', 'logo_path', 'version', 'provider_name', 'license', 'commercial_use', 'sort_order',
            'description', 'provider_info', 'links', 'tags', 'attribution_required', 'locally_embedded', 'notes'];
        $data = [];

        foreach ($fields as $field) {
            $data[$field] = $request->input($field);
        }

        return $data;
    }
}
