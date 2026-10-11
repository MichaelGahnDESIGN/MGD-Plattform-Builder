---
name: fragenkatalog
description: >-
  Interaktiver Design-Fragenkatalog mit KI-Antworten aus wählbarer Experten-Perspektive.
  Abdeckung: UX/UI, Architektur, Legal, Privacy, Security, Operations. Mit Recht-Kategorie (⚖️-Disclaimer).
  Lokal im Projekt unter .claude/catalog/ oder .codex/catalog/, Registry über registry/catalog.yml.
---

# /fragenkatalog — Guided Design Decisions

Strukturierte Fragen zu Projekt-Setup, Architektur und Compliance mit Expert-Perspektiven.

## Struktur

```
project/
├── .claude/catalog/
│   └── fragenkatalog.yml      # Lokal beantwortete Fragen
├── registry/
│   └── catalog.yml            # Katalog-Metadaten und Quellen
```

## Kategorien

| Kategorie | Fragen | Perspektiven |
|---|---|---|
| **Design** | UX, Layout, Accessibility, Design System | Product, UX, Designer |
| **Architektur** | Tech Stack, Patterns, Scaling | Architect, Backend, DevOps |
| **Legal** | ToS, Privacy, Liability | Legal ⚖️ |
| **Privacy** | GDPR, CCPA, Datenfluss | DPO, Privacy Engineer |
| **Security** | Auth, Encryption, Threat Model | Security Architect |
| **Operations** | Monitoring, Logs, Backups | SRE, DevOps |

## Aufruf

```bash
/fragenkatalog setup          # Katalog initialisieren
/fragenkatalog ask <frage>    # Frage stellen
/fragenkatalog expert <rolle> # Expert-Perspektive wählen (Product, Legal, Security, ...)
```

## Perspektiven

Jede Frage kann aus verschiedenen Blickwinkeln beantwortet werden:

- **Product** – Geschäftswert, User Stories
- **Design** – Aesthetik, Usability
- **Legal** – Regulatorische Anforderungen (nur mit ⚖️ wenn Recht-Kategorie)
- **Security** – Threat Model, Hardening
- **Operations** – Monitoring, Incident Response

Die Perspektive wird einmalig pro Session gewählt, kann aber `//expert <neue-rolle>` gewechselt werden.
