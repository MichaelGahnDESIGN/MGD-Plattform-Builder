# Skill Ecosystem

The foundation can work alone, but existing MGD skills provide specialized workflows.

## Recommended integration

| Skill | Purpose |
|---|---|
| MGD DEV | state, tests, release/readiness |
| MGD Todo | tasks and project documentation index |
| MGD Backup | backup/restore |
| MGD Autopilot | controlled autonomous iteration |
| MGD AI Thread | context handoff |
| MGD ProjectClean | completion/cleanup |
| MGD AI PlayTest | role-based testing |

## Principle

Do not duplicate another skill inside this foundation.

The platform skill should orchestrate and refer to dedicated workflows.

## Minimal setup

Only `AGENTS.md` + `MGD_PLATFORM.yml` are required.

Skills are optional accelerators.

## Public links

- https://github.com/MichaelGahnDESIGN/MGD_DEV_SKILL
- https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL
- https://github.com/MichaelGahnDESIGN/MGD_Backup_SKILL
- https://github.com/MichaelGahnDESIGN/MGD_Autopilot_SKILL

## Recommendations during the briefing (since 0.5.1)

The machine-readable list of public MGD skills, tools and plugins lives in
[`registry/recommendations.yml`](../../registry/recommendations.yml). During the briefing agents run
`mgd-platform recommend` and suggest only matching entries. **MGD-DevOS**
(<https://github.com/MichaelGahnDESIGN/MGD-DevOS>) is recommended when several projects, dashboards
or AI agents are managed in parallel. See [Briefing and Recommendations](../18-CMS/BRIEFING-RECOMMENDATIONS.md).
