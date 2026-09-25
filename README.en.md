<div align="center">

# MGD-Plattform-Builder

**A reusable, agent-friendly foundation for building and operating modern digital platforms.**

Privacy, security, compliance, modular backends, administration, moderation, Docker workflows, documentation and AI-agent collaboration in one project-neutral blueprint.

[![Version](https://img.shields.io/badge/version-0.5.1%20Pre--Alpha-orange?style=flat-square)](version.json)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)
[![Claude Code](https://img.shields.io/badge/Claude%20Code-compatible-6B5CE7?style=flat-square)](AGENTS.md)
[![ChatGPT Codex](https://img.shields.io/badge/ChatGPT%20Codex-compatible-10A37F?style=flat-square)](AGENTS.md)

[Deutsch](README.md) · **English** · [Installation](INSTALL.md) · [GitHub Wiki](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki) · [CLI documentation](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/18-CLI-Validator-und-Automatisierung) · [Roadmap](ROADMAP.md) · [Contributing](CONTRIBUTING.md)

</div>

---

## New in 0.5.1 Pre-Alpha: AI-generated CMS with mandatory features

With 0.5.1 the foundation becomes an **AI-agent driven CMS**. Claude Code, ChatGPT Codex and other agents use it to build websites for games, projects and platforms – from a **briefing**, a **starter template** and **mandatory features** that every project always contains.

| Mandatory feature | In short |
|---|---|
| **Versioning** | `version.json` as single source, `MAJOR.MINOR.PATCH` + status (Pre-Alpha … Stable). Start: `0.0.1 Pre-Alpha`. Shown below the login and in (game) settings; more locations configurable in the backoffice |
| **Release notes** | timeline in settings; the frontend sees frontend notes only, editor/admin/mod see everything |
| **Credits** | people and roles first, then all AI systems, tools, plugins, libraries, fonts and icons with logo, provider, links and license tags – everything embedded locally |
| **Legal / CMS pages** | contact, imprint, terms, privacy, cookies, cookie box, payment, shipping, withdrawal, withdrawal button, youth protection, accessibility, AI philosophy – edit, delete, add, revisions, export/import |
| **Settings** | always searchable and filterable; design colors, light/dark toggle, file locations, optional code editors |
| **Templates** | `templates/php-mysql-starter`: PHP + FTP + one MySQL DB (logins), optional second DB for personal data, always light **and** dark |

```bash
mgd-platform template create php-mysql-starter --target ./my-project
mgd-platform init --target ./my-project
mgd-platform briefing ./my-project --write     # mandatory questions: editor local/CDN, version display, updater, loading screen, SEO, ...
mgd-platform recommend ./my-project            # matching MGD skills, tools, plugins and MGD-DevOS
mgd-platform version ./my-project --bump patch --note "First deploy" --audience frontend
```

More: [Mandatory features](WIKI/18-CMS/MANDATORY-FEATURES.md) · [Versioning](WIKI/18-CMS/VERSIONING-RELEASE-NOTES.md) · [Credits](WIKI/18-CMS/CREDITS.md) · [Legal/CMS pages](WIKI/18-CMS/LEGAL-CMS-PAGES.md) · [Settings/design/themes](WIKI/18-CMS/SETTINGS-DESIGN-THEMES.md) · [Briefing](WIKI/18-CMS/BRIEFING-RECOMMENDATIONS.md) · [Templates](WIKI/18-CMS/TEMPLATES.md)

---

## Since 0.5: translation review, agent history and idempotent jobs

Version 0.5 adds team and operational workflows. Translations now move through draft, review, published or rejected states. JSON exports can be imported again and intentionally return to draft. Service principals have dedicated detail/history views. Background jobs can use idempotency keys and modular handler registration.

```text
Translation:
draft → review → published
              ↘ rejected

Agent:
created → token_rotated → revoked

Job:
request + idempotency key
→ one job
→ handler registry
→ done / retry / dead
```

More: [0.5 translation review, agent history and idempotent jobs](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/22-Translation-Review-Agenten-Historie-und-Idempotente-Jobs)

---

## Since 0.4: migrations, translations, agent management and dead-letter jobs

Version 0.4 adds operational building blocks to the runnable reference platform: checksum-verified database migrations, a draft/published translation registry, backoffice management for service principals, token rotation and revocation, plus job claiming, retries and dead-letter handling.

```bash
cd reference/php-mariadb
docker compose up -d db
composer install
cp config.example.php config.php
php scripts/migrate.php
php -S 127.0.0.1:8080 -t public
```

More: [0.4 migrations, i18n, agents and jobs](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/21-Migrationen-I18n-Agenten-und-Jobs)

---

## Since 0.3: runnable PHP/MariaDB reference platform

Version 0.3 adds a **runnable reference platform** with login, sessions, roles, capabilities, server-side authorization, backoffice screens, audit events, security events, AI-agent service principals and a database-backed jobs/outbox pattern.

```bash
cd reference/php-mariadb
docker compose up -d db
composer install
cp config.example.php config.php
php -S 127.0.0.1:8080 -t public
```

The reference is integration-tested against a real MariaDB instance in GitHub Actions.

[PHP/MariaDB reference Wiki guide](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/20-PHP-MariaDB-Referenzplattform)

---

## Since 0.2: CLI, validation and executable foundation

The Foundation now includes its own **`mgd-platform` CLI**. Projects can be initialized, validated, audited and checked for release readiness instead of relying on documentation alone.

```bash
mgd-platform init --preset game --target ./my-project
mgd-platform validate ./my-project
mgd-platform doctor ./my-project
mgd-platform audit ./my-project --write
mgd-platform release-check ./my-project
mgd-platform module create "Notifications" --target ./my-project
mgd-platform update ./my-project
```

Version 0.2 also adds a central capability registry, machine-readable release evidence, automated GitHub Foundation Checks, a PHP/MariaDB reference implementation and a local backoffice demo.

Full CLI documentation: [CLI, Validator and Automation](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/18-CLI-Validator-und-Automatisierung)

Reference implementations: [Reference Implementations and Demos](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/19-Referenzimplementierungen-und-Demos)

---

## What is this project?

The **MGD-Plattform-Builder** is a foundation for **AI-generated CMS projects** – not a SaaS product or rigid application framework.

It is a **reusable platform foundation** for projects that need more than a frontend and a database: user accounts, roles, administration, moderation, privacy, security, compliance, backups, staging, translations, support, documentation, AI agents and a controlled development workflow.

The foundation is intentionally **project-neutral**. It contains no customer-specific assumptions, private server paths, credentials, product names or fixed hosting provider.

Typical use cases include:

- web platforms and SaaS products
- online games and game backends
- community and social platforms
- creator and publishing platforms
- stores and marketplaces
- internal portals
- content and data platforms
- apps requiring administration, support or moderation

> [!IMPORTANT]
> This repository is a technical and organizational foundation. It is **not legal advice**, not a security certification and not a substitute for project-specific review.

---

## Why this exists

Many projects repeatedly rebuild the same critical systems:

- roles and permissions
- admin and moderation interfaces
- privacy rights
- audit logs
- security events
- backup and restore
- staging and deployment
- support and moderation
- translations
- file and data management
- feature flags
- documentation
- AI-agent rules
- legal and release gates

The foundation moves these concerns to the beginning of the project instead of treating them as late-stage additions.

**Product idea → foundation profile → modules and risks → implementation → documented release gates.**

---

## Core principles

1. **Privacy by Design**
2. **Security by Design**
3. **Modularity before premature distribution**
4. **A traceable source of truth**
5. **Agent-friendly structure**
6. **No hidden super-admin**
7. **Backup before risk**
8. **Project-neutral defaults**

---

## Coverage

| Area | Foundation coverage |
|---|---|
| Architecture | modular monolith, modules, events, jobs, API boundaries |
| Data | relational models, IDs, private data, audit, retention |
| Backoffice | admin, moderation, CMS, CRM, PIM, support |
| Authorization | roles + capabilities + policies |
| Privacy | data subject rights, deletion, DPIA screening |
| Security | threat model, MFA, sessions, secrets, incident response |
| Compliance | DE/EU-oriented checklists and legal-source model |
| Operations | Docker, staging, monitoring, backup, restore, deployment |
| Internationalization | translation keys, review states, import/export |
| Extensions | modules, themes, skins, entitlements |
| Agents | AGENTS.md, CLAUDE.md, skill integration |
| Governance | feature checks, decision logs, roadmap, release gates |

---

## What it is not

- not a finished framework
- not a replacement for established application frameworks
- not an automatic legal-compliance solution
- not a reason to collect more personal data
- not permission for autonomous agents to make unsafe production changes
- not a marketplace for arbitrary executable plugins
- not tied to GitHub, Gitea, Docker or one database engine

---

## Quick start

```bash
git clone https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder.git
cd MGD-Plattform-Builder
npm install
npm link
mgd-platform init --preset general --target ../my-project
cd ../my-project
mgd-platform doctor
mgd-platform audit --write
```

Then customize `MGD_PLATFORM.yml`. Before release, run:

```bash
mgd-platform validate
mgd-platform release-check
```

```text
/platform audit
```

Without skill support:

```text
Read AGENTS.md, MGD_PLATFORM.yml and the foundation documentation.
Create a gap report for architecture, data, privacy, security,
operations, documentation and release gates. Do not change anything yet.
```

---

## Project profile

`MGD_PLATFORM.yml` describes project-specific choices while the foundation stays neutral.

It can define:

- project type
- target markets
- languages
- technology stack
- modules
- privacy requirements
- compliance areas
- staging and backup model
- repository strategy
- AI-agent usage
- release gates

Schema: [`schema/mgd-platform.schema.json`](schema/mgd-platform.schema.json)

---

## Recommended architecture

The default starting point is a **modular monolith**:

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
├── Modules
├── Backoffice
└── Public/API
```

The foundation separates core services, optional modules and domain packs.

See:

- [Architecture Principles](WIKI/02-ARCHITECTURE/PRINCIPLES.md)
- [Modular Monolith](WIKI/02-ARCHITECTURE/MODULAR-MONOLITH.md)
- [Data Architecture](WIKI/02-ARCHITECTURE/DATA-ARCHITECTURE.md)
- [Modules and Plugins](WIKI/02-ARCHITECTURE/MODULES-PLUGINS.md)

---

## Backoffice

The recommended internal backoffice combines patterns from CMS, CRM and PIM systems while keeping authorization server-side.

Typical areas:

- dashboard
- content/data
- users/organizations
- moderation
- support
- billing
- CMS
- translations
- privacy
- security
- modules
- settings

Admin, moderation, support, privacy/compliance, translation and operations can share one shell while exposing different role-focused navigation, dashboards, filters and actions.

### Role-based backoffice views

The foundation explicitly supports separate internal UX views for:

- Administration
- Moderation
- Support
- Privacy / Compliance
- Translation / Editorial
- Operations / Security
- AI Operations / AI moderation supervision

A view is presentation only. Authorization remains server-side through capabilities and policies, and server responses should minimize fields per role.

See:
- [Backoffice](WIKI/02-ARCHITECTURE/BACKOFFICE.md)
- [Role-based Backoffice Views](WIKI/02-ARCHITECTURE/ROLE-BASED-BACKOFFICE-VIEWS.md).

---

## Privacy, security and compliance

Privacy and security are treated as product requirements, not documentation afterthoughts.

The foundation covers:

- data inventories
- data-subject rights
- retention and deletion
- processor registers
- DPIA screening
- threat modeling
- least privilege
- audit logs
- security events
- backup/restore
- incident response
- compliance source tracking

> [!CAUTION]
> Compliance packs organize work and evidence. They do not automatically make a product legally compliant.

---

## Docker and operations

Docker is optional but recommended for reproducible development and staging.

Production data should not be copied blindly into staging, and Git repositories must not be used as database backups.

See:

- [Docker & Staging](WIKI/06-OPERATIONS/DOCKER-STAGING.md)
- [Backup & Restore](WIKI/06-OPERATIONS/BACKUP-RESTORE.md)
- [Monitoring](WIKI/06-OPERATIONS/MONITORING.md)

---

## AI agents

The repository is designed for:

- Claude Code
- ChatGPT Codex
- other agents that support repository instructions or Markdown skills

It includes:

- `AGENTS.md`
- `CLAUDE.md`
- reusable templates
- an optional platform skill
- source-of-truth rules
- documentation requirements
- backup/deployment gates
- feature governance

See [Agent Workflow](WIKI/07-AGENTS/AGENT-WORKFLOW.md).

---

## MGD skill ecosystem

| Project | Purpose |
|---|---|
| [MGD DEV Skill](https://github.com/MichaelGahnDESIGN/MGD_DEV_SKILL) | project state, testing, release and deployment readiness |
| [MGD Todo Skill](https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL) | project task and documentation index |
| [MGD Backup Skill](https://github.com/MichaelGahnDESIGN/MGD_Backup_SKILL) | backup and restore workflows |
| [MGD Autopilot Skill](https://github.com/MichaelGahnDESIGN/MGD_Autopilot_SKILL) | controlled autonomous work |
| [MGD ProjectClean Skill](https://github.com/MichaelGahnDESIGN/MGD_ProjectClean_SKILL) | project completion and cleanup |
| [MGD AI Thread](https://github.com/MichaelGahnDESIGN/MGD_AI-Thread) | handoff between context windows |
| [MGD AI PlayTest Skill](https://github.com/MichaelGahnDESIGN/MGD_AI-PlayTest_SKILL) | role-based play/product testing |
| [MGD Docker Projektbuilder](https://github.com/MichaelGahnDESIGN/MGD_Docker_Projektbuilder) | generates technical starter skeletons |
| [MGD-DevOS](https://github.com/MichaelGahnDESIGN/MGD-DevOS) | desktop project hub with dashboards; recommended by the briefing when several projects or agents are managed |
| [MGD Living Documentation](https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation) | evidence-based, versioned project documentation |
| [MGD Software Updater Skill](https://github.com/MichaelGahnDESIGN/MGD_Software-Updater_SKILL) | plans and builds updaters when the briefing asks for one |

The complete machine-readable list with matching rules is [`registry/recommendations.yml`](registry/recommendations.yml) (`mgd-platform recommend`).

---

## Domain packs

Starter domain packs describe common needs for:

- games
- communities
- creator/publishing systems
- e-commerce
- general platforms

They are not complete products. They add common modules, entities, risks and checks.

See [Domain Packs](WIKI/10-DOMAIN-PACKS/README.md).

---

## Maturity

Current status: **0.5.1 Pre-Alpha** (see [`version.json`](version.json) and [`release-notes.json`](release-notes.json))

Schemas and recommendations may change before 1.0. Real-world feedback and contributions are welcome.

---

## Contributing

Please read:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [GOVERNANCE.md](GOVERNANCE.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)

Security issues should follow [SECURITY.md](SECURITY.md).

---

## License

MIT License. See [LICENSE](LICENSE).

---

## Legal notice

Maintainer information is linked in [IMPRESSUM.md](IMPRESSUM.md) without duplicating unnecessary personal data in this repository.

---

<div align="center">

**Build platforms that remain understandable when they grow.**

</div>
