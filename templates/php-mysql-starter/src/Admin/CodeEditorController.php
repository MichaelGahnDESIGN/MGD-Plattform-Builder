<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Files\CustomCodeStore;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use RuntimeException;

/**
 * Bearbeitung von custom.css, custom.js und (optional) custom/hooks.php. Nur Admins.
 */
final class CodeEditorController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $items = '';

        foreach (CustomCodeStore::LANGUAGES as $language => $definition) {
            $enabled = $this->isEnabled($language);
            $items .= '<li class="card"><h2>' . View::e($definition['label']) . '</h2>'
                . ($enabled ? Ui::link('/admin/code/' . $language, 'Bearbeiten') : '<p class="muted">' . View::e($this->disabledReason($language)) . '</p>')
                . '</li>';
        }

        return $this->page(
            'Code-Editoren',
            Ui::pageHeader('Code-Editoren', '', 'Vor jedem Speichern wird eine Sicherung unter storage/backups/code angelegt.')
                . Ui::notice('Achtung: Fehlerhafter Code kann die Website beschädigen. Änderungen werden protokolliert.', 'warning')
                . '<ul class="card-grid">' . $items . '</ul>',
            $user,
            '/admin/code'
        );
    }

    public function edit(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $language = (string) $params['lang'];

        if (!isset(CustomCodeStore::LANGUAGES[$language])) {
            throw new HttpException(404, 'Unbekannter Editor.');
        }

        if (!$this->isEnabled($language)) {
            throw new HttpException(403, $this->disabledReason($language));
        }

        $store = $this->store();
        $error = '';

        if ($request->isPost()) {
            try {
                $content = $request->input('content');
                $backup = $store->save($language, $content);
                $this->audit($user, 'code.update', 'code_file', $language, [
                    'bytes' => strlen($content),
                    'sha256' => hash('sha256', $content),
                    'backup' => $backup !== '' ? basename($backup) : null,
                ]);

                return $this->redirectWith('/admin/code/' . $language, 'success', 'Gespeichert. Sicherung: ' . ($backup !== '' ? basename($backup) : 'keine (neue Datei)'));
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        $content = $request->isPost() ? $request->input('content') : $store->read($language);
        $label = CustomCodeStore::LANGUAGES[$language]['label'];
        $warning = $language === 'php'
            ? Ui::notice('PHP-Code wird bei jedem Seitenaufruf ausgeführt. Ein Fehler kann die Website lahmlegen – Wiederherstellung dann per FTP aus storage/backups/code.', 'danger')
            : '';

        return $this->page(
            'Code-Editor ' . $label,
            Ui::pageHeader('Code-Editor: ' . $label, Ui::link('/admin/code', 'Zurück', 'button button-ghost'))
                . $warning . ($error !== '' ? Ui::notice($error, 'danger') : '')
                . Form::open('/admin/code/' . $language, ' class="stack"')
                . Form::textarea('content', 'Inhalt', $content, ' rows="28" class="code-input" spellcheck="false"')
                . Form::submit('Speichern (mit Sicherung)') . '</form>',
            $user,
            '/admin/code'
        );
    }

    private function isEnabled(string $language): bool
    {
        $settingEnabled = $this->app->settings()->bool('code_editor.' . $language . '_enabled');

        if ($language === 'php') {
            return $settingEnabled && $this->app->config->get('security.allow_php_editor') === true;
        }

        return $settingEnabled;
    }

    private function disabledReason(string $language): string
    {
        return $language === 'php'
            ? 'Deaktiviert. Benötigt die Einstellung "PHP-Editor" UND security.allow_php_editor = true in config.php.'
            : 'Deaktiviert. Aktivierung unter Einstellungen → Code-Editoren.';
    }

    private function store(): CustomCodeStore
    {
        return new CustomCodeStore(
            $this->app->config->paths(),
            (int) $this->app->config->get('security.max_code_file_bytes', 262144)
        );
    }
}
