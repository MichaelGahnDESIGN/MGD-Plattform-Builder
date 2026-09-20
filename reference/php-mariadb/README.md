# PHP / MariaDB Reference Platform

This directory contains a small but runnable reference platform for the **MGD Project Platform System**.

It demonstrates how the Foundation concepts can map to PHP 8.3+ and MariaDB without forcing Symfony, Laravel or another application framework.

It is intended for learning, architecture review, agent guidance and as a starting reference. It is **not a production-ready application**.

## Included

- PDO database connection
- account login with password hashing
- hardened PHP sessions
- CSRF protection
- roles and capabilities stored in MariaDB
- server-side authorization
- capability-aware backoffice navigation
- account suspension example
- audit log
- security-event log
- service principals for AI agents and automation
- Bearer-token API authentication
- database-backed jobs/outbox
- CLI worker example
- database-backed CI smoke test

## Structure

```text
reference/php-mariadb/
├── bootstrap.php
├── composer.json
├── config.example.php
├── database/
│   └── schema.sql
├── public/
│   ├── app.css
│   └── index.php
├── scripts/
│   ├── create-admin.php
│   ├── create-service-principal.php
│   └── worker.php
├── src/
│   ├── Core/
│   │   ├── Auth/
│   │   ├── Audit/
│   │   ├── Database/
│   │   ├── Http/
│   │   ├── Jobs/
│   │   ├── Permissions/
│   │   ├── Security/
│   │   └── Support/
│   └── Modules/
│       └── Accounts/
└── tests/
    └── smoke.php
```

## Requirements

- PHP 8.3 or newer
- extensions: PDO MySQL and mbstring
- MariaDB 10.6+ or compatible MySQL
- Composer

## Setup

### Option A: MariaDB with Docker

The included development profile starts MariaDB. The application schema is then created by the migration runner:

```bash
cd reference/php-mariadb
docker compose up -d db
composer install
cp config.example.php config.php
php scripts/migrate.php
```

For this local Docker profile, adjust `config.php` to:

```text
database: mgd_platform
user: mgd
password: mgd-local-only
host: 127.0.0.1
port: 3306
```

The passwords in `compose.yml` are development-only defaults and must not be reused in production.

### Option B: Existing local MariaDB

```bash
cd reference/php-mariadb
composer install
cp config.example.php config.php
```

Edit `config.php` for your local development database.

Run the migration runner:

```bash
php scripts/migrate.php
```

`database/schema.sql` remains as a readable baseline snapshot for reference and backwards compatibility. New installations and updates should use the migration runner.

Never commit real credentials in `config.php`.

## Create the first admin

Use environment variables so the password does not appear in a command argument:

```bash
export MGD_ADMIN_EMAIL="admin@example.local"
export MGD_ADMIN_PASSWORD="use-a-long-local-password"
php scripts/create-admin.php
unset MGD_ADMIN_PASSWORD
```

No default administrator password is included.

## Start the local reference server

```bash
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080/login
```

The backoffice exposes, depending on capabilities:

- Dashboard
- Accounts
- Translations
- Agents / API
- Audit
- Security events
- Jobs / Outbox

Navigation is capability-aware, and each protected route checks the capability again on the server.

## Roles

The example schema seeds:

### admin

Receives all example capabilities.

### moderator

Receives:

```text
content.read
moderation.case.read
moderation.case.decide
```

### support

Receives:

```text
content.read
support.case.read
```

Roles are bundles. Authorization uses capabilities.

## Database migrations

Migrations are stored in:

```text
database/migrations/
```

Run:

```bash
php scripts/migrate.php
```

The runner stores applied versions and SHA-256 checksums in `schema_migrations`.

If an already applied migration file changes later, the runner stops instead of silently continuing. This makes migration history tamper-evident and protects projects from accidentally rewriting database history.

The first migration can also adopt an existing 0.3 baseline database. This allows the reference to move from the old schema-import workflow to proper migrations without requiring a destructive rebuild.

## Translation workflow

The translation registry now uses a review workflow:

```text
draft
→ review
→ published
       ↘ rejected
```

A translator can prepare or import drafts without receiving publish authority.

Capabilities are separated:

```text
translations.read
translations.manage
translations.review
translations.import
translations.export
```

### Import translations

Simple import format:

```json
{
  "locale": "de",
  "translations": {
    "dashboard.welcome": "Willkommen",
    "dashboard.logout": {
      "value": "Abmelden",
      "description": "Dashboard logout action"
    }
  }
}
```

CLI:

```bash
php scripts/import-translations.php ./translations-de.json
```

Imports always become drafts.

### Export translations

```bash
php scripts/export-translations.php de > translations-de.json
```

Without a locale argument, all locales are exported.

The exported `mgd-translations-v1` format can be imported again. Re-imported entries intentionally return to draft state and must pass review again.

## Translation registry

The backoffice contains a translation registry with:

- translation keys
- locales
- draft and published state
- descriptions
- audit events
- fallback-ready lookup service

The service lives in:

```text
src/Core/I18n/TranslationRegistry.php
```

## Service-principal history

Each technical identity now has its own lifecycle history in addition to the global audit log.

The history records:

- creation
- token rotation
- revocation
- responsible actor
- event metadata
- timestamp

The backoffice links every service principal to a detail page showing its configuration and history.

## Service principals

Create an automation or AI-agent identity:

```bash
export MGD_SP_NAME="Local Agent"
export MGD_SP_SCOPES="content.read,support.case.read"
php scripts/create-service-principal.php
```

The generated token is shown once. MariaDB stores only its SHA-256 hash.

Test the identity endpoint:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://127.0.0.1:8080/api/me
```

The response contains the technical actor ID, actor type and scopes/capabilities.

## Job idempotency

Repeated requests can safely reuse one business-operation key.

Example:

```php
$result = $outbox->enqueueIdempotent(
    'demo.audit-export',
    'audit-export-order-123',
    ['requested_by' => $actor->id]
);
```

The same topic and key resolve to the same existing job instead of creating a duplicate.

Only the SHA-256 derived idempotency hash is stored.

## Job handler registry

Workers no longer require a growing `switch` statement.

Handlers are registered explicitly:

```php
$handlers->register(
    'demo.audit-export',
    new DemoAuditExportHandler()
);
```

This keeps queue infrastructure separate from project-specific job logic.

## Jobs / Outbox

The backoffice can enqueue a harmless demo job.

Process pending jobs:

```bash
php scripts/worker.php
```

The 0.4 worker claims jobs atomically before processing them. Multiple workers therefore do not intentionally pick the same pending record.

Failed jobs use exponential retry delays. Each job has a maximum attempt count. Once that limit is reached, the job moves to the `dead` state and can be retried manually from the backoffice.

Real projects should additionally consider idempotency keys, handler-specific retry policies, observability and dedicated queues when scale requires them.

## Audit and security events

Privileged business actions are written to `audit_events`.

Security-relevant signals such as failed logins are written separately to `security_events`.

The distinction is intentional:

```text
Audit Event
= who performed a relevant action?

Security Event
= what may indicate misuse, attack or abnormal behavior?
```

## Migration policy

The reference includes:

```text
database/MIGRATION-POLICY.md
```

The policy explains additive migration patterns, rollback limits, forward fixes, review questions and immutable checksums.

## Automated test

The repository CI starts a real MariaDB service and runs:

```bash
php tests/smoke.php
```

The smoke test imports the schema and verifies:

- capability resolution
- account suspension
- audit-event creation
- translation draft/review/publish workflow
- translation JSON export and safe re-import
- service-principal rotation, revocation and history
- idempotent job enqueueing
- handler-registry dispatch
- stale-worker recovery
- dead-letter retry

## Security limits of the reference

A real public product still needs project-specific controls such as:

- login rate limiting
- password reset and email verification
- MFA implementation
- trusted proxy/IP handling
- Content Security Policy
- production secret management
- secure HTTPS configuration
- session storage strategy
- richer validation and error handling
- monitoring and alerting
- production-grade migration review and rollback procedures
- stronger queue concurrency and idempotency controls
- security review and penetration testing where appropriate

The purpose of this reference is to make the Foundation architecture concrete without pretending that a small example is automatically production-safe.

## Public documentation

See the GitHub Wiki page:

https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System/wiki/20-PHP-MariaDB-Referenzplattform
