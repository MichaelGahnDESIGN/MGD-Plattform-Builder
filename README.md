<div align="center">

# MGD-Plattform-Builder

**A reusable, agent-friendly foundation for building and operating modern digital platforms.**

Privacy, security, compliance, modular backends, administration, moderation, Docker workflows, documentation and AI-agent collaboration in one project-neutral blueprint.

[![Version](https://img.shields.io/badge/version-0.6.0%20Pre--Alpha-orange?style=flat-square)](version.json)
[![License](https://img.shields.io/badge/license-MIT%20%2B%20MGD-blue?style=flat-square)](LICENSING.md)
[![Claude Code](https://img.shields.io/badge/Claude%20Code-compatible-6B5CE7?style=flat-square)](AGENTS.md)
[![ChatGPT Codex](https://img.shields.io/badge/ChatGPT%20Codex-compatible-10A37F?style=flat-square)](AGENTS.md)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?style=flat-square)](WIKI/06-OPERATIONS/DOCKER-STAGING.md)

**Deutsch** · [English](README.en.md) · [Installation](INSTALL.md) · [GitHub Wiki](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki) · [CLI-Dokumentation](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/18-CLI-Validator-und-Automatisierung) · [Roadmap](ROADMAP.md) · [Mitmachen](CONTRIBUTING.md)

</div>

---

## Neu in 0.6.0 Pre-Alpha: MGD-Plattform-Builder, Lizenz-Label, Benutzer, Medien und Module

Das Projekt heißt jetzt **MGD-Plattform-Builder**. Neu:

| Bereich | Was ist neu |
|---|---|
| **Lizenz** | Dual-Lizenz: CLI, Schemas und Doku bleiben MIT; Starter-Templates, Referenzplattform und Label stehen unter der [MGD-Lizenz](MGD-Lizenz.md). Details: [LICENSING.md](LICENSING.md) |
| **„powered by“-Label** | Pflicht-Label „powered by: Michael Gahn DESIGN“ mit Logo und Link (neuer Tab) im Footer, auf Landingpages, beim Login, im Backoffice und in den Einstellungen, dazu die Seite **Einstellungen › Lizenz** |
| **Whitelabel** | Label entfällt nur mit Whitelabel-Lizenz (500 €, einmalig pro Projekt/Domain), nachgewiesen durch einen signierten Schlüssel |
| **Benutzerverwaltung** | Benutzer anlegen, Rollen, sperren, löschen, Mein Konto, optionaler Passwort-Reset per E-Mail |
| **Medienbibliothek** | sichere Uploads (Bilder, PDF) mit MIME-Prüfung und Neukodierung ohne Metadaten |
| **GrapesJS** | speichert HTML, CSS und Projektdaten, inkl. Revisionen und Export/Import |
| **Updater** | Release-Kanäle (stable, beta, alpha, lts) im Manifest |
| **Module** | eigene und kostenpflichtige Module unter `modules/<id>/`, aktivierbar im Backoffice |

Mehr: [Lizenz, Label, Whitelabel und Module](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/25-Lizenz-Label-Whitelabel-und-Module)

---

## Seit 0.5.1: AI-generiertes CMS mit Pflichtfunktionen

Mit 0.5.1 wird die Foundation zu einem **AI-Agenten-gesteuerten CMS**. Claude Code, ChatGPT Codex und andere Agenten erstellen damit Websites für Spiele, Projekte und Plattformen – aus einem **Briefing**, einem **Starter-Template** und **Pflichtfunktionen**, die in jedem Projekt immer vorhanden sind.

| Pflichtfunktion | Kurz erklärt |
|---|---|
| **Versionierung** | `version.json` als einzige Quelle, Schema `MAJOR.MINOR.PATCH` + Status (Pre-Alpha … Stable). Start: `0.0.1 Pre-Alpha`. Anzeige unter Login und in den (Spiel-)Einstellungen, weitere Orte im Backoffice unter „Versionsnummern“ konfigurierbar |
| **Release Notes** | Timeline unter Einstellungen; Frontend sieht nur Frontend-Notes, Editor/Admin/Mod sehen alles |
| **Credits** | zuerst Personen und Rollen, dann alle KI-Systeme, Tools, Plugins, Bibliotheken, Schriften und Icons mit Logo, Anbieter, Links und Lizenz-Tags – alles lokal eingebettet |
| **Rechtstexte / CMS-Seiten** | Kontakt, Impressum, AGB, Datenschutz, Cookies, Cookie-Box, Zahlung, Versand, Widerruf, Widerrufs-Button, Jugendschutz, Barrierefreiheit, AI-Philosophie – bearbeiten, löschen, neu anlegen, Revisionen, Export/Import |
| **Einstellungen** | immer durchsuchbar und filterbar; Design-Farben, Light/Dark-Umschalter, Dateispeicherorte, optionale Code-Editoren |
| **Templates** | `templates/php-mysql-starter`: PHP + FTP + 1 MySQL-DB (Logins), optional 2. DB für personenbezogene Daten, immer Light **und** Dark |

```bash
mgd-platform template create php-mysql-starter --target ./mein-projekt
mgd-platform init --target ./mein-projekt
mgd-platform briefing ./mein-projekt --write     # Pflichtfragen: Editor lokal/CDN, Versionsanzeige, Updater, Ladebildschirm, SEO, ...
mgd-platform recommend ./mein-projekt            # passende MGD Skills, Tools, Plugins und MGD-DevOS
mgd-platform version ./mein-projekt --bump patch --note "Erster Deploy" --audience frontend
```

Mehr: [AI-CMS-Pflichtfunktionen](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/23-AI-CMS-Pflichtfunktionen) · [Briefing, Templates und Empfehlungen](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/24-Briefing-Templates-und-Empfehlungen) · [Technische Doku](WIKI/18-CMS/MANDATORY-FEATURES.md)

---

## Seit 0.5: Translation Review, Agenten-Historie und idempotente Jobs

Version 0.5 ergänzt echte Team- und Betriebsworkflows. Übersetzungen durchlaufen jetzt Draft, Review, Published beziehungsweise Rejected. JSON Exporte lassen sich wieder importieren, landen dabei absichtlich erneut im Draft. Service Principals besitzen eigene Detailseiten und eine Lebenszyklus-Historie. Hintergrundjobs können mit Idempotency Keys gegen doppelte Ausführung geschützt und über eine Handler Registry modular verarbeitet werden.

```text
Translation:
draft → review → published
              ↘ rejected

Agent:
created → token_rotated → revoked

Job:
request + idempotency key
→ genau ein Job
→ Handler Registry
→ done / retry / dead
```

Mehr: [0.5 Translation Review, Agenten-Historie und idempotente Jobs](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/22-Translation-Review-Agenten-Historie-und-Idempotente-Jobs)

---

## Seit 0.4: Migrationen, Übersetzungen, Agentenverwaltung und Dead Letter Jobs

Version 0.4 macht die Referenzplattform deutlich betriebstauglicher. Datenbankänderungen laufen jetzt über einen checksum-geprüften Migration Runner, Übersetzungen werden zentral mit Draft- und Published-Status verwaltet, Service Principals können im Backoffice erstellt, rotiert und widerrufen werden und Jobs unterstützen Claiming, Retry und Dead Letter States.

```bash
cd reference/php-mariadb
docker compose up -d db
composer install
cp config.example.php config.php
php scripts/migrate.php
php -S 127.0.0.1:8080 -t public
```

Mehr: [0.4 Migrationen, I18n, Agenten und Jobs](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/21-Migrationen-I18n-Agenten-und-Jobs)

---

## Seit 0.3: startbare PHP/MariaDB Referenzplattform

Version 0.3 ergänzt die ausführbare Foundation um eine **wirklich startbare Referenzplattform**. Sie zeigt Login, Sessions, Rollen, Capabilities, serverseitige Autorisierung, Adminbereich, Audit Log, Security Events, AI-Agenten-Zugänge und Hintergrundjobs in einer kleinen PHP/MariaDB Anwendung.

```bash
cd reference/php-mariadb
docker compose up -d db
composer install
cp config.example.php config.php
php -S 127.0.0.1:8080 -t public
```

Die Referenz wird in GitHub Actions gegen eine echte MariaDB getestet.

[PHP/MariaDB Referenz im Wiki](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/20-PHP-MariaDB-Referenzplattform)

---

## Seit 0.2: CLI, automatische Prüfungen und ausführbare Foundation

Die Foundation besitzt jetzt mit **`mgd-platform` eine eigene CLI**. Damit können Projekte nicht mehr nur anhand der Dokumentation geplant werden. Sie lassen sich initialisieren, validieren, auditieren und vor einem Release automatisch prüfen.

```bash
mgd-platform init --preset game --target ./mein-projekt
mgd-platform validate ./mein-projekt
mgd-platform doctor ./mein-projekt
mgd-platform audit ./mein-projekt --write
mgd-platform release-check ./mein-projekt
mgd-platform module create "Notifications" --target ./mein-projekt
mgd-platform update ./mein-projekt
```

Die wichtigsten Funktionen:

| Befehl | Aufgabe |
|---|---|
| `init` | Neues Projekt aus einem Preset vorbereiten |
| `validate` | `MGD_PLATFORM.yml` gegen Schema und Foundation-Regeln prüfen |
| `doctor` | Grundstruktur, Regeln und Evidence-Verzeichnis prüfen |
| `audit` | Gap Report mit priorisierten Findings erzeugen |
| `release-check` | Release-Gates gegen echte Evidence-Dateien prüfen |
| `module create` | Modulmanifest und Dokumentationsgerüst erzeugen |
| `update` | Foundation-Version vergleichen und Migration anstoßen |
| `version` | Versionsnummer anzeigen, erhöhen, Status setzen, Dateien synchronisieren, Release Note anlegen |
| `briefing` | offene Pflichtfragen des Agenten-Briefings anzeigen oder als `BRIEFING.md` schreiben |
| `recommend` | passende MGD Skills, Tools und Plugins (inkl. MGD-DevOS) empfehlen |
| `template` | Starter-Templates auflisten, prüfen (Light + Dark) und erzeugen |

Zusätzlich enthält das Projekt jetzt eine zentrale **Capability Registry**, maschinenlesbare **Release Evidence**, automatische **GitHub Foundation Checks**, eine **PHP/MariaDB Referenzimplementierung** und ein lokales **Backoffice-Demo**.

Ausführliche Erklärung: [CLI, Validator und Automatisierung](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/18-CLI-Validator-und-Automatisierung)

Referenzcode und Demos: [Referenzimplementierungen und Demos](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/19-Referenzimplementierungen-und-Demos)

---

## Was ist dieses Projekt?

Das **MGD-Plattform-Builder** ist eine Foundation für **AI-generierte CMS-Projekte** – kein SaaS und kein starres Framework.

Es ist eine **wiederverwendbare Plattform-Grundlage** für Projekte, die mehr benötigen als nur Frontend und Datenbank: Benutzerkonten, Rollen, Admin- und Moderatorbereiche, Datenschutz, Sicherheit, Compliance, Backups, Staging, Übersetzungen, Support, Dokumentation, AI-Agenten, Module und ein kontrollierter Entwicklungsprozess.

Die Foundation ist absichtlich **projektneutral**. Sie kennt keine konkrete Anwendung, Marke, Domain, Serveradresse oder kundenspezifische Infrastruktur. Stattdessen beschreibt sie robuste Muster, Datenmodelle, Governance-Regeln und Agenten-Workflows, die auf sehr unterschiedliche Projekte angewendet werden können.

Typische Einsatzbereiche:

- Webplattformen und SaaS-Produkte
- Online-Spiele und Game-Backends
- Community- und Social-Plattformen
- Creator- und Publishing-Plattformen
- Shops und Marktplätze
- interne Portale
- Content- und Datenplattformen
- Apps mit Admin-, Support- oder Moderationsbedarf

> [!IMPORTANT]
> Dieses Repository ist eine technische und organisatorische Grundlage. Es ist **keine Rechtsberatung**, keine Sicherheitszertifizierung und kein Ersatz für eine projektspezifische Prüfung.

---

## Warum gibt es das?

Viele Projekte bauen dieselben kritischen Bereiche immer wieder neu:

- Rollen und Berechtigungen
- Admin- und Moderatoroberflächen
- Datenschutzrechte
- Audit-Logs
- Security-Events
- Backups und Restore
- Staging und Deployment
- Support und Moderation
- Übersetzungen
- Datei- und Datenverwaltung
- Feature Flags
- Dokumentation
- AI-Agenten-Regeln
- rechtliche Prüf- und Release-Gates

Das kostet Zeit und führt dazu, dass Datenschutz, Security und Betrieb oft erst spät als „zusätzliche Aufgaben“ auftauchen.

Dieses Projekt dreht die Reihenfolge um:

**Produktidee → Foundation-Profil → Module und Risiken → technische Umsetzung → dokumentierte Release-Gates.**

---

## Leitprinzipien

1. **Privacy by Design**  
   Datenminimierung, Pseudonymisierung, Verschlüsselung, Löschbarkeit und Betroffenenrechte werden von Anfang an berücksichtigt.

2. **Security by Design**  
   Least Privilege, Defense in Depth, Auditierbarkeit, sichere Defaults, Backups und Incident Response gehören zur Architektur.

3. **Modular statt vorschnell verteilt**  
   Für kleine und mittlere Projekte ist ein modularer Monolith häufig effizienter als früh eingeführte Microservices.

4. **Eine nachvollziehbare Source of Truth**  
   Code, Entscheidungen, Todos und Dokumentation werden versioniert und verlinkt.

5. **Agent-friendly**  
   Claude Code, ChatGPT Codex und andere Agenten sollen schnell verstehen, wo Regeln, Entscheidungen, Risiken und offene Aufgaben stehen.

6. **Kein versteckter Superadmin**  
   Rollen werden durch Capabilities ergänzt. Sensible Datenzugriffe bleiben Need-to-know.

7. **Backup before risk**  
   Vor Migration, Deployment oder destruktiven Änderungen muss ein Rückweg existieren.

8. **Projektneutral**  
   Keine festen Hostinganbieter, Frameworks, Domains oder projektspezifischen Annahmen im Core.

---

## Was die Foundation abdeckt

| Bereich | Enthalten |
|---|---|
| Architektur | modularer Monolith, Module, Events, Jobs, API-Grenzen |
| Daten | relationale Modelle, IDs, private Daten, Audit, Retention |
| Backoffice | Admin, Moderation, CMS, CRM, PIM, Support |
| Berechtigungen | Roles + Capabilities + Policies |
| Datenschutz | Privacy by Design, Betroffenenrechte, Löschung, DSFA-Screening |
| Sicherheit | Threat Model, MFA, Sessions, Secrets, Incident Response |
| Compliance | DE/EU-orientierte Checklisten und Rechtsbibliothek-Modell |
| Betrieb | Docker, Staging, Monitoring, Backup, Restore, Deployment |
| Internationalisierung | Translation Keys, Freigaben, Import/Export |
| Erweiterungen | Module, Themes, Skins, Entitlements |
| Agenten | AGENTS.md, CLAUDE.md, Skill-Integration, Dokumentationsworkflow |
| Governance | Feature-Check, Entscheidungen, Roadmap, Releases |
| CLI & Validation | Init, Validator, Doctor, Audit, Module Generator, Release Check |
| Evidence | maschinenlesbare Release-Nachweise mit Ablaufdatum |
| Referenzen | PHP/MariaDB Architektur und lokales Backoffice-Demo |
| AI-CMS | Versionierung, Release Notes, Credits, Rechtstexte mit Revisionen, durchsuchbare Einstellungen, Design, Light/Dark |
| Briefing & Empfehlungen | Pflichtfragen für Agenten, Empfehlungen für MGD-DevOS und MGD Skills |
| Templates | FTP-fähiger PHP/MySQL-Starter mit Light- und Dark-Variante |

---

## Was die Foundation bewusst nicht ist

- kein monolithisches Fertig-CMS von der Stange – der PHP/MySQL-Starter ist ein Ausgangspunkt, den Agenten projektspezifisch ausbauen
- kein fertiges PHP-, Node-, Java- oder .NET-Framework
- kein Ersatz für Laravel, Symfony, Django, Spring, Directus oder ähnliche Systeme
- kein automatisch rechtssicheres Komplettpaket
- kein Grund, personenbezogene Daten unnötig zu sammeln
- kein Freibrief für ungeprüfte AI-Agenten-Aktionen
- kein Plugin-Marktplatz für beliebigen ausführbaren Fremdcode
- kein Zwang zu GitHub, Gitea, Docker oder einer bestimmten Datenbank

Die Foundation beschreibt **Schnittstellen, Prinzipien, Prüfpunkte und Templates**. Die konkrete Implementierung darf zum Projekt passen.

---

## Schnellstart

### 1. Repository klonen und CLI vorbereiten

```bash
git clone https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder.git
cd MGD-Plattform-Builder
npm install
npm link
```

Danach steht lokal der Befehl `mgd-platform` zur Verfügung.

### 2. Projekt mit der CLI initialisieren

```bash
mgd-platform init --preset general --target ../mein-projekt
cd ../mein-projekt
```

Alternativ kann das Projektprofil weiterhin manuell kopiert werden:

```bash
cp templates/MGD_PLATFORM.example.yml MGD_PLATFORM.yml
```

Beispiel:

```yaml
project:
  name: "Example Platform"
  type: "community"
  foundation_version: "0.6.0"

market:
  countries: ["DE"]

languages:
  default: "de"
  enabled: ["de"]
  prepared: ["en"]

features:
  accounts: true
  moderation: true
  support: true
  billing: false
  translations: true

infrastructure:
  docker: true
  staging: "local"
  git_source: "github"
```

### 3. Agentenregeln übernehmen

```bash
cp templates/AGENTS.md ./AGENTS.md
cp templates/CLAUDE.md ./CLAUDE.md
```

### 4. Foundation automatisch prüfen

```bash
mgd-platform validate
mgd-platform doctor
mgd-platform audit --write
```

Vor einem Release:

```bash
mgd-platform release-check
```

### 5. Optionales Agenten-Audit

Mit installiertem Platform-Skill:

```text
/platform audit
```

Oder ohne Skill-System:

```text
Lies AGENTS.md, MGD_PLATFORM.yml und die Foundation-Dokumentation.
Erstelle einen Gap-Report für Architektur, Daten, Datenschutz, Sicherheit,
Betrieb, Dokumentation und Release-Gates. Ändere noch nichts.
```

---

## Projektprofil: `MGD_PLATFORM.yml`

Die Datei beschreibt die Eigenschaften eines konkreten Projekts, ohne die Foundation selbst projektspezifisch zu machen.

Sie kann enthalten:

- Projekttyp
- Zielmärkte
- Sprachen
- Tech-Stack
- aktivierte Module
- Datenschutzanforderungen
- Compliance-Bereiche
- Staging- und Backupmodell
- Repository-Strategie
- AI-Agenten-Nutzung
- Release-Gates

Schema: [`schema/mgd-platform.schema.json`](schema/mgd-platform.schema.json)

---

## Architekturmodell

Die Foundation empfiehlt als Ausgangspunkt einen **modularen Monolithen**:

```text
Application
├── Core
│   ├── Auth
│   ├── Permissions
│   ├── Security
│   ├── Audit
│   ├── Database
│   ├── Events
│   ├── Jobs
│   └── I18n
│
├── Modules
│   ├── Accounts
│   ├── Content
│   ├── Moderation
│   ├── Support
│   ├── Billing
│   ├── Privacy
│   ├── CMS
│   └── ...
│
├── Backoffice
└── Public/API
```

Nicht jedes Projekt braucht alle Module. Die Foundation trennt deshalb **Core**, **optionale Module** und **Domain Packs**.

Mehr:

- [Architekturprinzipien](WIKI/02-ARCHITECTURE/PRINCIPLES.md)
- [Modularer Monolith](WIKI/02-ARCHITECTURE/MODULAR-MONOLITH.md)
- [Datenarchitektur](WIKI/02-ARCHITECTURE/DATA-ARCHITECTURE.md)
- [Module und Plugins](WIKI/02-ARCHITECTURE/MODULES-PLUGINS.md)

---

## Backoffice: CMS + CRM + PIM

Die Foundation sieht einen gemeinsamen internen Backoffice-Kern vor:

```text
Dashboard
├── Inhalte / Daten
├── Benutzer / Organisationen
├── Moderation
├── Support
├── Billing
├── CMS
├── Übersetzungen
├── Datenschutz
├── Sicherheit
├── Module
└── Einstellungen
```

Admin, Moderator, Support, Datenschutz/Compliance, Übersetzung und Operations müssen dafür nicht als getrennte Anwendungen gebaut werden. Eine gemeinsame Shell kann je Rolle beziehungsweise Capability unterschiedliche Navigation, Dashboards, Filter, Tabellen und Aktionen zeigen.

### Rollenbasierte Backoffice-Ansichten

Die Foundation unterstützt ausdrücklich unterschiedliche interne Ansichten pro Rolle:

- Administration
- Moderation
- Support
- Datenschutz / Compliance
- Übersetzung / Redaktion
- Operations / Security
- AI Operations / AI-Moderationsaufsicht

Die Ansicht ist nur UX. Berechtigungen werden immer serverseitig über Capabilities und Policies geprüft. Auch die zurückgegebenen Datenfelder sollen je Rolle minimiert werden.

Mehr:
- [Backoffice-Konzept](WIKI/02-ARCHITECTURE/BACKOFFICE.md)
- [Rollenbasierte Backoffice-Ansichten](WIKI/02-ARCHITECTURE/ROLE-BASED-BACKOFFICE-VIEWS.md)

---

## Module, Plugins, Themes und Skins

Erweiterbarkeit ist vorgesehen, aber mit einer wichtigen Sicherheitsgrenze:

> [!WARNING]
> Die Foundation empfiehlt **keinen ungeprüften Upload beliebiger ausführbarer Plugins** in produktive Systeme.

Bevorzugt werden:

- geprüfte Module
- Manifeste
- Abhängigkeiten
- Capabilities
- Events
- definierte UI Extension Points
- Feature Flags
- Entitlements
- Themes und Skins ohne versteckte Businesslogik

Mehr: [Module und Plugins](WIKI/02-ARCHITECTURE/MODULES-PLUGINS.md)

---

## Datenschutz

Datenschutz wird nicht als Footer-Text behandelt, sondern als Produkt- und Architekturthema.

Vorgesehen sind:

- Dateninventar
- Verzeichnis von Verarbeitungstätigkeiten
- Rechtsgrundlagen-Matrix
- Betroffenenrechte
- Auskunft und Export
- Berichtigung und Löschung
- Einschränkung und Widerspruch
- Einwilligungsverwaltung
- Aufbewahrungs- und Löschregeln
- DSFA-Screening
- Auftragsverarbeiter-Register
- Privacy by Default

Mehr:

- [Privacy by Design](WIKI/04-PRIVACY/PRIVACY-BY-DESIGN.md)
- [Betroffenenrechte](WIKI/04-PRIVACY/DATA-SUBJECT-RIGHTS.md)

---

## Sicherheit

Die Security-Basis umfasst:

- Threat Modeling
- Least Privilege
- serverseitige Autorisierung
- sichere Sessions
- MFA für privilegierte Rollen
- Secret Management
- Audit-Logs
- Security Events
- Upload-Sicherheit
- Rate Limits
- Backup/Restore
- Incident Response
- Security Reviews
- sichere Fehlerzustände

Mehr:

- [Security Model](WIKI/03-SECURITY/SECURITY-MODEL.md)
- [Threat Model](WIKI/03-SECURITY/THREAT-MODEL.md)
- [Incident Response](WIKI/03-SECURITY/INCIDENT-RESPONSE.md)

---

## Compliance und Rechtsbibliothek

Die Foundation enthält ein Modell, um rechtliche Anforderungen **nachvollziehbar zu recherchieren und zu dokumentieren**.

Ein Eintrag kann enthalten:

- Primärquelle
- Rechtsraum
- Kategorie
- Anwendbarkeit
- Prüfdatum
- Relevanz
- abgeleitete Maßnahmen
- offene Rechtsfragen
- Review-Termin

> [!CAUTION]
> Ein Compliance-Pack ist keine Rechtsberatung und macht ein Produkt nicht automatisch rechtskonform.

Mehr:

- [DE/EU Compliance](WIKI/05-COMPLIANCE/DE-EU.md)
- [Rechtsbibliothek](WIKI/05-COMPLIANCE/LEGAL-LIBRARY.md)
- [Compliance-Datenmodell](compliance/README.md)

---

## Docker, Staging und Backups

Docker ist optional, aber für reproduzierbare Dev-/Staging-Umgebungen empfohlen.

```text
Local / Dev
    ↓
Staging
    ↓
Production
```

Produktivdaten sollen nicht ungeprüft in Staging kopiert werden. Backups werden getrennt von Git-Repositories behandelt. Git ist kein Datenbank-Backup.

Mehr:

- [Docker & Staging](WIKI/06-OPERATIONS/DOCKER-STAGING.md)
- [Backup & Restore](WIKI/06-OPERATIONS/BACKUP-RESTORE.md)
- [Monitoring](WIKI/06-OPERATIONS/MONITORING.md)

---

## AI-Agenten

Das Projekt ist ausdrücklich für Coding-Agenten ausgelegt.

Unterstützte Arbeitsweisen:

- Claude Code
- ChatGPT Codex
- andere Agenten, die Markdown-Regeln, Skills oder Repository-Instructions verstehen

Enthalten:

- `AGENTS.md`
- `CLAUDE.md`
- Templates
- optionaler Platform-Skill
- Source-of-Truth-Regeln
- Dokumentationspflicht
- Backup- und Deployment-Gates
- Feature-Governance

Mehr: [Agent Workflow](WIKI/07-AGENTS/AGENT-WORKFLOW.md)

---

## Zusammenspiel mit den MGD Skills

Die Foundation dupliziert vorhandene Skills nicht, sondern kann sie orchestrieren.

| Projekt | Rolle |
|---|---|
| [MGD DEV Skill](https://github.com/MichaelGahnDESIGN/MGD_DEV_SKILL) | Projektstatus, Tests, Release, Sync und Deployment Readiness |
| [MGD Todo Skill](https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL) | operative Aufgaben und Dokumentationsindex |
| [MGD Backup Skill](https://github.com/MichaelGahnDESIGN/MGD_Backup_SKILL) | Backup- und Restore-Workflows |
| [MGD Autopilot Skill](https://github.com/MichaelGahnDESIGN/MGD_Autopilot_SKILL) | kontrollierte autonome Arbeitsläufe |
| [MGD ProjectClean Skill](https://github.com/MichaelGahnDESIGN/MGD_ProjectClean_SKILL) | Abschluss und Cleanup |
| [MGD AI Thread](https://github.com/MichaelGahnDESIGN/MGD_AI-Thread) | Übergaben zwischen Kontextfenstern |
| [MGD AI PlayTest Skill](https://github.com/MichaelGahnDESIGN/MGD_AI-PlayTest_SKILL) | rollenbasierte Play-/Produkttests |
| [MGD Docker Projektbuilder](https://github.com/MichaelGahnDESIGN/MGD_Docker_Projektbuilder) | erzeugt technische Startgerüste; diese Foundation definiert Architektur, Betrieb und Governance |
| [MGD-DevOS](https://github.com/MichaelGahnDESIGN/MGD-DevOS) | Desktop-Projektzentrale mit Dashboards; wird im Briefing empfohlen, wenn mehrere Projekte oder Agenten betreut werden |
| [MGD Living Documentation](https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation) | belegbare, versionierte Projektdokumentation |
| [MGD Software Updater Skill](https://github.com/MichaelGahnDESIGN/MGD_Software-Updater_SKILL) | Updater planen und umsetzen, wenn im Briefing gewünscht |
| [Fragenkatalog Skill](https://github.com/MichaelGahnDESIGN/Fragenkatalog-Skill) | vertieft das Briefing für Konzept- und Designfragen |

Die vollständige, maschinenlesbare Liste mit Empfehlungsregeln steht in [`registry/recommendations.yml`](registry/recommendations.yml) (`mgd-platform recommend`).

Weitere öffentliche Projekte:
[Michael Gahn DESIGN – eigene Projekte](https://michael-gahn.de/eigene-projekte/)

---

## Domain Packs

Domain Packs ergänzen die Foundation um typische Module, Datenobjekte, Risiken und Prüfpunkte.

Enthaltene Startpacks:

- Game
- Community
- Creator / Publishing
- E-Commerce
- General Platform

Siehe [Domain Packs](WIKI/10-DOMAIN-PACKS/README.md).

---

## Repository-Struktur

```text
MGD-Plattform-Builder/
├── README.md
├── README.en.md
├── LICENSE
├── AGENTS.md
├── CLAUDE.md
├── CONTRIBUTING.md
├── SECURITY.md
├── GOVERNANCE.md
├── ROADMAP.md
├── CHANGELOG.md
├── IMPRESSUM.md
│
├── bin/
│   └── mgd-platform.js
├── src/
│   ├── commands/
│   └── lib/
├── version.json              # Versionsnummer + Status (Single Source of Truth)
├── release-notes.json
├── registry/
│   ├── capabilities.yml
│   ├── briefing.yml           # Pflichtfragen des Agenten-Briefings
│   └── recommendations.yml    # MGD Skills, Tools, Plugins, MGD-DevOS
├── reference/
│   ├── php-mariadb/
│   └── backoffice-demo/
├── platform/
│   ├── SKILL.md
│   └── BRIEFING.md
│
├── schema/
│   ├── mgd-platform.schema.json
│   ├── module-manifest.schema.json
│   ├── evidence.schema.json
│   ├── capability-registry.schema.json
│   ├── version.schema.json
│   ├── release-notes.schema.json
│   └── template-manifest.schema.json
│
├── templates/
│   ├── MGD_PLATFORM.example.yml
│   ├── AGENTS.md
│   ├── CLAUDE.md
│   ├── version.example.json
│   ├── release-notes.example.json
│   └── php-mysql-starter/     # FTP-fähiges CMS, Light + Dark
│
├── compliance/
│   ├── README.md
│   ├── schema/
│   └── templates/
│
└── WIKI/
    └── ...
```

---

## Reifegrad

Aktueller Stand: **0.6.0 Pre-Alpha** (siehe [`version.json`](version.json) und [`release-notes.json`](release-notes.json))

Vor 1.0 können sich Schemas und Empfehlungen noch ändern. Beiträge aus realen Projekten sind ausdrücklich erwünscht.

Siehe [Roadmap](ROADMAP.md).

---

## Mitmachen

Beiträge sind willkommen, besonders:

- zusätzliche Tech-Stack-Adapter
- Security-Checks
- Compliance-Quellenmodelle
- neue Domain Packs
- bessere Templates
- Docker-/Staging-Beispiele
- Tests und Validatoren
- Agenten-Kompatibilität
- Übersetzungen
- Dokumentationsverbesserungen

Bitte [CONTRIBUTING.md](CONTRIBUTING.md) und [GOVERNANCE.md](GOVERNANCE.md) lesen.

---

## Security

Sicherheitslücken bitte **nicht mit Exploitdetails als öffentliches Issue** melden.

Siehe [SECURITY.md](SECURITY.md).

---

## Lizenz

Dual lizenziert, siehe [LICENSING.md](LICENSING.md):

- **MIT** ([LICENSE](LICENSE)): CLI, Validator, Schemas, Registries, Skripte, Dokumentation
- **MGD-Lizenz** ([MGD-Lizenz.md](MGD-Lizenz.md)): Starter-Templates, Referenzplattform, „powered by“-Label und daraus erstellte Projekte. Pflicht-Label „powered by: Michael Gahn DESIGN“; Entfernen nur mit Whitelabel-Lizenz (500 €, einmalig pro Projekt/Domain)

Versionen bis 0.5.1 bleiben MIT.

---

## Impressum / Legal notice

Dieses Repository enthält keine privaten Zugangsdaten, Serverpfade oder kundenspezifischen Informationen.

Öffentliche Anbieterinformationen des Maintainers: [IMPRESSUM.md](IMPRESSUM.md)

---

## Verwandte Projekte

- [MGD DEV Skill](https://github.com/MichaelGahnDESIGN/MGD_DEV_SKILL)
- [MGD Todo Skill](https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL)
- [MGD Backup Skill](https://github.com/MichaelGahnDESIGN/MGD_Backup_SKILL)
- [MGD Autopilot Skill](https://github.com/MichaelGahnDESIGN/MGD_Autopilot_SKILL)
- [MGD Docker Projektbuilder](https://github.com/MichaelGahnDESIGN/MGD_Docker_Projektbuilder)
- [Alle öffentlichen Repositories](https://github.com/MichaelGahnDESIGN)

---

<div align="center">

**Build platforms that remain understandable when they grow.**

</div>
