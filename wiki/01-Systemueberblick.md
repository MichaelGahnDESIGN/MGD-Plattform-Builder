# Systemüberblick

Das **MGD-Plattform-Builder** ist eine wiederverwendbare technische und organisatorische Grundlage für digitale Plattformen. Es ist kein fertiges CMS, kein SaaS-Produkt und kein starres Framework. Stattdessen beschreibt es robuste Muster für Architektur, Rollen, Datenschutz, Sicherheit, Betrieb, Dokumentation und die Zusammenarbeit mit Coding-Agenten.

## Für welche Projekte ist es gedacht?

Die Foundation eignet sich für Webplattformen, SaaS-Produkte, Online-Spiele, Community- und Social-Plattformen, Creator- und Publishing-Angebote, Shops, Marktplätze, interne Portale sowie Apps mit Admin-, Support- oder Moderationsbedarf.

## Welches Problem löst das System?

In vielen Projekten entstehen kritische Themen zu spät. Rollen werden improvisiert, Datenschutz wird als reine Rechtstext-Aufgabe verstanden, Backups werden nicht getestet und AI-Agenten erhalten zu breite Rechte. Die Foundation verschiebt diese Themen an den Anfang.

Der gewünschte Ablauf lautet:

```text
Produktidee
  ↓
Projektprofil
  ↓
Risiken und Module
  ↓
Architektur und Berechtigungen
  ↓
Umsetzung
  ↓
Tests und Release-Gates
  ↓
Betrieb und dokumentierte Weiterentwicklung
```

## Grundprinzipien

Die wichtigsten Prinzipien sind Privacy by Design, Security by Design, Least Privilege, nachvollziehbare Dokumentation, getrennte Verantwortlichkeiten, sichere Backups, kontrollierte Releases und möglichst kleine, verständliche Systemgrenzen.

Für kleine und mittlere Projekte ist ein **modularer Monolith** der empfohlene Startpunkt. Microservices sind kein Selbstzweck und sollen erst eingeführt werden, wenn Skalierung, Trust Boundaries, Datenresidenz oder unabhängige Releases einen konkreten Grund liefern.

## Foundation und konkrete Anwendung

Die Foundation definiert nicht die Geschäftslogik eines bestimmten Produkts. Ein Spiel, eine Community oder ein Shop nutzt dieselben Grundbausteine, ergänzt sie aber mit eigenen Domain-Modellen.

Projektbezogene Entscheidungen gehören in das jeweilige Projekt. Die Foundation bleibt projektneutral.

## Reifegradmodell

**Level 0: Prototyp.** Minimale Struktur, keine öffentlichen Nutzer und keine sensiblen Daten.

**Level 1: Kontrollierte Entwicklung.** Projektprofil, Rollenmodell, Source-of-Truth-Dokumentation, Backup und Staging existieren.

**Level 2: Private Beta.** Privacy- und Security-Prüfungen, Audit, Support und Incident Response sind einsatzbereit.

**Level 3: Öffentliches Produkt.** Release-Gates, Monitoring, Restore-Tests und Compliance-Nachweise werden aktiv gepflegt.

**Level 4: Skalierte Plattform.** Zusätzliche Datentrennung, Observability, SLOs und spezialisierte Security- oder Compliance-Prozesse können sinnvoll werden.

## Was das System ausdrücklich nicht verspricht

Die Foundation ist keine Rechtsberatung, keine Sicherheitszertifizierung und keine Garantie für Rechtskonformität. Sie ersetzt auch keine projektspezifische Risikoanalyse. Sie hilft dabei, relevante Fragen früh zu erkennen, nachvollziehbar zu dokumentieren und technische Schutzmaßnahmen systematisch einzuplanen.

Weiter: [[02-Schnellstart]] · [[04-Architektur]] · [[07-Datenschutz-und-Compliance]]