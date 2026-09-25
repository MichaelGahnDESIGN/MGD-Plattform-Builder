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
| Einstellungen | Backoffice → Einstellungen | Über 15 Kategorien (u. a. E-Mail), Suche und Kategoriefilter clientseitig (`settings-search.js`) und serverseitig (`?q=`, `?category=`). |
| CMS-Editor | Einstellungen → CMS-Editor | TinyMCE, GrapesJS, Quill, Markdown oder Textfeld; lokal (`public/assets/vendor/`) oder per CDN. Fallback auf Textfeld. |
| GrapesJS-Design | CMS-Seiten (Editor GrapesJS) | Format `grapesjs` speichert HTML, CSS und Projektdaten (JSON, max. `security.max_editor_project_bytes`) – in Revisionen, Wiederherstellung und Import/Export. CSS wird bereinigt (`CssSanitizer`: kein `@import`, keine externen `url()`, kein `expression()`/`behavior`) und auf `.page-content` begrenzt; öffentliche Ausgabe als `<style nonce>`. Inline-`style`-Attribute werden weiterhin entfernt – GrapesJS arbeitet mit Klassen/IDs (`avoidInlineStyle`, `forceClass`). |
| Medien | Backoffice → Medien | Upload (mehrere Dateien) von JPG, PNG, WebP, GIF und PDF (kein SVG/HTML). Endung, erkannter MIME-Typ und Bildprüfung müssen passen; Größenlimit `security.max_upload_bytes`; Bilder werden per GD neu kodiert (`media.reencode_images`, entfernt EXIF). Zufällige Dateinamen unter `/uploads/JJJJ/MM/`. Suche, Alt-Text, Pfad kopieren, Löschen nur ohne Verwendung (Seiten, Credit-Logos, Einstellungen). Pfad-Vorschläge im Credits-Logo, OG-Bild und Lade-Logo. |
| Benutzer | Backoffice → Benutzer (Admin) | Liste mit Suche, Rollen-/Statusfilter und Seiten. Anlegen mit Einmal-Passwort (vorgegeben oder erzeugt, nur einmal angezeigt), Rolle/Status/Name/E-Mail ändern, Passwort-Reset durch Admin, Löschen mit Bestätigung. Der letzte aktive Admin und das eigene Konto können nicht herabgestuft, deaktiviert oder gelöscht werden. |
| Mein Konto | Backoffice → Mein Konto | Anzeigename und eigenes Passwort ändern (aktuelles Passwort nötig). Nach Login mit Einmal-Passwort ist eine Passwortänderung Pflicht. |
| Passwort vergessen | `/passwort-vergessen`, Einstellungen → E-Mail | Optional (`mail.password_reset`). Link mit Einmal-Token (32 Zufallsbytes, gespeichert als SHA-256, 60 min gültig), gedrosselt pro E-Mail/IP, Antworten immer neutral. Benötigt Mail-Transport, Absenderadresse und `app.url` (oder SEO → Kanonische Basis-URL). |
| Design | Einstellungen → Design | Farben getrennt für Hell/Dunkel, Radius, lokale Schrift. Ausgabe als CSS-Variablen, Vorschau, „Auf Standard zurücksetzen“. |
| Light/Dark | Einstellungen → Darstellung | Standardmodus (System/Hell/Dunkel), Umschalter an wählbaren Orten, Nutzerwahl im Browser. Kein Flackern dank Inline-Skript mit Nonce. |
| Dateispeicherorte | Backoffice → Dateispeicherorte | Zeigt die Pfade aus `config.php` mit Status (vorhanden/beschreibbar). Nicht per Web änderbar. |
| Code-Editoren | Backoffice → Code-Editoren | `custom.css`/`custom.js` für Admins. PHP (`custom/hooks.php`) nur bei Einstellung **und** `security.allow_php_editor = true`. Sicherung vor jedem Speichern, Audit-Log. |
| SEO | Einstellungen → SEO | Titelmuster, Meta-Beschreibung, OG-Bild (lokal), index/noindex, Canonical; `/sitemap.xml` und `/robots.txt`. |
| Ladebildschirm | Einstellungen → Ladebildschirm | Spinner, Balken oder Logo, Mindestdauer, Failsafe nach 6 s. |
| Updater | Backoffice → Updater | Prüft ein HTTPS-Manifest für den eingestellten Kanal (stable, beta, alpha, lts; 5 s Timeout). Installiert nie automatisch. Format siehe unten. |
| Cookie-Box | Einstellungen → Cookie-Box | Text aus Snippet `cookie-box-text`. Standard: nur essenziell. Tracking nur nach Einwilligung (Event `mgd:consent`). |
| Wartungsmodus | Einstellungen → Wartungsmodus | HTTP 503 für Besucher, Admins können umgehen. Login und Backoffice bleiben erreichbar. |

### Update-Manifest

Der Updater ruft die Manifest-URL mit angehängtem `channel=<kanal>` auf (vorhandene Query-Parameter bleiben erhalten).
Zwei Formate werden akzeptiert:

```json
{ "version": "1.2.0", "status": "stable", "notes_url": "https://example.org/release-notes" }
```

```json
{
  "channels": {
    "stable": { "version": "1.2.0", "status": "stable", "notes_url": "https://example.org/release-notes" },
    "beta":   { "version": "1.3.0", "status": "beta" }
  }
}
```

Fehlt der eingestellte Kanal, wird `stable` verwendet (mit Hinweis); fehlt auch dieser, schlägt die Prüfung fehl.
`status` muss ein gültiger Versionsstatus sein, `notes_url` wird nur als HTTPS-Link angezeigt.

### E-Mail-Versand

`config.php` → `mail.transport`: `disabled` (Standard), `mail` (PHP `mail()`) oder `smtp`
(`host`, `port`, `encryption` = `tls` für STARTTLS bzw. `ssl` für implizites TLS, `username`, `password`, `timeout`).
Zertifikate werden immer geprüft. Absenderadresse und -name stehen unter Einstellungen → E-Mail.
Für Links in E-Mails `app.url` setzen (z. B. `https://example.org`); die URL wird nie aus dem Host-Header abgeleitet.

## Rollen

| Rolle | Rechte |
|-------|--------|
| `admin` | Alles, inkl. Benutzerverwaltung, Einstellungen, Dateispeicherorte, Code-Editoren, Updater, Audit-Log, endgültiges Löschen |
| `editor` | CMS-Seiten, Papierkorb (wiederherstellen), Revisionen, Import/Export, Medien, Release Notes, Credits, Mein Konto |
| `moderator` | Dashboard, Seitenliste (nur lesen), Mein Konto |
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
- Audit-Log für Seiten, Einstellungen, Credits, Release Notes, Code-Änderungen, Import/Export, Logins, Benutzerverwaltung, Kontoänderungen, Passwort-Resets, Medien und Update-Prüfungen.
- Passwörter: mindestens 12 Zeichen, `password_hash()`; Einmal-Passwörter erzwingen eine Änderung beim nächsten Login.
- Uploads: Allowlist (JPG, PNG, WebP, GIF, PDF), MIME-Prüfung per `finfo`, keine PHP-/Skript-Inhalte, zufällige Dateinamen.
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
Wiederherstellung, Papierkorb, Import/Export, Sanitizer, Einstellungssuche, Versionslabel, Release-Notes-Filter,
Credits, Benutzerverwaltung (inkl. Schutz des letzten Admins), Passwort-Reset-Token, Medien, GrapesJS-Revisionen,
CSS-Sanitizer, Mailer (Header-Injection) und Update-Manifeste. Die Prüfungen ohne Datenbank stehen am Anfang
(`tests/smoke/gaps-no-db.php`), die mit Datenbank in `tests/smoke/gaps-db.php`. Nur gegen eine **Testdatenbank** ausführen.

Nach einem Update immer `php scripts/migrate.php` ausführen (neu: `002_user_management`, `003_media`,
`004_page_design`). `004` ersetzt die CHECK-Bedingung `chk_pages_format` portabel (MySQL ≥ 8.0.19 bzw. MariaDB ≥ 10.2;
ältere MySQL-Versionen ignorieren CHECK-Bedingungen ohnehin).

## Verzeichnisstruktur

```
config/            Konfiguration (config.php nicht versionieren)
custom/hooks.php   optionale Projekt-Hooks (PHP)
database/          SQL-Migrationen (core, private) und Seed-Daten
public/            Web-Root: index.php, .htaccess, assets/, uploads/
scripts/           CLI: install, migrate, sync-release-notes, export/import, ftp-deploy
src/               Anwendung (eigener PSR-4-Autoloader, Namespace MGD\Starter)
storage/           Sicherungen, Logs, Exporte (nicht öffentlich)
tests/smoke.php    Smoke-Test (Teilprüfungen in tests/smoke/)
```

## Lizenz, „powered by“-Label und Whitelabel

Der Starter steht unter der [MGD-Lizenz](MGD-Lizenz.md). Websites dürfen frei genutzt werden, auch gewerblich.
Dafür zeigt jede Installation das Label **„powered by: Michael Gahn DESIGN“** (Logo, Link auf michael-gahn.de in
neuem Tab) im öffentlichen Footer, auf öffentlichen und privaten Landingpages, beim Login, im Backoffice-Footer und in
den Einstellungen, außerdem die Seite **Einstellungen › Lizenz** mit dem vollständigen Lizenztext.

- Gestaltbar ist nur die Ausrichtung (Einstellungen › Lizenz: links, Mitte, rechts); Light/Dark passt sich automatisch an.
- Label und Lizenzseite entfallen nur mit einer **Whitelabel-Lizenz** (500 €, einmalig pro Projekt/Domain). Den signierten
  Schlüssel (`MGD1.…`) unter Einstellungen › Lizenz oder in `config.php` (`license.whitelabel_key`) eintragen.
- Werden Lizenztext, Label oder Logos verändert, zeigt das Backoffice Admins einen Warnhinweis.

## Module

Eigene und kostenpflichtige Module liegen unter `modules/<id>/` und werden unter **Einstellungen › Module** aktiviert.
Aufbau, Manifest und Beispiel: [modules/README.md](modules/README.md). Kostenpflichtige Module brauchen einen signierten
Modul-Schlüssel. Nach dem Aktivieren laufen ihre Migrationen automatisch.

## Update bestehender Installationen

Nach dem Hochladen einer neuen Version immer `php scripts/migrate.php` ausführen (oder den Web-Installer bei
Hosting ohne SSH erneut nach Anleitung nutzen). Ab dieser Version braucht der Login die Spalten aus Migration 002.

## Bekannte Grenzen (Pre-Alpha)

- Andere laufende Sitzungen eines Kontos bleiben nach Passwortänderung, Deaktivierung oder Rollenwechsel bis zum nächsten
  Seitenaufruf gültig (Rolle/Status werden pro Anfrage neu gelesen, Sitzungen werden aber nicht zentral beendet).
- Medien: animierte GIFs werden nicht neu kodiert; Verwendungsprüfung durchsucht aktuelle Seiten, Credit-Logos und
  Einstellungen, aber keine alten Revisionen. Keine Bildbearbeitung/Thumbnails.
- GrapesJS: Backslash-Escapes und externe Ressourcen im CSS werden entfernt; `style`-Attribute bleiben verboten.
- Die Rechtstexte sind Platzhalter und **keine Rechtsberatung**.
