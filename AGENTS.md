# AGENTS.md

This repository is designed to be usable by ChatGPT Codex, Claude Code and other coding agents.

## Mission

Keep the foundation project-neutral, privacy-aware, secure and understandable.

## Read first

1. `README.md`
2. `GOVERNANCE.md`
3. `WIKI/README.md`
4. `WIKI/09-GOVERNANCE/FEATURE-GOVERNANCE.md`
5. relevant domain/architecture document

## Source of truth

- repository files = current technical/documentation state
- `CHANGELOG.md` = released changes
- `ROADMAP.md` = planned direction
- `WIKI` = detailed knowledge base
- issues/PRs = proposals and active collaboration

## Hard safety rules

- never add real credentials, tokens, API keys or passwords
- never add private customer/user data
- never copy production database dumps into Git
- never hardcode private server paths or IP addresses
- do not claim legal certification
- do not weaken least-privilege guidance for convenience
- do not add arbitrary executable plugin-upload patterns as a default
- distinguish examples from normative requirements

## Agent workflow

Before changing a subsystem:

1. identify the relevant wiki area
2. identify project-neutral impact
3. consider privacy/security/compliance
4. make the smallest coherent change
5. update docs/schemas/examples together
6. report what changed and what remains open

## Related MGD skills

If installed, prefer existing skills instead of reimplementing workflows:

- /dev
- /todo
- /backup
- /autopilot
- /thread
- /projectclean
- /playtest

See `WIKI/07-AGENTS/SKILL-ECOSYSTEM.md`.

## Public repository rule

Everything committed here must be safe for a public repository.
