<?php

declare(strict_types=1);

/*
 * Startinhalte. Rechtstexte sind PLATZHALTER und müssen rechtlich geprüft und angepasst werden.
 * Wird nur angelegt, wenn der Slug noch nicht existiert.
 */

$placeholder = '<p class="placeholder-notice"><strong>Platzhalter – rechtlich prüfen lassen.</strong> '
    . 'Dieser Text ist ein unverbindliches Muster und keine Rechtsberatung.</p>';

$legal = static fn (string $slug, string $title, string $body): array => [
    'slug' => $slug,
    'title' => $title,
    'page_type' => 'legal',
    'status' => 'published',
    'content_format' => 'html',
    'content_html' => $placeholder . $body,
    'meta_description' => $title,
];

return [
    [
        'slug' => 'home',
        'title' => 'Willkommen',
        'page_type' => 'page',
        'status' => 'published',
        'content_format' => 'html',
        'content_html' => '<section class="hero"><p class="hero__eyebrow">Mein Projekt</p>'
            . '<h1>Deine Plattform für Spiele, Projekte und Communities</h1>'
            . '<p class="hero__lead">Diese Startseite ist ein Platzhalter aus dem MGD PHP/MySQL Starter. Ersetze Titel, Text und Bilder im Backoffice unter CMS-Seiten.</p>'
            . '<p class="hero__actions"><a class="button" href="/seite/kontakt">Kontakt aufnehmen</a><a class="button button-ghost" href="/release-notes">Neuigkeiten ansehen</a></p></section>'
            . '<ul class="feature-grid"><li><h3>Schnell startklar</h3><p>Läuft auf einfachem PHP-Hosting mit FTP und einer MySQL-Datenbank.</p></li>'
            . '<li><h3>Rechtstexte inklusive</h3><p>Impressum, Datenschutz und weitere Pflichtseiten mit Revisionen, Export und Import.</p></li>'
            . '<li><h3>Hell und dunkel</h3><p>Light- und Dark-Mode, eigene Farben und Schriften unter Einstellungen › Design.</p></li></ul>'
            . '<section class="cta-band"><h2>Bereit für den nächsten Schritt?</h2><p>Erzähl uns, was du vorhast.</p><p><a class="button" href="/seite/kontakt">Jetzt melden</a></p></section>',
        'meta_description' => 'Startseite',
    ],
    $legal('kontakt', 'Kontakt', '<h2>So erreichst du uns</h2><p>E-Mail: kontakt@example.org</p><p>Anschrift: Musterstraße 1, 12345 Musterstadt</p>'),
    $legal('impressum', 'Impressum', '<h2>Angaben gemäß § 5 DDG</h2><p>Vorname Nachname<br>Musterstraße 1<br>12345 Musterstadt</p><h2>Kontakt</h2><p>E-Mail: kontakt@example.org</p><h2>Verantwortlich für den Inhalt</h2><p>Vorname Nachname (Anschrift wie oben)</p>'),
    $legal('agb', 'Allgemeine Geschäftsbedingungen', '<h2>1. Geltungsbereich</h2><p>Platzhalter.</p><h2>2. Vertragsschluss</h2><p>Platzhalter.</p><h2>3. Preise und Zahlung</h2><p>Platzhalter.</p>'),
    $legal('datenschutz', 'Datenschutzerklärung', '<h2>1. Verantwortliche Stelle</h2><p>Platzhalter.</p><h2>2. Server-Logfiles</h2><p>Platzhalter.</p><h2>3. Cookies und lokaler Speicher</h2><p>Es werden nur technisch notwendige Speicherungen verwendet (Sitzungs-Cookie für das Backoffice, Farbmodus und Cookie-Hinweis im lokalen Speicher des Browsers).</p><h2>4. Deine Rechte</h2><p>Platzhalter.</p>'),
    $legal('cookies', 'Cookie-Richtlinie', '<h2>Technisch notwendige Speicherungen</h2><ul><li>Sitzungs-Cookie (nur bei Anmeldung)</li><li>Farbmodus (lokaler Speicher, optional)</li><li>Cookie-Hinweis gelesen (lokaler Speicher)</li></ul><p>Tracking findet ohne Einwilligung nicht statt.</p>'),
    $legal('zahlung', 'Zahlungsbedingungen', '<h2>Zahlungsarten</h2><p>Platzhalter.</p>'),
    $legal('versand', 'Versand und Lieferung', '<h2>Lieferzeiten und Versandkosten</h2><p>Platzhalter. Bei digitalen Produkten: Bereitstellung per Download bzw. Freischaltung.</p>'),
    $legal('widerruf', 'Widerrufsbelehrung', '<h2>Widerrufsrecht</h2><p>Platzhalter.</p><h2>Muster-Widerrufsformular</h2><p>Platzhalter.</p>'),
    $legal('jugendschutz', 'Jugendschutz', '<h2>Altersfreigaben</h2><p>Platzhalter. Angaben zu Alterskennzeichen (z. B. USK) und Jugendschutzbeauftragten.</p>'),
    $legal('barrierefreiheit', 'Erklärung zur Barrierefreiheit', '<h2>Stand der Vereinbarkeit</h2><p>Platzhalter.</p><h2>Feedback und Kontakt</h2><p>Platzhalter.</p>'),
    $legal('ki-philosophie', 'KI-Philosophie', '<h2>Wie wir KI einsetzen</h2><p>Platzhalter. Beschreibe transparent, wofür KI-Systeme verwendet werden, wie Inhalte geprüft werden und wo menschliche Verantwortung liegt.</p>'),
    [
        'slug' => 'credits',
        'title' => 'Credits',
        'page_type' => 'page',
        'status' => 'published',
        'content_format' => 'html',
        'content_html' => '<p>Dieses Projekt entsteht dank vieler Menschen und Werkzeuge. Hier danken wir allen Mitwirkenden und nennen die verwendeten Komponenten samt Lizenzen.</p>',
        'meta_description' => 'Mitwirkende und verwendete Komponenten',
    ],
    [
        'slug' => 'cookie-box-text',
        'title' => 'Text der Cookie-Box',
        'page_type' => 'snippet',
        'status' => 'published',
        'content_format' => 'html',
        'content_html' => '<p>Wir verwenden nur technisch notwendige Speicherungen. Tracking findet ohne deine Einwilligung nicht statt.</p>',
        'meta_description' => '',
    ],
    [
        'slug' => 'widerrufs-button-text',
        'title' => 'Text des Widerrufs-Buttons',
        'page_type' => 'snippet',
        'status' => 'published',
        'content_format' => 'html',
        'content_html' => '<p>Vertrag widerrufen</p>',
        'meta_description' => '',
    ],
];
