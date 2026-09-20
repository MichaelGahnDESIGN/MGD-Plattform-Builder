# Database Migrations

## Principles

- version controlled
- additive first
- deterministic
- tested before production
- backup/rollback path known
- application compatibility considered

## High-risk operations

Flag explicitly:

- column/table drops
- type narrowing
- destructive data rewrites
- uniqueness constraints on dirty data
- huge table locks

## Compatibility migration

Preferred pattern:

1. add new structure
2. backfill
3. dual/compatibility reads if needed
4. switch writers
5. verify
6. remove legacy in a later release

## Evidence

Record:

- migration version
- execution time
- rows changed
- validation result
- backup reference
- post-deploy smoke result

## Production

Do not run unreviewed ad-hoc SQL as the normal deployment method.
