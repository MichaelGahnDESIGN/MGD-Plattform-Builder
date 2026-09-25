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
6. `WIKI/18-CMS/MANDATORY-FEATURES.md` for features every project must provide

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
- treat role-specific backoffice views as presentation only; authorization remains server-side
- minimize returned fields per role/capability instead of fetching sensitive fields and hiding them in the UI
- distinguish examples from normative requirements

## Versioning of this repository

- `version.json` is the single source of truth (currently `0.5.1 Pre-Alpha`)
- change it only with `node bin/mgd-platform.js version --bump ... --note ...`; never edit `VERSION` or `package.json` by hand
- every release adds an entry to `release-notes.json` and `CHANGELOG.md`
- starter templates in `templates/<id>/` must keep their light and dark variants (`npm run check:templates`)

## License label

Never remove or weaken the "powered by: Michael Gahn DESIGN" label, the license page, `MGD-Lizenz.md` or the
integrity check in templates – they are part of the MGD License. Changes to these files need the maintainer and
`node scripts/update-license-hashes.js`.

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
