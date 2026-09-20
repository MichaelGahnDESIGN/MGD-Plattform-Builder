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

```bash
cd reference/php-mariadb
composer install
cp config.example.php config.php
```

Edit `config.php` for your local development database.

Import:

```text
database/schema.sql
```

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

The backoffice exposes:

- Dashboard
- Accounts
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

## Jobs / Outbox

The backoffice can enqueue a harmless demo job.

Process pending jobs:

```bash
php scripts/worker.php
```

The reference keeps the worker intentionally small. Real projects should add explicit handlers, idempotency rules, retry limits, observability and dead-letter handling when needed.

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

## Automated test

The repository CI starts a real MariaDB service and runs:

```bash
php tests/smoke.php
```

The smoke test imports the schema and verifies:

- capability resolution
- account suspension
- audit-event creation
- jobs/outbox enqueue and completion

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
- database migrations instead of direct schema import
- dedicated queue concurrency controls
- security review and penetration testing where appropriate

The purpose of this reference is to make the Foundation architecture concrete without pretending that a small example is automatically production-safe.

## Public documentation

See the GitHub Wiki page:

https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System/wiki/20-PHP-MariaDB-Referenzplattform
