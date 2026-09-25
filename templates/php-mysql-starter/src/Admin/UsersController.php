<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\PasswordPolicy;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Auth\UserManager;
use MGD\Starter\Core\Auth\UserRepository;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use Throwable;

/**
 * Benutzerverwaltung (nur Admins). Einmal-Passwörter werden genau einmal angezeigt
 * (direkt in der Antwort, nicht in der Session) und müssen beim ersten Login geändert werden.
 */
final class UsersController extends AdminController
{
    private const PATH = '/admin/users';
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $filters = [
            'q' => mb_substr($request->query('q'), 0, 100),
            'role' => Role::tryFrom($request->query('role'))?->value ?? '',
            'status' => array_key_exists($request->query('status'), UserRepository::STATUSES) ? $request->query('status') : '',
        ];
        $result = $this->app->users()->list($filters['q'], $filters['role'], $filters['status'], max(1, (int) $request->query('page', '1')), self::PER_PAGE);
        $rows = array_map(fn (array $row): array => $this->row($row, $user), $result['rows']);

        return $this->page(
            'Benutzer',
            Ui::pageHeader('Benutzer', Ui::link(self::PATH . '/new', 'Neues Konto'), 'Konten, Rollen und Zugänge verwalten. Der letzte aktive Admin ist geschützt.')
                . UserViews::filter(self::PATH, $filters)
                . ($rows === [] ? Ui::emptyState('Keine Konten gefunden.') : Ui::table(['Name', 'E-Mail', 'Rolle', 'Status', 'Letzter Login', ''], $rows))
                . UserViews::pagination(self::PATH, $filters, $result['page'], $result['total'], $result['per_page']),
            $user,
            self::PATH
        );
    }

    public function create(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (!$request->isPost()) {
            return $this->createForm($user, ['role' => Role::Editor->value]);
        }

        try {
            $result = $this->manager()->create(
                $request->input('email'),
                $request->input('display_name'),
                $this->role($request->input('role')),
                $request->input('initial_password')
            );
        } catch (InvalidArgumentException $exception) {
            return $this->createForm($user, $this->submitted($request), $exception->getMessage());
        }

        $this->audit($user, 'user.create', 'user', $result['id'], ['role' => $request->input('role'), 'generated_password' => $result['generated']]);
        $email = UserRepository::normalizeEmail($request->input('email'));

        return $this->oneTimePassword($user, 'Konto angelegt', $email, $result['password'], $result['id']);
    }

    public function edit(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $id = (int) $params['id'];
        $target = $this->app->users()->find($id) ?? throw new HttpException(404, 'Konto nicht gefunden.');

        if (!$request->isPost()) {
            return $this->editForm($user, $target);
        }

        try {
            $changed = $this->manager()->update(
                $user,
                $id,
                $request->input('display_name'),
                $request->input('email'),
                $this->role($request->input('role')),
                $request->input('status')
            );
        } catch (InvalidArgumentException $exception) {
            return $this->editForm($user, [...$target, ...$this->submitted($request)], $exception->getMessage());
        }

        if ($changed !== []) {
            $this->audit($user, 'user.update', 'user', $id, [
                'changed' => $changed,
                'role' => $request->input('role'),
                'status' => $request->input('status'),
            ]);
        }

        return $this->redirectWith(self::PATH . '/' . $id . '/edit', 'success', $changed === [] ? 'Keine Änderungen.' : 'Konto gespeichert.');
    }

    public function resetPassword(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $id = (int) $params['id'];
        $target = $this->app->users()->find($id) ?? throw new HttpException(404, 'Konto nicht gefunden.');

        if ($id === $user->id) {
            return $this->redirectWith(AccountController::PATH, 'warning', 'Das eigene Passwort bitte unter "Mein Konto" ändern.');
        }

        $password = $this->manager()->resetPassword($id);
        $this->audit($user, 'user.password.reset', 'user', $id);

        return $this->oneTimePassword($user, 'Passwort zurückgesetzt', (string) $target['email'], $password, $id);
    }

    public function delete(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $id = (int) $params['id'];

        try {
            $target = $this->manager()->delete($user, $id);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWith(self::PATH, 'danger', $exception->getMessage());
        }

        $this->audit($user, 'user.delete', 'user', $id, ['email' => $target['email'], 'role' => $target['role']]);

        try {
            $this->app->privateData()->deleteProfile($id);
        } catch (Throwable $exception) {
            error_log('[mgd-starter] user.delete private profile ' . $id . ': ' . $exception->getMessage());

            return $this->redirectWith(self::PATH, 'warning', 'Konto gelöscht, private Profildaten konnten aber nicht entfernt werden (siehe Server-Log).');
        }

        return $this->redirectWith(self::PATH, 'success', 'Konto gelöscht.');
    }

    private function row(array $row, User $actor): array
    {
        $id = (int) $row['id'];
        $status = (string) $row['status'];

        return [
            View::e($row['display_name']) . ($id === $actor->id ? ' ' . Ui::badge('du', 'info') : '')
                . ((int) $row['must_change_password'] === 1 ? ' ' . Ui::badge('Einmal-Passwort', 'warning') : ''),
            View::e($row['email']),
            Ui::badge(Role::fromString((string) $row['role'])->label()),
            Ui::badge(UserRepository::STATUSES[$status] ?? $status, $status === 'active' ? 'success' : 'danger'),
            View::e(View::date($row['last_login_at'], 'd.m.Y H:i') ?: '–'),
            '<div class="row-actions">' . Ui::link(self::PATH . '/' . $id . '/edit', 'Bearbeiten', 'button button-small') . '</div>',
        ];
    }

    private function createForm(User $user, array $values, string $error = ''): Response
    {
        $body = Ui::pageHeader('Neues Konto', Ui::link(self::PATH, 'Zur Liste', 'button button-ghost'))
            . ($error !== '' ? Ui::notice($error, 'danger') : '')
            . Form::open(self::PATH . '/new', ' class="stack card"')
            . UserViews::profileFields($values)
            . Form::text('initial_password', 'Einmal-Passwort (leer = automatisch erzeugen, mind. ' . PasswordPolicy::MIN_LENGTH . ' Zeichen)', '', ' autocomplete="new-password" maxlength="' . PasswordPolicy::MAX_LENGTH . '"', 'password')
            . '<p class="muted">Das Passwort muss beim ersten Login geändert werden.</p>'
            . Form::submit('Konto anlegen') . '</form>';

        return $this->page('Neues Konto', $body, $user, self::PATH);
    }

    private function editForm(User $user, array $target, string $error = ''): Response
    {
        $id = (int) $target['id'];
        $self = $id === $user->id;
        $actions = $self ? '' : Form::action(self::PATH . '/' . $id . '/password', 'Passwort zurücksetzen', 'Neues Einmal-Passwort erzeugen? Das bisherige Passwort wird ungültig.')
            . Form::action(self::PATH . '/' . $id . '/delete', 'Konto löschen', 'Konto endgültig löschen? Dies kann nicht rückgängig gemacht werden.', 'button button-danger');
        $body = Ui::pageHeader('Konto bearbeiten', Ui::link(self::PATH, 'Zur Liste', 'button button-ghost') . $actions)
            . ($error !== '' ? Ui::notice($error, 'danger') : '')
            . ($self ? Ui::notice('Das ist dein eigenes Konto: Rolle und Status können nicht geändert werden.', 'info') : '')
            . UserViews::meta($target)
            . Form::open(self::PATH . '/' . $id . '/edit', ' class="stack card"')
            . UserViews::profileFields($target, true)
            . Form::submit('Speichern') . '</form>';

        return $this->page('Konto bearbeiten', $body, $user, self::PATH);
    }

    private function oneTimePassword(User $user, string $title, string $email, string $password, int $id): Response
    {
        $body = Ui::pageHeader($title, Ui::link(self::PATH . '/' . $id . '/edit', 'Zum Konto', 'button button-ghost'))
            . Ui::notice('Dieses Einmal-Passwort wird nur jetzt angezeigt. Bitte sicher übermitteln – es muss beim ersten Login geändert werden.', 'warning')
            . '<section class="card"><dl class="meta-list"><dt>E-Mail</dt><dd>' . View::e($email) . '</dd>'
            . '<dt>Einmal-Passwort</dt><dd><code class="one-time-password">' . View::e($password) . '</code></dd></dl></section>';

        return $this->page($title, $body, $user, self::PATH)->withHeader('Cache-Control', 'no-store');
    }

    private function role(string $value): Role
    {
        return Role::tryFrom($value) ?? throw new InvalidArgumentException('Ungültige Rolle.');
    }

    private function manager(): UserManager
    {
        return new UserManager($this->app->users());
    }

    private function submitted(Request $request): array
    {
        return [
            'email' => $request->input('email'),
            'display_name' => $request->input('display_name'),
            'role' => $request->input('role'),
            'status' => $request->input('status', 'active'),
        ];
    }
}
