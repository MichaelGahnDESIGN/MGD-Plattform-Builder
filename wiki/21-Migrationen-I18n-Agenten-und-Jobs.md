# 0.4: Migrationen, Übersetzungen, Agenten und Jobs

Version 0.4 erweitert die startbare PHP/MariaDB Referenzplattform um mehrere wiederverwendbare Betriebsbausteine.

Der Schwerpunkt liegt auf vier Themen:

* versionierte Datenbankmigrationen
* zentrale Translation Registry
* Service Principal Verwaltung für AI-Agenten und Automation
* robustere Jobs/Outbox mit Retry und Dead Letter State

## Datenbankmigrationen

Die Datenbank wird ab 0.4 nicht mehr durch wiederholtes manuelles Importieren einer großen SQL-Datei weiterentwickelt.

Stattdessen liegen Migrationen unter:

```text
reference/php-mariadb/database/migrations/
```

Aktueller Stand:

```text
0001_baseline.sql
0002_translation_registry.sql
0003_service_principal_management.sql
0004_outbox_dead_letter.sql
0005_operational_capabilities.sql
```

Ausgeführt werden sie mit:

```bash
php scripts/migrate.php
```

## Wie der Migration Runner arbeitet

Der Runner:

1. erstellt bei Bedarf `schema_migrations`
2. sortiert Migrationen nach Dateiname
3. berechnet für jede Datei einen SHA-256 Checksum
4. prüft bereits ausgeführte Migrationen
5. führt nur fehlende Migrationen aus
6. speichert Version, Checksum und Zeitpunkt

Wenn eine bereits ausgeführte Migration später verändert wurde, wird der Lauf gestoppt.

Das verhindert, dass historische Datenbankänderungen still umgeschrieben werden.

## Migration eines bestehenden 0.3 Setups

Wer die 0.3 Referenz bereits über `schema.sql` aufgebaut hat, muss die Datenbank nicht neu erstellen.

Der Runner erkennt die vorhandenen Core-Tabellen und kann die Baseline als bereits vorhanden übernehmen. Danach werden nur die neuen 0.4 Migrationen ausgeführt.

## Neuer Installationsablauf

```bash
cd reference/php-mariadb

docker compose up -d db
composer install
cp config.example.php config.php

php scripts/migrate.php
```

Danach wird der erste Admin angelegt:

```bash
export MGD_ADMIN_EMAIL="admin@example.local"
export MGD_ADMIN_PASSWORD="ein-langes-lokales-passwort"

php scripts/create-admin.php

unset MGD_ADMIN_PASSWORD
```

## Translation Registry

Die Translation Registry trennt Übersetzungsschlüssel von konkreten Sprachwerten.

Beispiel:

```text
dashboard.welcome
```

kann Werte besitzen für:

```text
de
en
fr
...
```

Jeder Sprachwert besitzt zusätzlich einen Status:

```text
draft
published
```

Damit können Übersetzungen vorbereitet werden, ohne sie sofort öffentlich zu verwenden.

## Berechtigungen für Übersetzungen

```text
translations.read
translations.manage
```

Lesen und Bearbeiten bleiben dadurch getrennt.

## Translation Service

Die Implementierung befindet sich unter:

```text
src/Core/I18n/TranslationRegistry.php
```

Der Service unterstützt:

* Translation Keys
* Locale Validierung
* Draft und Published Status
* Upserts
* Audit Events
* Published Lookup
* optionales Fallback Locale

## Backoffice

Im Backoffice gibt es jetzt den Bereich:

```text
Translations
```

Dort können Nutzer mit `translations.manage` Übersetzungen anlegen oder aktualisieren.

Die Aktion erzeugt zusätzlich ein Audit Event.

## Service Principals

Service Principals sind technische Identitäten für:

* AI-Agenten
* lokale Automation
* Cronjobs
* Integrationen
* externe Services

Sie verwenden keine menschliche Admin Session.

## Neue Backoffice Verwaltung

Der Bereich:

```text
Agents / API
```

zeigt technische Identitäten, deren Scopes, Status und letzte Verwendung.

Benötigte Capabilities:

```text
service-principals.read
service-principals.manage
```

## Service Principal erstellen

Über das Backoffice oder weiterhin per CLI.

CLI Beispiel:

```bash
export MGD_SP_NAME="Content Agent"
export MGD_SP_SCOPES="content.read,translations.read"

php scripts/create-service-principal.php
```

Scopes müssen inzwischen als echte Capabilities registriert sein.

Ein frei erfundener Scope wie:

```text
everything.superadmin
```

wird abgelehnt.

## Token Sicherheit

Beim Erstellen oder Rotieren wird der Token nur einmal angezeigt.

In MariaDB wird ausschließlich gespeichert:

```text
SHA-256(token)
```

Zusätzlich speichert die Foundation:

* Erstellungszeitpunkt
* Ablaufdatum
* Widerrufsstatus
* letzte Rotation
* letzte erfolgreiche Verwendung

## Token Rotation

Ein aktiver Service Principal kann im Backoffice einen neuen Token erhalten.

Der alte Token funktioniert anschließend nicht mehr.

Die Rotation wird auditiert.

## Token Widerruf

Ein Service Principal kann widerrufen werden.

Danach akzeptiert `ServicePrincipalAuth` seinen Token nicht mehr.

Auch der Widerruf wird als Audit Event gespeichert.

## Jobs und Outbox

Das Job-System wurde in 0.4 deutlich robuster.

Neue Capabilities:

```text
jobs.read
jobs.manage
```

## Atomic Claim

Worker lesen Jobs nicht mehr nur aus der Tabelle.

Ein Worker muss einen Job zuerst claimen:

```text
pending
↓
processing
```

Dabei werden gespeichert:

```text
locked_at
locked_by
```

Dadurch können mehrere Worker parallel arbeiten, ohne absichtlich denselben verfügbaren Job zu übernehmen.

## Retry

Schlägt ein Job fehl, steigt:

```text
attempts
```

Der nächste Versuch wird zeitlich verzögert.

Das Beispiel verwendet einen exponentiellen Backoff bis maximal 60 Minuten.

## Max Attempts

Jeder Job besitzt:

```text
max_attempts
```

Der Default ist:

```text
5
```

## Dead Letter State

Wenn ein Job zu oft fehlschlägt:

```text
processing
↓
dead
```

Zusätzlich werden gespeichert:

```text
last_error
failed_at
```

Damit verschwindet ein dauerhaft defekter Job nicht einfach aus dem System.

## Dead Job erneut versuchen

Im Backoffice können Nutzer mit `jobs.manage` einen Dead Letter Job erneut freigeben.

Dabei wird:

```text
status → pending
attempts → 0
error → leer
failed_at → leer
```

gesetzt.

Die Aktion wird auditiert.

## Wiederverwendbare Backoffice Komponenten

Mit 0.4 beginnt außerdem die Extraktion kleiner UI-Helfer unter:

```text
src/Core/Backoffice/Ui.php
```

Aktuell enthalten:

* Badges
* Notices
* Empty States
* Tabellen

Das Ziel ist nicht, ein eigenes Frontend Framework zu bauen.

Stattdessen sollen wiederkehrende Backoffice Muster konsistent und einfach wiederverwendbar werden.

## Automatische Tests

Der PHP/MariaDB Smoke Test arbeitet ab 0.4 auf einer leeren Datenbank.

Er prüft unter anderem:

```text
Migrationen ausführen
→ Migrationen erneut ausführen
→ Checksums bleiben stabil
→ Admin Capabilities vorhanden
→ Account Aktion
→ Audit
→ Translation speichern und lesen
→ Service Principal erstellen
→ Bearer Token authentifizieren
→ last_used_at aktualisieren
→ Job claimen
→ Job abschließen
→ Job absichtlich fehlschlagen lassen
→ Dead Letter State prüfen
→ Dead Job erneut freigeben
```

Damit werden die neuen 0.4 Bausteine gegen eine echte MariaDB getestet.

## Sicherheitsprinzip

Auch mit dem erweiterten Backoffice gilt weiterhin:

```text
sichtbarer Menüpunkt
≠
Berechtigung
```

Jeder geschützte Endpoint prüft die Capability serverseitig erneut.

## Nächste Ausbaustufe

Nach 0.4 sind besonders interessant:

* Node/PostgreSQL Referenz
* Migration Rollback Strategien
* Service Principal Detailseiten
* feinere Job Handler
* Idempotency Keys
* Translation Import und Export
* Translation Review Workflow
* stärkere wiederverwendbare Backoffice Komponenten

Weiter: [[20-PHP-MariaDB-Referenzplattform]] · [[18-CLI-Validator-und-Automatisierung]] · [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]
