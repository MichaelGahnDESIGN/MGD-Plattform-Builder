# Architektur

Die Foundation empfiehlt einen modularen Monolithen als sicheren und effizienten Ausgangspunkt.

## Zielbild

```text
Application
├── Core
│   ├── Auth
│   ├── Permissions
│   ├── Security
│   ├── Audit
│   ├── Database
│   ├── Events
│   ├── Jobs
│   └── I18n
│
├── Modules
│   ├── Accounts
│   ├── Content
│   ├── Moderation
│   ├── Support
│   ├── Billing
│   ├── Privacy
│   └── CMS
│
├── Backoffice
└── Public / API
```

Nicht jedes Projekt benötigt jedes Modul.

## Warum modularer Monolith?

Ein einzelnes Deployment ist einfach zu betreiben, zu testen und zu debuggen. Klare Modulgrenzen verhindern trotzdem, dass sich die gesamte Anwendung zu einem untrennbaren Block entwickelt.

Separate Services werden erst sinnvoll, wenn ein konkreter Bedarf besteht, etwa unabhängige Skalierung, eigene Trust Boundaries, getrennte Release-Verantwortung, Datenresidenz oder Failure Isolation.

## Anforderungen an Module

Jedes Modul soll Verantwortung, eigene Daten, öffentliche Schnittstellen, Events, Berechtigungen, Jobs, Migrationen und Retention-Auswirkungen benennen.

Cross-Modul-Kommunikation soll über explizite Services oder Domain Events erfolgen.

## Server entscheidet

UI-Ausblendungen sind keine Autorisierung. Jede privilegierte Aktion muss serverseitig geprüft werden.

## Datenbankänderungen

Bevorzugt wird eine additive Migration:

```text
Neue Struktur anlegen
→ Daten backfillen
→ Kompatibilität herstellen
→ Reads umstellen
→ Writes umstellen
→ beobachten
→ Legacy später entfernen
```

## Relationale Daten und JSON

Identitäten, Besitzverhältnisse, Status, Berechtigungen, Billing und Moderation gehören bevorzugt in relationale Strukturen. JSON eignet sich für flexible, risikoarme optionale Metadaten und Snapshots.

## Interne und öffentliche IDs

Interne numerische IDs dürfen effizient bleiben. Öffentliche URLs und APIs sollten bei Enumerationsrisiken nicht vorhersagbare IDs verwenden.

Technische Tiefenreferenz: [WIKI/02-ARCHITECTURE](https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System/tree/main/WIKI/02-ARCHITECTURE)

Weiter: [[05-Rollen-Berechtigungen-und-Backoffice]] · [[06-Daten-Dateien-und-Speicherung]]