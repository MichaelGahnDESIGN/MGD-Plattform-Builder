# Backoffice: CMS + CRM + PIM

## One shell, many roles

Admin, moderation, support, translation and privacy teams can share a common backoffice shell.

The server controls access through capabilities.

## Role-based views

The same shell can expose different task-oriented views for different roles without creating separate backend applications.

Typical examples:

- Administration
- Moderation
- Support
- Privacy / Compliance
- Translation / Editorial
- Operations / Security
- AI Operations / AI Moderation Supervision

A role view may change navigation, dashboard widgets, default filters and visible columns, but it is **never** an authorization boundary.

Server-side capabilities and policies remain authoritative.

Projects with multi-role users may allow switching between permitted views without changing the user's capabilities.

See [Role-based Backoffice Views](ROLE-BASED-BACKOFFICE-VIEWS.md).

## Suggested navigation

- Dashboard
- Content / Data
- Users / Organizations
- Moderation
- Support
- Billing
- CMS
- Translations
- Privacy / Compliance
- Security
- Modules
- Themes / Skins
- Settings

## List views

Support:

- search
- filters
- status chips
- sort
- pagination
- bulk actions
- saved views later

## Detail views

Prefer tabs for complex objects.

Example generic content object:

- Overview
- Relations
- Media
- Permissions
- Moderation
- Analytics
- History / Audit

## Dangerous actions

Require:

- explicit confirmation
- reason
- re-authentication for high-risk actions
- audit event
- permission check

## Global search

Search must respect the same access rules as detail pages. Never use a global index as an authorization bypass.

## CRM behavior without surveillance

Support status, account state and relationship context can be useful.

Avoid building unnecessary behavioral profiling simply because a CRM pattern makes it convenient.
