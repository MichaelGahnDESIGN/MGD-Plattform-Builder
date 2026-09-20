# Changelog

All notable changes to this project will be documented in this file.

The format follows the spirit of Keep a Changelog and semantic versioning.

## [Unreleased]

### Planned

- Node/PostgreSQL reference implementation
- translation registry
- database migration runner
- reusable backoffice components
- richer compliance automation
- richer agent integration

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
