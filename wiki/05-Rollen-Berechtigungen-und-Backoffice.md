# Rollen, Berechtigungen und Backoffice

## Rollen sind Bündel, nicht die eigentliche Sicherheitslogik

Typische Rollen sind User, Moderator, Support, Admin oder Translator. Die konkrete Autorisierung erfolgt über **Capabilities** und **Policies**.

Beispiele:

```text
content.read
content.publish
moderation.case.read
moderation.case.decide
support.case.read
privacy.request.manage
translations.publish
security.audit.read
```

## Policies

Eine Policy kann mehrere Faktoren kombinieren:

```text
Capability
+ Eigentümer des Objekts
+ Status des Objekts
+ Organisationszugehörigkeit
+ Sensitivität
+ Kontext
= Entscheidung
```

## Least Privilege

Support erhält nicht automatisch Zugriff auf Billing-Details. Moderatoren benötigen keine Account-Secrets. Ein Admin ist nicht automatisch berechtigt, jedes private Datenfeld einzusehen.

Sensible Rechte sollen so eng wie möglich vergeben werden.

## Backoffice

Admin, Moderation, Support, Übersetzung und Datenschutz können dieselbe interne Oberfläche verwenden. Die sichtbaren Bereiche werden serverseitig anhand der Capabilities bestimmt.

Empfohlene Hauptbereiche:

```text
Dashboard
Inhalte / Daten
Benutzer / Organisationen
Moderation
Support
Billing
CMS
Übersetzungen
Privacy / Compliance
Security
Module
Themes / Skins
Einstellungen
```

## Listen und Detailansichten

Professionelle Listen sollten Suche, Filter, Status, Sortierung, Pagination und kontrollierte Bulk-Aktionen unterstützen. Komplexe Detailseiten können in Tabs gegliedert werden.

## Gefährliche Aktionen

Für Löschen, Sperren, Rollenänderungen, Datenexporte oder andere Hochrisikoaktionen gelten zusätzliche Schutzmaßnahmen:

* explizite Bestätigung
* Begründung
* erneute Authentifizierung bei besonders sensiblen Vorgängen
* serverseitige Permission-Prüfung
* Audit Event

## Globale Suche

Eine globale Suche darf niemals zu einem Berechtigungs-Bypass werden. Suchergebnisse müssen denselben Zugriffskontrollen unterliegen wie die jeweilige Detailseite.

Weiter: [[08-Sicherheit-und-Threat-Model]] · [[12-Support-Moderation-I18n-und-Barrierefreiheit]]