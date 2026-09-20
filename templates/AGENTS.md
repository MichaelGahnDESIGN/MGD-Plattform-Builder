# AGENTS.md

This project follows the MGD Project Platform System.

## Read first

1. README.md
2. MGD_PLATFORM.yml
3. project documentation
4. current TODO/issue source
5. security/privacy rules relevant to the task

## Rules

- preserve the existing architecture unless a change is justified
- do not expose secrets or personal data
- do not use production data as test fixtures
- server-side authorization is mandatory
- role-specific backoffice views must be capability/policy driven
- do not fetch sensitive fields for a role merely to hide them in the frontend
- document privacy/security impact for new features
- create or verify a rollback path before risky changes
- update project documentation with implementation changes
- do not deploy to production without the project's approval rule

## Foundation

Reference:
https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System
