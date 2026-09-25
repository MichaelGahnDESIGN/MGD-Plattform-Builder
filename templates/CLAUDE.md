# CLAUDE.md

This project follows the MGD Project Platform System.

Before a substantial change:

- read MGD_PLATFORM.yml
- read AGENTS.md
- inspect the existing implementation
- identify privacy/security implications
- identify which role-specific backoffice views need the feature
- verify server-side capabilities and data projections for each affected role
- find the project's test and backup rules
- make the smallest coherent change
- update documentation and tasks

Never place credentials, production data or private infrastructure details in public repository content.

Foundation:
https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System

## Mandatory features and briefing

Follow the "Mandatory features" section in `AGENTS.md`. Start new work with the briefing
(`platform/BRIEFING.md` in the foundation), recommend matching MGD skills/tools via
`mgd-platform recommend`, and bump `version.json` plus `release-notes.json` with every delivery.
