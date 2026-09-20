# Database Migration Policy

The PHP/MariaDB reference treats migration files as immutable history.

## Rules

1. Never edit an already applied migration.
2. Create a new numbered migration for every schema or seed change.
3. Run migrations on staging before production.
4. Take or verify an appropriate backup before high-risk migrations.
5. Prefer additive migrations over destructive replacements.
6. Separate schema expansion from later cleanup when possible.
7. Document whether rollback is safe before deployment.

## Recommended change pattern

For a risky schema change:

```text
expand
→ backfill
→ deploy compatible code
→ switch reads/writes
→ observe
→ contract old structure later
```

This is safer than changing a column destructively in one deployment.

## Rollback

Not every migration should have an automatic down migration.

A code rollback is only safe when the database remains compatible with the older code.

For destructive or data-transforming migrations, prefer a documented forward fix unless a tested reverse migration exists.

## Migration review

Before production, review:

- expected table locks
- estimated data volume
- index creation impact
- backward compatibility
- backup/restore state
- rollback or forward-fix path
- application version ordering

## Immutable checksums

The reference runner stores a SHA-256 checksum for each applied SQL migration.

If the file later changes, the runner stops.

Do not bypass this check by editing the migration history table. Create a new migration instead.
