<?php

declare(strict_types=1);

/*
 * MGD PHP/MySQL Starter – Beispielkonfiguration.
 *
 * Kopieren nach config/config.php und alle Werte 'change-me' ersetzen.
 * config/config.php enthält Geheimnisse und darf NIE ins Repository.
 * Diese Datei ist die einzige Quelle der Wahrheit für Dateipfade.
 */

$root = dirname(__DIR__);

return [
    'app' => [
        'name' => 'MGD PHP/MySQL Starter',
        'environment' => 'production',
        // Leer lassen, wenn die Seite im Domain-Root läuft; sonst z. B. '/cms'.
        'base_path' => '',
        'locale' => 'de',
        'timezone' => 'Europe/Berlin',
        // Zufälliger geheimer Schlüssel (mind. 32 Zeichen), z. B. bin2hex(random_bytes(32)).
        'key' => 'change-me',
        'session_name' => 'mgd_starter_session',
        // Öffentliche Basis-URL ohne Pfad, z. B. 'https://example.org' – für Links in E-Mails (Passwort-Reset).
        // Leer = SEO-Einstellung "Kanonische Basis-URL". Wird nie aus dem Host-Header abgeleitet.
        'url' => '',
    ],

    'db' => [
        // Pflicht: Kerndatenbank für Logins, Einstellungen und CMS.
        'core' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'change-me',
            'user' => 'change-me',
            'password' => 'change-me',
            'charset' => 'utf8mb4',
        ],
        // Optional: zweite Datenbank für personenbezogene/sensible Daten.
        // null = private Tabellen liegen in der Kerndatenbank (über eigene Verbindung, später trennbar).
        'private' => null,
        /*
        'private' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'change-me',
            'user' => 'change-me',
            'password' => 'change-me',
            'charset' => 'utf8mb4',
        ],
        */
    ],

    'ftp' => [
        'host' => 'change-me',
        'port' => 21,
        'user' => 'change-me',
        'password' => 'change-me',
        // Zielverzeichnis auf dem Server, z. B. '/' oder '/htdocs/projekt'.
        'remote_path' => '/change-me',
        'passive' => true,
    ],

    'license' => [
        // Optional: Whitelabel-Schlüssel (MGD1.…) hier statt im Backoffice hinterlegen.
        // Ohne gültigen, signierten Schlüssel für diese Domain bleibt das Label Pflicht (MGD-Lizenz).
        'whitelabel_key' => '',
    ],
    'install' => [
        // Web-Installer (/install) nur mit diesem Token (mind. 16 Zeichen). Nach der Installation leeren.
        'token' => 'change-me',
    ],

    'paths' => [
        'root' => $root,
        'public' => $root . '/public',
        'uploads' => $root . '/public/uploads',
        'custom_assets' => $root . '/public/assets/custom',
        'vendor_editors' => $root . '/public/assets/vendor',
        'fonts' => $root . '/public/assets/fonts',
        'storage' => $root . '/storage',
        'backups' => $root . '/storage/backups',
        'code_backups' => $root . '/storage/backups/code',
        'logs' => $root . '/storage/logs',
        'exports' => $root . '/storage/exports',
        'install_lock' => $root . '/storage/install.lock',
        'custom_php' => $root . '/custom/hooks.php',
        'modules' => $root . '/modules',
        'license_file' => $root . '/MGD-Lizenz.md',
        'version_file' => $root . '/version.json',
        'release_notes_file' => $root . '/release-notes.json',
        'config_file' => __FILE__,
    ],

    'security' => [
        // Session-Cookie nur über HTTPS senden. Nur für lokale Entwicklung ohne HTTPS auf false setzen.
        'session_secure' => true,
        // HSTS-Header senden (nur aktivieren, wenn HTTPS dauerhaft eingerichtet ist).
        'hsts' => false,
        // PHP-Code-Editor (custom/hooks.php). Zusätzlich muss die Einstellung im Backoffice aktiv sein.
        'allow_php_editor' => false,
        // Maximale Größe einer Code-Datei in Bytes.
        'max_code_file_bytes' => 262144,
        // Maximale Größe eines CMS-Imports in Bytes.
        'max_import_bytes' => 5242880,
        // Maximale Größe einer hochgeladenen Mediendatei in Bytes (zusätzlich upload_max_filesize/post_max_size beachten).
        'max_upload_bytes' => 5242880,
        // Maximale Größe der GrapesJS-Projektdaten (JSON) einer Seite in Bytes.
        'max_editor_project_bytes' => 2097152,
    ],

    'media' => [
        // Bilder nach dem Upload per GD neu kodieren (entfernt EXIF-/Metadaten). Ohne GD bleibt das Original.
        'reencode_images' => true,
    ],

    'mail' => [
        // 'disabled' (Standard), 'mail' (PHP mail()) oder 'smtp'.
        'transport' => 'disabled',
        'smtp' => [
            'host' => 'change-me',
            'port' => 587,
            // 'tls' = STARTTLS (Port 587), 'ssl' = implizites TLS (Port 465).
            'encryption' => 'tls',
            'username' => 'change-me',
            'password' => 'change-me',
            'timeout' => 10,
        ],
    ],
];
