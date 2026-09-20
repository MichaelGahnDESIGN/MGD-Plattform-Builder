# Threat Model

## Actors

### Anonymous attacker

May try brute force, injection, enumeration, scraping or denial of service.

### Malicious user

May attempt horizontal access, abuse limits, manipulate IDs, spam or game reputation systems.

### Compromised privileged account

May access sensitive records, change permissions or hide traces.

### Compromised dependency/host

May expose secrets, code or data.

### Malicious or manipulated AI agent

May be influenced by prompt injection or untrusted repository content.

## Common attack classes

- account takeover
- broken access control / IDOR
- injection
- XSS
- CSRF
- unsafe file upload
- SSRF
- path traversal
- replay attacks
- webhook spoofing
- token leakage
- business logic abuse
- backup destruction
- CI/CD compromise

## Security invariants

A project should define statements that must always remain true.

Examples:

1. private resources are not reachable through alternate endpoints
2. changing an ID cannot reveal another user's data
3. privilege removal takes effect promptly
4. privileged actions are auditable
5. payment state comes from verified server-side sources
6. secrets do not enter client bundles or Git history
