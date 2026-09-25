<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\PasswordPolicy;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Auth\UserManager;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * "Mein Konto" für jede Backoffice-Rolle: Anzeigename und eigenes Passwort.
 * Auch erreichbar, solange ein Einmal-Passwort geändert werden muss.
 */
final class AccountController extends AdminController
{
    public const PATH = '/admin/account';
    public const PASSWORD_PATH = '/admin/account/password';

    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Moderator);

        if ($user->mustChangePassword) {
            return Response::redirect(self::PASSWORD_PATH);
        }

        if (!$request->isPost()) {
            return $this->render($user);
        }

        try {
            $this->app->users()->updateProfile($user->id, $request->input('display_name'));
        } catch (InvalidArgumentException $exception) {
            return $this->render($user, $exception->getMessage());
        }

        $this->audit($user, 'account.profile.update', 'user', $user->id);

        return $this->redirectWith(self::PATH, 'success', 'Anzeigename gespeichert.');
    }

    public function password(Request $request): Response
    {
        $user = $this->guard($request, Role::Moderator);

        if (!$request->isPost()) {
            return $this->render($user, '', true);
        }

        try {
            (new UserManager($this->app->users()))->changeOwnPassword(
                $user,
                $request->input('current_password'),
                $request->input('new_password'),
                $request->input('new_password_confirm')
            );
        } catch (InvalidArgumentException $exception) {
            return $this->render($user, $exception->getMessage(), true);
        }

        session_regenerate_id(true);
        $this->audit($user, 'account.password.change', 'user', $user->id, ['forced' => $user->mustChangePassword]);

        return $this->redirectWith($user->mustChangePassword ? '/admin' : self::PATH, 'success', 'Passwort geändert.');
    }

    protected function allowsPendingPasswordChange(): bool
    {
        return true;
    }

    private function render(User $user, string $error = '', bool $passwordOnly = false): Response
    {
        $forced = $user->mustChangePassword;
        $intro = $forced
            ? Ui::notice('Du hast dich mit einem Einmal-Passwort angemeldet. Bitte lege jetzt ein eigenes Passwort fest.', 'warning')
            : '';
        $profile = $passwordOnly ? '' : '<section class="card"><h2>Profil</h2>'
            . '<dl class="meta-list"><dt>E-Mail</dt><dd>' . View::e($user->email) . '</dd><dt>Rolle</dt><dd>' . View::e($user->role->label()) . '</dd></dl>'
            . Form::open(self::PATH, ' class="stack"')
            . Form::text('display_name', 'Anzeigename', $user->displayName, ' required maxlength="120" autocomplete="name"')
            . Form::submit('Speichern') . '</form></section>';
        $password = '<section class="card"><h2>Passwort ändern</h2>'
            . Form::open(self::PASSWORD_PATH, ' class="stack"')
            . Form::text('current_password', $forced ? 'Einmal-Passwort' : 'Aktuelles Passwort', '', ' required autocomplete="current-password"', 'password')
            . Form::text('new_password', 'Neues Passwort (mind. ' . PasswordPolicy::MIN_LENGTH . ' Zeichen)', '', ' required minlength="' . PasswordPolicy::MIN_LENGTH . '" maxlength="' . PasswordPolicy::MAX_LENGTH . '" autocomplete="new-password"', 'password')
            . Form::text('new_password_confirm', 'Neues Passwort wiederholen', '', ' required autocomplete="new-password"', 'password')
            . Form::submit('Passwort ändern') . '</form></section>';

        return $this->page(
            'Mein Konto',
            Ui::pageHeader('Mein Konto') . $intro . ($error !== '' ? Ui::notice($error, 'danger') : '') . $profile . $password,
            $user,
            self::PATH
        );
    }
}
