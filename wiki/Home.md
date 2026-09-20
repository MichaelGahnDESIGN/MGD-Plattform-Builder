# MGD Project Platform System

Willkommen im öffentlichen Wiki des **MGD Project Platform Systems**.

Diese Dokumentation erklärt die Foundation so, dass Entwickler, Projektverantwortliche, Agenten und externe Dritte verstehen können, **was das System ist, wie es eingesetzt wird, welche Sicherheits- und Datenschutzprinzipien gelten und wie ein Projekt damit betrieben und weiterentwickelt wird**.

> **Kurz gesagt:** Das MGD Project Platform System ist eine projektneutrale Grundlage für moderne digitale Plattformen mit Accounts, Rollen, Admin- und Moderatorbereichen, Datenschutz, Sicherheit, Compliance, Backups, Staging, Support, Dokumentation und AI-Agenten.

## Wo sollte ich anfangen?

Wenn du das System zum ersten Mal siehst, lies in dieser Reihenfolge:

1. [[01-Systemueberblick]]
2. [[02-Schnellstart]]
3. [[03-Projektprofil-und-Konfiguration]]
4. [[04-Architektur]]
5. [[05-Rollen-Berechtigungen-und-Backoffice]]
6. [[07-Datenschutz-und-Compliance]]
7. [[08-Sicherheit-und-Threat-Model]]
8. [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]

Danach kannst du über die Sidebar gezielt in einzelne Themen einsteigen.

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

Die Foundation befindet sich aktuell im **Early Foundation / 0.1.x** Stadium. Vor Version 1.0 können sich Schemas und Empfehlungen noch verändern.

Adoptierende Projekte sollten die verwendete Foundation-Version im eigenen `MGD_PLATFORM.yml` dokumentieren.

## Wichtige Hinweise

Diese Foundation ist **keine Rechtsberatung**, keine Sicherheitszertifizierung und kein automatischer Nachweis der Rechtskonformität.

Sie ist eine technische und organisatorische Grundlage, die hilft, relevante Themen strukturiert, prüfbar und frühzeitig zu behandeln.

## Direkte Einstiege

**Neu hier?**  
→ [[01-Systemueberblick]]

**Du willst ein Projekt starten?**  
→ [[02-Schnellstart]]

**Du willst ein bestehendes Projekt migrieren?**  
→ [[13-Migration-bestehender-Projekte]]

**Du willst verstehen, wie AI-Agenten eingebunden werden?**  
→ [[09-AI-Agenten-und-Automation]]

**Du willst wissen, wie Releases und Betrieb funktionieren?**  
→ [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]

**Du suchst die technische Repo-Struktur?**  
→ [[17-Technische-Referenz-und-Repo-Struktur]]

---

Repository: [MichaelGahnDESIGN/Projekt-Plattform-System](https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System)

Technische Tiefendokumentation: [WIKI im Hauptrepository](https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System/tree/main/WIKI)
