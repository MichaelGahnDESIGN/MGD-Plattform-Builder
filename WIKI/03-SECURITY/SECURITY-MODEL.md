# Security Model

## Objectives

Protect:

- accounts
- sessions
- private data
- privileged actions
- content integrity
- payments/entitlements
- secrets
- backups
- deployment chain

## Baseline controls

- HTTPS
- secure session cookies
- session rotation
- server-side authorization
- CSRF protection where applicable
- parameterized database access
- output encoding/sanitization
- rate limiting
- MFA for privileged roles
- secret separation
- audit logging
- security event logging
- backup and restore testing
- incident response

## Fail closed

Privileged actions should fail closed when authorization cannot be verified.

## Logging

Never log:

- passwords
- full tokens
- session cookies
- payment secrets
- full private message bodies by default

## Supply chain

Track:

- dependencies
- update policy
- CI/CD trust
- package sources
- signing where appropriate

## Security evidence

A mature project should be able to show:

- last restore test
- last permission review
- last dependency review
- relevant penetration/security review
- incident/postmortem records
