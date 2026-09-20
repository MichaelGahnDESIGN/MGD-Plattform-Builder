# Backup & Restore

## A backup is not proven until restore works

Track both backup success and restore-test success.

## What to protect

- database
- non-reconstructable uploads
- configuration
- legal/document versions
- critical encryption/recovery material through a separate secure process

## Do not use Git as database backup

Git is intentionally historical and easy to clone.

Production dumps and personal data do not belong in normal repositories.

## Suggested retention

Choose based on project needs and legal obligations.

A common starting pattern:

- daily
- weekly
- monthly

## Restore testing

Test in an isolated environment:

1. restore database/files
2. verify integrity
3. apply migrations
4. run smoke tests
5. record result
6. clean test environment

## Ransomware resilience

A compromised production account should not be able to delete every backup.

Prefer separate credentials and at least one separated/offline/versioned copy.
