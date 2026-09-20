# Data Architecture

## One database is often the correct starting point

Do not split databases by user interface such as "admin DB" vs "user DB".

Admins, moderators and users often work with the same underlying entities.

Split physically later by **trust/data domain** when there is a real benefit.

Potential future domains:

- core/catalog
- identity/private
- analytics
- audit/security

## Logical separation now

Even in one database, separate tables/modules for:

- public profile
- private identity
- auth
- sessions
- audit
- analytics
- billing
- support
- privacy requests

## Data classification

Recommended classes:

- public
- internal
- private
- sensitive
- restricted

Classification should drive:

- permissions
- encryption
- logging
- backup handling
- retention
- export behavior

## Encryption

Passwords are hashed, never reversibly encrypted.

Sensitive values that must be recovered may use application-level authenticated encryption.

Encryption keys must not live in the same database as encrypted data.

## Analytics

Prefer aggregation and pseudonymization.

Do not duplicate names/emails into event tables without necessity.

## Audit

Audit events should be append-oriented and harder to modify than normal business records.
