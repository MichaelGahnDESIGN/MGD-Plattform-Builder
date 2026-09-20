# Testing and Release Gates

## Test layers

- unit
- integration
- authorization/negative tests
- migration tests
- end-to-end/smoke
- backup restore
- security checks

## Role tests

For permission-sensitive features test at least:

- owner
- unrelated user
- moderator/support role
- admin
- logged-out where relevant
- blocked/disabled account

## Release gates

Examples:

- backup current
- restore recently verified
- migrations tested
- privacy impact reviewed
- security impact reviewed
- legal/compliance gate resolved
- staging smoke green
- rollback documented

## Evidence

A release should be able to point to the checks that justified it.
