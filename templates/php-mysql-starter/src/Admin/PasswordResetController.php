<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\PasswordPolicy;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\AdminLayout;
use MGD\Starter\Core\View\Flash;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use RuntimeException;

/**
 * Optionaler Passwort-Reset per E-Mail (/passwort-vergessen, /passwort-zuruecksetzen).
 * Aktiv nur mit Einstellung mail.password_reset, funktionierendem Mail-Transport und bekannter Basis-URL.
 * Antworten sind immer neutral – es wird nie verraten, ob ein Konto existiert.
 */
final class PasswordResetController extends AdminController
{
    public const REQUEST_PATH = '/passwort-vergessen';
    public const RESET_PATH = '/passwort-zuruecksetzen';
    private const NEUTRAL_MESSAGE = 'Falls ein aktives Konto mit dieser E-Mail-Adresse existiert, wurde ein Link zum Zurücksetzen gesendet. Er ist 60 Minuten gültig.';

    public static function isAvailable(App $app): bool
    {
        return $app->settings()->bool('mail.password_reset') && $app->mailer()->isEnabled() && self::baseUrl($app) !== '';
    }

    public function showRequest(Request $request): Response
    {
        $this->assertAvailable();

        return $this->bare('Passwort vergessen', $this->requestForm());
    }

    public function sendRequest(Request $request): Response
    {
        $this->assertAvailable();
        $this->app->requireCsrf($request);
        $result = $this->app->passwordResets()->request($request->input('email'), $request->ip());

        if ($result !== null) {
            $this->mail($result['user'], $result['token']);
        }

        return $this->bare('Passwort vergessen', Ui::notice(self::NEUTRAL_MESSAGE, 'info') . $this->loginLink());
    }

    public function showReset(Request $request): Response
    {
        $this->assertAvailable();
        $token = $request->query('token');

        if (!$this->app->passwordResets()->isValid($token)) {
            return $this->invalidLink();
        }

        return $this->bare('Neues Passwort', $this->resetForm($token));
    }

    public function reset(Request $request): Response
    {
        $this->assertAvailable();
        $this->app->requireCsrf($request);
        $token = $request->input('token');

        try {
            $userId = $this->app->passwordResets()->consume($token, $request->input('password'), $request->input('password_confirm'));
        } catch (InvalidArgumentException $exception) {
            return $this->app->passwordResets()->isValid($token)
                ? $this->bare('Neues Passwort', Ui::notice($exception->getMessage(), 'danger') . $this->resetForm($token), 422)
                : $this->invalidLink();
        }

        $this->app->audit()->record(null, 'auth.password_reset.complete', 'user', (string) $userId);
        Flash::add('success', 'Passwort geändert. Du kannst dich jetzt anmelden.');

        return Response::redirect('/login');
    }

    private function mail(User $user, string $token): void
    {
        $link = self::baseUrl($this->app) . View::url(self::RESET_PATH . '?token=' . $token);
        $site = $this->app->settings()->string('general.site_name');
        $body = "Hallo " . $user->displayName . ",\n\n"
            . "für dein Konto bei " . $site . " wurde ein neues Passwort angefordert.\n"
            . "Über diesen Link kannst du innerhalb von 60 Minuten ein neues Passwort festlegen:\n\n"
            . $link . "\n\n"
            . "Falls du das nicht angefordert hast, ignoriere diese E-Mail. Dein Passwort bleibt unverändert.\n";

        try {
            $this->app->mailer()->send($user->email, 'Passwort zurücksetzen – ' . $site, $body);
            $this->app->audit()->record(null, 'auth.password_reset.request', 'user', (string) $user->id);
        } catch (RuntimeException | InvalidArgumentException $exception) {
            error_log('[mgd-starter] password reset mail: ' . $exception->getMessage());
            $this->app->audit()->record(null, 'auth.password_reset.mail_failed', 'user', (string) $user->id);
        }
    }

    private static function baseUrl(App $app): string
    {
        $base = $app->config->string('app.url') ?: $app->settings()->string('seo.canonical_base');

        return preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?$#', rtrim($base, '/')) === 1 ? rtrim($base, '/') : '';
    }

    private function assertAvailable(): void
    {
        if (!self::isAvailable($this->app)) {
            throw new HttpException(404, 'Seite nicht gefunden.');
        }
    }

    private function requestForm(): string
    {
        return '<h1>Passwort vergessen</h1><p class="muted">Wir senden dir einen Link zum Zurücksetzen.</p>'
            . Form::open(self::REQUEST_PATH, ' class="stack"')
            . Form::text('email', 'E-Mail', '', ' autocomplete="username" required maxlength="254"', 'email')
            . Form::submit('Link anfordern') . '</form>' . $this->loginLink();
    }

    private function resetForm(string $token): string
    {
        return '<h1>Neues Passwort</h1>'
            . Form::open(self::RESET_PATH, ' class="stack"')
            . '<input type="hidden" name="token" value="' . View::e($token) . '">'
            . Form::text('password', 'Neues Passwort (mind. ' . PasswordPolicy::MIN_LENGTH . ' Zeichen)', '', ' required minlength="' . PasswordPolicy::MIN_LENGTH . '" maxlength="' . PasswordPolicy::MAX_LENGTH . '" autocomplete="new-password"', 'password')
            . Form::text('password_confirm', 'Passwort wiederholen', '', ' required autocomplete="new-password"', 'password')
            . Form::submit('Passwort speichern') . '</form>';
    }

    private function invalidLink(): Response
    {
        return $this->bare('Link ungültig', '<h1>Link ungültig</h1>'
            . Ui::notice('Der Link ist ungültig, abgelaufen oder wurde bereits verwendet.', 'warning')
            . '<p>' . Ui::link(self::REQUEST_PATH, 'Neuen Link anfordern', 'button button-secondary') . '</p>', 410);
    }

    private function loginLink(): string
    {
        return '<p><a href="' . View::e(View::url('/login')) . '">Zurück zur Anmeldung</a></p>';
    }

    private function bare(string $title, string $body, int $status = 200): Response
    {
        return Response::html((new AdminLayout($this->app))->bare($title, $body), $status)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }
}
