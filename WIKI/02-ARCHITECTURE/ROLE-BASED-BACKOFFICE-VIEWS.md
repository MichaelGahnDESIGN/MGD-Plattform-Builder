# Role-based Backoffice Views

## Goal

One platform may have many internal actors, but it should not need a separate backend application for every role.

The recommended pattern is:

**one shared backoffice shell + role/capability-specific views + server-side policies.**

Examples of internal actors:

- administrator
- moderator
- support
- privacy/compliance
- translator/editor
- billing/operations
- creator or partner operator
- human supervisor for AI-assisted moderation
- service principals and AI agents

The UI may look different for each role while the underlying application, data model and authorization system remain shared.

---

## Core rule: views are not authorization

A hidden menu item is not a security boundary.

A role-specific view only controls presentation:

- navigation
- landing page
- dashboard widgets
- available shortcuts
- default filters
- visible columns
- density and context

Every API request and every sensitive data field still requires server-side authorization.

A user must not gain access to data simply by manually opening another role's URL.

---

## Shared shell

A shared shell may provide:

- global layout
- sidebar
- top bar
- search
- notifications
- account/session controls
- consistent tables/forms/dialogs
- responsive behavior
- accessibility primitives

Role views then plug into that shell.

Example:

```text
Backoffice Shell
├── Admin View
├── Moderator View
├── Support View
├── Privacy / Compliance View
├── Translation / Editorial View
└── AI Operations View
```

The same module may appear differently in more than one view.

---

## Recommended role views

### Administration

Typical purpose:

- system overview
- users and organizations
- permissions
- modules
- billing configuration
- platform settings
- security status
- backups
- audit
- compliance overview

Admin does **not** automatically mean unrestricted access to all private user data.

Highly sensitive data may still require:

- dedicated capability
- case assignment
- re-authentication
- reason
- audit event

### Moderation

Typical purpose:

- moderation queue
- reports
- content review
- policy categories
- appeals
- verification cases

Moderators should normally not receive:

- billing details
- full account secrets
- unrelated private history
- privacy-request data
- security administration
- global platform settings

### Support

Typical purpose:

- assigned support cases
- account state required for the case
- technical metadata
- escalation
- safe links into other modules

Support should not become a general-purpose user surveillance view.

### Privacy / Compliance

Typical purpose:

- data-subject requests
- retention status
- processor register
- legal-source reviews
- DPIA/DSFA records
- document versions
- deadlines

Because this area can expose sensitive information, capabilities should be narrow and strongly audited.

### Translation / Editorial

Typical purpose:

- translation keys
- draft/review/published states
- CMS pages
- editorial content
- import/export
- locale coverage

Publishing legal or safety-critical text may require a stronger capability than drafting it.

### Operations / Security

Typical purpose:

- system health
- jobs
- backups
- security events
- sessions
- service principals
- deployment status

This view should be separate from normal content administration where practical.

### AI Operations / AI Moderation Supervision

The recommended pattern is **not** to give an AI agent a human super-admin UI.

Instead:

- AI/service principals use scoped APIs
- the backoffice exposes a human supervision view
- humans can see AI recommendations, confidence/metadata, actions and audit history
- irreversible/high-risk actions can require human approval
- user-generated content is treated as untrusted input and never as agent instructions

Example AI operations widgets:

- cases triaged by AI
- recommendations awaiting review
- agent errors/retries
- token/service-principal status
- recent automated actions
- audit trail

---

## View resolution

A project may resolve a view from:

1. authenticated actor
2. actor type
3. roles
4. capabilities
5. organization/team membership
6. current context
7. optional user preference among allowed views

A person with several roles may be allowed to switch views.

Example:

```text
User: moderator + translator

Available views:
- Moderation
- Translation

Not available:
- Admin
- Privacy
- Security
```

Switching views must never grant new capabilities.

---

## Navigation model

Do not hardcode navigation solely by role name.

Prefer:

```text
module registers navigation item
        ↓
view says where it belongs
        ↓
capability/policy filters access
        ↓
user sees only allowed item
```

This makes modules reusable across projects.

Example module declaration:

```yaml
navigation:
  group: moderation
  label: Reports
  views:
    - moderation
    - admin
  requires:
    - moderation.case.read
```

---

## Dashboard model

Each role view may define its own dashboard composition.

Admin dashboard:

- platform health
- users
- storage
- critical alerts
- release/backup status

Moderator dashboard:

- pending reports
- pending content reviews
- appeals
- SLA/queue age

Support dashboard:

- assigned tickets
- escalations
- response-time warnings

Translation dashboard:

- missing translations
- review queue
- locale coverage

AI supervision dashboard:

- pending AI recommendations
- automated actions
- failed tasks
- service-principal health

Dashboard cards must respect the same data policies as their detail pages.

---

## Data minimization per view

Role views should actively reduce data exposure.

Recommended pattern:

```text
same entity
├── public/basic projection
├── support projection
├── moderation projection
├── admin lifecycle projection
└── privacy/security projection
```

Do not fetch all fields and hide them with CSS.

The server should return the smallest projection required for the current capability and use case.

---

## URLs

Projects may use:

```text
/backoffice
/backoffice/moderation
/backoffice/support
/backoffice/privacy
```

or role-oriented aliases such as:

```text
/admin
/mod
/support
```

The URL structure is a product choice.

It must never replace server-side authorization.

---

## Mobile and responsive behavior

Role views should share the same design system:

- sidebar / drawer behavior
- tables
- filters
- status badges
- modals
- forms
- touch targets
- keyboard navigation
- error states

A moderator mobile view may be denser or more task-focused than the admin view, but it should still feel like the same product.

---

## Machine-readable project profile

Projects can declare role views in `MGD_PLATFORM.yml`.

Example:

```yaml
backoffice:
  enabled: true
  shared_shell: true
  default_route: "/backoffice"
  role_views:
    - id: "admin"
      label: "Administration"
      actor_types: ["human"]
      roles: ["admin"]
      landing: "/backoffice"
      navigation_groups: ["dashboard", "content", "users", "system"]
    - id: "moderation"
      label: "Moderation"
      actor_types: ["human"]
      roles: ["moderator"]
      landing: "/backoffice/moderation"
      navigation_groups: ["dashboard", "moderation", "support"]
    - id: "ai-ops"
      label: "AI Operations"
      actor_types: ["human"]
      roles: ["admin"]
      landing: "/backoffice/ai"
      navigation_groups: ["ai", "audit"]
```

These fields describe UX defaults only.

Capabilities and policies remain authoritative.

---

## Agent requirements

When an agent creates or modifies a backoffice module it should ask:

- which roles need this module?
- which capabilities are required?
- which fields are required per role?
- which role views should show it?
- which actions are read-only vs write?
- which actions need re-authentication?
- what must be audited?
- is AI involved?
- can the server return a smaller data projection?

The agent must not solve role separation only by hiding frontend controls.

---

## Testing matrix

For each role-sensitive module test at least:

- allowed human role
- unrelated human role
- administrator
- service principal / AI agent if applicable
- logged-out request
- direct API access
- direct URL access
- field-level data redaction
- denied write action
- role/view switching
- mobile rendering

A secure result means both UI **and** API behavior are correct.
