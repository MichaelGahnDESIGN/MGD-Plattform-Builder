<?php

declare(strict_types=1);

namespace MGD\Starter\Install;

use InvalidArgumentException;
use MGD\Starter\Cms\CreditInput;
use MGD\Starter\Cms\ReleaseNoteSync;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Database\MigrationRunner;

/**
 * Gemeinsame Installationslogik für CLI (scripts/install.php) und Web-Installer (/install).
 * Alle Schritte sind idempotent und können gefahrlos erneut ausgeführt werden.
 */
final class Installer
{
    /** @var list<string> */
    private array $log = [];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        $root = $this->app->config->path('root');
        $core = new MigrationRunner($this->app->databases->core(), $root . '/database/core', 'core');
        $private = new MigrationRunner($this->app->databases->private(), $root . '/database/private', 'private');

        foreach ([...$core->migrate(), ...$private->migrate()] as [$version, $status]) {
            $this->log[] = 'Migration ' . $version . ': ' . $status;
        }

        if (!$this->app->databases->hasSeparatePrivateDatabase()) {
            $this->log[] = 'Hinweis: Keine private Datenbank konfiguriert – private Tabellen liegen in der Kerndatenbank.';
        }

        return $this->log;
    }

    /**
     * @return list<string>
     */
    public function install(?array $admin = null): array
    {
        $this->migrate();
        $this->log[] = 'Einstellungen: ' . $this->app->settings()->seedDefaults() . ' Standardwerte angelegt.';
        $this->seedPages();
        $this->seedCredits();
        $this->syncReleaseNotes();

        if ($admin !== null) {
            $this->createAdmin((string) $admin['email'], (string) $admin['name'], (string) $admin['password']);
        }

        $this->writeLock();

        return $this->log;
    }

    public function createAdmin(string $email, string $name, string $password): void
    {
        if ($this->app->users()->emailExists($email)) {
            $this->log[] = 'Admin: ' . $email . ' existiert bereits – übersprungen.';

            return;
        }

        $id = $this->app->users()->create($email, $name, $password, Role::Admin);
        $this->app->audit()->record(null, 'user.create', 'user', (string) $id, ['role' => 'admin', 'source' => 'installer']);
        $this->log[] = 'Admin angelegt: ' . $email;
    }

    private function seedPages(): void
    {
        $pages = $this->app->pages();
        $input = $this->app->pageInput();
        $created = 0;

        foreach (require $this->app->config->path('root') . '/database/seeds/pages.php' as $raw) {
            if ($pages->findBySlug((string) $raw['slug'], false, true) !== null) {
                continue;
            }

            $pages->create($input->normalize($raw), null, 'Installation');
            $created++;
        }

        $this->log[] = 'CMS-Seiten: ' . $created . ' angelegt.';
    }

    private function seedCredits(): void
    {
        $credits = $this->app->credits();

        if ($credits->componentCount() > 0) {
            $this->log[] = 'Credits: bereits vorhanden – übersprungen.';

            return;
        }

        $seed = require $this->app->config->path('root') . '/database/seeds/credits.php';
        $input = new CreditInput();

        foreach ($seed['people'] as $person) {
            $credits->createPerson($input->person($person));
        }

        foreach ($seed['components'] as $component) {
            $credits->createComponent($input->component($component));
        }

        $this->log[] = 'Credits: ' . count($seed['components']) . ' Komponenten angelegt.';
    }

    private function syncReleaseNotes(): void
    {
        try {
            $summary = (new ReleaseNoteSync($this->app->releaseNotes()))->syncFile($this->app->config->path('release_notes_file'));
            $this->log[] = 'Release Notes: ' . $summary['created'] . ' neu, ' . $summary['updated'] . ' aktualisiert.';

            foreach ($summary['errors'] as $error) {
                $this->log[] = 'Release Notes Fehler: ' . $error;
            }
        } catch (InvalidArgumentException $exception) {
            $this->log[] = 'Release Notes übersprungen: ' . $exception->getMessage();
        }
    }

    private function writeLock(): void
    {
        $lock = $this->app->config->path('install_lock');

        if (@file_put_contents($lock, gmdate('c') . "\n") === false) {
            $this->log[] = 'Warnung: ' . basename($lock) . ' konnte nicht geschrieben werden (Schreibrechte für storage/ prüfen).';

            return;
        }

        $this->log[] = 'Installationssperre geschrieben.';
    }
}
