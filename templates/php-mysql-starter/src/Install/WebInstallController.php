<?php

declare(strict_types=1);

namespace MGD\Starter\Install;

use InvalidArgumentException;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Csrf;
use MGD\Starter\Core\Auth\UserRepository;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\View;
use PDOException;

/**
 * Web-Installer für Hosting ohne SSH/CLI. Nur aktiv, solange
 * storage/install.lock fehlt UND install.token (mind. 16 Zeichen) in config.php gesetzt ist.
 * Nutzt ein eigenes Minimal-Layout, weil vor der Installation noch keine Tabellen existieren.
 */
final class WebInstallController
{
    private const MIN_TOKEN_LENGTH = 16;

    public function __construct(private readonly App $app)
    {
    }

    public function show(Request $request): Response
    {
        $this->assertAvailable();

        return Response::html($this->layout($this->form()));
    }

    public function run(Request $request): Response
    {
        $this->assertAvailable();
        $this->app->requireCsrf($request);

        if (!hash_equals($this->token(), $request->input('token'))) {
            return Response::html($this->layout($this->form('Installations-Token ist falsch.')), 403);
        }

        if ($request->input('password') !== $request->input('password_confirm')) {
            return Response::html($this->layout($this->form('Die Passwörter stimmen nicht überein.')), 422);
        }

        try {
            $log = (new Installer($this->app))->install([
                'email' => $request->input('email'),
                'name' => $request->input('name'),
                'password' => $request->input('password'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return Response::html($this->layout($this->form($exception->getMessage())), 422);
        } catch (PDOException) {
            return Response::html($this->layout($this->form('Datenbankfehler. Zugangsdaten in config/config.php prüfen.')), 500);
        }

        $items = implode('', array_map(static fn (string $line): string => '<li>' . View::e($line) . '</li>', $log));

        return Response::html($this->layout(
            '<h1>Installation abgeschlossen</h1><ul class="install-log">' . $items . '</ul>'
            . '<p class="notice notice-warning">Bitte jetzt <code>install.token</code> in config/config.php leeren.</p>'
            . '<p><a class="button" href="' . View::e(View::url('/login')) . '">Zum Login</a></p>'
        ));
    }

    private function assertAvailable(): void
    {
        if (is_file($this->app->config->path('install_lock')) || strlen($this->token()) < self::MIN_TOKEN_LENGTH) {
            throw new HttpException(404, 'Seite nicht gefunden.');
        }
    }

    private function token(): string
    {
        $token = $this->app->config->string('install.token');

        return $token === 'change-me' ? '' : $token;
    }

    private function form(string $error = ''): string
    {
        return '<h1>Installation</h1><p class="muted">Legt Tabellen, Standardinhalte und den ersten Admin an.</p>'
            . ($error !== '' ? '<div class="notice notice-danger" role="alert">' . View::e($error) . '</div>' : '')
            . '<form method="post" action="' . View::e(View::url('/install')) . '" class="stack">'
            . '<input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">'
            . '<label class="field"><span>Installations-Token (aus config.php)</span><input type="password" name="token" required autocomplete="off"></label>'
            . '<label class="field"><span>Admin-Name</span><input type="text" name="name" required maxlength="120"></label>'
            . '<label class="field"><span>Admin-E-Mail</span><input type="email" name="email" required maxlength="254" autocomplete="username"></label>'
            . '<label class="field"><span>Passwort (mind. ' . UserRepository::MIN_PASSWORD_LENGTH . ' Zeichen)</span><input type="password" name="password" required minlength="'
            . UserRepository::MIN_PASSWORD_LENGTH . '" autocomplete="new-password"></label>'
            . '<label class="field"><span>Passwort wiederholen</span><input type="password" name="password_confirm" required autocomplete="new-password"></label>'
            . '<button type="submit" class="button">Installieren</button></form>';
    }

    private function layout(string $body): string
    {
        $css = '';

        foreach (['tokens', 'theme-light', 'theme-dark', 'site', 'admin'] as $file) {
            $css .= '<link rel="stylesheet" href="' . View::e(View::asset('css/' . $file . '.css')) . '">';
        }

        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow"><title>Installation</title>' . $css . '</head>'
            . '<body class="area-login"><main id="main" class="auth-main"><div class="auth-card">' . $body . '</div></main></body></html>';
    }
}
