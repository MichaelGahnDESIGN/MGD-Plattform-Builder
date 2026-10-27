<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * Zeigt die in config.php definierten Pfade mit Status. Pfade sind nicht über das Web änderbar.
 */
final class FileLocationsController extends AdminController
{
    private const LOCATIONS = [
        'root' => ['Projektverzeichnis', 'Basis aller Pfade.'],
        'public' => ['Web-Root', 'Per FTP hochladen; Document Root des Webservers.'],
        'uploads' => ['Uploads', 'Hochgeladene Dateien. PHP-Ausführung ist per .htaccess gesperrt.'],
        'custom_assets' => ['Eigene Assets', 'custom.css und custom.js (Code-Editoren).'],
        'custom_php' => ['PHP-Hooks', 'custom/hooks.php – nur mit allow_php_editor bearbeitbar.'],
        'vendor_editors' => ['Editor-Bibliotheken', 'Lokal eingebettete Editoren (TinyMCE, GrapesJS, Quill).'],
        'fonts' => ['Schriften', 'Lokal gehostete Schriftdateien.'],
        'storage' => ['Speicher', 'Nicht öffentlich. Sicherungen, Logs, Exporte.'],
        'backups' => ['Sicherungen', 'Allgemeine Sicherungen.'],
        'code_backups' => ['Code-Sicherungen', 'Automatische Kopien vor jedem Speichern im Code-Editor.'],
        'logs' => ['Logs', 'Fehlerprotokolle.'],
        'exports' => ['Exporte', 'CLI-Exporte (scripts/export-pages.php).'],
        'version_file' => ['version.json', 'Versionsnummer und Status.'],
        'release_notes_file' => ['release-notes.json', 'Quelle für die Release-Notes-Synchronisierung.'],
        'config_file' => ['Konfiguration', 'config/config.php – enthält Geheimnisse, nie öffentlich.'],
        'install_lock' => ['Installationssperre', 'Existiert nach erfolgreicher Installation.'],
    ];

    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $showAbsolute = $this->app->settings()->bool('files.show_absolute_paths');
        $root = rtrim((string) ($this->app->config->paths()['root'] ?? ''), '/');
        $rows = [];

        foreach (self::LOCATIONS as $key => [$label, $description]) {
            $path = (string) ($this->app->config->paths()[$key] ?? '');
            $exists = $path !== '' && file_exists($path);
            $display = $showAbsolute || $root === '' || !str_starts_with($path, $root) ? $path : '.' . substr($path, strlen($root));
            $rows[] = [
                '<strong>' . View::e($label) . '</strong><br><span class="muted">' . View::e($description) . '</span>',
                '<code>' . View::e($display !== '' ? $display : '–') . '</code><br><code class="setting-key">paths.' . View::e($key) . '</code>',
                $exists ? Ui::badge(is_dir($path) ? 'Ordner' : 'Datei', 'success') : Ui::badge('fehlt', 'warning'),
                $exists ? Ui::badge(is_writable($path) ? 'beschreibbar' : 'nur lesbar', is_writable($path) ? 'info' : 'neutral') : '',
            ];
        }

        return $this->page(
            'Dateispeicherorte',
            Ui::pageHeader('Dateispeicherorte', '', 'Quelle der Wahrheit ist config/config.php (Abschnitt "paths"). Änderungen nur dort.')
                . Ui::table(['Ort', 'Pfad', 'Vorhanden', 'Schreibrecht'], $rows),
            $user,
            '/admin/files'
        );
    }
}
