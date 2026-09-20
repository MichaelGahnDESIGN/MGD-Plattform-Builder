# Roadmap

The roadmap is intentionally capability-oriented. Dates are not promises.

## 0.1 Foundation — completed

- documentation structure
- project profile schema
- agent instructions
- privacy/security/compliance baseline
- architecture and operations wiki
- starter domain packs

## 0.2 Executable Foundation — completed

- `mgd-platform` CLI
- project initialization with presets
- `MGD_PLATFORM.yml` schema validation
- cross-field foundation rules
- project doctor
- automated gap audit and Markdown report
- machine-readable release evidence
- release readiness check
- module skeleton generator
- foundation version check
- capability registry and schema
- documentation link checker
- automated GitHub Foundation Checks
- bootstrap smoke tests
- public Wiki documentation for CLI and tooling

## 0.3 Runnable Reference Platform — completed

- runnable PHP 8.3 / MariaDB reference platform
- account login and hardened sessions
- CSRF protection
- roles and capability resolution from MariaDB
- capability-aware backoffice navigation
- server-side authorization
- account suspension example
- audit-event dashboard
- security-event dashboard
- service-principal authentication for AI agents and automation
- Bearer-token identity endpoint
- database-backed jobs/outbox
- CLI worker example
- local MariaDB Docker Compose profile
- real MariaDB integration smoke test in GitHub Actions
- complete public Wiki guide

## 0.4 Reference Expansion

- Node/PostgreSQL reference
- translation registry
- database migration runner
- reusable backoffice components
- richer jobs/outbox handlers and dead-letter pattern
- service-principal management UI
- generic audit/security event components
- Docker staging example with app container

## 0.5 Compliance Tooling

- machine-readable legal-source index
- review-date reminders
- processor register validator
- retention matrix validator
- DPIA screening helper
- release compliance checklist
- evidence dashboard

## 0.6 Agent Ecosystem

- richer Claude/Codex commands
- guided project bootstrap assistant
- richer foundation update assistant
- compatibility with MGD DEV/Todo/Backup/Autopilot skills
- agent-readable evidence reports
- safe agent capability profiles

## 1.0 Stable Foundation

Criteria:

- stable project profile schema
- documented migration policy
- stable module/capability conventions
- tested reference implementations
- mature contribution workflow
- security review
- at least two substantially different real-world adoption patterns

## Out of scope for 1.0

- becoming a full application framework
- arbitrary executable plugin marketplace
- automatic legal certification
- hosted SaaS control plane
