<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Module;

use InvalidArgumentException;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Database\MigrationRunner;
use MGD\Starter\Core\Http\Router;
use RuntimeException;
use Throwable;

/**
 * Findet Module unter modules/<id>/, verwaltet aktiv/inaktiv und bootet aktive Module.
 * Module werden per FTP/Git eingespielt – ein Upload ausführbaren Codes über das Web ist bewusst nicht vorgesehen.
 * Kostenpflichtige Module (license "paid") laufen nur mit gültigem, signiertem Modul-Schlüssel.
 */
final class ModuleManager
{
    /** @var array<string, array{version: string, enabled: bool}>|null */
    private ?array $states = null;

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, ModuleManifest>|null */
    private ?array $discovered = null;

    public function __construct(private readonly App $app, private readonly string $directory)
    {
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $modules = [];
        $this->errors = [];

        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            try {
                $manifest = ModuleManifest::fromDirectory($dir);
                $modules[$manifest->id] = $manifest;
            } catch (InvalidArgumentException $exception) {
                $this->errors[basename($dir)] = $exception->getMessage();
            }
        }

        ksort($modules);

        return $this->discovered = $modules;
    }

    /**
     * @return array<string, string> Ordnername => Fehlermeldung der letzten discover()-Runde
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isEnabled(string $id): bool
    {
        return $this->states()[$id]['enabled'] ?? false;
    }

    public function isLicensed(ModuleManifest $module): bool
    {
        return !$module->paid || $this->app->licenses()->moduleGrant($module->id) !== null;
    }

    /**
     * Version des installierten Starters laut template.json (nicht die Projektversion).
     */
    public function starterVersion(): ?string
    {
        $file = $this->app->pathOr('template_manifest', 'template.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : null;
    }

    public function isCompatible(ModuleManifest $module): bool
    {
        if ($module->requiresStarter === '') {
            return true;
        }

        $version = $this->starterVersion();

        return $version !== null && ModuleManifest::satisfies($module->requiresStarter, $version);
    }

    public function enable(ModuleManifest $module, User $user): void
    {
        if (!$this->isCompatible($module)) {
            throw new RuntimeException('Das Modul verlangt Starter ' . $module->requiresStarter
                . ', installiert ist ' . ($this->starterVersion() ?? 'eine unbekannte Version') . '.');
        }

        if (!$this->isLicensed($module)) {
            throw new RuntimeException('Für dieses kostenpflichtige Modul fehlt ein gültiger Lizenzschlüssel.');
        }

        if ($module->migrations !== null) {
            (new MigrationRunner($this->app->databases->core(), $module->migrations, 'module/' . $module->id))->migrate();
        }

        $this->saveState($module, true, $user);
    }

    public function disable(ModuleManifest $module, User $user): void
    {
        $this->saveState($module, false, $user);
    }

    /**
     * Lädt aktive, lizenzierte Module. Ein defektes Modul legt die Website nicht lahm, sondern wird protokolliert.
     */
    public function boot(Router $router): void
    {
        foreach ($this->activeModules() as $module) {
            try {
                $register = require $module->entry;

                if (is_callable($register)) {
                    $register($router, $this->app, $module);
                }
            } catch (Throwable $exception) {
                error_log('[mgd-starter] Modul ' . $module->id . ': ' . $exception->getMessage());
            }
        }
    }

    /**
     * @return list<array{href: string, label: string, role: \MGD\Starter\Core\Auth\Role}>
     */
    public function menu(): array
    {
        $items = [];

        foreach ($this->activeModules() as $module) {
            array_push($items, ...$module->menu);
        }

        return $items;
    }

    /**
     * @return list<ModuleManifest>
     */
    private function activeModules(): array
    {
        return array_values(array_filter(
            $this->discover(),
            fn (ModuleManifest $module): bool => $this->isEnabled($module->id) && $this->isLicensed($module) && $this->isCompatible($module),
        ));
    }

    /**
     * @return array<string, array{version: string, enabled: bool}>
     */
    private function states(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        try {
            $rows = $this->app->databases->core()->query('SELECT module_id, version, enabled FROM modules')->fetchAll();
        } catch (Throwable) {
            return $this->states = [];
        }

        $states = [];

        foreach ($rows as $row) {
            $states[(string) $row['module_id']] = ['version' => (string) $row['version'], 'enabled' => (bool) $row['enabled']];
        }

        return $this->states = $states;
    }

    private function saveState(ModuleManifest $module, bool $enabled, User $user): void
    {
        $statement = $this->app->databases->core()->prepare(
            'INSERT INTO modules (module_id, version, enabled, updated_by, updated_at)
             VALUES (:id, :version, :enabled, :user_id, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE version = VALUES(version), enabled = VALUES(enabled),
                 updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        );
        $statement->execute(['id' => $module->id, 'version' => $module->version, 'enabled' => $enabled ? 1 : 0, 'user_id' => $user->id]);
        $this->states = null;
    }
}
