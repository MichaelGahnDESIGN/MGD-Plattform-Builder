# Security Policy

## Reporting a vulnerability

Please do not publish active exploit details, secrets or personal data in public issues.

Use a private contact method associated with the repository maintainer or GitHub's private vulnerability reporting feature when available.

Include:

- affected component
- impact
- reproducible steps with minimal data access
- expected and actual behavior
- suggested mitigation if known

## Please avoid

- accessing other users' data
- denial-of-service testing
- credential attacks against real users
- destructive changes
- public disclosure before a reasonable remediation window

## Project security principles

The foundation itself follows:

- least privilege
- secure defaults
- no secrets in Git
- backup before risky changes
- explicit trust boundaries
- auditable privileged actions
- privacy-aware logging
- fail-closed behavior for privileged authorization when verification is unavailable

## Scope

This repository mainly contains architecture, schemas, templates and documentation. Reference implementations added later will define supported versions and security maintenance windows separately.
