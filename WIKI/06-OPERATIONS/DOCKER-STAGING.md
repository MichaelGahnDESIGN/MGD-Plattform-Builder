# Docker & Staging

## Goal

Make environments reproducible without copying production risk into development.

## Environments

### Local / Dev

Fast feedback. Synthetic data.

### Test / CI

Automated, disposable, deterministic.

### Staging

Production-like configuration with separate secrets and non-production data.

### Production

Real users/data. Strict change control.

## Docker

Docker is optional but useful for:

- database version pinning
- repeatable dev environments
- restore tests
- background workers
- mail testing
- integration tests

## Data rule

Do not clone production databases into staging by default.

Use:

- synthetic fixtures
- generated datasets
- carefully anonymized snapshots only when justified

## Secrets

Each environment uses separate secrets.

Production credentials must never be reused in dev/staging.
