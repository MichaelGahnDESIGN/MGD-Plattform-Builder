<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Cms\ReleaseNoteInput;
use MGD\Starter\Cms\ReleaseNoteSync;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\Version\VersionStatus;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use MGD\Starter\Site\ReleaseNotesTimeline;

final class ReleaseNotesController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $audience = array_key_exists($request->query('audience'), ReleaseNoteInput::AUDIENCES) ? $request->query('audience') : '';
        $filter = '<form method="get" class="filter-bar" action="' . View::e(View::url('/admin/release-notes')) . '">'
            . Form::select('audience', 'Zielgruppe', ['' => 'Alle Zielgruppen', ...ReleaseNoteInput::AUDIENCES], $audience)
            . Form::submit('Filtern', 'button button-secondary') . '</form>';
        $actions = Ui::link('/admin/release-notes/new', 'Neuer Eintrag')
            . Form::action('/admin/release-notes/sync', 'Aus release-notes.json synchronisieren', '', 'button button-secondary');
        $timeline = (new ReleaseNotesTimeline($this->app->locale()))->render(
            $this->app->releaseNotes()->grouped($audience),
            static fn (array $entry): string => Ui::link('/admin/release-notes/' . $entry['id'] . '/edit', 'Bearbeiten', 'button button-small button-ghost')
                . Form::action('/admin/release-notes/' . $entry['id'] . '/delete', 'Löschen', 'Eintrag löschen?', 'button button-small button-danger')
        );

        return $this->page(
            'Release Notes',
            Ui::pageHeader('Release Notes', $actions, 'Öffentlich (/release-notes) erscheinen nur Einträge mit Zielgruppe "Frontend".')
                . $filter . $timeline,
            $user,
            '/admin/release-notes'
        );
    }

    public function create(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $version = $this->app->version();
        $defaults = ['version' => $version->number, 'status' => $version->status->value, 'date' => gmdate('Y-m-d'), 'type' => 'feature', 'audience' => ['frontend']];

        if (!$request->isPost()) {
            return $this->form($user, '/admin/release-notes/new', $defaults);
        }

        try {
            $data = (new ReleaseNoteInput())->normalize($this->submitted($request));
            $id = $this->app->releaseNotes()->create($data);
            $this->audit($user, 'release_note.create', 'release_note', $id, ['version' => $data['version']]);

            return $this->redirectWith('/admin/release-notes', 'success', 'Eintrag angelegt.');
        } catch (InvalidArgumentException $exception) {
            return $this->form($user, '/admin/release-notes/new', $this->submitted($request), $exception->getMessage());
        }
    }

    public function edit(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $id = (int) $params['id'];
        $entry = $this->app->releaseNotes()->find($id) ?? throw new HttpException(404, 'Eintrag nicht gefunden.');
        $action = '/admin/release-notes/' . $id . '/edit';

        if (!$request->isPost()) {
            return $this->form($user, $action, $entry);
        }

        try {
            $data = (new ReleaseNoteInput())->normalize($this->submitted($request));
            $this->app->releaseNotes()->update($id, $data);
            $this->audit($user, 'release_note.update', 'release_note', $id, ['version' => $data['version']]);

            return $this->redirectWith('/admin/release-notes', 'success', 'Eintrag gespeichert.');
        } catch (InvalidArgumentException $exception) {
            return $this->form($user, $action, $this->submitted($request), $exception->getMessage());
        }
    }

    public function delete(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $entry = $this->app->releaseNotes()->find((int) $params['id']) ?? throw new HttpException(404, 'Eintrag nicht gefunden.');
        $this->app->releaseNotes()->delete($entry['id']);
        $this->audit($user, 'release_note.delete', 'release_note', $entry['id'], ['version' => $entry['version'], 'title' => $entry['title']]);

        return $this->redirectWith('/admin/release-notes', 'success', 'Eintrag gelöscht.');
    }

    public function sync(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);

        try {
            $summary = (new ReleaseNoteSync($this->app->releaseNotes()))->syncFile($this->app->config->path('release_notes_file'));
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWith('/admin/release-notes', 'danger', $exception->getMessage());
        }

        $this->audit($user, 'release_note.sync', 'release_note', null, [...$summary, 'errors' => count($summary['errors'])]);
        $message = 'Synchronisiert: ' . $summary['created'] . ' neu, ' . $summary['updated'] . ' aktualisiert, '
            . $summary['unchanged'] . ' unverändert' . ($summary['errors'] !== [] ? ', Fehler: ' . implode(' | ', array_slice($summary['errors'], 0, 5)) : '') . '.';

        return $this->redirectWith('/admin/release-notes', $summary['errors'] === [] ? 'success' : 'warning', $message);
    }

    private function form(User $user, string $action, array $entry, string $error = ''): Response
    {
        $items = $entry['items'] ?? [];
        $body = Ui::pageHeader($action === '/admin/release-notes/new' ? 'Neuer Release-Note-Eintrag' : 'Release-Note bearbeiten')
            . ($error !== '' ? Ui::notice($error, 'danger') : '')
            . Form::open($action, ' class="stack"') . '<div class="grid-2">'
            . Form::text('version', 'Version (MAJOR.MINOR.PATCH)', (string) ($entry['version'] ?? ''), ' required pattern="\d+\.\d+\.\d+"')
            . Form::select('status', 'Status', VersionStatus::options($this->app->locale()), (string) ($entry['status'] ?? ''))
            . Form::text('date', 'Datum', (string) ($entry['date'] ?? ''), ' required', 'date')
            . Form::select('type', 'Typ', ReleaseNoteInput::TYPES, (string) ($entry['type'] ?? ''))
            . '</div>'
            . Form::text('title', 'Titel', (string) ($entry['title'] ?? ''), ' required maxlength="190"')
            . Form::textarea('items', 'Punkte (einer pro Zeile)', is_array($items) ? implode("\n", $items) : (string) $items, ' rows="8"')
            . Form::checkboxes('audience', 'Zielgruppen', ReleaseNoteInput::AUDIENCES, is_array($entry['audience'] ?? null) ? $entry['audience'] : [])
            . Form::submit('Speichern') . '</form>';

        return $this->page('Release Notes', $body, $user, '/admin/release-notes');
    }

    private function submitted(Request $request): array
    {
        return [
            'version' => $request->input('version'),
            'status' => $request->input('status'),
            'date' => $request->input('date'),
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'items' => $request->input('items'),
            'audience' => $request->inputList('audience'),
        ];
    }
}
