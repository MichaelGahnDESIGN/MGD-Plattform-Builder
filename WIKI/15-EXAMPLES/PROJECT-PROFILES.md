# Example Project Profiles

These examples are intentionally fictional and contain no real infrastructure.

## Small internal tool

```yaml
project:
  name: "Example Internal Tool"
  type: "internal"
  foundation_version: "0.1.0"

market:
  countries: ["DE"]

languages:
  default: "de"
  enabled: ["de"]

features:
  accounts: true
  moderation: false
  support: false
  billing: false
  uploads: false
  cms: false
  translations: false

infrastructure:
  docker: true
  staging: "local"
  git_source: "github"
  backup: "local"
  monitoring: true
```

## Public community

Key additions:

- moderation
- reports
- support
- public profiles
- notifications
- privacy center
- abuse controls

## Online game

Key additions:

- game domain pack
- inventory/entitlements
- telemetry
- anti-abuse/anti-cheat
- client/backend version compatibility
- store if monetized

## Creator platform

Key additions:

- creator organizations
- uploads
- rights declarations
- moderation
- publishing workflow
- payouts only after separate financial/KYC design
