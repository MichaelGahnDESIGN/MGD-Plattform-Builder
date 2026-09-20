# 0.5: Translation Review, Agenten-Historie und idempotente Jobs

Version 0.5 erweitert die Referenzplattform um Workflows, die besonders bei wachsender Teamgröße und AI-Agenten wichtig werden.

Die wichtigsten neuen Bereiche sind:

* Translation Review Workflow
* Translation Import und Export
* Service Principal Detailseiten und Historie
* Job Idempotency Keys
* Job Handler Registry
* dokumentierte Migration Policy

## Translation Review Workflow

Übersetzungen werden nicht mehr direkt von einem Bearbeiter veröffentlicht.

Der Lebenszyklus lautet:

```text
draft
↓
review
↓
published

review
↓
rejected
↓
draft
```

Damit können Übersetzer Texte vorbereiten, ohne automatisch die Berechtigung zur Veröffentlichung zu besitzen.

## Translation Capabilities

Die Foundation trennt inzwischen:

```text
translations.read
translations.manage
translations.review
translations.import
translations.export
```

Ein Translator kann beispielsweise lesen, bearbeiten, importieren und exportieren.

Die eigentliche Freigabe kann einer anderen Rolle vorbehalten bleiben.

## Draft speichern

Ein neuer oder geänderter Text wird zunächst als Draft gespeichert.

Beispiel:

```text
dashboard.welcome
de
Willkommen
draft
```

## Review anfordern

Ein Draft kann anschließend in den Review Zustand überführt werden.

```text
draft
→ review
```

Dabei wird gespeichert, wann der Review angefordert wurde.

## Freigeben

Ein Nutzer mit:

```text
translations.review
```

kann den Text veröffentlichen.

```text
review
→ published
```

Die Foundation speichert:

* Review Zeitpunkt
* Review Actor
* optionale Review Notiz

## Ablehnen

Ein Review kann abgelehnt werden:

```text
review
→ rejected
```

Der Text bleibt erhalten und kann erneut bearbeitet werden.

## Translation Import

Importierte Übersetzungen werden bewusst immer als Draft behandelt.

Dadurch kann ein Import niemals direkt einen veröffentlichten Text ersetzen, ohne erneut durch den Review Workflow zu gehen.

Ein einfaches Importformat:

```json
{
  "locale": "de",
  "translations": {
    "dashboard.welcome": "Willkommen",
    "dashboard.logout": {
      "value": "Abmelden",
      "description": "Button zum Abmelden"
    }
  }
}
```

CLI:

```bash
php scripts/import-translations.php translations-de.json
```

## Translation Export

Einzelne Sprache:

```bash
php scripts/export-translations.php de > translations-de.json
```

Alle Sprachen:

```bash
php scripts/export-translations.php > translations-all.json
```

Das Exportformat heißt:

```text
mgd-translations-v1
```

Ein Export kann anschließend wieder importiert werden.

Beim Reimport werden die Einträge erneut zu Drafts.

## Backoffice

Der Bereich:

```text
Translations
```

zeigt jetzt:

* Key
* Locale
* Wert
* Status
* Review Notiz
* Aktionen

Mögliche Aktionen hängen von den Capabilities ab.

Ein Bearbeiter sieht beispielsweise:

```text
Save Draft
Submit Review
```

Ein Reviewer sieht:

```text
Approve
Reject
```

## Service Principal Detailseite

Unter:

```text
Agents / API
```

kann jeder technische Zugang geöffnet werden.

Die Detailseite zeigt unter anderem:

* Name
* Public ID
* Beschreibung
* Status
* Scopes
* Erstellungszeitpunkt
* Ablaufdatum
* letzte Rotation
* letzte Nutzung
* Ersteller

## Eigene Agenten-Historie

Neben dem globalen Audit Log gibt es jetzt eine separate Ereignishistorie pro Service Principal.

Beispiel:

```text
created
token_rotated
revoked
```

Jedes Ereignis enthält:

* Actor
* Typ
* Zeit
* Metadaten

Diese Historie liegt in:

```text
service_principal_events
```

## Warum zusätzlich zum Audit Log?

Das globale Audit Log beantwortet:

```text
Was ist insgesamt im System passiert?
```

Die Service Principal Historie beantwortet:

```text
Was ist im Lebenszyklus genau dieses Agenten-Zugangs passiert?
```

Beide Perspektiven sind nützlich.

## Job Idempotency Keys

Ein typisches Problem bei Hintergrundjobs:

Ein Request wird erneut gesendet.

Beispiele:

* Doppelklick
* Netzwerk Retry
* API Retry
* Webhook Wiederholung
* Agent führt denselben Schritt erneut aus

Ohne Schutz könnten zwei identische Jobs entstehen.

0.5 ergänzt deshalb:

```text
enqueueIdempotent()
```

Beispiel:

```php
$outbox->enqueueIdempotent(
    'demo.audit-export',
    'export-order-123',
    [
        'requested_by' => $actor->id
    ]
);
```

Der gleiche Topic plus derselbe Idempotency Key erzeugt nur einen Job.

## Speicherung

Der eigentliche Schlüssel muss nicht dauerhaft gespeichert werden.

Die Referenz speichert:

```text
SHA-256(topic + idempotency key)
```

in:

```text
idempotency_hash
```

Darauf liegt ein Unique Index.

## Rückgabe

Der Aufruf liefert:

```text
id
created
```

Beim ersten Request:

```text
created = true
```

Beim Duplikat:

```text
created = false
```

und dieselbe Job ID wird zurückgegeben.

## Job Handler Registry

Der Worker enthält nicht mehr für jeden Job einen immer größer werdenden `switch`.

Stattdessen existiert:

```text
JobHandlerRegistry
```

Ein Handler wird registriert:

```php
$handlers->register(
    'demo.audit-export',
    new DemoAuditExportHandler()
);
```

Danach übernimmt der Worker nur noch:

```text
Job claimen
↓
Payload lesen
↓
passenden Handler finden
↓
Handler ausführen
↓
Done oder Retry/Dead
```

## Warum ist das besser?

Neue Job-Typen können als eigene Klassen ergänzt werden.

Die Infrastruktur muss dabei nicht jedes Mal umgebaut werden.

Das reduziert Kopplung zwischen Queue und Fachlogik.

## Migration Policy

Mit 0.5 gibt es zusätzlich:

```text
reference/php-mariadb/database/MIGRATION-POLICY.md
```

Sie beschreibt Regeln für reale Datenbankänderungen.

Wichtigste Grundregel:

```text
Eine bereits ausgeführte Migration wird nie verändert.
```

Neue Änderungen erhalten immer eine neue Migration.

## Empfohlenes Muster

Für riskantere Schemaänderungen:

```text
expand
↓
backfill
↓
kompatiblen Code deployen
↓
Reads/Writes umstellen
↓
beobachten
↓
alte Struktur später entfernen
```

Dadurch muss nicht jede Änderung gleichzeitig in Code und Datenbank erfolgen.

## Rollback

Nicht jede Datenbankmigration besitzt sinnvollerweise eine automatische Down Migration.

Insbesondere bei Datenumwandlungen kann ein Forward Fix sicherer sein.

Vor einem Production Deployment soll dokumentiert werden:

* Ist alter Code nach Migration noch kompatibel?
* Kann die Migration zurückgerollt werden?
* Gibt es einen Forward Fix?
* Ist Backup beziehungsweise Restore geprüft?
* Sind große Locks zu erwarten?
* Muss die Änderung in mehreren Deployments erfolgen?

## Neue Migrationen in 0.5

```text
0006_translation_review_workflow.sql
0007_service_principal_history.sql
0008_job_idempotency.sql
```

## Automatische Tests

Der MariaDB Smoke Test prüft in 0.5 zusätzlich:

```text
8 Migrationen
↓
Draft Translation
↓
Review Submit
↓
Approve
↓
Export
↓
Reimport als Draft
↓
erneuter Review
↓
Service Principal erstellen
↓
Token verwenden
↓
Token rotieren
↓
alter Token ungültig
↓
Historie vorhanden
↓
Principal widerrufen
↓
Token ungültig
↓
idempotenten Job zweimal anlegen
↓
nur ein Job entsteht
↓
Handler Registry ausführen
↓
Stale Worker Recovery
↓
Dead Letter
↓
Retry
```

## Sicherheitsprinzipien

0.5 hält weiterhin an denselben Regeln fest:

```text
UI Sichtbarkeit ist keine Autorisierung.
```

```text
Import ist keine Veröffentlichung.
```

```text
Agent Token ist kein Benutzerpasswort.
```

```text
Retry darf keine doppelte Fachaktion erzeugen.
```

Gerade der letzte Punkt ist der Grund für Idempotency Keys.

## Nächste sinnvolle Ausbaustufe

Nach 0.5 sind besonders interessant:

* Node/PostgreSQL Referenz
* umfangreicheres Backoffice Component Set
* Translation CSV Import und Export
* Review Queue mit Filtern
* Job Handler Discovery
* Job Idempotency auf Fachobjekt-Ebene
* Compliance Automation
* Evidence Dashboard
* tiefere Claude/Codex Integration

Weiter: [[21-Migrationen-I18n-Agenten-und-Jobs]] · [[20-PHP-MariaDB-Referenzplattform]] · [[18-CLI-Validator-und-Automatisierung]]
