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
- audit and security-event dashboards
- service-principal authentication
- Bearer-token identity endpoint
- database-backed jobs/outbox
- local MariaDB Docker Compose profile
- real MariaDB integration smoke test

## 0.4 Operational Building Blocks — completed

- checksum-verified database migration runner
- migration history and baseline adoption
- translation registry with draft/published state
- translation backoffice
- published translation lookup with optional fallback locale
- service-principal backoffice
- validated service-principal scopes
- one-time token display
- token rotation and revocation
- last-used tracking
- jobs-specific capabilities
- atomic outbox claim
- worker identity and locking
- exponential retry
- maximum attempt count
- dead-letter state
- dead-letter retry from backoffice
- reusable backoffice UI helpers
- extended MariaDB integration smoke test
- complete public 0.4 Wiki documentation

## 0.5 Reference Expansion

- Node/PostgreSQL reference
- translation import/export
- translation review workflow
- migration rollback strategy examples
- idempotency keys for jobs
- richer job-handler registry
- service-principal detail/history view
- stronger reusable backoffice components
- Docker staging example with app container

## 0.6 Compliance Tooling

- machine-readable legal-source index
- review-date reminders
- processor register validator
- retention matrix validator
- DPIA screening helper
- release compliance checklist
- evidence dashboard

## 0.7 Agent Ecosystem

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
