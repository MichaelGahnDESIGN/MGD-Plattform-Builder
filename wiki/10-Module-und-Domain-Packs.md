# Module, Plugins und Domain Packs

## Module

Ein Modul ist ein abgegrenzter Funktionsbereich mit klarer Verantwortung.

Ein gutes Modul beschreibt mindestens:

* Verantwortungsbereich
* Datenbesitz
* Abhängigkeiten
* öffentliche Services oder API
* Events
* Berechtigungen
* Jobs
* Migrationen
* Retention-Auswirkungen
* Tests

## Plugins

Die Foundation empfiehlt keinen offenen Upload beliebigen ausführbaren Fremdcodes in Produktionssysteme.

Stattdessen werden kontrollierte Erweiterungsmechanismen bevorzugt:

* geprüfte Module
* Manifest-Dateien
* definierte Dependencies
* Capabilities
* Events
* UI Extension Points
* Feature Flags
* Entitlements
* Themes und Skins

Dadurch bleibt klar, welche Erweiterung was darf und welche Systembereiche betroffen sind.

## Domain Packs

Domain Packs ergänzen die neutrale Foundation um typische Planungsdefaults für bestimmte Projektarten.

Aktuell vorgesehen sind:

* Game
* Community
* Creator und Publishing
* E-Commerce
* General Platform

Ein Pack kann typische Module, Entitäten, Permissions, Missbrauchsmuster, Datenschutzrisiken, Betriebsanforderungen und Release-Gates vorschlagen.

## Packs kombinieren

Ein Projekt kann mehrere Packs kombinieren. Ein Online-Spiel mit Creator Marketplace und Verkäufen könnte beispielsweise Game, Creator und E-Commerce verwenden.

Konflikte werden nicht automatisch aufgelöst. Die Entscheidung gehört in die eigene Architektur-Dokumentation des Projekts.

## Themes und Skins

Visuelle Erweiterungen sollten keine versteckte Businesslogik oder Berechtigungslogik enthalten. Präsentation und Fachlogik bleiben getrennt.

## Entitlements

Entitlements beschreiben Nutzungsrechte für Features, Produkte, Inhalte oder Module. Sie sind nicht dasselbe wie Sicherheitsberechtigungen.

Beispiel: Ein Nutzer darf ein Premium-Modul besitzen, ohne dadurch Admin-Rechte zu erhalten.

Technische Referenz: [Domain Packs](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/tree/main/WIKI/10-DOMAIN-PACKS)

Weiter: [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]