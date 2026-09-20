# Concept

## Goal

The foundation helps projects establish a reliable operational backbone before complexity grows.

It focuses on the parts that are usually rediscovered late:

- identity and permissions
- admin and moderation
- privacy and data rights
- security and incident response
- compliance evidence
- backups and restore
- staging and deployment
- internationalization
- support
- agent collaboration
- documentation and governance

## Design target

A project should be able to answer:

1. What data do we store?
2. Who may access it?
3. Why is it needed?
4. How is it deleted?
5. How do we recover from failure?
6. How are privileged actions audited?
7. Which legal/security checks block release?
8. Where do agents find the current truth?
9. How can a new module be added without breaking the rest?

## Foundation vs application

The foundation does not define the application's business domain.

Instead, it defines reusable boundaries and checks.

A domain pack may suggest common entities, but the adopting project remains responsible for its own domain model.

## Maturity model

### Level 0: Prototype

Minimal structure, no public users or sensitive data.

### Level 1: Controlled development

Project profile, source-of-truth docs, backups, staging and role model exist.

### Level 2: Private beta

Privacy/security checks, audit, support and incident response are operational.

### Level 3: Public product

Release gates, monitoring, restore testing and compliance evidence are active.

### Level 4: Scaled platform

Physical data separation, advanced observability, stronger SLOs and dedicated security/compliance processes may become useful.
