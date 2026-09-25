# 0.5.1: Briefing, Templates und Empfehlungen

Diese Seite erklärt, wie ein KI-Agent ein neues Projekt mit dem MGD-Plattform-Builder aufsetzt: **Briefing → Template → Empfehlungen → Bauen → Version und Release Notes**. Die Pflichtfunktionen selbst stehen auf [[23-AI-CMS-Pflichtfunktionen]].

```bash
mgd-platform template create php-mysql-starter --target ./mein-projekt
mgd-platform init --target ./mein-projekt
mgd-platform briefing ./mein-projekt --write
mgd-platform recommend ./mein-projekt
mgd-platform validate ./mein-projekt
```

---

## 1. Das Briefing

Bevor ein Agent baut, führt er das Interview aus `platform/BRIEFING.md` durch. Die Fragen stehen maschinenlesbar in `registry/briefing.yml`; jede Frage zeigt auf ein Feld in `MGD_PLATFORM.yml`.

```bash
mgd-platform briefing ./mein-projekt           # ✓ beantwortet · ✗ offene Pflichtfrage · optional
mgd-platform briefing ./mein-projekt --write   # erzeugt BRIEFING.md mit Checkboxen
```

Solange Pflichtfragen offen sind, endet der Befehl mit Exit-Code 1 – praktisch für Agenten und CI.

### Was der Agent immer abfragt

| Bereich | Fragen |
|---|---|
| Projekt | Beschreibung, Typ, Länder, Sprachen |
| Template & Hosting | Template, **zweite MySQL-Datenbank für personenbezogene Daten?**, Auslieferung (FTP, FTPS, SFTP, Git, Docker), Staging, Backups |
| Versionierung | Startstatus (Standard `0.0.1 Pre-Alpha`), **wo die Version angezeigt wird** (Login, Einstellungen, Spieleinstellungen, Footer, öffentliche/private Landingpage), **öffentlich/privat/beides**, welche Release Notes das Frontend zeigt |
| CMS | **Editor** (TinyMCE, GrapesJS, Quill, Editor.js, Markdown, Textfeld) und **lokal eingebunden oder CDN**, Rechts- und Pflichtseiten |
| Credits | beteiligte Personen und Rollen, Bestätigung „alles lokal eingebettet“ |
| Backoffice | Einstellungen durchsuchbar (Pflicht), **Dateispeicherorte anzeigen?**, **Code-Editoren für PHP, JS, CSS, ...?**, Rollenansichten |
| Darstellung | Standardmodus, **Light/Dark-Umschalter ja/nein und wo**, Markenfarben |
| Erlebnis | **Updater**, **Ladeüberbrückung** (Ladebildschirm, Spinner, Fortschrittsbalken), **SEO**, Cookie-Box, Wartungsmodus, Fehlerseiten, Onboarding, Suche |
| Agenten | welche Agenten, Freigabe für Produktionsänderungen, angenommene Empfehlungen |

Pflichtfunktionen (Versionierung, Release Notes, Credits, Rechtstexte, Einstellungssuche, Light/Dark, Design) werden **nie infrage gestellt** – gefragt wird nur, wie sie eingerichtet werden.

Abschluss: `briefing.completed: true`, Datum, offene Punkte in `briefing.open_questions`.

## 2. Empfehlungen: MGD-DevOS, Skills und Plugins

`mgd-platform recommend` gleicht das Projektprofil mit `registry/recommendations.yml` ab (Projekttyp, Features, Briefing-Themen, Staging, Docker, Stichwörter in der Beschreibung) und nennt passende öffentliche Projekte von [MichaelGahnDESIGN](https://github.com/MichaelGahnDESIGN) mit Begründung.

| Empfehlung | Wann |
|---|---|
| [MGD-DevOS](https://github.com/MichaelGahnDESIGN/MGD-DevOS) | AI-Agenten, mehrere Projekte oder Dashboards – Desktop-Projektzentrale mit `/projektstart` und `/dashboard` |
| DEV, Todo, Living Documentation, Backup, ProjectClean | immer (Basis) |
| Software Updater Skill | wenn ein Updater gewünscht ist |
| AI Project Updater Skill | bei Staging |
| Fragenkatalog Skill | bei konzeptlastigen Projekten (Spiele, Creator, Community) |
| CI Designmanual | bei Marken-/Designfokus |
| AI PlayTest, Bugreport | Tests mit Nutzerrollen, Feedback-Systeme |
| AI Thread, Autopilot, Prozesse, Claude-Codex MCP | bei intensiver Agentenarbeit |
| WordPress MCP, Divi 5, AI-Kennzeichnung, JTL SEO | nur bei passenden Stichwörtern (WordPress, Shopware, JTL, KI-Bilder) |
| Platform Builder | wenn Docker-Hosting geplant ist |

Empfehlungen sind Vorschläge. Angenommene Einträge stehen in `briefing.accepted_recommendations` und werden mit ✓ markiert.

## 3. Templates

Im Ordner `templates/` liegen Projektdateien (Profil, `AGENTS.md`, `CLAUDE.md`, `version.example.json`, `release-notes.example.json`) und **Starter-Templates**. Jedes Starter-Template braucht:

* `template.json` (Anforderungen, Datenbanken, Auslieferung, Features)
* **eine Light- und eine Dark-Variante** – Pflicht
* `version.json` (Start `0.0.1 Pre-Alpha`) und `release-notes.json`
* `README.md`

`npm run check:templates` prüft das automatisch.

```bash
mgd-platform template list
mgd-platform template check
mgd-platform template create php-mysql-starter --target ./mein-projekt
```

### php-mysql-starter

Ein FTP-fähiges CMS in reinem PHP (ab 8.2, ohne Composer) mit MySQL/MariaDB.

**Mindestanforderung:** PHP, FTP und **eine MySQL-Datenbank**, in der mindestens die Logindaten liegen (dazu Einstellungen und CMS-Inhalte).
**Optional:** eine **zweite MySQL-Datenbank** für personenbezogene und sensible Daten (Profile, Anschriften, Einwilligungen). Ohne zweite Datenbank läuft der Zugriff trotzdem über eine eigene Verbindung, sodass sich die Daten später sauber auslagern lassen.

Enthalten:

* Login mit Drosselung, Rollen Admin > Redaktion > Moderation > Nutzer, CSRF, Security-Header mit CSP-Nonce, Audit-Log
* Versionsnummer aus `version.json`, Anzeigeorte und Sichtbarkeit einstellbar
* Release-Notes-Timeline (öffentlich nur `frontend`, Backoffice alles), Sync aus `release-notes.json`
* Credits: Personen und Rollen, Komponenten mit Logo, Anbieter, Links, Lizenz-Tags
* CMS-Seiten inkl. aller Rechtstexte und Snippets: bearbeiten, löschen (Papierkorb), neu anlegen, Revisionen, Export/Import, serverseitiger HTML-Sanitizer
* Editor-Auswahl mit **lokaler Einbindung** (`public/assets/vendor/<editor>/`) oder CDN
* Einstellungen mit Suche und Kategoriefilter (auch ohne JavaScript), Design-Farben für Light und Dark, Light/Dark-Umschalter mit Orten
* Dateispeicherorte mit Status, Code-Editoren für CSS/JS (PHP nur mit Konfigurationsschalter, Backup vor dem Speichern)
* SEO (Titel, Beschreibung, `sitemap.xml`, `robots.txt`, noindex), Ladebildschirm, Updater-Prüfung (nie automatische Installation), Cookie-Box, Wartungsmodus
* Web-Installer für Hosting ohne SSH (Token + Lock-Datei), CLI-Installer, FTP/FTPS-Deploy (standardmäßig Probelauf)

Installation per FTP:

1. `config/config.example.php` nach `config/config.php` kopieren, Datenbank, `app.key` und `install.token` eintragen
2. alles hochladen (Webroot auf `public/`)
3. `https://<host>/install` öffnen, Admin anlegen, danach `install.token` leeren

Installation per CLI:

```bash
cp config/config.example.php config/config.php
php scripts/install.php --admin-email=admin@example.org --admin-name="Admin"
php scripts/ftp-deploy.php            # Probelauf
php scripts/ftp-deploy.php --execute  # hochladen (FTPS)
```

Tests: `php tests/smoke.php` gegen eine **Testdatenbank** (Umgebungsvariablen `MGD_TEST_DB_*`). In GitHub Actions laufen Lint und Smoke-Test gegen MariaDB.

> [!NOTE]
> Pre-Alpha-Grenzen: noch keine Benutzerverwaltung und kein Medien-Upload im Backoffice, GrapesJS speichert nur HTML, der Updater sendet den Kanal noch nicht mit. Siehe `templates/php-mysql-starter/README.md`.

## 4. Nach jeder Auslieferung

```bash
mgd-platform version --bump patch --note "Kurzbeschreibung" --audience frontend,backoffice
mgd-platform version --check
```

Danach im Backoffice „Aus release-notes.json synchronisieren“ oder `php scripts/sync-release-notes.php`.

Technische Doku (Englisch): [WIKI/18-CMS](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/tree/main/WIKI/18-CMS)
