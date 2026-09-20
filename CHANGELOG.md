# Changelog

All notable changes to this project will be documented in this file.

The format follows the spirit of Keep a Changelog and semantic versioning.

## [Unreleased]

### Planned

- Node/PostgreSQL reference implementation
- stronger reusable backoffice components
- translation CSV workflows and review queues
- domain-level job idempotency examples
- compliance automation
- richer agent integration

## [0.5.0] - 2026-09-20

### Added

- translation draft, review, published and rejected workflow
- separate `translations.review`, `translations.import` and `translations.export` capabilities
- translator starter role
- JSON translation import
- JSON translation export
- reusable `mgd-translations-v1` exchange format
- CLI translation import and export scripts
- translation review actions in the backoffice
- service-principal detail pages
- dedicated `service_principal_events` lifecycle history
- creation, rotation and revocation history records
- job idempotency hashes and unique database constraint
- `enqueueIdempotent()` duplicate protection
- explicit `JobHandlerRegistry`
- dedicated demonstration job-handler class
- database migration policy documentation
- expanded integration tests for review, import/export, agent history, token rotation, idempotency and handler dispatch
- complete public Wiki guide for Foundation 0.5

### Changed

- imported translations always enter draft state
- exported translation JSON can be imported again
- workers dispatch through registered handlers instead of a hard-coded switch
- job enqueue now returns the created job ID
- service-principal lifecycle information is available independently of the global audit log
- capability registry version aligned with Foundation 0.5

### Security

- translation import cannot bypass publication review
- old service-principal tokens become unusable after rotation
- revoked service principals remain unusable
- repeated business requests can deduplicate jobs through idempotency keys
- idempotency keys are stored only as SHA-256 derived hashes

## [0.4.0] - 2026-09-20

### Added

- checksum-aware MariaDB migration runner
- `schema_migrations` history with immutable migration checks
- adoption path for existing 0.3 baseline databases
- translation-key registry
- locale-specific draft and published translations
- translation backoffice and audit events
- service-principal management backoffice
- registered-scope validation for service principals
- service-principal token rotation and revocation
- service-principal last-used tracking
- one-time token display in the backoffice
- jobs-specific read/manage capabilities
- atomic database job claiming with worker identity
- stale processing-job recovery after abandoned worker locks
- exponential retry delays
- per-job maximum attempt counts
- dead-letter state and manual retry
- reusable backoffice UI helpers for tables, badges and notices
- extended integration tests covering migrations, translations, service principals and dead letters
- full public Wiki guide for 0.4

### Changed

- new reference installations now use `php scripts/migrate.php`
- Docker MariaDB setup no longer auto-imports a mutable schema snapshot
- CLI-created service principals now validate requested scopes against registered capabilities
- jobs no longer use the security-audit capability as their operational permission
- revoked service principals cannot be accidentally reactivated by token rotation
- service-principal create/rotate/revoke actions and their audit events are transactional

### Security

- migration checksums detect modified historical migrations
- arbitrary service-principal scope names are rejected
- expired or revoked service principals cannot rotate tokens
- token usage updates `last_used_at`
- dead-letter failures remain visible instead of disappearing from the worker loop

## [0.3.0] - 2026-09-20

### Added

- runnable PHP 8.3 / MariaDB reference platform
- account login with password hashing
- secure session-cookie defaults and session rotation
- CSRF protection
- account roles and capability resolution from MariaDB
- capability-aware backoffice navigation
- server-side endpoint authorization
- account suspension example with audit logging
- audit-event backoffice view
- security-event backoffice view
- failed-login security-event example
- service principals for AI agents and automation
- hashed Bearer tokens and `/api/me` identity endpoint
- database-backed jobs/outbox implementation
- CLI worker example
- first-admin creation script without default credentials
- service-principal creation script with one-time token output
- local MariaDB Docker Compose development profile
- PHP linting and Composer validation in Foundation Checks
- real MariaDB integration smoke test in GitHub Actions
- public Wiki guide for the runnable PHP/MariaDB reference platform

### Security

- no default administrator password is shipped
- automation tokens are stored hashed
- UI visibility is not used as an authorization boundary
- failed-login logs use an email hash instead of raw email
- self-suspension is blocked in the reference backoffice

## [0.2.0] - 2026-09-20

### Added

- `mgd-platform` Node.js CLI
- project initialization with general, game, community, creator and ecommerce presets
- schema and cross-field validation for project profiles
- `doctor` project structure checks
- automated foundation gap audit
- Markdown audit reports
- machine-readable release evidence
- release readiness checks
- module skeleton generator
- foundation version check
- evidence JSON schema
- central capability registry and validation schema
- automated schema, example and documentation checks
- GitHub Actions Foundation Checks
- CLI bootstrap smoke tests
- framework-neutral PHP/MariaDB starter reference
- local capability-driven backoffice demo
- complete public Wiki documentation for CLI and automation

### Changed

- public GitHub Wiki is prominently linked from the README
- CLI is the recommended quick-start path
- project profile schema enforces additional security relationships
- Wiki publishing workflow uses the clearer `publish-wiki.yml` filename

## [0.1.0] - 2026-09-20

### Added

- initial public project foundation
- German and English README
- agent instructions for Claude Code and ChatGPT Codex
- project profile schema and example
- privacy, security and compliance architecture
- modular backend and backoffice concepts
- Docker, staging, backup and monitoring guidance
- domain packs
- governance, security and contribution documentation
- optional platform agent skill
