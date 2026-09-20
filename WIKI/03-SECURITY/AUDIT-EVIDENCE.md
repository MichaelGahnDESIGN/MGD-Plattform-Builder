# Audit and Evidence

## Audit is not debug logging

Debug logs answer "what happened technically?"

Audit answers "who or what performed a relevant action?"

## Audit event fields

- event ID
- actor/service principal
- capability/action
- target type/id
- result
- reason when required
- timestamp
- correlation/request ID
- metadata without unnecessary personal data

## Events worth auditing

- role/capability changes
- moderation decisions
- privacy requests
- billing entitlement changes
- security settings
- agent token lifecycle
- module enable/disable
- dangerous exports
- high-risk data access

## Evidence register

Projects may maintain evidence entries for:

- restore tests
- security reviews
- permission reviews
- incident postmortems
- dependency reviews
- compliance reviews

Sensitive evidence itself may live outside Git. Store only a reference and status.
