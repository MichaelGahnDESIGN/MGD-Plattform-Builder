# Project Wiki

This wiki is the detailed knowledge base for the **MGD-Plattform-Builder**.

It is organized so humans and coding agents can enter at different depths without reading the entire repository.

## Start here

- [Concept](00-OVERVIEW/CONCEPT.md)
- [Terminology](00-OVERVIEW/TERMINOLOGY.md)
- [Quick Start](01-GETTING-STARTED/QUICKSTART.md)
- [Adopting the Foundation](01-GETTING-STARTED/ADOPTION.md)

## Architecture

- [Principles](02-ARCHITECTURE/PRINCIPLES.md)
- [Modular Monolith](02-ARCHITECTURE/MODULAR-MONOLITH.md)
- [Data Architecture](02-ARCHITECTURE/DATA-ARCHITECTURE.md)
- [Backoffice: CMS + CRM + PIM](02-ARCHITECTURE/BACKOFFICE.md)
- [Role-based Backoffice Views](02-ARCHITECTURE/ROLE-BASED-BACKOFFICE-VIEWS.md)
- [Modules and Plugins](02-ARCHITECTURE/MODULES-PLUGINS.md)
- [Roles, Capabilities and Policies](02-ARCHITECTURE/PERMISSIONS.md)
- [Files and Storage](02-ARCHITECTURE/FILES-STORAGE.md)
- [Billing and Entitlements](02-ARCHITECTURE/BILLING-ENTITLEMENTS.md)
- [API and Agent Access](02-ARCHITECTURE/API-AGENT-ACCESS.md)
- [Database Migrations](02-ARCHITECTURE/DATABASE-MIGRATIONS.md)

## AI-generated CMS (mandatory features, since 0.5.1)

- [Mandatory Features Overview](18-CMS/MANDATORY-FEATURES.md)
- [Versioning and Release Notes](18-CMS/VERSIONING-RELEASE-NOTES.md)
- [Credits](18-CMS/CREDITS.md)
- [Legal and CMS Pages](18-CMS/LEGAL-CMS-PAGES.md)
- [Settings, Design, Light/Dark, File Locations, Code Editors](18-CMS/SETTINGS-DESIGN-THEMES.md)
- [Briefing and Recommendations](18-CMS/BRIEFING-RECOMMENDATIONS.md)
- [Templates](18-CMS/TEMPLATES.md)

## Security

- [Security Model](03-SECURITY/SECURITY-MODEL.md)
- [Threat Model](03-SECURITY/THREAT-MODEL.md)
- [Incident Response](03-SECURITY/INCIDENT-RESPONSE.md)
- [Audit and Evidence](03-SECURITY/AUDIT-EVIDENCE.md)

## Privacy

- [Privacy by Design](04-PRIVACY/PRIVACY-BY-DESIGN.md)
- [Data Subject Rights](04-PRIVACY/DATA-SUBJECT-RIGHTS.md)
- [Data Classification](04-PRIVACY/DATA-CLASSIFICATION.md)

## Compliance

- [Germany / EU](05-COMPLIANCE/DE-EU.md)
- [Legal Library](05-COMPLIANCE/LEGAL-LIBRARY.md)
- [Public Transparency](05-COMPLIANCE/PUBLIC-TRANSPARENCY.md)

## Operations

- [Docker & Staging](06-OPERATIONS/DOCKER-STAGING.md)
- [Backup & Restore](06-OPERATIONS/BACKUP-RESTORE.md)
- [Monitoring](06-OPERATIONS/MONITORING.md)
- [Deployment](06-OPERATIONS/DEPLOYMENT.md)
- [Testing and Release Gates](06-OPERATIONS/TESTING-RELEASE-GATES.md)

## AI Agents

- [Agent Workflow](07-AGENTS/AGENT-WORKFLOW.md)
- [Skill Ecosystem](07-AGENTS/SKILL-ECOSYSTEM.md)
- [AI-assisted Moderation](07-AGENTS/AI-MODERATION.md)

## Support & Moderation

- [Support System](13-SUPPORT-MODERATION/SUPPORT.md)
- [Moderation System](13-SUPPORT-MODERATION/MODERATION.md)

## Accessibility

- [Accessibility](14-ACCESSIBILITY/ACCESSIBILITY.md)

## Internationalization

- [Translations](08-I18N/TRANSLATIONS.md)

## Governance

- [Feature Governance](09-GOVERNANCE/FEATURE-GOVERNANCE.md)
- [Documentation and Source of Truth](09-GOVERNANCE/DOCUMENTATION-SOURCE-OF-TRUTH.md)

## Domain Packs

- [Overview](10-DOMAIN-PACKS/README.md)
- [Game](10-DOMAIN-PACKS/GAME.md)
- [Community](10-DOMAIN-PACKS/COMMUNITY.md)
- [Creator / Publishing](10-DOMAIN-PACKS/CREATOR.md)
- [E-Commerce](10-DOMAIN-PACKS/ECOMMERCE.md)

## Adoption

- [Migrating an Existing Project](11-ADOPTION/MIGRATION-EXISTING-PROJECT.md)

## Reference

- [Stack Adapters](12-REFERENCE/STACK-ADAPTERS.md)

## Examples

- [Example Project Profiles](15-EXAMPLES/PROJECT-PROFILES.md)

## Extending

- [Contributing Packs and Adapters](16-EXTENDING/CONTRIBUTING-PACKS.md)

## Foundation Releases

- [Foundation Updates](17-RELEASES/FOUNDATION-UPDATES.md)

---

## Documentation policy

The wiki should separate:

- **current principles**
- **recommended patterns**
- **optional patterns**
- **examples**
- **project-specific decisions**

Project-specific decisions belong in the adopting project's repository, not in this foundation.

Security findings, real credentials, production dumps, customer data and private legal correspondence never belong in this public wiki.
