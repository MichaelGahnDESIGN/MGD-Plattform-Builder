# Beispiele, FAQ und Glossar

## Beispielprofile

Im Repository liegen mehrere synthetische Projektprofile unter `examples/`.

Verfügbar sind unter anderem:

* `general-platform.yml`
* `game.yml`
* `community.yml`
* `creator.yml`
* `ecommerce.yml`

Sie dienen als Ausgangspunkt und dürfen nicht blind in Produktion übernommen werden.

## Beispiel: Community

Eine Community könnte Accounts, Moderation, Support und Übersetzungen aktivieren. Billing wäre optional. Das Projektprofil beschreibt zusätzlich Zielmarkt, Sprachen, Privacy-Anforderungen, Security-Baseline und Infrastruktur.

## Beispiel: E-Commerce

Ein Shop oder Marktplatz ergänzt typischerweise Billing, Consumer Flows, Transaktionsdaten, Retention-Regeln und strengere Release-Gates für Payment- und Vertragsprozesse.

## Beispiel: Game

Ein Online-Spiel kann Account-, Entitlement-, Moderations-, Support- und Game-spezifische Module kombinieren. In-Game-Käufe ändern nichts an der Trennung zwischen Nutzungsrechten und administrativen Berechtigungen.

---

## FAQ

### Muss ich die komplette Foundation implementieren?

Nein. Die Foundation ist modular. Übernimm nur die Bausteine, die dein Projekt wirklich benötigt.

### Muss ich einen modularen Monolithen verwenden?

Nein. Er ist der empfohlene Default. Andere Architekturen sind möglich, wenn sie begründet und dokumentiert sind.

### Ist das Projekt ein Framework?

Nein. Es ist eine Architektur-, Governance- und Betriebsgrundlage. Die konkrete Implementierung kann mit unterschiedlichen Stacks erfolgen.

### Macht die Foundation mein Projekt DSGVO-konform?

Nein. Sie hilft dabei, technische und organisatorische Datenschutzthemen strukturiert zu berücksichtigen. Rechtskonformität muss projektspezifisch geprüft werden.

### Darf ein AI-Agent Admin-Rechte erhalten?

Nur wenn das im konkreten Projekt bewusst entschieden und abgesichert wurde. Der Default sind eigene Service Principals mit kleinen Scopes, Ablauf, Widerruf und Audit.

### Ist Git ein Backup?

Für Code ja, für Produktionsdaten nein. Datenbankdumps und personenbezogene Daten gehören nicht in normale Git-Repositories.

### Muss ich Docker verwenden?

Nein. Docker ist optional. Entscheidend sind reproduzierbare Umgebungen und ein nachvollziehbarer Deployment-Prozess.

### Kann ich mehrere Domain Packs kombinieren?

Ja. Konflikte und projektspezifische Entscheidungen müssen explizit dokumentiert werden.

### Muss jedes Release manuell erfolgen?

Nein. Automatisierung ist ausdrücklich möglich. High-Risk Deployments benötigen aber passende Gates, Tests und einen kontrollierten Freigabemechanismus.

---

## Glossar

**Foundation**  
Die projektneutrale Sammlung aus Regeln, Templates, Schemas und Empfehlungen.

**Project Profile**  
Die Datei `MGD_PLATFORM.yml` des konkreten Projekts.

**Core**  
Nicht leichtfertig entfernbare Basiskomponenten wie Auth, Permissions, Security, Audit und Privacy Controls.

**Module**  
Abgegrenzter Funktionsbereich mit Datenbesitz, Schnittstellen, Berechtigungen und Dokumentation.

**Domain Pack**  
Planungsdefaults für eine Projektkategorie wie Game, Community oder E-Commerce.

**Backoffice**  
Interne Oberfläche für Admin, Moderation, Support, Datenschutz oder Übersetzung.

**Role**  
Menschenlesbares Bündel von Rechten.

**Capability**  
Konkrete Berechtigung wie `content.publish`.

**Policy**  
Serverseitige Entscheidungslogik für Aktionen auf Ressourcen.

**Service Principal**  
Nichtmenschliche Identität für Automation oder AI-Agenten.

**Entitlement**  
Nutzungsrecht für Feature, Produkt, Modul oder Inhalt.

**Audit Event**  
Dauerhafter Nachweis einer privilegierten oder geschäftsrelevanten Aktion.

**Security Event**  
Signal, das Missbrauch oder einen Sicherheitsvorfall anzeigen kann.

**Release Gate**  
Bedingung, die vor einem Release erfüllt sein muss.

**Legal Library**  
Strukturierte Sammlung von Rechtsquellen, Prüfständen und abgeleiteten Maßnahmen.

Zurück: [[01-Systemueberblick]]