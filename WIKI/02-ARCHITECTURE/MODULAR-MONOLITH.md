# Modular Monolith

## Why

It combines fast development with maintainable boundaries.

## Suggested layout

```text
src/
  Core/
    Auth/
    Permissions/
    Database/
    Security/
    Audit/
    Events/
    Jobs/
    I18n/
    Modules/
  Modules/
    Accounts/
    Content/
    Moderation/
    Support/
    Billing/
    Privacy/
    CMS/
```

## Internal module pattern

A module may contain:

- Module manifest
- Service
- Repository
- Policy
- Validator
- Routes/Controller
- Migrations
- Translations
- Tests

Do not create classes solely to satisfy a pattern.

## Events

Use explicit domain events for cross-module reactions.

Example:

```text
ContentPublished
  -> Notifications
  -> SearchIndex
  -> Analytics
  -> Audit
```

Start synchronously. Add a queue only where latency/retry behavior requires it.

## Jobs

A database-backed jobs/outbox table is often enough for:

- mail
- push
- exports
- image processing
- webhooks
- scheduled cleanup

Avoid external queue infrastructure until it solves a real need.
