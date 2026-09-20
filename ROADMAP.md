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
- project-profile schema validation
- cross-field foundation rules
- project doctor
- automated gap audit
- machine-readable release evidence
- release readiness checks
- module skeleton generator
- capability registry
- automated Foundation Checks

## 0.3 Runnable Reference Platform — completed

- runnable PHP 8.3 / MariaDB reference platform
- login and hardened sessions
- CSRF protection
- roles and capabilities
- server-side authorization
- capability-driven backoffice
- audit and security events
- service-principal authentication
- database-backed jobs/outbox
- real MariaDB integration tests

## 0.4 Operational Building Blocks — completed

- checksum-verified database migration runner
- migration history and baseline adoption
- translation registry
- translation backoffice
- service-principal management
- token rotation and revocation
- last-used tracking
- atomic job claiming
- retry and maximum attempts
- dead-letter state and retry
- stale-worker recovery
- reusable backoffice UI helpers
- migration policy foundations

## 0.5 Workflow Expansion — completed

- translation draft/review/published/rejected workflow
- separate translation review capability
- translation JSON import
- translation JSON export
- safe export re-import as draft
- translator starter role
- service-principal detail view
- dedicated service-principal lifecycle history
- transactional principal create/rotate/revoke plus audit
- job idempotency keys
- duplicate job detection
- job handler registry
- dedicated handler classes
- migration policy documentation
- expanded MariaDB integration smoke tests
- complete public Wiki documentation for 0.5

## 0.6 Reference Expansion

- Node/PostgreSQL reference
- stronger reusable backoffice component set
- translation CSV import/export
- translation review queues and filters
- job handler discovery
- domain-level idempotency examples
- Docker staging example with application container
- migration rollback examples

## 0.7 Compliance Tooling

- machine-readable legal-source index
- review-date reminders
- processor register validator
- retention matrix validator
- DPIA screening helper
- release compliance checklist
- evidence dashboard

## 0.8 Agent Ecosystem

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
