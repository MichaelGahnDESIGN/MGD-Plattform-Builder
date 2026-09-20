# Migration bestehender Projekte

Die Foundation verlangt keinen Rewrite.

## Ziel

Ein bestehendes Projekt wird schrittweise an robuste Betriebs-, Security- und Governance-Muster herangeführt, ohne funktionierende Teile unnötig zu ersetzen.

## Phase 1: Inventur

Dokumentiere:

* Tech-Stack
* Module und Kernfunktionen
* Datenbanken und Datenklassen
* Rollen und privilegierte Konten
* externe Services
* lokale, Staging- und Produktionsumgebungen
* Deployment
* Backups und Restore
* Logs und Monitoring
* aktuelle Dokumentation
* bekannte Incidents und Risiken

## Phase 2: Projektprofil

Erstelle `MGD_PLATFORM.yml` passend zum Ist-Zustand. Das Profil soll zunächst ehrlich dokumentieren, was existiert, nicht was idealerweise existieren sollte.

## Phase 3: Gap Report

Vergleiche den Ist-Zustand mit der Foundation in diesen Bereichen:

```text
Architektur
Daten
Berechtigungen
Datenschutz
Sicherheit
Betrieb
Dokumentation
Release-Gates
```

Bewerte Lücken nach Risiko und Abhängigkeiten.

## Phase 4: Kritische Risiken zuerst

Beispiele für typische Prioritäten:

1. fehlende oder ungeprüfte Backups
2. unsichere Secrets
3. breite Admin-Rechte
4. fehlende serverseitige Autorisierung
5. unkontrollierte Produktionsdeployments
6. fehlende Datenschutzprozesse
7. fehlende Auditierbarkeit
8. technische Schulden ohne Dokumentation

## Phase 5: Grenzen sichtbar machen

Bestehende große Codebereiche werden nicht sofort zerlegt. Zuerst werden Verantwortlichkeiten, Datenbesitz und Schnittstellen dokumentiert. Danach kann schrittweise modularisiert werden.

## Phase 6: Release-Gates

Neue Änderungen sollen bereits nach den neuen Regeln laufen, auch wenn Legacy-Bereiche noch migriert werden.

## Anti-Pattern

Ein kompletter Neuaufbau nur aus architektonischer Begeisterung ist kein Fortschritt, wenn dabei getestete Geschäftslogik, Datenmigrationen oder operative Erfahrung verloren gehen.

Weiter: [[02-Schnellstart]] · [[14-Governance-Dokumentation-und-Releases]]