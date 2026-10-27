<?php

declare(strict_types=1);

/*
 * CLI-Smoke-Test gegen eine MySQL/MariaDB-Testdatenbank.
 * Nicht destruktiv: legt Testdaten mit Zufallssuffix an und löscht keine Tabellen.
 *
 *   MGD_TEST_DB_HOST=127.0.0.1 MGD_TEST_DB_PORT=3306 MGD_TEST_DB_NAME=mgd_starter_test \
 *   MGD_TEST_DB_USER=mgd MGD_TEST_DB_PASSWORD=mgdpass php tests/smoke.php
 */

use MGD\Starter\Cms\CreditInput;
use MGD\Starter\Cms\ReleaseNoteInput;
use MGD\Starter\Cms\ReleaseNoteSync;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Config;
use MGD\Starter\Core\Database\Databases;
use MGD\Starter\Core\Http\SecurityHeaders;
use MGD\Starter\Core\Security\HtmlSanitizer;
use MGD\Starter\Core\Settings\SettingsRegistry;
use MGD\Starter\Core\Version\Version;
use MGD\Starter\Install\Installer;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/src/autoload.php';

$passed = 0;

function check(bool $condition, string $label): void
{
    global $passed;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }

    $passed++;
    echo "ok   {$label}\n";
}

// --- Ohne Datenbank -------------------------------------------------------
$sanitizer = new HtmlSanitizer();
$dirty = '<p onclick="alert(1)">Hallo <script>alert(2)</script><a href="javascript:alert(3)">x</a>'
    . '<a href="https://example.org" target="_blank">ok</a><img src="data:image/png;base64,AAA" onerror="x()"></p><iframe src="https://evil.test"></iframe>';
$clean = $sanitizer->sanitize($dirty);
check(!str_contains($clean, '<script') && !str_contains($clean, 'alert(2)'), 'Sanitizer entfernt <script> samt Inhalt');
check(!str_contains(strtolower($clean), 'onclick') && !str_contains(strtolower($clean), 'onerror'), 'Sanitizer entfernt on*-Attribute');
check(!str_contains($clean, 'javascript:') && !str_contains($clean, 'data:image'), 'Sanitizer entfernt javascript:/data:-URLs');
check(str_contains($clean, 'href="https://example.org"') && str_contains($clean, 'rel="noopener noreferrer"'), 'Sanitizer behält sichere Links');
check(!str_contains($clean, '<iframe'), 'Sanitizer entfernt iframes');
check($sanitizer->sanitize('<h2>Ü</h2><ul><li>ä</li></ul>') === '<h2>Ü</h2><ul><li>ä</li></ul>', 'Sanitizer erhält UTF-8 und erlaubte Tags');

$version = Version::fromFile($root . '/version.json');
check($version->label() === '0.0.1 Pre-Alpha', 'Versionslabel "0.0.1 Pre-Alpha"');
check($version->label(true, 'en') === '0.0.1 Pre-Alpha' && $version->label(false) === '0.0.1', 'Versionslabel ohne Status');
check(trim((string) file_get_contents($root . '/VERSION')) === $version->number, 'VERSION entspricht version.json');

$registry = new SettingsRegistry();
$darkResults = $registry->search('dark');
check(isset($darkResults['theme']) && isset($darkResults['design']), 'Einstellungssuche "dark" findet Darstellung und Design');
check(array_keys($registry->search('', 'seo')) === ['seo'], 'Kategoriefilter liefert nur SEO');
check(count($registry->categories()) >= 15, 'Mindestens 15 Einstellungskategorien');
require __DIR__ . '/smoke/gaps-no-db.php';

require __DIR__ . '/license-smoke.php';

// --- Mit Datenbank --------------------------------------------------------
$dbConfig = [
    'host' => getenv('MGD_TEST_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('MGD_TEST_DB_PORT') ?: 3306),
    'name' => getenv('MGD_TEST_DB_NAME') ?: 'mgd_platform',
    'user' => getenv('MGD_TEST_DB_USER') ?: 'mgd',
    'password' => getenv('MGD_TEST_DB_PASSWORD') ?: 'mgdpass',
];
$config = new Config([
    'app' => ['key' => 'smoke-test-key', 'locale' => 'de'],
    'db' => ['core' => $dbConfig, 'private' => null],
    'paths' => [
        'root' => $root,
        'public' => $root . '/public',
        'version_file' => $root . '/version.json',
        'release_notes_file' => $root . '/release-notes.json',
        'install_lock' => sys_get_temp_dir() . '/mgd-starter-smoke.lock',
    ],
    'security' => ['session_secure' => false],
]);
$app = new App($config, new Databases($dbConfig), new SecurityHeaders());
$suffix = bin2hex(random_bytes(3));

$installer = new Installer($app);
$log = $installer->migrate();
check(count(array_filter($log, static fn (string $l): bool => str_starts_with($l, 'Migration'))) >= 2, 'Migrationen core + private ausgeführt');
$second = (new Installer($app))->migrate();
check(count(array_filter($second, static fn (string $l): bool => str_ends_with($l, 'already-applied'))) >= 2, 'Migrationen sind idempotent');

$settings = $app->settings();
$settings->seedDefaults();
check($settings->string('theme.default_mode') === 'system', 'Standardwert theme.default_mode');
$settings->save('design.light.primary', '#123ABC', null);
check($settings->string('design.light.primary') === '#123abc', 'Farbwert gespeichert und normalisiert');
$rejected = false;

try {
    $settings->save('design.light.primary', 'red;}</style><script>', null);
} catch (InvalidArgumentException) {
    $rejected = true;
}

check($rejected, 'Ungültige Farbe wird abgelehnt');
$settings->resetPrefix('design.');
check($settings->string('design.light.primary') === '#2563eb', 'Design-Reset stellt Standard wieder her');

$userId = $app->users()->create('smoke-' . $suffix . '@example.test', 'Smoke Editor', 'test-password-only-' . $suffix, Role::Editor);
$editor = $app->users()->findActive($userId);
check($editor !== null && $editor->role === Role::Editor && !$editor->can(Role::Admin), 'Benutzer mit Rolle Redaktion');

$pages = $app->pages();
$input = $app->pageInput();
$slug = 'smoke-' . $suffix;
$pageId = $pages->create($input->normalize(['slug' => $slug, 'title' => 'Smoke', 'status' => 'published', 'content_html' => '<p>Version 1</p>']), $editor, 'Erstellt');
$revision = $pages->update($pageId, $input->normalize(['slug' => $slug, 'title' => 'Smoke 2', 'status' => 'published', 'content_html' => '<p onclick="x()">Version 2</p><script>bad()</script>']), $editor, 'Update');
check($revision === 2 && count($pages->revisions($pageId)) === 2, 'Update erzeugt Revision 2');
check($pages->find($pageId)['content_html'] === '<p>Version 2</p>', 'Gespeichertes HTML ist bereinigt');

$first = array_values(array_filter($pages->revisions($pageId), static fn (array $r): bool => (int) $r['revision_no'] === 1))[0];
$restored = $pages->restoreRevision((int) $first['id'], $editor);
check($restored === 3 && $pages->find($pageId)['content_html'] === '<p>Version 1</p>', 'Revision wiederhergestellt als Revision 3');

$pages->softDelete($pageId, $editor);
check($pages->findBySlug($slug) === null && in_array($slug, array_column($pages->list('', '', true), 'slug'), true), 'Soft Delete verschiebt in Papierkorb');
$pages->restore($pageId);
check($pages->findBySlug($slug) !== null, 'Wiederherstellen aus Papierkorb');

$markdown = $pages->create($input->normalize(['slug' => 'md-' . $suffix, 'title' => 'MD', 'content_format' => 'markdown', 'content_source' => "# Titel\n\n**fett** [x](javascript:alert(1))"]), $editor);
$mdPage = $pages->find($markdown);
check(str_contains($mdPage['content_html'], '<h1>Titel</h1>') && !str_contains($mdPage['content_html'], 'javascript:'), 'Markdown wird gerendert und bereinigt');

$export = $app->pageTransfer()->export([$pageId], true);
check(count($export['pages']) === 1 && count($export['pages'][0]['revisions']) === 3, 'Export inkl. Revisionen');
$export['pages'][0]['content_html'] = '<p>Importiert</p><img src="x" onerror="y()">';
$summary = $app->pageTransfer()->import($app->pageTransfer()->decode(json_encode($export, JSON_THROW_ON_ERROR), 1_000_000), $editor);
check($summary['updated'] === 1 && $summary['errors'] === [], 'Import aktualisiert bestehende Seite');
check($pages->find($pageId)['content_html'] === '<p>Importiert</p><img src="x">' && (int) $pages->find($pageId)['current_revision'] === 4, 'Import erzeugt bereinigte neue Revision');
$export['pages'][0]['slug'] = 'import-' . $suffix;
$summary = $app->pageTransfer()->import($export, $editor);
check($summary['created'] === 1, 'Import legt neue Seite an');
$badFormat = false;

try {
    $app->pageTransfer()->import(['format' => 'other', 'pages' => []], $editor);
} catch (InvalidArgumentException) {
    $badFormat = true;
}

check($badFormat, 'Import lehnt unbekanntes Format ab');

$sync = new ReleaseNoteSync($app->releaseNotes());
$syncResult = $sync->syncFile($root . '/release-notes.json');
check($syncResult['errors'] === [], 'release-notes.json synchronisiert');
check($sync->syncFile($root . '/release-notes.json')['created'] === 0, 'Synchronisierung ist idempotent');
$noteInput = new ReleaseNoteInput();
$app->releaseNotes()->create($noteInput->normalize(['version' => '0.0.1', 'status' => 'pre-alpha', 'date' => '2026-09-25', 'type' => 'fix', 'title' => 'Intern ' . $suffix, 'items' => ['x'], 'audience' => ['backoffice']]));
$public = array_column($app->releaseNotes()->all('frontend'), 'title');
$all = array_column($app->releaseNotes()->all(), 'title');
check(!in_array('Intern ' . $suffix, $public, true) && in_array('Intern ' . $suffix, $all, true), 'Zielgruppenfilter "frontend" blendet Backoffice-Einträge aus');

$credits = $app->credits();
$creditInput = new CreditInput();
$componentId = $credits->createComponent($creditInput->component([
    'name' => 'Smoke Lib ' . $suffix, 'category' => 'library', 'license' => 'MIT', 'tags' => 'MIT, Kommerziell erlaubt',
    'links' => "GitHub | https://github.com/example/lib\nLizenz | https://example.org/license", 'commercial_use' => 'yes',
    'attribution_required' => '1', 'logo_path' => '/assets/credits/lib.svg',
]));
$component = $credits->findComponent($componentId);
check($component !== null && $component['tags'] === ['MIT', 'Kommerziell erlaubt'] && count($component['links']) === 2, 'Credit-Komponente anlegen');
$credits->updateComponent($componentId, $creditInput->component([...$component, 'name' => 'Smoke Lib 2', 'links' => $component['links']]));
check($credits->findComponent($componentId)['name'] === 'Smoke Lib 2', 'Credit-Komponente bearbeiten');
$hotlink = false;

try {
    $creditInput->component(['name' => 'X', 'logo_path' => 'https://cdn.example.org/logo.png']);
} catch (InvalidArgumentException) {
    $hotlink = true;
}

check($hotlink, 'Hotlink-Logo wird abgelehnt');
$credits->deleteComponent($componentId);
check($credits->findComponent($componentId) === null, 'Credit-Komponente löschen');

$app->privateData()->saveProfile($userId, ['display_name' => 'Smoke', 'real_name' => 'Erika Muster', 'birthdate' => '1990-01-31', 'address' => ['city' => 'Musterstadt']]);
$profile = $app->privateData()->findProfile($userId);
check($profile !== null && $profile['real_name'] === 'Erika Muster' && $profile['address']['city'] === 'Musterstadt', 'Private Profildaten über eigene Verbindung');
$app->privateData()->deleteProfile($userId);

require __DIR__ . '/smoke/gaps-db.php';

$app->audit()->record($editor, 'smoke.test', 'smoke', $suffix);
check($app->audit()->recent(5, 'smoke')[0]['resource_id'] === $suffix, 'Audit-Log schreibt Einträge');

echo PHP_EOL, "Alle {$passed} Prüfungen bestanden.", PHP_EOL;
