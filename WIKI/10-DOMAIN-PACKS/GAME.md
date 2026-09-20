# Game Domain Pack

## Typical modules

- account/profile
- inventory
- entitlements
- progression
- matchmaking/session
- moderation
- player reports
- support
- telemetry
- store
- live-ops configuration

## Common security/abuse concerns

- cheating
- inventory manipulation
- replayed requests
- fake purchases
- account theft
- botting
- leaderboard manipulation

## Data

Separate gameplay telemetry from identity where possible.

Do not keep high-frequency raw events forever without purpose.

## Operations

Games may need stronger version compatibility between client and backend than ordinary web apps.

Document:

- supported client versions
- migration behavior
- maintenance mode
- forced update rules
