<div align="center">

# MGD Project Platform System

**A reusable, agent-friendly foundation for building and operating modern digital platforms.**

Privacy, security, compliance, modular backends, administration, moderation, Docker workflows, documentation and AI-agent collaboration in one project-neutral blueprint.

[![Status](https://img.shields.io/badge/status-early%20foundation-orange?style=flat-square)](ROADMAP.md)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)
[![Claude Code](https://img.shields.io/badge/Claude%20Code-compatible-6B5CE7?style=flat-square)](AGENTS.md)
[![ChatGPT Codex](https://img.shields.io/badge/ChatGPT%20Codex-compatible-10A37F?style=flat-square)](AGENTS.md)

[Deutsch](README.md) · **English** · [Wiki](WIKI/README.md) · [Roadmap](ROADMAP.md) · [Contributing](CONTRIBUTING.md)

</div>

---

## What is this project?

The **MGD Project Platform System** is not a finished CMS, SaaS product or rigid application framework.

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
git clone https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System.git
cd Projekt-Plattform-System
cp templates/MGD_PLATFORM.example.yml MGD_PLATFORM.yml
cp templates/AGENTS.md ./AGENTS.md
cp templates/CLAUDE.md ./CLAUDE.md
```

Then customize `MGD_PLATFORM.yml` and ask your coding agent to run a read-only foundation audit first.

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

Admin and moderator views can share one shell and differ only through capabilities.

See [Backoffice](WIKI/02-ARCHITECTURE/BACKOFFICE.md).

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
| [MGD Platform Builder](https://github.com/MichaelGahnDESIGN/MGD_Platform-Builder_TOOL) | generates technical starter skeletons |

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

Current status: **Early Foundation / 0.1.x**

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
