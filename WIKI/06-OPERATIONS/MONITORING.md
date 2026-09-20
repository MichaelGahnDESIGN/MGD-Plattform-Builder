# Monitoring

## Monitor service health, not people

Useful signals:

- availability
- HTTP error rate
- database health
- storage
- backup status
- mail failures
- background jobs
- webhook failures
- security events
- certificate/domain expiry

## Privacy

Avoid sending full request bodies, private messages, tokens or user identifiers into monitoring tools unless truly necessary.

## Alert levels

- info
- warning
- critical

## Public status

A public status page may show:

- service state
- maintenance
- known incidents
- history

Do not expose active attack details while an incident is unresolved.
