# Roles, Capabilities and Policies

## Roles are bundles, not security logic

Typical roles may include:

- user
- moderator
- support
- admin
- translator

But permissions should be concrete.

Examples:

- `content.read`
- `content.publish`
- `moderation.case.read`
- `moderation.case.decide`
- `support.case.read`
- `privacy.request.manage`
- `translations.publish`
- `security.audit.read`

## Policies

Policies combine:

- actor capability
- resource ownership
- resource state
- organization membership
- sensitivity
- contextual constraints

## Least privilege

A support user should not see billing details unless the support case needs them.

A moderator should not gain access to private analytics or account secrets.

## Service principals

Automation/AI agents should use dedicated identities with:

- scopes
- expiry
- revocation
- audit
- rate limits

Do not model an AI agent as a human super-admin by default.


## Role views do not grant permissions

A backoffice view is a UX profile, not a security role.

Examples:

- an admin view may show system, billing and compliance navigation
- a moderation view may focus on reports and review queues
- a support view may focus on assigned tickets
- a translation view may focus on locale coverage and review

Switching views must never add capabilities.

The backend must still evaluate the actor, capability, resource, data sensitivity and context for every protected request.

See [Role-based Backoffice Views](ROLE-BASED-BACKOFFICE-VIEWS.md).
