<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\License\LicenseGrant;
use MGD\Starter\Core\License\LicenseRepository;
use MGD\Starter\Core\Module\ModuleManifest;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use RuntimeException;

/**
 * Module aktivieren/deaktivieren und Lizenzschlüssel für kostenpflichtige Module hinterlegen.
 */
final class ModulesController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $manager = $this->app->modules();
        $rows = [];

        foreach ($manager->discover() as $module) {
            $rows[] = [
                '<strong>' . View::e($module->name) . '</strong><br><code>' . View::e($module->id) . '</code>'
                    . ($module->description !== '' ? '<br><span class="muted">' . View::e($module->description) . '</span>' : ''),
                View::e($module->version) . ($module->vendor !== '' ? '<br><span class="muted">' . View::e($module->vendor) . '</span>' : ''),
                $module->paid
                    ? ($manager->isLicensed($module) ? Ui::badge('lizenziert', 'success') : Ui::badge('Schlüssel fehlt', 'warning'))
                    : Ui::badge('kostenlos', 'neutral'),
                $manager->isEnabled($module->id) ? Ui::badge('aktiv', 'success') : Ui::badge('inaktiv', 'neutral'),
                $this->actions($module, $manager->isEnabled($module->id)),
            ];
        }

        $errors = '';

        foreach ($manager->errors() as $folder => $message) {
            $errors .= Ui::notice('Modul „' . $folder . '“ ist ungültig: ' . $message, 'warning');
        }

        $body = Ui::pageHeader('Module', '', 'Module liegen unter modules/<id>/ und werden per FTP oder Git eingespielt.')
            . $errors
            . ($rows === [] ? Ui::emptyState('Keine Module gefunden. Siehe modules/README.md.')
                : Ui::table(['Modul', 'Version', 'Lizenz', 'Status', 'Aktionen'], $rows));

        return $this->page('Module', $body, $user, '/admin/modules');
    }

    public function toggle(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $module = $this->module($request->input('module'));
        $enable = $request->input('state') === 'on';

        try {
            $enable ? $this->app->modules()->enable($module, $user) : $this->app->modules()->disable($module, $user);
        } catch (RuntimeException $exception) {
            return $this->redirectWith('/admin/modules', 'danger', $exception->getMessage());
        }

        $this->audit($user, $enable ? 'module.enable' : 'module.disable', 'module', $module->id, ['version' => $module->version]);

        return $this->redirectWith('/admin/modules', 'success', $module->name . ($enable ? ' aktiviert.' : ' deaktiviert.'));
    }

    public function saveLicense(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $module = $this->module($request->input('module'));
        $key = trim($request->input('license_key'));
        $grant = $this->app->licenses()->validate($key, LicenseGrant::TYPE_MODULE, $module->id);

        if ($grant === null) {
            $this->audit($user, 'module.license.rejected', 'module', $module->id);

            return $this->redirectWith('/admin/modules', 'danger', 'Schlüssel ungültig, abgelaufen oder nicht für dieses Modul/diese Domain.');
        }

        (new LicenseRepository($this->app->databases->core()))->store(LicenseGrant::TYPE_MODULE, $module->id, $key, $grant->licensee, $user->id);
        $this->audit($user, 'module.license.saved', 'module', $module->id, ['licensee' => $grant->licensee]);

        return $this->redirectWith('/admin/modules', 'success', 'Lizenz für ' . $module->name . ' gespeichert.');
    }

    private function actions(ModuleManifest $module, bool $enabled): string
    {
        $toggle = Form::open('/admin/modules/toggle', ' class="inline-form"')
            . '<input type="hidden" name="module" value="' . View::e($module->id) . '">'
            . '<input type="hidden" name="state" value="' . ($enabled ? 'off' : 'on') . '">'
            . Form::submit($enabled ? 'Deaktivieren' : 'Aktivieren', 'button button-secondary') . '</form>';

        if (!$module->paid) {
            return $toggle;
        }

        return $toggle . Form::open('/admin/modules/license')
            . '<input type="hidden" name="module" value="' . View::e($module->id) . '">'
            . Form::textarea('license_key', 'Modul-Lizenzschlüssel', '', 'rows="2" spellcheck="false" autocomplete="off" required')
            . Form::submit('Schlüssel speichern', 'button button-ghost') . '</form>';
    }

    private function module(string $id): ModuleManifest
    {
        $module = $this->app->modules()->discover()[$id] ?? null;

        if ($module === null) {
            throw new HttpException(404, 'Modul nicht gefunden.');
        }

        return $module;
    }
}
