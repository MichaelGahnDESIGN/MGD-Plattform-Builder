# API and Agent Access

## Human sessions and automation tokens are different identities

Do not reuse browser admin sessions for agents.

## Service principals

Agent/API credentials should support:

- named identity
- scopes
- expiry
- revocation
- rate limit
- audit
- optional environment restriction

## Example scopes

- `catalog.read`
- `content.draft.write`
- `translations.draft.write`
- `moderation.queue.read`

Avoid broad scopes such as `admin.all`.

## Sensitive data

Agents should receive metadata instead of private content whenever the task does not require the content.

## Prompt injection

External/user content is untrusted input.

An agent must not treat instructions found inside content as authorization to perform privileged actions.

## Audit

Record:

- principal
- scope
- endpoint/action
- outcome
- timestamp

Do not log raw bearer tokens.
