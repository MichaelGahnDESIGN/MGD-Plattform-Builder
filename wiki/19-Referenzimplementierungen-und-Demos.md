# Referenzimplementierungen und Demos

Die Foundation enthält neben Dokumentation und CLI auch konkrete Referenzimplementierungen. Sie zeigen, wie die abstrakten Regeln in echten technischen Strukturen aussehen können.

Diese Beispiele sind bewusst klein gehalten. Sie sind Lern-, Architektur- und Integrationsreferenzen und keine fertigen Produktionsanwendungen.

## PHP und MariaDB Referenz

Pfad:

```text
reference/php-mariadb/
```

Die Referenz zeigt eine framework-neutrale Umsetzung für PHP 8.x und MariaDB.

Enthalten sind unter anderem:

```text
PDO Connection
Actor Model
Capability Authorization
Audit Logger
Account Service
Service Principals
SQL Schema
```

Struktur:

```text
reference/php-mariadb/
├── composer.json
├── config.example.php
├── database/
│   └── schema.sql
└── src/
    ├── Core/
    │   ├── Auth/
    │   ├── Audit/
    │   ├── Database/
    │   └── Permissions/
    └── Modules/
        └── Accounts/
```

### Beispiel: Actor

Ein Actor ist die Identität, die eine Aktion ausführt. Das kann ein Mensch oder eine technische Identität sein.

Die Referenz prüft konkrete Capabilities statt nur einen Rollennamen.

Beispiel:

```text
accounts.suspend
```

### Beispiel: Audit

Wird ein Account gesperrt, schreibt der Beispielservice zusätzlich ein Audit Event mit Actor, Aktion, Ressource, Zeitpunkt und Begründung.

Das demonstriert die Foundation-Regel:

```text
Privilegierte Aktion
→ serverseitige Berechtigungsprüfung
→ Fachaktion
→ Audit Event
```

### Service Principals

Automationen und AI-Agenten werden als eigene technische Identitäten modelliert.

Das Beispiel-Schema enthält:

```text
service_principals
```

mit Token Hash, Scopes, Ablaufdatum und Widerruf.

Browser-Sessions von Administratoren müssen dadurch nicht für Automationen zweckentfremdet werden.

## Backoffice Demo

Pfad:

```text
reference/backoffice-demo/index.html
```

Die Demo ist eine vollständig lokale HTML-Datei ohne externe Fonts oder JavaScript-Bibliotheken.

Sie demonstriert die gemeinsame Backoffice-Shell für:

```text
Admin
Moderator
Support
```

Je nach gewählter Rolle werden nur Bereiche angezeigt, für die die Rolle Capabilities besitzt.

Beispiel:

```text
Admin
→ Content
→ Moderation
→ Support
→ Privacy
→ Security
→ Permissions

Moderator
→ Content
→ Moderation

Support
→ Content
→ Support
```

Wichtig: Das Demo-UI zeigt nur das Prinzip. In einer echten Anwendung darf das Verstecken eines Menüpunktes niemals die serverseitige Autorisierung ersetzen.

## Warum Referenzimplementierungen?

Dokumentation beschreibt, **was** ein gutes System tun soll.

Referenzimplementierungen zeigen, **wie** diese Idee technisch aussehen kann.

Damit können Entwickler und AI-Agenten konkrete Muster übernehmen, ohne die Foundation zu einem starren Framework zu machen.

## Geplante weitere Referenzen

Vorgesehen sind unter anderem:

* Node.js und PostgreSQL
* Jobs und Outbox
* Translation Registry
* Audit und Security Event Dashboard
* generische Backoffice-Komponenten
* Docker Staging Beispiel
* Release Evidence Dashboard

Weiter: [[18-CLI-Validator-und-Automatisierung]] · [[04-Architektur]]
