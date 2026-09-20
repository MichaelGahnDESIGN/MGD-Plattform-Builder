---
name: platform
description: >-
  Audits, plans and bootstraps projects against the MGD Project Platform System.
  Covers architecture, data, admin/mod backoffice, privacy, security, compliance,
  operations, Docker, backups, i18n, documentation and agent governance.
---

# /platform

Use the MGD Project Platform System as a project-neutral foundation.

## Modes

### /platform audit

Read-only first.

Inspect the project and report:

- current architecture
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
