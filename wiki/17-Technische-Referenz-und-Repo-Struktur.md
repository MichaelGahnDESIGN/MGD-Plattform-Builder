# Technische Referenz und Repository-Struktur

Diese Seite hilft Dritten dabei, sich schnell im Repository zu orientieren.

## Hauptstruktur

```text
Projekt-Plattform-System/
├── README.md
├── README.en.md
├── INSTALL.md
├── AGENTS.md
├── CLAUDE.md
├── SECURITY.md
├── GOVERNANCE.md
├── ROADMAP.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── IMPRESSUM.md
├── LICENSE
│
├── platform/
│   ├── SKILL.md
│   └── agents/
│
├── schema/
│   ├── mgd-platform.schema.json
│   └── module-manifest.schema.json
│
├── templates/
│   ├── MGD_PLATFORM.example.yml
│   ├── MODULE.example.json
│   ├── AGENTS.md
│   ├── CLAUDE.md
│   └── FEATURE-GOVERNANCE.md
│
├── examples/
│   ├── general-platform.yml
│   ├── game.yml
│   ├── community.yml
│   ├── creator.yml
│   └── ecommerce.yml
│
├── compliance/
│   ├── schema/
│   └── templates/
│
├── tools/
│   └── legal-library/
│
├── WIKI/
│   └── technische Tiefendokumentation
│
└── wiki/
    └── Quelle des öffentlichen GitHub Wikis
```

## Wichtige Dateien

**README.md**  
Projektüberblick, Leitprinzipien, Schnellstart und Einstiegspunkte.

**MGD_PLATFORM.yml**  
Wird in einem adoptierenden Projekt angelegt und beschreibt dessen Profil.

**AGENTS.md / CLAUDE.md**  
Arbeitsregeln für Coding-Agenten.

**platform/SKILL.md**  
Foundation-spezifischer Agenten-Skill.

**schema/mgd-platform.schema.json**  
Validierung des Projektprofils.

**schema/module-manifest.schema.json**  
Struktur für Modul-Manifeste.

**templates/**  
Vorlagen für neue Projekte und Governance.

**examples/**  
Synthetische Beispielprofile.

**WIKI/**  
Detaillierte technische Referenzdokumentation im Hauptrepository.

**wiki/**  
Kuratiertes, für Dritte lesbares GitHub Wiki. Änderungen an diesem Ordner werden automatisiert in das GitHub Wiki veröffentlicht.

## Technische Tiefendokumentation

Die englischsprachige Tiefendokumentation unter `WIKI/` enthält unter anderem:

* Architekturprinzipien
* Modular Monolith
* Datenarchitektur
* Datenbankmigrationen
* Files und Storage
* Billing und Entitlements
* API und Agent Access
* Security Model und Threat Model
* Incident Response
* Audit Evidence
* Privacy by Design
* Data Subject Rights
* Compliance
* Docker und Staging
* Backup und Restore
* Monitoring
* Deployment
* Testing und Release Gates
* AI-Agenten
* Internationalisierung
* Governance
* Domain Packs
* Migration
* Support und Moderation
* Accessibility
* Foundation Updates

## Versionierung

Die Foundation befindet sich im frühen Entwicklungsstadium. Vor 1.0 können sich Schemas und Empfehlungen ändern. Adoptierende Projekte sollen die verwendete Foundation-Version im Projektprofil dokumentieren und Updates bewusst übernehmen.

Weiter: [[14-Governance-Dokumentation-und-Releases]]