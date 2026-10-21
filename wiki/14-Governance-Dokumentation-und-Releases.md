# Governance, Dokumentation und Releases

## Source of Truth

Code, Architekturentscheidungen, Projektprofil, Risiken, Tasks und Release-Gates sollen versioniert und miteinander verlinkt sein.

Die wichtigste Regel lautet: Es muss klar sein, welche Quelle für welche Information maßgeblich ist.

## Foundation und Projekt trennen

Die Foundation dokumentiert allgemeine Prinzipien. Projektbezogene Entscheidungen gehören in das jeweilige Projekt.

Beispiele für projektspezifische Inhalte:

* echte Domains
* Hostingdetails
* Kundendaten
* interne Server
* konkrete Produktionszugänge
* vertrauliche Verträge
* NDA-Inhalte

Solche Informationen gehören nicht in dieses öffentliche Foundation Wiki.

## Feature Governance

Bevor eine größere Funktion umgesetzt wird, sollte geklärt werden:

* Welches Problem löst sie?
* Welche Daten entstehen?
* Welche Rollen und Capabilities sind betroffen?
* Gibt es Datenschutz- oder Security-Auswirkungen?
* Welche Migrationen sind nötig?
* Welche Tests und Release-Gates werden benötigt?
* Welche Dokumentation ändert sich?

## Living Documentation, Graphify und Todo (ab 0.7.0)

Die Quellen der Wahrheit eines Projekts sind **Living Documentation** (belegbarer Stand, Entscheidungen, offene Punkte, Risiken), **Graphify** (maschinell erzeugte Karte) und **Todo** (Aufgaben). Alle drei werden mit `mgd-platform companions install` zusammen mit dem Projekt eingerichtet und nach jeder Änderung gepflegt; `release-check` blockiert, solange einer fehlt. Details: [[27-Begleiter-Living-Documentation-Graphify-Todo]].

## Dokumentationspflicht

Eine Änderung ist nicht vollständig, wenn relevante Dokumentation wissentlich falsch bleibt.

Der Umfang der Dokumentation richtet sich nach dem Risiko. Eine kleine UI-Korrektur benötigt nicht dieselbe Dokumentation wie ein neues Rollenmodell.

## Releases

Vor einem Release werden mindestens Tests, Migrationen, Backup beziehungsweise Rollback, Security-Auswirkungen, Datenschutz-Auswirkungen und Dokumentationsstand geprüft.

## Foundation Updates

Projekte sollen nicht automatisch jede neue Foundation-Version ungeprüft übernehmen.

Empfohlener Ablauf:

```text
Release Notes lesen
→ relevante Änderungen identifizieren
→ Projektprofil prüfen
→ Gap Report
→ Änderungen geplant übernehmen
→ Tests und Gates ausführen
→ Foundation Version im Projektprofil aktualisieren
```

## Ehrliche Statusberichte

Agenten und Entwickler melden klar, was umgesetzt, getestet und nicht geprüft wurde. Ein Commit allein beweist weder ein erfolgreiches Deployment noch einen sicheren Produktionszustand.

Weiter: [[15-Beispiele-FAQ-und-Glossar]] · [[16-Mitmachen-und-Weiterentwickeln]]