# Migrating an Existing Project

## Rule: no big-bang rewrite

The foundation should improve an existing project incrementally.

## Step 1: inventory

Document:

- current modules
- database
- roles
- personal data
- deployment
- backup
- environments
- known risks

## Step 2: create project profile

Describe the current state honestly.

## Step 3: critical gaps first

Prioritize:

- no backup
- broken authorization
- exposed secrets
- missing restore path
- sensitive data leaks
- uncontrolled production deployment

## Step 4: introduce boundaries

Create services/policies/modules around existing code.

Do not move everything at once.

## Step 5: additive data migration

Create new structures, backfill, switch traffic gradually, remove legacy later.

## Step 6: backoffice migration

Build a shared shell and move one area at a time.

## Step 7: measure

Keep evidence of tests, incidents and restore results.
