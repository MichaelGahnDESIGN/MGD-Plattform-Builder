# Skill Ecosystem

The foundation can work alone, but existing MGD skills provide specialized workflows.

## Mandatory companions (since 0.7.0)

Three of them are not optional. Living documentation, Graphify and the Todo skill are installed with
every project (`mgd-platform companions install`) and maintained with every change. See
[Mandatory Companions](COMPANIONS.md).

| Companion | Purpose |
|---|---|
| [MGD Living Documentation](https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation) | the state: evidence-based facts, decisions, open points, risks |
| [Graphify](https://github.com/Graphify-Labs/graphify) | the map: machine-generated project graph, part of the living documentation |
| [MGD Todo](https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL) | the work: tasks and project documentation index |

## Recommended integration

| Skill | Purpose |
|---|---|
| [zugang](../Zugaenge-einrichten.md) | local credential import, verified file protection and minimal authentication tests; Codex and Claude Code |
| [code-kommentare](../Code-Kommentare.md) | optional concise German comment style, preserving safety notes and correct syntax |
| MGD DEV | state, tests, release/readiness |
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
- https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL (mandatory companion)
- https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation (mandatory companion)
- https://github.com/Graphify-Labs/graphify (mandatory companion)
- https://github.com/MichaelGahnDESIGN/MGD_Backup_SKILL
- https://github.com/MichaelGahnDESIGN/MGD_Autopilot_SKILL

## Recommendations during the briefing (since 0.5.1)

The machine-readable list of public MGD skills, tools and plugins lives in
[`registry/recommendations.yml`](../../registry/recommendations.yml). During the briefing agents run
`mgd-platform recommend` and suggest only matching entries. **MGD-DevOS**
(<https://github.com/MichaelGahnDESIGN/MGD-DevOS>) is recommended when several projects, dashboards
or AI agents are managed in parallel. See [Briefing and Recommendations](../18-CMS/BRIEFING-RECOMMENDATIONS.md).
