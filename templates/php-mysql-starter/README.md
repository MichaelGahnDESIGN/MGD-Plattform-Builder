# MGD PHP/MySQL Starter

**Version 0.0.1 Pre-Alpha** · Lizenz: MIT

Ein FTP-fähiger, KI-freundlicher CMS-Starter für Websites von Spielen, Projekten und Plattformen.
Reines PHP ≥ 8.2 ohne Composer, MySQL/MariaDB über PDO – läuft auf günstigem Shared Hosting.
Jede Oberfläche gibt es in einer **Light**- und einer **Dark**-Variante.

## Mindestanforderungen

| Bedarf | Details |
|--------|---------|
| PHP | ≥ 8.2 mit `pdo_mysql`, `mbstring`, `dom` (optional `curl` für den Updater, `ftp` für das Deploy-Skript) |
| Webserver | Apache mit `mod_rewrite` (`.htaccess` wird mitgeliefert) |
| Zugang | FTP/FTPS – SSH ist **nicht** nötig (Web-Installer) |
| Datenbank 1 (Pflicht) | „Kern“: Logins, Einstellungen, CMS, Release Notes, Credits, Audit-Log |
| Datenbank 2 (optional) | „Privat“: personenbezogene Profildaten (Klarname, Adresse, Geburtsdatum, Einwilligungen) |

Ohne zweite Datenbank liegen die privaten Tabellen in der Kerndatenbank, werden aber über eine **eigene
Verbindung** (`PrivateDataRepository`) angesprochen. Später genügt es, `db.private` in der Konfiguration zu
setzen und die Tabelle `user_profiles` umzuziehen – ohne Codeänderung.

## Installation per FTP (ohne SSH)

1. `config/config.example.php` nach `config/config.php` kopieren und ausfüllen:
   - `db.core` (und optional `db.private`)
   - `app.key`: zufälliger Wert, z. B. Ausgabe von `php -r "echo bin2hex(random_bytes(32));"`
   - `install.token`: beliebiger geheimer Text mit mindestens 16 Zeichen
   - `app.base_path`, falls die Seite in einem Unterordner liegt (z. B. `/cms`)
2. Alles per FTP hochladen (oder `php scripts/ftp-deploy.php --execute --with-config`, siehe unten).
3. Document Root des Webservers auf `public/` setzen. Geht das beim Hoster nicht, sorgt die mitgelieferte
   `.htaccess` im Projektordner dafür, dass alles nach `public/` umgeleitet und interne Ordner gesperrt werden.
4. Schreibrechte für `storage/`, `public/uploads/` und `public/assets/custom/` setzen (z. B. 755/775 je nach Hoster).
5. `https://deine-domain.example/install` öffnen, Token eingeben und den ersten Admin anlegen.
6. Danach `install.token` in `config/config.php` wieder leeren. Die Datei `storage/install.lock` sperrt den Installer.
7. Anmelden unter `/login`, Rechtstexte unter **CMS-Seiten** anpassen (alle sind als
   „Platzhalter – rechtlich prüfen lassen“ markiert).

## Installation mit CLI (SSH oder lokal)

```bash
cp config/config.example.php config/config.php   # und ausfüllen
php scripts/install.php --admin-email=admin@example.org --admin-name="Admin"
# Passwort wird verdeckt abgefragt oder aus MGD_ADMIN_PASSWORD gelesen – nie als Argument übergeben.
```

Weitere Skripte:

| Skript | Zweck |
|--------|-------|
| `php scripts/migrate.php` | Ausstehende Migrationen (Kern + Privat) ausführen |
| `php scripts/sync-release-notes.php` | `release-notes.json` in die Datenbank übernehmen (Upsert nach Version + Titel) |
| `php scripts/export-pages.php [--with-revisions] [--ids=1,2] [--out=datei.json]` | CMS-Seiten als JSON exportieren |
| `php scripts/import-pages.php --file=datei.json` | CMS-Seiten importieren (neue Seiten oder neue Revisionen, HTML wird bereinigt) |
| `php scripts/ftp-deploy.php` | FTP-Deployment, standardmäßig nur **Probelauf** |

### FTP-Deployment

```bash
php scripts/ftp-deploy.php                    # Probelauf: Dateiliste anzeigen
php scripts/ftp-deploy.php --execute          # Hochladen per FTPS (explizites TLS)
```

- Zugangsdaten aus `config.php` (Abschnitt `ftp`) oder Umgebungsvariablen `MGD_FTP_HOST`, `MGD_FTP_PORT`,
  `MGD_FTP_USER`, `MGD_FTP_PASSWORD`, `MGD_FTP_REMOTE_PATH`.
- Unverschlüsseltes FTP nur mit `--insecure` (nicht empfohlen).
- `config/config.php` wird nur mit `--with-config` hochgeladen; `custom.css`, `custom.js` und `custom/hooks.php`
  nur mit `--with-custom` (damit im Backoffice gemachte Änderungen nicht überschrieben werden).
- Ausgeschlossen: `.git`, `tests/`, `*.md`, Uploads und Laufzeitdaten in `storage/`.

## Funktionen

| Bereich | Wo | Beschreibung |
|---------|----|--------------|
| Versionierung | `version.json`, Einstellungen → Versionsnummern | Schema MAJOR.MINOR.PATCH (Major = Release-Linie, Minor = neue Funktionen in Spiel/Editor, Patch = Patches bestehender Funktionen). Status: pre-alpha, alpha, beta, pre-release, release, stable, staging, hotfix, lts, deprecated. Anzeige wählbar (Login, Einstellungen, Backoffice-Footer, öffentlicher Header/Footer, öffentliche/private Startseite) und nach Zielgruppe (öffentlich/privat/beides). |
| Release Notes | Backoffice → Release Notes, öffentlich `/release-notes` | Zeitleiste nach Version (neueste zuerst) mit Status-, Typ- und Datumsangabe. Zielgruppen-Tags: frontend, backoffice, editor, platform, game, api. Öffentlich erscheinen nur `frontend`-Einträge. Button „Aus release-notes.json synchronisieren“. |
| Credits | Backoffice → Credits, öffentlich `/credits` | Personen mit Rollen, Einleitung als CMS-Seite `credits`, Komponenten (KI, Tools, Bibliotheken, Schriften, Icons …) mit lokalem Logo, Anbieterhinweis, Links, Lizenz, Tags, kommerzieller Nutzung, Namensnennungspflicht. |
| CMS & Rechtstexte | Backoffice → CMS-Seiten, öffentlich `/seite/{slug}` | Seitentypen Seite/Rechtliches/Snippet. Jede Speicherung = Revision (Autor + Notiz). Papierkorb mit Wiederherstellen, endgültiges Löschen nur für Admins. Revisionen ansehen und wiederherstellen (als neue Revision). Import/Export als JSON. Startseite = Slug `home`. |
| Einstellungen | Backoffice → Einstellungen | 15 Kategorien, Suche und Kategoriefilter clientseitig (`settings-search.js`) und serverseitig (`?q=`, `?category=`). |
| CMS-Editor | Einstellungen → CMS-Editor | TinyMCE, GrapesJS, Quill, Markdown oder Textfeld; lokal (`public/assets/vendor/`) oder per CDN. Fallback auf Textfeld. |
| Design | Einstellungen → Design | Farben getrennt für Hell/Dunkel, Radius, lokale Schrift. Ausgabe als CSS-Variablen, Vorschau, „Auf Standard zurücksetzen“. |
| Light/Dark | Einstellungen → Darstellung | Standardmodus (System/Hell/Dunkel), Umschalter an wählbaren Orten, Nutzerwahl im Browser. Kein Flackern dank Inline-Skript mit Nonce. |
| Dateispeicherorte | Backoffice → Dateispeicherorte | Zeigt die Pfade aus `config.php` mit Status (vorhanden/beschreibbar). Nicht per Web änderbar. |
| Code-Editoren | Backoffice → Code-Editoren | `custom.css`/`custom.js` für Admins. PHP (`custom/hooks.php`) nur bei Einstellung **und** `security.allow_php_editor = true`. Sicherung vor jedem Speichern, Audit-Log. |
| SEO | Einstellungen → SEO | Titelmuster, Meta-Beschreibung, OG-Bild (lokal), index/noindex, Canonical; `/sitemap.xml` und `/robots.txt`. |
| Ladebildschirm | Einstellungen → Ladebildschirm | Spinner, Balken oder Logo, Mindestdauer, Failsafe nach 6 s. |
| Updater | Backoffice → Updater | Prüft ein HTTPS-Manifest `{version, status, notes_url}` (5 s Timeout). Installiert nie automatisch. |
| Cookie-Box | Einstellungen → Cookie-Box | Text aus Snippet `cookie-box-text`. Standard: nur essenziell. Tracking nur nach Einwilligung (Event `mgd:consent`). |
| Wartungsmodus | Einstellungen → Wartungsmodus | HTTP 503 für Besucher, Admins können umgehen. Login und Backoffice bleiben erreichbar. |

## Rollen

| Rolle | Rechte |
|-------|--------|
| `admin` | Alles, inkl. Einstellungen, Dateispeicherorte, Code-Editoren, Updater, Audit-Log, endgültiges Löschen |
| `editor` | CMS-Seiten, Papierkorb (wiederherstellen), Revisionen, Import/Export, Release Notes, Credits |
| `moderator` | Dashboard, Seitenliste (nur lesen) |
| `user` | Kein Backoffice-Zugang |

Die Rolle wird in **jedem** Controller serverseitig geprüft; das Menü blendet nur zusätzlich aus.

## Sicherheit

- Prepared Statements (PDO, echte Prepares) für alle Abfragen.
- CSRF-Token auf jedem POST, Session-Cookie `HttpOnly`, `SameSite=Strict`, `Secure` (konfigurierbar), `session_regenerate_id` beim Login.
- Login-Drosselung (5 Fehlversuche pro E-Mail bzw. 20 pro IP in 15 Minuten; gespeichert als HMAC-Hash).
- Alle Ausgaben werden escaped; CMS-HTML wird per Allowlist-Sanitizer (DOMDocument) bereinigt: keine Skripte, keine `on*`-Attribute, keine `javascript:`/`data:`-URLs.
- Security-Header: CSP mit Nonce für Skripte (kein `unsafe-inline` für Skripte), `frame-ancestors 'none'`, `X-Content-Type-Options`, `Referrer-Policy`, optional HSTS.
  Im Backoffice sind Inline-**Styles** erlaubt, weil die WYSIWYG-Editoren sie benötigen.
- `public/uploads/` führt keine Skripte aus; `config/`, `src/`, `storage/`, `custom/` sind per `.htaccess` gesperrt.
- Audit-Log für Seiten, Einstellungen, Credits, Release Notes, Code-Änderungen, Import/Export und Logins.
- Keine Geheimnisse im Repository: nur `config.example.php` mit Platzhaltern; `config/config.php` ist per `.gitignore` ausgeschlossen.

## Light- und Dark-Variante

- `public/assets/css/theme-light.css` und `theme-dark.css` enthalten jeweils die vollständige Palette.
- Umschaltung über `data-theme="light|dark"` am `<html>`-Element; ohne JavaScript folgt die Seite `prefers-color-scheme`.
- Die Design-Einstellungen überschreiben die Variablen (`--color-primary`, `--color-surface` …) je Modus.
- Eigene Styles in `custom.css` bitte immer über diese Variablen schreiben.

## Tests

```bash
MGD_TEST_DB_HOST=127.0.0.1 MGD_TEST_DB_PORT=3306 MGD_TEST_DB_NAME=mgd_starter_test \
MGD_TEST_DB_USER=mgd MGD_TEST_DB_PASSWORD=mgdpass php tests/smoke.php
```

Der Smoke-Test ist nicht destruktiv (Testdaten mit Zufallssuffix) und prüft u. a. Migrationen, Revisionen,
Wiederherstellung, Papierkorb, Import/Export, Sanitizer, Einstellungssuche, Versionslabel, Release-Notes-Filter
und Credits. Nur gegen eine **Testdatenbank** ausführen.

## Verzeichnisstruktur

```
config/            Konfiguration (config.php nicht versionieren)
custom/hooks.php   optionale Projekt-Hooks (PHP)
database/          SQL-Migrationen (core, private) und Seed-Daten
public/            Web-Root: index.php, .htaccess, assets/, uploads/
scripts/           CLI: install, migrate, sync-release-notes, export/import, ftp-deploy
src/               Anwendung (eigener PSR-4-Autoloader, Namespace MGD\Starter)
storage/           Sicherungen, Logs, Exporte (nicht öffentlich)
tests/smoke.php    Smoke-Test
```

## Bekannte Grenzen (Pre-Alpha)

- Keine Benutzerverwaltung im Backoffice (weitere Konten per SQL/CLI anlegen), kein Passwort-Reset per E-Mail.
- Keine Medienverwaltung/Upload-Oberfläche; Dateien per FTP nach `public/uploads/` legen.
- GrapesJS speichert nur HTML, keine Editor-Stile.
- Die Rechtstexte sind Platzhalter und **keine Rechtsberatung**.
