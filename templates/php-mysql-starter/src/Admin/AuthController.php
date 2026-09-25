<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Auth\LoginResult;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\AdminLayout;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use Throwable;

final class AuthController extends AdminController
{
    public function showLogin(Request $request): Response
    {
        $user = $this->app->currentUser();

        if ($user !== null && $user->can(Role::Moderator)) {
            return Response::redirect('/admin');
        }

        return Response::html($this->loginPage());
    }

    public function login(Request $request): Response
    {
        $this->app->requireCsrf($request);
        $email = mb_substr($request->input('email'), 0, 254);
        $result = $this->app->auth()->attempt($email, $request->input('password'), $request->ip());

        if ($result->status === LoginResult::THROTTLED) {
            return Response::html($this->loginPage('Zu viele Anmeldeversuche. Bitte in 15 Minuten erneut versuchen.'), 429);
        }

        if ($result->status !== LoginResult::OK || $result->user === null) {
            return Response::html($this->loginPage('Anmeldung fehlgeschlagen. E-Mail oder Passwort ist falsch.', $email), 401);
        }

        $this->app->audit()->record($result->user, 'auth.login', 'session');

        if ($result->user->mustChangePassword && $result->user->can(Role::Moderator)) {
            return Response::redirect(AccountController::PASSWORD_PATH);
        }

        return Response::redirect($result->user->can(Role::Moderator) ? '/admin' : '/');
    }

    public function logout(Request $request): Response
    {
        $this->app->requireCsrf($request);
        $user = $this->app->currentUser();

        if ($user !== null) {
            $this->app->audit()->record($user, 'auth.logout', 'session');
        }

        $this->app->auth()->logout();

        return Response::redirect('/login');
    }

    private function loginPage(string $error = '', string $email = ''): string
    {
        $body = '<h1>Anmelden</h1><p class="muted">Backoffice-Zugang</p>'
            . ($error !== '' ? Ui::notice($error, 'danger') : '')
            . Form::open('/login', ' class="stack"')
            . Form::text('email', 'E-Mail', $email, ' autocomplete="username" required maxlength="254"', 'email')
            . Form::text('password', 'Passwort', '', ' autocomplete="current-password" required', 'password')
            . Form::submit('Anmelden') . '</form>'
            . ($this->passwordResetAvailable()
                ? '<p><a href="' . View::e(View::url(PasswordResetController::REQUEST_PATH)) . '">Passwort vergessen?</a></p>'
                : '');

        return (new AdminLayout($this->app))->bare('Anmelden', $body);
    }

    private function passwordResetAvailable(): bool
    {
        try {
            return PasswordResetController::isAvailable($this->app);
        } catch (Throwable $exception) {
            error_log('[mgd-starter] password reset availability: ' . $exception->getMessage());

            return false;
        }
    }
}
