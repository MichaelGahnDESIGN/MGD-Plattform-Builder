# Support System

## Source of truth

The product should own the canonical support case when sensitive user communication is involved.

External issue/CRM/project tools can receive minimized references.

## Case model

Typical fields:

- public case ID
- subject/user relation
- category
- priority
- sensitivity
- status
- assigned role
- created/updated/closed
- retention class

## Separation

Do not treat these as ordinary support if they need specialized controls:

- privacy requests
- security reports
- authority requests
- legal/copyright notices

## Integrations

For GitHub/Gitea/CRM/project tools prefer:

- internal case ID
- category
- status
- technical summary
- link back

Avoid automatically copying full private messages into external systems.
