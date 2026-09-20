# Mitmachen und Weiterentwickeln

Beiträge zur Foundation sind willkommen, besonders wenn sie projektneutral und nachvollziehbar bleiben.

## Geeignete Beiträge

Besonders hilfreich sind:

* zusätzliche Stack Adapter
* Security Checks
* Compliance-Quellenmodelle
* neue Domain Packs
* bessere Templates
* Docker- und Staging-Beispiele
* Validatoren und Tests
* Agenten-Kompatibilität
* Übersetzungen
* Dokumentationsverbesserungen

## Vor einer Änderung

Lies:

* `CONTRIBUTING.md`
* `GOVERNANCE.md`
* `SECURITY.md`
* relevante Wiki-Seiten
* vorhandene Schemas und Templates

## Anforderungen an Beiträge

Eine Änderung soll möglichst klein, nachvollziehbar und rückwärtsbewusst sein. Neue Konzepte benötigen eine klare Begründung und dürfen die Foundation nicht unnötig an einen bestimmten Anbieter oder Stack binden.

## Neue Domain Packs

Ein Domain Pack sollte typische Module, Datenobjekte, Permissions, Risiken, Privacy-Auswirkungen, Betriebsanforderungen und Release-Gates beschreiben.

Es soll Empfehlungen geben, aber keine projektspezifischen Details enthalten.

## Neue Stack Adapter

Ein Stack Adapter zeigt, wie die Foundation-Prinzipien in einem bestimmten technischen Ökosystem umgesetzt werden können.

Er soll zwischen allgemeinem Prinzip und stack-spezifischer Umsetzung unterscheiden.

## Security Findings

Sicherheitslücken mit verwertbaren Exploitdetails gehören nicht in öffentliche Issues. Das Vorgehen steht in `SECURITY.md`.

## Pull Requests

Ein guter Pull Request erklärt:

* Problem und Motivation
* betroffene Bereiche
* technische Änderung
* Risiken
* Tests
* Dokumentationsänderungen
* Breaking Changes, falls vorhanden

## Dokumentation gehört zum Change

Wenn sich Verhalten, Schema, Rollenmodell oder Release-Prozess ändert, sollen die zugehörigen Dokumente im selben Change aktualisiert werden.

## Öffentliche Foundation

Keine echten Credentials, privaten Serverpfade, Kundendaten, NDA-Inhalte oder vertrauliche Rechtskorrespondenz committen.

Repository: [MichaelGahnDESIGN/Projekt-Plattform-System](https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System)