# PHP/MariaDB Referenzplattform

Die PHP/MariaDB Referenz ist die erste **wirklich startbare Beispielplattform** des MGD Project Platform Systems.

Sie zeigt, wie die abstrakten Foundation-Regeln in einer kleinen klassischen Webanwendung umgesetzt werden können, ohne Symfony, Laravel oder ein anderes Framework vorzuschreiben.

Pfad im Repository:

```text
reference/php-mariadb/
```

## Was ist bereits funktionsfähig?

Die Referenz enthält:

* Login mit Passwort-Hashing
* PHP Sessions mit sicheren Cookie-Defaults
* CSRF-Schutz
* Rollen und Capabilities aus MariaDB
* serverseitige Autorisierung
* capability-gesteuerte Backoffice-Navigation
* Account-Sperrung als privilegierte Beispielaktion
* Audit Log
* Security Event Log
* Service Principals für AI-Agenten und Automation
* Bearer-Token API
* Jobs/Outbox
* CLI Worker
* automatischen PHP/MariaDB Smoke Test

## Voraussetzungen

Für die lokale Nutzung werden benötigt:

```text
PHP 8.3+
PDO MySQL
mbstring
MariaDB
Composer
```

## Installation

Repository klonen und in die Referenz wechseln:

```bash
git clone https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System.git
cd Projekt-Plattform-System/reference/php-mariadb
```

### Einfachster Weg mit Docker

Die enthaltene `compose.yml` startet eine lokale MariaDB. Das Schema wird anschließend über die versionierten Migrationen aufgebaut:

```bash
docker compose up -d db
composer install
cp config.example.php config.php
php scripts/migrate.php
```

Für das lokale Docker-Profil werden in `config.php` diese Entwicklungsdaten verwendet:

```text
Host: 127.0.0.1
Port: 3306
Datenbank: mgd_platform
Benutzer: mgd
Passwort: mgd-local-only
```

Diese Zugangsdaten sind ausschließlich lokale Demo-Defaults und dürfen nicht für Produktion übernommen werden.

### Ohne Docker

Alternativ kann eine vorhandene lokale MariaDB verwendet werden:

```bash
composer install
cp config.example.php config.php
```

Danach werden in `config.php` die Daten der lokalen Entwicklungsdatenbank eingetragen.

Die laufende Datenbankstruktur wird ab 0.4 über `database/migrations/` verwaltet.

`database/schema.sql` bleibt als lesbare Baseline-Referenz erhalten. Für neue Installationen und Updates ist `php scripts/migrate.php` der vorgesehene Weg.

Die Details zu Migrationen, Translation Registry, Agentenverwaltung und Dead Letter Jobs stehen unter [[21-Migrationen-I18n-Agenten-und-Jobs]].

## Ersten Administrator anlegen

Es gibt bewusst **kein Standardpasswort**.

Der erste Admin wird über Umgebungsvariablen erzeugt:

```bash
export MGD_ADMIN_EMAIL="admin@example.local"
export MGD_ADMIN_PASSWORD="ein-langes-lokales-passwort"
php scripts/create-admin.php
unset MGD_ADMIN_PASSWORD
```

Das Passwort wird mit PHP `password_hash()` gespeichert.

## Lokalen Server starten

```bash
php -S 127.0.0.1:8080 -t public
```

Danach:

```text
http://127.0.0.1:8080/login
```

## Backoffice

Nach dem Login stehen abhängig von den eigenen Capabilities verschiedene Bereiche zur Verfügung.

Der Admin sieht beispielsweise:

```text
Dashboard
Accounts
Translations
Agents / API
Audit
Security
Jobs
```

Ein Moderator oder Support-Mitarbeiter würde nur die Bereiche erhalten, für die seine Rolle Capabilities besitzt.

Wichtig ist die Trennung:

```text
Navigation ausblenden
≠
Autorisierung
```

Auch jeder Endpoint prüft die Capability serverseitig erneut.

## Beispielrollen

### Admin

Der Admin erhält im Beispiel alle registrierten Capabilities.

### Moderator

```text
content.read
moderation.case.read
moderation.case.decide
```

### Support

```text
content.read
support.case.read
```

Die Rolle selbst ist nur ein Bündel. Die Sicherheitsentscheidung erfolgt über konkrete Capabilities.

## Account-Sperrung

Die Account-Liste demonstriert eine privilegierte Aktion.

Für eine Sperrung wird benötigt:

```text
accounts.suspend
```

Die Aktion wird serverseitig geprüft und anschließend im Audit Log dokumentiert.

Das Beispiel verhindert zusätzlich die eigene Sperrung über die Demo-Oberfläche.

## Audit Log

Audit Events beantworten Fragen wie:

```text
Wer hat was getan?
Wann?
An welcher Ressource?
Mit welcher technischen Identität?
```

Beispiele:

```text
auth.login
auth.logout
accounts.suspend
jobs.demo.enqueue
```

## Security Events

Security Events werden getrennt geführt.

Ein fehlgeschlagener Login erzeugt beispielsweise:

```text
auth.login.failed
```

Die eingegebene E-Mail-Adresse wird dabei nicht einfach in das Security Log geschrieben. Im Beispiel wird stattdessen ein Hash verwendet.

## AI-Agenten und Service Principals

Automationen und Agenten verwenden keine menschliche Admin-Session.

Ein Service Principal wird so erzeugt:

```bash
export MGD_SP_NAME="Local Agent"
export MGD_SP_SCOPES="content.read,support.case.read"
php scripts/create-service-principal.php
```

Der Token wird einmal angezeigt.

In der Datenbank liegt ausschließlich sein Hash.

Ein Test gegen die API:

```bash
curl -H "Authorization: Bearer DEIN_TOKEN" \
  http://127.0.0.1:8080/api/me
```

Die API antwortet mit Actor-ID, Actor-Typ und Capabilities.

Damit wird das Foundation-Prinzip sichtbar:

```text
Mensch
→ Account Actor
→ Rollen
→ Capabilities

AI / Automation
→ Service Principal
→ Scopes
→ Capabilities
```

Beide verwenden danach dasselbe Autorisierungsmodell.

## Jobs und Outbox

Das Backoffice enthält eine kleine Jobs-Ansicht.

Dort kann ein harmloser Beispieljob erzeugt werden.

Der Worker wird gestartet mit:

```bash
php scripts/worker.php
```

Das Muster eignet sich später beispielsweise für:

* E-Mails
* Exporte
* Bildverarbeitung
* Notifications
* Webhooks
* Cleanup
* Hintergrundberechnungen

Für größere Systeme können später dedizierte Queues ergänzt werden. Für viele kleinere Plattformen reicht eine DB-basierte Outbox als verständlicher Start.

## Automatischer Datenbanktest

Die GitHub Foundation Checks starten für diese Referenz eine echte MariaDB.

Danach werden Composer, PHP Syntax und ein Smoke Test ausgeführt.

Der Test prüft tatsächlich:

```text
Schema importierbar
→ Rollen und Capabilities auflösbar
→ Account-Sperrung funktioniert
→ Audit Event wird geschrieben
→ Job kann erzeugt werden
→ Job kann abgeschlossen werden
```

Damit ist die Referenz nicht nur Beispielcode, sondern wird automatisch gegen eine reale Datenbank getestet.

## Was fehlt bewusst noch?

Die Referenz ist kein fertiges Produktionssystem.

Für ein öffentliches Produkt wären je nach Projekt zusätzlich nötig:

* Login Rate Limiting
* Passwort-Reset
* E-Mail-Verifikation
* echtes MFA
* Content Security Policy
* Produktions-Secret-Management
* HTTPS-Konfiguration
* produktive Session-Infrastruktur
* Monitoring und Alerts
* produktionsspezifische Rollback- und Migration-Reviews
* weitergehende Queue-Idempotenz und Concurrency-Kontrollen
* weitere Eingabevalidierung
* projektspezifisches Threat Modeling
* Security Review

Diese Grenzen werden bewusst dokumentiert, statt eine falsche Produktionsreife zu suggerieren.

## Warum ist diese Referenz wichtig?

Die Foundation trennt bewusst Prinzip und Implementierung.

Das Wiki kann sagen:

```text
privilegierte Aktionen müssen auditiert werden
```

Die Referenz zeigt dazu echten Code:

```text
Capability prüfen
→ Account ändern
→ Audit Event schreiben
```

Dadurch bekommen Menschen und Coding-Agenten ein konkretes Muster, ohne dass das gesamte MGD Project Platform System zu einem PHP-Framework wird.

Weiter: [[21-Migrationen-I18n-Agenten-und-Jobs]] · [[18-CLI-Validator-und-Automatisierung]] · [[19-Referenzimplementierungen-und-Demos]] · [[05-Rollen-Berechtigungen-und-Backoffice]]
