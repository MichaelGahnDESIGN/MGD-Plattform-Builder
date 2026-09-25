---
name: platform
description: >-
  Audits, plans and bootstraps projects against the MGD-Plattform-Builder.
  Covers architecture, data, admin/mod backoffice, privacy, security, compliance,
  operations, Docker, backups, i18n, documentation and agent governance.
---

# /platform

Use the MGD-Plattform-Builder as a project-neutral foundation.

## Mandatory features (every project)

Versioning (`version.json`, MAJOR.MINOR.PATCH + status, default `0.0.1 Pre-Alpha`), release-notes
timeline, credits (people + components), editable legal/CMS pages with revisions, trash,
export/import, searchable and filterable settings, light **and** dark variants, editable design
tokens. Never ask whether to build them – only how to configure them.

## Modes

### /platform briefing

Run the interview from `platform/BRIEFING.md` and `registry/briefing.yml` before building.
Ask mandatory questions in small groups, write answers into `MGD_PLATFORM.yml`, then run
`mgd-platform recommend` and suggest matching public MGD skills, tools and plugins – including
**MGD-DevOS** when several projects, dashboards or agents are managed. Close with
`briefing.completed: true`.

### /platform template

Create a project from `templates/<id>/` (`mgd-platform template create php-mysql-starter`).
Every template must ship a light and a dark variant.

### /platform release

Bump the version (`mgd-platform version --bump patch|minor|major [--status beta]`), add a release
note with audience (`--note "..." --audience frontend,backoffice`) and sync targets
(`mgd-platform version --check`).

### /platform audit

Read-only first.

Inspect the project and report:

- mandatory features (versioning, release notes, credits, legal pages, settings search, light/dark, design)
- current architecture
- role-specific backoffice views and capability boundaries
- project profile
- missing foundation areas
- privacy gaps
- security gaps
- operations gaps
- documentation gaps
- recommended next step

Do not change production systems.

### /platform init

Create or complete:

- `MGD_PLATFORM.yml`
- `AGENTS.md`
- `CLAUDE.md`
- documentation structure
- initial governance checklist

Do not overwrite existing project rules blindly.

### /platform module <name>

Plan a module using:

- purpose
- entities
- permissions
- events
- UI extension points
- privacy
- security
- audit
- retention
- tests
- migration

### /platform backoffice

Audit or design the internal backoffice with role-based views.

Check:

- shared shell vs duplicated role-specific applications
- roles, capabilities and policies
- role-specific navigation and dashboards
- server-side field projection / data minimization
- admin, moderation, support, privacy, translation and operations views
- AI/service-principal separation
- direct URL/API authorization
- multi-role view switching
- audit and re-authentication for sensitive actions

Do not treat hidden navigation as authorization.

### /platform compliance

Build a source-based compliance inventory for the declared market/jurisdiction.

Never claim automatic legal compliance.

### /platform security

Run a project-level security gap review using the foundation's threat and control model.

### /platform update

Compare the project's declared foundation version with the repository version and produce a migration plan before changing files.

## Interaction with other skills

Prefer, when installed:

- /todo for task tracking
- /dev for release/readiness
- /backup before risky changes
- /autopilot only with explicit safe goals
- /thread for handoff
- /projectclean after completion

## Public safety

Never place secrets, personal data, private server information or production dumps in this repository or generated public documentation.
