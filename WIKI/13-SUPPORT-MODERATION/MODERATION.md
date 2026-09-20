# Moderation System

## Core model

Separate:

- report
- moderation case
- moderation event
- decision
- appeal

This preserves history instead of overwriting one status field.

## Typical states

- submitted
- triage
- review
- changes requested
- approved
- rejected
- blocked
- appealed
- closed

## Permissions

Moderators should see what is required for the case, not arbitrary private account data.

## Appeals

A mature system supports:

- clear reason
- appeal deadline where applicable
- second review where possible
- conflict-of-interest handling
- immutable decision history

## Automation

Automated prechecks can assist triage, but the project should document where human review is required.
