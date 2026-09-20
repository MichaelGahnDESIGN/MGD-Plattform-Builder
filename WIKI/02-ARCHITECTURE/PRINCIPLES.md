# Architecture Principles

## Prefer a modular monolith first

A single deployable application with clear module boundaries is usually the fastest safe default.

Split into separate services only when a measurable reason exists:

- independent scaling
- different trust boundary
- independent release ownership
- data residency
- failure isolation
- technology constraint

## Keep boundaries explicit

Each module should declare:

- responsibility
- data it owns
- public API/service interface
- emitted/subscribed events
- permissions
- jobs
- migrations
- retention impact

## Server decides

Client-side UI hiding is never authorization.

## Additive migration first

Prefer:

1. create new structure
2. backfill
3. compatibility layer
4. switch reads
5. switch writes
6. observe
7. remove legacy later

## Relational core, JSON at the edges

Use relational structures for:

- identities
- permissions
- ownership
- status
- relationships
- billing
- moderation
- rights

Use JSON for flexible, low-risk optional metadata and snapshots.

## Separate public IDs from internal keys

Internal numeric IDs can remain efficient while public URLs/API identifiers use non-sequential IDs where enumeration risk matters.
