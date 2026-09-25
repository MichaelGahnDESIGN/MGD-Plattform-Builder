# Quick Start

## New project

1. Copy `templates/MGD_PLATFORM.example.yml` into the new repository as `MGD_PLATFORM.yml`.
2. Copy `templates/AGENTS.md` and `templates/CLAUDE.md`.
3. Choose a domain pack if useful.
4. Fill out market, language, feature and infrastructure fields.
5. Run a read-only foundation audit.
6. Create the project's own architecture/privacy/security documents.
7. Add task tracking and release gates.
8. Build the smallest useful module first.

## Existing project

Do not rewrite the project.

Start with an inventory:

- stack
- data
- roles
- environments
- backups
- deployment
- documentation
- incidents/known risks

Then use [Migration](../11-ADOPTION/MIGRATION-EXISTING-PROJECT.md).

## Suggested first agent prompt

```text
Read MGD_PLATFORM.yml, AGENTS.md and the MGD-Plattform-Builder.
Do not change code yet.
Create a gap report grouped by:
architecture, data, permissions, privacy, security,
operations, documentation and release gates.
For each gap, classify it as critical/high/medium/low.
```
