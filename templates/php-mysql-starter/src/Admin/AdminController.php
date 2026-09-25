<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\RedirectException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\AdminLayout;
use MGD\Starter\Core\View\Flash;

abstract class AdminController
{
    public function __construct(protected readonly App $app)
    {
    }

    /**
     * Prüft Rolle (immer) und CSRF (bei POST). Konten mit Einmal-Passwort werden
     * zur Passwortänderung umgeleitet, bis sie ein eigenes Passwort gesetzt haben.
     */
    protected function guard(Request $request, Role $minimum): User
    {
        $user = $this->app->requireRole($minimum);

        if ($user->mustChangePassword && !$this->allowsPendingPasswordChange()) {
            throw new RedirectException(AccountController::PASSWORD_PATH);
        }

        if ($request->isPost()) {
            $this->app->requireCsrf($request);
        }

        return $user;
    }

    /**
     * Nur der Konto-Controller ist trotz ausstehender Passwortänderung erreichbar.
     */
    protected function allowsPendingPasswordChange(): bool
    {
        return false;
    }

    /**
     * @param array{scripts?: list<string>, styles?: list<string>} $assets
     */
    protected function page(string $title, string $body, User $user, string $active, array $assets = []): Response
    {
        return Response::html((new AdminLayout($this->app))->render($title, $body, $user, $active, $assets));
    }

    protected function redirectWith(string $path, string $kind, string $message): Response
    {
        Flash::add($kind, $message);

        return Response::redirect($path);
    }

    protected function audit(User $user, string $action, string $type, string|int|null $id = null, array $meta = []): void
    {
        $this->app->audit()->record($user, $action, $type, $id === null ? null : (string) $id, $meta);
    }
}
