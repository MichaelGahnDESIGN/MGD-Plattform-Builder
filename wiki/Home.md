# MGD-Plattform-Builder

Willkommen im öffentlichen Wiki des **MGD-Plattform-Builders**.

Diese Dokumentation erklärt die Foundation so, dass Entwickler, Projektverantwortliche, Agenten und externe Dritte verstehen können, **was das System ist, wie es eingesetzt wird, welche Sicherheits- und Datenschutzprinzipien gelten und wie ein Projekt damit betrieben und weiterentwickelt wird**.

> **Neu in 0.5.1 Pre-Alpha:** Die Foundation wird zum **AI-Agenten-gesteuerten CMS** – mit Pflichtfunktionen (Versionierung, Release Notes, Credits, editierbare Rechtstexte, durchsuchbare Einstellungen, Design, Light/Dark), Agenten-Briefing, Empfehlungen für MGD-DevOS und MGD Skills sowie einem FTP-fähigen PHP/MySQL-Starter. → [[23-AI-CMS-Pflichtfunktionen]] · [[24-Briefing-Templates-und-Empfehlungen]]

> **Kurz gesagt:** Das MGD-Plattform-Builder ist eine projektneutrale Grundlage für moderne digitale Plattformen mit Accounts, Rollen, Admin- und Moderatorbereichen, Datenschutz, Sicherheit, Compliance, Backups, Staging, Support, Dokumentation und AI-Agenten.

## Wo sollte ich anfangen?

Wenn du das System zum ersten Mal siehst, lies in dieser Reihenfolge:

1. [[01-Systemueberblick]]
2. [[02-Schnellstart]]
3. [[23-AI-CMS-Pflichtfunktionen]]
4. [[24-Briefing-Templates-und-Empfehlungen]]
5. [[03-Projektprofil-und-Konfiguration]]
6. [[04-Architektur]]
7. [[05-Rollen-Berechtigungen-und-Backoffice]]
8. [[07-Datenschutz-und-Compliance]]
9. [[08-Sicherheit-und-Threat-Model]]
10. [[18-CLI-Validator-und-Automatisierung]]
11. [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]

Danach kannst du über die Sidebar gezielt in einzelne Themen einsteigen. Auf jeder Wiki-Seite stellt außerdem der globale Footer die wichtigsten Projekt- und Rechtshinweise bereit.

## Für wen ist die Foundation gedacht?

Sie eignet sich unter anderem für:

* Webplattformen und SaaS-Produkte
* Online-Spiele und Game-Backends
* Community- und Social-Plattformen
* Creator- und Publishing-Angebote
* Shops und Marktplätze
* interne Portale
* Content- und Datenplattformen
* Apps mit Admin-, Support- oder Moderationsbedarf

## Was ist enthalten?

| Bereich | Inhalt |
|---|---|
| Architektur | Modularer Monolith, Module, Events, Jobs, APIs |
| Identität | Accounts, Rollen, Capabilities, Policies |
| Backoffice | Admin, Moderation, Support, CMS, CRM, PIM |
| Daten | Datenbesitz, Klassifikation, Migrationen, Files |
| Datenschutz | Privacy by Design, Betroffenenrechte, Retention |
| Sicherheit | Threat Model, MFA, Audit, Incident Response |
| Compliance | DE/EU-orientierte Prüflogik und Rechtsquellenmodell |
| Betrieb | Staging, Deployment, Monitoring, Backup, Restore |
| AI-Agenten | Service Principals, Scopes, Agent Workflow |
| Erweiterungen | Module, Domain Packs, Themes, Entitlements |
| Governance | Dokumentation, Feature Governance, Release-Gates |
| CLI & Automation | Init, Validator, Doctor, Audit, Module Generator, Release-Checks |
| AI-CMS | Versionierung, Release Notes, Credits, Rechtstexte mit Revisionen, durchsuchbare Einstellungen, Design, Light/Dark |
| Briefing & Templates | Pflichtfragen für Agenten, Empfehlungen (MGD-DevOS, Skills, Plugins), PHP/MySQL-Starter für FTP |
| Referenzen | startbare PHP/MariaDB Plattform, Review Workflows, Agenten-Historie, idempotente Jobs und reale Integrationstests |

## Zentrale Idee

Die Foundation versucht nicht, jede Plattform gleich aussehen zu lassen. Sie sorgt dafür, dass wichtige Fragen früh beantwortet werden:

* Welche Daten werden gespeichert?
* Wer darf sie sehen oder ändern?
* Warum werden sie benötigt?
* Wie werden sie gelöscht?
* Wie wird ein Ausfall wiederhergestellt?
* Wie werden privilegierte Aktionen auditiert?
* Welche Prüfungen blockieren einen Release?
* Wo finden Entwickler und Agenten die aktuelle Wahrheit?
* Wie lässt sich das System erweitern, ohne alles miteinander zu verkleben?

## Projektstatus

Aktuelle Version: **0.5.1 Pre-Alpha**. Die Foundation befindet sich im frühen Stadium. Seit 0.2 besitzt sie eine eigene CLI und automatische Prüfungen. 0.3 brachte die startbare PHP/MariaDB Referenzplattform. 0.4 ergänzte Migrationen, I18n, Agentenverwaltung und robuste Jobs. Mit 0.5 kommen ein echter Translation Review Workflow, JSON Import und Export, Agenten-Historien, Job Idempotency Keys und eine Handler Registry hinzu. Vor Version 1.0 können sich Schemas und Empfehlungen noch verändern.

Adoptierende Projekte sollten die verwendete Foundation-Version im eigenen `MGD_PLATFORM.yml` dokumentieren.

## Wichtige Hinweise

Diese Foundation ist **keine Rechtsberatung**, keine Sicherheitszertifizierung und kein automatischer Nachweis der Rechtskonformität.

Sie ist eine technische und organisatorische Grundlage, die hilft, relevante Themen strukturiert, prüfbar und frühzeitig zu behandeln.

## Direkte Einstiege

**Neu hier?**  
→ [[01-Systemueberblick]]

**Du willst ein Projekt starten?**  
→ [[02-Schnellstart]]

**Du willst die CLI verwenden oder dein Projekt automatisch prüfen?**  
→ [[18-CLI-Validator-und-Automatisierung]]

**Du willst funktionierende Architekturbeispiele sehen?**  
→ [[19-Referenzimplementierungen-und-Demos]]

**Du willst die 0.4 Betriebsbausteine verstehen?**  
→ [[21-Migrationen-I18n-Agenten-und-Jobs]]

**Du willst Translation Review, Agenten-Historie und idempotente Jobs verstehen?**  
→ [[22-Translation-Review-Agenten-Historie-und-Idempotente-Jobs]]

**Du willst ein AI-generiertes CMS mit allen Pflichtfunktionen aufsetzen?**  
→ [[23-AI-CMS-Pflichtfunktionen]] und [[24-Briefing-Templates-und-Empfehlungen]]

**Du willst wissen, wie Lizenz, „powered by“-Label, Whitelabel und Module funktionieren?**  
→ [[25-Lizenz-Label-Whitelabel-und-Module]]

**Du willst ein bestehendes Projekt migrieren?**  
→ [[13-Migration-bestehender-Projekte]]

**Du willst verstehen, wie AI-Agenten eingebunden werden?**  
→ [[09-AI-Agenten-und-Automation]]

**Du willst wissen, wie Releases und Betrieb funktionieren?**  
→ [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]

**Du suchst die technische Repo-Struktur?**  
→ [[17-Technische-Referenz-und-Repo-Struktur]]

---

Repository: [MichaelGahnDESIGN/MGD-Plattform-Builder](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder)

Technische Tiefendokumentation: [WIKI im Hauptrepository](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/tree/main/WIKI)
