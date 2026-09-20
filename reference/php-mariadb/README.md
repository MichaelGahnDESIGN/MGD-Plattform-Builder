# PHP / MariaDB Reference Implementation

This directory is a small, intentionally framework-neutral reference for the MGD Project Platform System.

It is **not** a production-ready application. It demonstrates how the Foundation concepts can map to PHP 8.x and MariaDB without forcing Symfony, Laravel or another framework.

## Included

- PDO connection with exception mode
- explicit Actor model
- capability-based authorization
- audit-event writer
- example account service
- relational schema for accounts, roles, capabilities and audit events
- service-principal table for automation and AI agents

## Structure

```text
reference/php-mariadb/
├── composer.json
├── config.example.php
├── database/
│   └── schema.sql
└── src/
    ├── Core/
    │   ├── Auth/
    │   ├── Audit/
    │   ├── Database/
    │   └── Permissions/
    └── Modules/
        └── Accounts/
```

## Setup

```bash
cd reference/php-mariadb
composer install
cp config.example.php config.php
```

Import `database/schema.sql` into a development database and configure `config.php`.

Never commit real database credentials.

## Important

The code intentionally stays small. It demonstrates boundaries and security decisions, not a finished product.
