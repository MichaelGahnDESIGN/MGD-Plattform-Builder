<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\Update\UpdateChecker;
use MGD\Starter\Core\Version\VersionStatus;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use RuntimeException;

final class UpdaterController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        return $this->page('Updater', $this->body(), $user, '/admin/updater');
    }

    public function check(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $settings = $this->app->settings();

        if (!$settings->bool('updater.enabled') || $settings->string('updater.manifest_url') === '') {
            return $this->redirectWith('/admin/updater', 'warning', 'Updater ist deaktiviert oder keine Manifest-URL gesetzt.');
        }

        try {
            $result = (new UpdateChecker())->check($settings->string('updater.manifest_url'), $this->app->version());
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return $this->redirectWith('/admin/updater', 'danger', 'Prüfung fehlgeschlagen: ' . $exception->getMessage());
        }

        $this->audit($user, 'updater.check', 'updater', null, ['remote_version' => $result['version'], 'newer' => $result['newer']]);

        return $this->page('Updater', $this->body($result), $user, '/admin/updater');
    }

    private function body(?array $result = null): string
    {
        $settings = $this->app->settings();
        $version = $this->app->version();
        $info = '<dl class="meta-list"><dt>Installierte Version</dt><dd>' . View::e($version->label(true, $this->app->locale())) . '</dd>'
            . '<dt>Kanal</dt><dd>' . View::e($settings->string('updater.channel')) . '</dd>'
            . '<dt>Manifest</dt><dd><code>' . View::e($settings->string('updater.manifest_url') ?: '–') . '</code></dd></dl>';
        $resultHtml = '';

        if ($result !== null) {
            $status = VersionStatus::tryFrom($result['status']);
            $label = $result['version'] . ' ' . ($status?->label($this->app->locale()) ?? '');
            $resultHtml = $result['newer']
                ? Ui::notice('Neue Version verfügbar: ' . $label . '. Bitte manuell per FTP aktualisieren (Backup vorher!).', 'success')
                : Ui::notice('Kein Update verfügbar (Server meldet ' . $label . ').', 'info');

            if ($result['notes_url'] !== '') {
                $resultHtml .= '<p><a href="' . View::e($result['notes_url']) . '" rel="noopener noreferrer" target="_blank">Release Notes ansehen</a></p>';
            }
        }

        return Ui::pageHeader('Updater', '', 'Prüft nur auf neue Versionen. Es wird nie automatisch installiert.')
            . '<section class="card">' . $info . $resultHtml
            . ($settings->bool('updater.enabled')
                ? Form::open('/admin/updater/check') . Form::submit('Nach Updates suchen') . '</form>'
                : '<p class="muted">Aktivierung unter Einstellungen → Updater.</p>')
            . '</section>';
    }
}
