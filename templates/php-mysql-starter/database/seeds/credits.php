<?php

declare(strict_types=1);

/*
 * Start-Credits für den eigenen Stack. Logos nur lokal (siehe public/assets/credits/).
 */

return [
    'people' => [
        ['name' => 'Dein Name', 'role' => 'Projektleitung', 'link_url' => '', 'sort_order' => 10],
    ],
    'components' => [
        [
            'name' => 'PHP',
            'category' => 'framework',
            'logo_path' => '',
            'description' => 'Serverseitige Programmiersprache (Version 8.2 oder neuer).',
            'provider_name' => 'The PHP Group',
            'provider_info' => 'PHP ist unter der PHP License verfügbar.',
            'links' => [['label' => 'Website', 'url' => 'https://www.php.net/'], ['label' => 'Lizenz', 'url' => 'https://www.php.net/license/']],
            'license' => 'PHP-3.01',
            'tags' => ['Open Source', 'Kommerziell erlaubt'],
            'commercial_use' => 'yes',
            'attribution_required' => false,
            'locally_embedded' => false,
            'version' => '8.2+',
            'sort_order' => 10,
        ],
        [
            'name' => 'MySQL / MariaDB',
            'category' => 'service',
            'logo_path' => '',
            'description' => 'Relationale Datenbank für Logins, Einstellungen und CMS (optional zweite Datenbank für private Daten).',
            'provider_name' => 'Oracle / MariaDB Foundation',
            'provider_info' => 'MySQL Community Server und MariaDB Server stehen unter der GPL-2.0.',
            'links' => [['label' => 'MySQL', 'url' => 'https://www.mysql.com/'], ['label' => 'MariaDB', 'url' => 'https://mariadb.org/']],
            'license' => 'GPL-2.0',
            'tags' => ['Open Source', 'Serverseitig genutzt'],
            'commercial_use' => 'yes',
            'attribution_required' => false,
            'locally_embedded' => false,
            'version' => '',
            'sort_order' => 20,
        ],
        [
            'name' => 'MGD PHP/MySQL Starter',
            'category' => 'framework',
            'logo_path' => '',
            'description' => 'FTP-fähiger CMS-Starter mit Versionierung, Release Notes, Credits, Rechtstexten und Light/Dark-Design.',
            'provider_name' => 'MGD Project Platform System',
            'provider_info' => 'Bereitgestellt unter der MIT-Lizenz.',
            'links' => [['label' => 'GitHub', 'url' => 'https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System']],
            'license' => 'MIT',
            'tags' => ['MIT', 'Kommerziell erlaubt', 'Lokal eingebettet'],
            'commercial_use' => 'yes',
            'attribution_required' => false,
            'locally_embedded' => true,
            'version' => '0.0.1',
            'sort_order' => 30,
        ],
        [
            'name' => 'MGD Project Platform System',
            'category' => 'tool',
            'logo_path' => '',
            'description' => 'Wiederverwendbare Projektgrundlage mit Governance-, Sicherheits- und Dokumentationsvorgaben.',
            'provider_name' => 'MGD Project Platform System',
            'provider_info' => 'Siehe Lizenz im Repository.',
            'links' => [['label' => 'GitHub', 'url' => 'https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System']],
            'license' => 'MIT',
            'tags' => ['MIT', 'Kommerziell erlaubt'],
            'commercial_use' => 'yes',
            'attribution_required' => false,
            'locally_embedded' => false,
            'version' => '',
            'sort_order' => 40,
        ],
    ],
];
