# CLI, Validator und Automatisierung

Mit Version 0.2 erhält das MGD Project Platform System eine eigene Kommandozeilenoberfläche. Damit wird die Foundation nicht mehr nur gelesen, sondern kann Projekte aktiv initialisieren, prüfen und auf Release-Bereitschaft kontrollieren.

Die CLI heißt:

```text
mgd-platform
```

Sie läuft lokal mit Node.js und benötigt keinen Webserver.

## Installation für die Entwicklung

Repository klonen und Abhängigkeiten installieren:

```bash
git clone https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System.git
cd Projekt-Plattform-System
npm install
```

Danach kann die CLI direkt gestartet werden:

```bash
node ./bin/mgd-platform.js --help
```

Optional kann sie im geklonten Repository lokal verlinkt werden:

```bash
npm link
```

Danach steht der Befehl `mgd-platform` direkt im Terminal zur Verfügung.

## Verfügbare Befehle

### `mgd-platform init`

Initialisiert ein Projekt mit Foundation-Dateien.

```bash
mgd-platform init
```

Mit Projektpreset:

```bash
mgd-platform init --preset game
mgd-platform init --preset community
mgd-platform init --preset creator
mgd-platform init --preset ecommerce
```

In ein anderes Verzeichnis:

```bash
mgd-platform init --preset game --target ./mein-projekt
```

Angelegt beziehungsweise übernommen werden unter anderem:

```text
MGD_PLATFORM.yml
AGENTS.md
CLAUDE.md
FEATURE-GOVERNANCE.md
.mgd/evidence/
```

Bestehende Dateien werden standardmäßig nicht überschrieben. Für einen bewussten Ersatz kann `--force` verwendet werden.

### `mgd-platform validate`

Prüft `MGD_PLATFORM.yml` gegen das JSON Schema und zusätzliche Foundation-Regeln.

```bash
mgd-platform validate
```

Oder eine konkrete Datei:

```bash
mgd-platform validate ./MGD_PLATFORM.yml
```

Geprüft werden nicht nur Datentypen. Die Foundation prüft auch logische Abhängigkeiten.

Beispiele:

* Standardsprache muss in den aktivierten Sprachen enthalten sein.
* Aktivierte AI-Agenten benötigen einen aktivierten Agentenbereich.
* Billing benötigt Audit Logging.
* Sensible Daten benötigen strengere Security-Kontrollen.
* Personenbezogene Daten benötigen eine Retention-Strategie.

### `mgd-platform doctor`

Prüft, ob ein Projekt grundlegende Foundation-Bestandteile besitzt.

```bash
mgd-platform doctor
```

Der Doctor betrachtet unter anderem:

```text
MGD_PLATFORM.yml
AGENTS.md
CLAUDE.md
SECURITY.md
FEATURE-GOVERNANCE.md
.mgd/evidence/
Projektprofil-Validität
```

Das Ergebnis zeigt Blocker und Empfehlungen getrennt an.

### `mgd-platform audit`

Führt einen Foundation Gap Check durch.

```bash
mgd-platform audit
```

Mit Bericht als Markdown:

```bash
mgd-platform audit --write
```

Dabei wird zusätzlich erzeugt:

```text
MGD_PLATFORM_AUDIT.md
```

Findings werden nach Schweregrad sortiert:

```text
CRITICAL
HIGH
MEDIUM
LOW
```

Der Audit prüft beispielsweise Projektprofil, Dokumentation, Backup-Nachweise, Agentenfreigaben und Security-Gates.

### `mgd-platform release-check`

Prüft, ob die im Projektprofil definierten Release-Gates tatsächlich durch Nachweise belegt sind.

```bash
mgd-platform release-check
```

Beispielausgabe:

```text
MGD PLATFORM RELEASE CHECK
==========================

✓ Project profile valid
✓ privacy-reviewed
✓ security-reviewed
✗ restore-tested — evidence missing

Release readiness: BLOCKED
```

Das ist ein zentraler Unterschied zwischen einer reinen Checkliste und der MGD Foundation: Ein Release-Gate kann maschinell als erfüllt oder nicht erfüllt bewertet werden.

## Evidence-Dateien

Nachweise liegen im Projekt unter:

```text
.mgd/evidence/
```

Für ein Gate namens:

```text
restore-tested
```

wird beispielsweise erwartet:

```text
.mgd/evidence/restore-tested.json
```

Beispiel:

```json
{
  "gate": "restore-tested",
  "status": "pass",
  "checked_at": "2026-09-20T00:00:00Z",
  "expires_at": "2026-12-20T00:00:00Z",
  "source": "Restore test report",
  "reviewer": "project owner",
  "notes": "Restore completed successfully."
}
```

Mögliche Statuswerte:

```text
pass
fail
pending
```

Ein Nachweis kann über `expires_at` automatisch veralten.

Das Schema befindet sich unter:

```text
schema/evidence.schema.json
```

### `mgd-platform module create`

Erzeugt ein neues Modulgerüst.

```bash
mgd-platform module create "Notifications"
```

Ergebnis:

```text
modules/
└── notifications/
    ├── module.json
    └── README.md
```

Das Manifest enthält unter anderem:

* Modul-ID
* Version
* Typ
* Abhängigkeiten
* Permissions
* abonnierte Events
* ausgesendete Events
* Übersetzungen
* Backoffice-Integration
* Entitlement
* Datenklassifikation

Die README fordert zusätzlich Dokumentation zu Zweck, Datenbesitz, Berechtigungen, Events, Privacy, Retention, Security und Tests ein.

### `mgd-platform update`

Vergleicht die im Projekt deklarierte Foundation-Version mit der lokal vorhandenen Version.

```bash
mgd-platform update
```

Ein Versionsunterschied wird nicht automatisch überschrieben. Die CLI fordert zuerst einen Audit und eine bewusste Migration.

## Capability Registry

Die Foundation besitzt eine zentrale Registry:

```text
registry/capabilities.yml
```

Dadurch sollen Projekte keine zufälligen oder widersprüchlichen Permission-Namen erfinden.

Beispiele:

```text
content.read
content.publish
moderation.case.read
moderation.case.decide
support.case.read
privacy.request.manage
security.audit.read
permissions.manage
accounts.suspend
```

Jede Capability kann zusätzlich Risikostufe, Audit-Pflicht und Re-Authentication-Anforderung definieren.

## Automatische GitHub Checks

Bei Pushes und Pull Requests läuft der Workflow:

```text
Foundation Checks
```

Er prüft automatisch:

* Node Tests
* JSON Schemas
* Capability Registry
* Beispielprofile
* lokale Markdown-Links
* Wiki-Links
* Bootstrap-Smoke-Test

Dadurch prüft die Foundation sich selbst nach ihren eigenen Qualitätsregeln.

## Empfohlener Ablauf für ein neues Projekt

```bash
mgd-platform init --preset community
cd mein-projekt
mgd-platform doctor
mgd-platform audit --write
```

Danach wird das Projektprofil angepasst und entwickelt.

Vor einem Release:

```bash
mgd-platform validate
mgd-platform audit
mgd-platform release-check
```

## Zusammenspiel mit AI-Agenten

Claude Code, ChatGPT Codex oder andere Agenten können die CLI selbst verwenden.

Ein sinnvoller Agentenauftrag ist:

```text
Lies AGENTS.md und MGD_PLATFORM.yml.
Führe mgd-platform doctor und mgd-platform audit aus.
Behebe keine Findings automatisch.
Erstelle zuerst einen priorisierten Plan und nenne alle Release-Blocker.
```

Die CLI ersetzt Agenten nicht. Sie liefert ihnen eine gemeinsame, deterministische Prüfgrundlage.

Weiter: [[19-Referenzimplementierungen-und-Demos]] · [[14-Governance-Dokumentation-und-Releases]]
