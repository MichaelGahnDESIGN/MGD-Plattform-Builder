# Projektprofil und Konfiguration

Das Herzstück der Adoption ist die Datei `MGD_PLATFORM.yml`. Sie beschreibt ein konkretes Projekt, ohne die Foundation selbst projektspezifisch zu machen.

## Zweck

Das Profil beantwortet zentrale Fragen maschinenlesbar und für Menschen nachvollziehbar. Coding-Agenten können dadurch schneller erkennen, welche Regeln für ein Projekt gelten.

Typische Bereiche sind:

```yaml
project:
  name: "Example Platform"
  type: "community"
  foundation_version: "0.2.0"

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

privacy:
  personal_data: true
  data_subject_portal: true

security:
  mfa_privileged: true
  audit_log: true

infrastructure:
  docker: true
  staging: "local"
  git_source: "github"

release_gates:
  - "restore-tested"
  - "permission-review-complete"
```

## Welche Informationen gehören hinein?

Das Projektprofil soll Projekttyp, Zielmärkte, Sprachen, relevante Features, Datenschutzanforderungen, Security-Baseline, Infrastruktur, Staging, Backupmodell, Repository-Strategie, AI-Agenten-Nutzung und Release-Gates beschreiben.

## Was gehört nicht hinein?

Keine Passwörter, API-Keys, Tokens, personenbezogenen Produktionsdaten, private Serverzugänge oder vertrauliche Kundeninformationen.

## Pflichtabschnitte ab 0.5.1

| Abschnitt | Inhalt |
|---|---|
| `versioning` | Version, Status, Anzeigeorte, Sichtbarkeit, Release Notes |
| `cms` | Editor, lokal oder CDN, Revisionen, Import/Export, Rechtsseiten |
| `credits` | Personen, Komponenten, nur lokale Assets |
| `settings` | durchsuchbar und filterbar |
| `appearance` | Light + Dark, Standardmodus, Umschalter-Orte, Design-Farben |
| `file_locations` | wichtige Pfade (ohne Secrets) |
| `code_editors` | CSS/JS/PHP-Editoren und Schutzmaßnahmen |
| `experience` | Updater, Ladebildschirm, SEO, Cookie-Box, Wartungsmodus, ... |
| `template` | Starter-Template, Varianten, Datenbanken, Auslieferung |
| `briefing` | Briefing-Status und angenommene Empfehlungen |

Siehe [[23-AI-CMS-Pflichtfunktionen]].

## Schema

Das Repository enthält ein JSON Schema unter:

`schema/mgd-platform.schema.json`

Damit kann das Projektprofil automatisiert validiert werden.

Empfohlen:

```bash
mgd-platform validate
```

Die CLI prüft zusätzlich logische Zusammenhänge, die über reine Datentypen hinausgehen. Mehr dazu unter [[18-CLI-Validator-und-Automatisierung]].

## Source of Truth

Das Profil beschreibt den beabsichtigten Zustand. Die Implementierung, Architektur-Dokumentation und tatsächliche Produktionskonfiguration müssen dazu passen. Abweichungen sollen nicht stillschweigend bestehen bleiben.

Wenn eine Änderung am Produkt das Profil beeinflusst, wird das Profil im selben Change aktualisiert.

Weiter: [[04-Architektur]] · [[14-Governance-Dokumentation-und-Releases]]