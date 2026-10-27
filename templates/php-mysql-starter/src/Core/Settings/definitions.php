<?php

declare(strict_types=1);

/*
 * Einstellungs-Registry. Jede Einstellung:
 * key, category, label, description, keywords, type (bool|text|textarea|color|select|multiselect|url|number),
 * options, default, visibility (public|private), constraints (min, max, max_length, https_only, local_path, pattern).
 */

$setting = static fn (
    string $key,
    string $category,
    string $type,
    string $label,
    string $description,
    mixed $default,
    array $options = [],
    array $keywords = [],
    string $visibility = 'private',
    array $constraints = [],
): array => compact('key', 'category', 'type', 'label', 'description', 'default', 'options', 'keywords', 'visibility', 'constraints');

$categories = [
    'general' => 'Allgemein',
    'version' => 'Versionsnummern',
    'release_notes' => 'Release Notes',
    'credits' => 'Credits',
    'legal' => 'Rechtliches & CMS-Seiten',
    'cms_editor' => 'CMS-Editor',
    'design' => 'Design',
    'theme' => 'Darstellung (Light/Dark)',
    'seo' => 'SEO',
    'loader' => 'Ladebildschirm',
    'updater' => 'Updater',
    'cookie_box' => 'Cookie-Box',
    'files' => 'Dateispeicherorte',
    'code_editor' => 'Code-Editoren',
    'maintenance' => 'Wartungsmodus',
    'mail' => 'E-Mail',
    'license' => 'Lizenz',
];

$localPath = ['local_path' => true, 'max_length' => 300];
$mediaPath = [...$localPath, 'media_picker' => true];
$legalSlugs = [
    'kontakt' => 'Kontakt', 'impressum' => 'Impressum', 'agb' => 'AGB', 'datenschutz' => 'Datenschutz',
    'cookies' => 'Cookies', 'zahlung' => 'Zahlung', 'versand' => 'Versand', 'widerruf' => 'Widerruf',
    'jugendschutz' => 'Jugendschutz', 'barrierefreiheit' => 'Barrierefreiheit', 'ki-philosophie' => 'KI-Philosophie',
];

$settings = [
    $setting('general.site_name', 'general', 'text', 'Name der Website', 'Wird im Header, im Titel und in E-Mails verwendet.', 'Mein Projekt', [], ['titel', 'name', 'marke'], 'public', ['max_length' => 120]),
    $setting('general.tagline', 'general', 'text', 'Untertitel', 'Kurzer Claim unter dem Namen.', 'Spiele, Projekte und Plattformen', [], ['slogan', 'claim'], 'public', ['max_length' => 200]),
    $setting('general.footer_text', 'general', 'text', 'Footer-Text', 'Kurzer Text im öffentlichen Footer.', '© Mein Projekt', [], ['copyright', 'fußzeile'], 'public', ['max_length' => 200]),
    $setting('general.header_pages', 'general', 'text', 'Header-Navigation', 'Kommagetrennte Slugs veröffentlichter Seiten für die Hauptnavigation.', 'home', [], ['menü', 'navigation'], 'public', ['max_length' => 300, 'pattern' => '/^[a-z0-9,\- ]*$/']),

    $setting('version.display_enabled', 'version', 'bool', 'Versionsnummer anzeigen', 'Blendet die Versionsnummer an den gewählten Orten ein.', true, [], ['version', 'build']),
    $setting('version.show_status', 'version', 'bool', 'Status anzeigen', 'Zeigt den Status (z. B. Pre-Alpha) hinter der Nummer.', true, [], ['alpha', 'beta', 'status']),
    $setting('version.audience', 'version', 'select', 'Zielgruppe', 'Öffentlich, nur im Backoffice oder beides.', 'both', ['public' => 'Öffentlich', 'private' => 'Privat (Backoffice)', 'both' => 'Beides'], ['sichtbarkeit']),
    $setting('version.locations', 'version', 'multiselect', 'Anzeigeorte', 'Wo die Versionsnummer erscheint.', ['login', 'settings', 'backoffice_footer'], MGD\Starter\Core\Version\VersionDisplay::LOCATIONS, ['footer', 'header', 'login', 'landingpage']),

    $setting('release_notes.public_enabled', 'release_notes', 'bool', 'Öffentliche Release Notes', 'Aktiviert /release-notes (zeigt nur Einträge mit Zielgruppe "frontend").', true, [], ['changelog', 'änderungen'], 'public'),
    $setting('release_notes.footer_link', 'release_notes', 'bool', 'Link im Footer', 'Zeigt einen Link zu den Release Notes im Footer.', true, [], ['footer', 'link'], 'public'),

    $setting('credits.public_enabled', 'credits', 'bool', 'Öffentliche Credits', 'Aktiviert die Seite /credits.', true, [], ['mitwirkende', 'lizenzen'], 'public'),
    $setting('credits.footer_link', 'credits', 'bool', 'Link im Footer', 'Zeigt einen Link zu den Credits im Footer.', true, [], ['footer'], 'public'),
    $setting('credits.show_logos', 'credits', 'bool', 'Logos anzeigen', 'Zeigt lokale Logos/Icons der Komponenten.', true, [], ['icon', 'bild'], 'public'),

    $setting('legal.footer_slugs', 'legal', 'multiselect', 'Rechtliche Links im Footer', 'Welche Rechtsseiten im Footer verlinkt werden.', ['kontakt', 'impressum', 'datenschutz', 'cookies', 'barrierefreiheit'], $legalSlugs, ['impressum', 'datenschutz', 'agb', 'footer'], 'public'),
    $setting('legal.withdrawal_button', 'legal', 'bool', 'Widerrufs-Button anzeigen', 'Zeigt den Widerrufs-Button (Text aus Snippet "widerrufs-button-text") im Footer.', false, [], ['widerruf', 'button', 'verbraucher'], 'public'),
    $setting('legal.trash_notice', 'legal', 'bool', 'Papierkorb-Hinweis', 'Zeigt Redakteuren beim Löschen einen Hinweis zur Wiederherstellung.', true, [], ['papierkorb', 'löschen']),

    $setting('cms_editor.editor', 'cms_editor', 'select', 'Editor', 'Editor für CMS-Seiten. Serverseitig wird immer bereinigt.', 'plain', ['tinymce' => 'TinyMCE', 'grapesjs' => 'GrapesJS', 'quill' => 'Quill', 'markdown' => 'Markdown', 'plain' => 'Einfaches Textfeld (HTML)'], ['wysiwyg', 'editor', 'tinymce', 'grapesjs', 'quill', 'markdown']),
    $setting('cms_editor.delivery', 'cms_editor', 'select', 'Auslieferung', 'Lokal eingebettet (empfohlen, datenschutzfreundlich) oder per CDN.', 'local', ['local' => 'Lokal (/assets/vendor)', 'cdn' => 'CDN'], ['cdn', 'lokal', 'vendor']),
    $setting('cms_editor.cdn_script_url', 'cms_editor', 'url', 'CDN-Skript-URL', 'Nur HTTPS. Wird nur bei Auslieferung "CDN" verwendet.', '', [], ['cdn', 'script'], 'private', ['https_only' => true, 'max_length' => 500]),
    $setting('cms_editor.cdn_style_url', 'cms_editor', 'url', 'CDN-Stylesheet-URL', 'Optional, nur HTTPS (z. B. für Quill oder GrapesJS).', '', [], ['cdn', 'css'], 'private', ['https_only' => true, 'max_length' => 500]),
    $setting('cms_editor.revision_note_required', 'cms_editor', 'bool', 'Änderungsnotiz erforderlich', 'Jede Speicherung braucht eine Notiz für die Revision.', false, [], ['revision', 'notiz', 'historie']),

    $setting('design.radius', 'design', 'number', 'Eckenradius (px)', 'Radius für Karten, Buttons und Felder.', 8, [], ['radius', 'ecken'], 'public', ['min' => 0, 'max' => 32]),
    $setting('design.font_family', 'design', 'text', 'Schriftfamilie', 'Nur lokal eingebundene Schriften (siehe public/assets/fonts). Keine CDN-Fonts.', 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', [], ['font', 'schrift', 'typografie'], 'public', ['max_length' => 200, 'pattern' => '/^[A-Za-z0-9 ,"\'-]+$/']),
    $setting('design.font_file', 'design', 'text', 'Schriftdatei (lokal)', 'Optional: .woff2-Datei unter /assets/fonts/. Wird als Schrift "Site Font" registriert – in der Schriftfamilie voranstellen.', '', [], ['woff2', 'font'], 'public', $localPath),

    $setting('theme.default_mode', 'theme', 'select', 'Standardmodus', 'System folgt der Einstellung des Geräts.', 'system', ['system' => 'System', 'light' => 'Hell', 'dark' => 'Dunkel'], ['dark mode', 'light mode', 'hell', 'dunkel'], 'public'),
    $setting('theme.show_toggle', 'theme', 'bool', 'Umschalter anzeigen', 'Zeigt den Hell/Dunkel-Umschalter.', true, [], ['toggle', 'umschalter'], 'public'),
    $setting('theme.toggle_locations', 'theme', 'multiselect', 'Orte des Umschalters', 'Wo der Umschalter erscheint.', ['header', 'login', 'settings'], ['header' => 'Header', 'footer' => 'Footer', 'login' => 'Login', 'settings' => 'Backoffice / Einstellungen', 'floating' => 'Schwebend (unten rechts)'], ['toggle', 'position'], 'public'),
    $setting('theme.allow_user_choice', 'theme', 'bool', 'Nutzerwahl speichern', 'Besucher können ihre Wahl lokal im Browser speichern.', true, [], ['localstorage', 'präferenz'], 'public'),

    $setting('seo.site_title', 'seo', 'text', 'Seitentitel', 'Standardtitel für Suchmaschinen.', 'Mein Projekt', [], ['title', 'titel'], 'public', ['max_length' => 120]),
    $setting('seo.title_pattern', 'seo', 'text', 'Titelmuster', 'Platzhalter: {page} und {site}.', '{page} · {site}', [], ['title', 'muster'], 'public', ['max_length' => 120]),
    $setting('seo.meta_description', 'seo', 'textarea', 'Meta-Beschreibung', 'Standardbeschreibung (max. 300 Zeichen).', '', [], ['description', 'beschreibung'], 'public', ['max_length' => 300]),
    $setting('seo.og_image', 'seo', 'text', 'Standard-OG-Bild', 'Lokaler Pfad unter /assets/ oder /uploads/.', '', [], ['open graph', 'social', 'bild'], 'public', $mediaPath),
    $setting('seo.robots', 'seo', 'select', 'Indexierung', 'Suchmaschinen erlauben oder verbieten.', 'index', ['index' => 'index, follow', 'noindex' => 'noindex, nofollow'], ['robots', 'noindex', 'google'], 'public'),
    $setting('seo.canonical_base', 'seo', 'url', 'Kanonische Basis-URL', 'z. B. https://example.org – für Canonical-Links und sitemap.xml.', '', [], ['canonical', 'sitemap', 'domain'], 'public', ['max_length' => 300]),

    $setting('loader.enabled', 'loader', 'bool', 'Ladebildschirm aktiv', 'Zeigt beim Laden einen kurzen Ladebildschirm.', false, [], ['loading', 'preloader', 'spinner'], 'public'),
    $setting('loader.text', 'loader', 'text', 'Ladetext', 'Text unter der Animation.', 'Wird geladen …', [], ['text'], 'public', ['max_length' => 120]),
    $setting('loader.min_duration_ms', 'loader', 'number', 'Mindestdauer (ms)', 'Mindestanzeigedauer in Millisekunden.', 300, [], ['dauer', 'zeit'], 'public', ['min' => 0, 'max' => 5000]),
    $setting('loader.style', 'loader', 'select', 'Stil', 'Darstellung des Ladebildschirms.', 'spinner', ['spinner' => 'Spinner', 'bar' => 'Balken', 'logo' => 'Logo'], ['animation'], 'public'),
    $setting('loader.logo_path', 'loader', 'text', 'Logo-Pfad', 'Lokaler Pfad für Stil "Logo".', '', [], ['logo'], 'public', $mediaPath),

    $setting('updater.enabled', 'updater', 'bool', 'Update-Prüfung aktiv', 'Erlaubt Admins, nach neuen Versionen zu suchen. Es wird nie automatisch installiert.', false, [], ['update', 'aktualisierung']),
    $setting('updater.channel', 'updater', 'select', 'Kanal', 'Release-Kanal für die Prüfung.', 'stable', ['stable' => 'Stabil', 'beta' => 'Beta', 'alpha' => 'Alpha', 'lts' => 'LTS'], ['kanal', 'channel']),
    $setting('updater.manifest_url', 'updater', 'url', 'Manifest-URL', 'HTTPS-URL zu JSON {version, status, notes_url} oder {channels: {stable: {…}, beta: {…}}}. Es wird ?channel=<Kanal> angehängt.', '', [], ['manifest', 'json'], 'private', ['https_only' => true, 'max_length' => 500]),

    $setting('cookie_box.enabled', 'cookie_box', 'bool', 'Cookie-Box aktiv', 'Hinweis zu Cookies/lokalem Speicher anzeigen.', true, [], ['cookie', 'consent', 'dsgvo'], 'public'),
    $setting('cookie_box.essential_only', 'cookie_box', 'bool', 'Nur essenzielle Cookies', 'Es gibt nur technisch notwendige Speicherungen – nur Button "Verstanden".', true, [], ['tracking', 'essenziell'], 'public'),
    $setting('cookie_box.snippet_slug', 'cookie_box', 'text', 'Text-Snippet', 'Slug des CMS-Snippets mit dem Text der Cookie-Box.', 'cookie-box-text', [], ['snippet', 'text'], 'public', ['max_length' => 120, 'pattern' => '/^[a-z0-9-]+$/']),
    $setting('cookie_box.policy_slug', 'cookie_box', 'text', 'Cookie-Richtlinie', 'Slug der verlinkten Rechtsseite.', 'cookies', [], ['richtlinie', 'link'], 'public', ['max_length' => 120, 'pattern' => '/^[a-z0-9-]+$/']),

    $setting('files.show_absolute_paths', 'files', 'bool', 'Absolute Pfade anzeigen', 'Zeigt vollständige Serverpfade statt relativer Pfade.', false, [], ['pfad', 'server', 'speicherort']),

    $setting('code_editor.css_enabled', 'code_editor', 'bool', 'CSS-Editor', 'Admins dürfen custom.css bearbeiten.', true, [], ['css', 'stylesheet']),
    $setting('code_editor.js_enabled', 'code_editor', 'bool', 'JavaScript-Editor', 'Admins dürfen custom.js bearbeiten.', true, [], ['js', 'javascript']),
    $setting('code_editor.php_enabled', 'code_editor', 'bool', 'PHP-Editor', 'Nur wirksam, wenn zusätzlich security.allow_php_editor in config.php aktiv ist.', false, [], ['php', 'hooks']),

    $setting('maintenance.enabled', 'maintenance', 'bool', 'Wartungsmodus aktiv', 'Öffentliche Seiten zeigen eine Wartungsmeldung (HTTP 503).', false, [], ['wartung', 'offline', 'maintenance']),
    $setting('maintenance.message', 'maintenance', 'textarea', 'Wartungsmeldung', 'Text für Besucher während der Wartung.', 'Wir führen gerade Wartungsarbeiten durch. Bitte versuche es später erneut.', [], ['meldung', 'text'], 'public', ['max_length' => 1000]),
    $setting('license.powered_by_align', 'license', 'select', 'Ausrichtung des Labels „powered by“', 'Einzige erlaubte Gestaltung des Pflicht-Labels (MGD-Lizenz). Das Label selbst entfällt nur mit gültiger Whitelabel-Lizenz, siehe Einstellungen › Lizenz.', 'center', MGD\Starter\Core\License\PoweredBy::ALIGNMENTS, ['lizenz', 'label', 'powered by', 'whitelabel', 'michael gahn design']),
    $setting('maintenance.admin_bypass', 'maintenance', 'bool', 'Admins umgehen Wartung', 'Angemeldete Admins sehen die Seite normal.', true, [], ['admin', 'bypass']),

    $setting('mail.password_reset', 'mail', 'bool', 'Passwort-Reset per E-Mail', 'Zeigt "Passwort vergessen?" beim Login. Benötigt mail.transport in config.php, eine Absenderadresse und app.url (oder SEO → Kanonische Basis-URL).', false, [], ['passwort', 'reset', 'vergessen', 'mail']),
    $setting('mail.from_address', 'mail', 'text', 'Absenderadresse', 'Absender für System-E-Mails, z. B. no-reply@example.org.', '', [], ['absender', 'from', 'e-mail'], 'private', ['max_length' => 254, 'pattern' => '/^[^@\s<>"\',;]+@[^@\s<>"\',;]+\.[^@\s<>"\',;]+$/']),
    $setting('mail.from_name', 'mail', 'text', 'Absendername', 'Anzeigename des Absenders. Leer = Name der Website.', '', [], ['absender', 'name'], 'private', ['max_length' => 120]),
];

$palette = [
    'primary' => ['Primärfarbe', '#2563eb', '#60a5fa'],
    'primary_contrast' => ['Primär-Kontrast', '#ffffff', '#0b1220'],
    'secondary' => ['Sekundärfarbe', '#475569', '#94a3b8'],
    'accent' => ['Akzent', '#9333ea', '#c084fc'],
    'success' => ['Erfolg', '#15803d', '#4ade80'],
    'warning' => ['Warnung', '#b45309', '#fbbf24'],
    'danger' => ['Gefahr', '#b91c1c', '#f87171'],
    'info' => ['Info', '#0369a1', '#38bdf8'],
    'background' => ['Hintergrund', '#f8fafc', '#0b1220'],
    'surface' => ['Fläche', '#ffffff', '#111a2e'],
    'text' => ['Text', '#0f172a', '#e2e8f0'],
    'muted' => ['Gedämpfter Text', '#475569', '#94a3b8'],
    'border' => ['Rahmen', '#cbd5e1', '#26324a'],
];

foreach (['light' => 'Hell', 'dark' => 'Dunkel'] as $mode => $modeLabel) {
    foreach ($palette as $name => [$label, $lightDefault, $darkDefault]) {
        $settings[] = $setting(
            'design.' . $mode . '.' . $name,
            'design',
            'color',
            $label . ' (' . $modeLabel . ')',
            'Farbe "' . $label . '" im ' . ($mode === 'light' ? 'hellen' : 'dunklen') . ' Modus.',
            $mode === 'light' ? $lightDefault : $darkDefault,
            [],
            ['farbe', 'color', $mode, $modeLabel, $name],
            'public'
        );
    }
}

return ['categories' => $categories, 'settings' => $settings, 'palette' => array_keys($palette)];
