# AI-assisted Moderation

AI may assist moderation, but the permission and accountability model must remain explicit.

## Useful AI tasks

- classify queue priority
- summarize reports
- detect likely duplicates
- suggest policy categories
- highlight missing evidence
- draft a response for human review

## High-risk tasks

Do not default to fully autonomous irreversible decisions for:

- account termination
- rights disputes
- identity verification
- legal notices
- severe safety cases

## Data minimization

Send only the case data required for the task.

## Transparency

Document when AI materially participates in moderation decisions.

## Audit

Record:

- model/service identity when practical
- case
- task type
- recommendation
- human decision where required

## Prompt injection

Reported/user content may contain hostile instructions. Treat it as untrusted evidence, not agent commands.
