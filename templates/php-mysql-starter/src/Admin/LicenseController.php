<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Cms\MarkdownRenderer;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\License\LicenseGrant;
use MGD\Starter\Core\License\LicenseRepository;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * Einstellungen › Lizenz: Pflicht-Label, vollständiger Lizenztext, Integrität und Whitelabel-Schlüssel.
 */
final class LicenseController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Moderator);

        $body = Ui::pageHeader('Lizenz', '', 'MGD-Lizenz – Nutzung, Pflicht-Label und Whitelabel.')
            . $this->app->poweredBy()->render('license')
            . '<section class="card"><h2>Status</h2>' . $this->status() . '</section>'
            . ($user->can(Role::Admin) ? $this->whitelabelForm() : '')
            . '<section class="card prose license-text">' . $this->licenseHtml() . '</section>';

        return $this->page('Lizenz', $body, $user, '/admin/license');
    }

    public function saveWhitelabel(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $key = trim($request->input('license_key'));
        $grant = $this->app->licenses()->validate($key, LicenseGrant::TYPE_WHITELABEL);

        if ($grant === null) {
            $this->audit($user, 'license.whitelabel.rejected', 'license', LicenseRepository::WHITELABEL_SUBJECT);

            return $this->redirectWith('/admin/license', 'danger',
                'Der Schlüssel ist ungültig, abgelaufen oder gilt nicht für die Domain „' . $this->app->licenses()->host() . '“.');
        }

        $this->repository()->store(LicenseGrant::TYPE_WHITELABEL, LicenseRepository::WHITELABEL_SUBJECT, $key, $grant->licensee, $user->id);
        $this->audit($user, 'license.whitelabel.saved', 'license', LicenseRepository::WHITELABEL_SUBJECT, [
            'licensee' => $grant->licensee,
            'project_id' => $grant->projectId,
        ]);

        return $this->redirectWith('/admin/license', 'success', 'Whitelabel-Lizenz für ' . $grant->licensee . ' gespeichert.');
    }

    public function removeWhitelabel(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $this->repository()->remove(LicenseGrant::TYPE_WHITELABEL, LicenseRepository::WHITELABEL_SUBJECT);
        $this->audit($user, 'license.whitelabel.removed', 'license', LicenseRepository::WHITELABEL_SUBJECT);

        return $this->redirectWith('/admin/license', 'success', 'Whitelabel-Lizenz entfernt. Das Pflicht-Label ist wieder aktiv.');
    }

    private function status(): string
    {
        $licenses = $this->app->licenses();
        $grant = $licenses->whitelabel();
        $violations = $this->app->licenseIntegrity()->violations();
        $integrity = $violations === []
            ? Ui::badge('unverändert', 'success')
            : Ui::badge('verändert: ' . implode(', ', $violations), 'danger');
        $whitelabel = $grant === null
            ? Ui::badge('keine – Label ist Pflicht', 'neutral')
            : Ui::badge('aktiv', 'success') . ' ' . View::e($grant->licensee . ' · Projekt ' . $grant->projectId
                . ' · Domains: ' . implode(', ', $grant->domains));

        return '<dl class="meta-list"><dt>Domain</dt><dd><code>' . View::e($licenses->host() ?: '–') . '</code></dd>'
            . '<dt>Whitelabel-Lizenz</dt><dd>' . $whitelabel . '</dd>'
            . '<dt>Lizenzdateien</dt><dd>' . $integrity . '</dd></dl>'
            . '<p class="muted">Whitelabel-Lizenzen (500 €, einmalig pro Projekt/Domain) gibt es bei '
            . '<a href="https://michael-gahn.de" target="_blank" rel="noopener">Michael Gahn DESIGN</a>.</p>';
    }

    private function whitelabelForm(): string
    {
        $remove = $this->app->licenses()->isWhitelabel()
            ? Form::action('/admin/license/whitelabel/remove', 'Whitelabel-Lizenz entfernen', 'Whitelabel-Lizenz wirklich entfernen?')
            : '';

        return '<section class="card"><h2>Whitelabel-Schlüssel</h2>'
            . Form::open('/admin/license/whitelabel')
            . Form::textarea('license_key', 'Lizenzschlüssel (beginnt mit MGD1.)', '', 'rows="3" required spellcheck="false" autocomplete="off"')
            . Form::submit('Schlüssel prüfen und speichern') . '</form>' . $remove . '</section>';
    }

    private function licenseHtml(): string
    {
        $file = $this->app->pathOr('license_file', 'MGD-Lizenz.md');
        $markdown = is_file($file) ? (string) file_get_contents($file) : '';

        if ($markdown === '') {
            return Ui::notice('Lizenzdatei MGD-Lizenz.md fehlt. Das ist ein Lizenzverstoß.', 'danger');
        }

        return $this->app->sanitizer()->sanitize((new MarkdownRenderer())->render($markdown));
    }

    private function repository(): LicenseRepository
    {
        return new LicenseRepository($this->app->databases->core());
    }
}
