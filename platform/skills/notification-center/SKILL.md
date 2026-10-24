---
name: notification-center
description: Entwirft und implementiert ein datensparsames Notification Center mit öffentlichen und persönlichen Meldungen.
---

# Notification Center

Nutze diesen Skill für eine Glocke, einen Posteingang oder Benachrichtigungsbereich in einer Website, einem Shop oder einer Plattform. Er trennt immer öffentliche Inhalte von persönlichen Zustellungen.

## Vor der Umsetzung

1. Benenne Projektwurzel, Plattform, Version und Integrationspunkt.
2. Bestimme, welche Ereignisse eine Meldung erzeugen dürfen. Standard: neue veröffentlichte Inhalte, ausdrücklich angekündigte Aktualisierungen, Bestellstatus, Kontosicherheit und manuelle Admin-Meldungen.
3. Bestimme Rollen: Besucher sehen nur öffentliche Meldungen; angemeldete Nutzer nur ihre eigene Inbox; Redakteure erstellen öffentliche Entwürfe; Admins dürfen persönliche Empfänger auswählen.
4. Prüfe Datenminimierung, Aufbewahrung, Export/Löschung, Backup, Migration, Rückfall und Feature-Flag vor dem ersten produktiven Einsatz.

## Datenmodell

Verwende mindestens zwei logisch getrennte Datensätze:

```text
Notification
  id, scope(public|personal), type, source_key, title, body, target_url,
  status(draft|scheduled|published|archived), published_at, expires_at

NotificationRecipient
  notification_id, recipient_id, read_at, dismissed_at
```

`source_key` ist für automatische Ereignisse eindeutig. Verwende zum Beispiel `product:<id>:published:<revision>` oder `order:<id>:status:<state>`. Ein wiederholter Hook darf keine weitere Meldung erzeugen. Manuelle Meldungen erhalten keine automatisch wiederverwendete Quell-ID.

Persönliche Meldungen und ihr Lesestatus bleiben serverseitig. Öffentliche Ungelesen-Zustände dürfen lokal im Browser liegen, wenn dort ausschließlich öffentliche Meldungs-IDs gespeichert werden. Keine E-Mail-Adresse, Kunden-ID, Bestellung oder persönliche Nachricht in Local Storage, URL, HTML-Dataset oder öffentlichem JSON ausgeben.

## Rechte und Auslieferung

- Serverseitig vor jeder persönlichen Abfrage prüfen: aktuelle Anmeldung, Empfänger-ID, Mandant/Shop und Meldungsstatus.
- Schreibwege verlangen Authentifizierung, Berechtigung, CSRF/Nonce und Eingabevalidierung.
- Öffentliche Feeds dürfen nur veröffentlichte und nicht abgelaufene öffentliche Meldungen enthalten.
- Entwürfe, geplante Meldungen, archivierte Einträge und fremde Empfänger sind niemals über öffentliche Routen erreichbar.
- Rückgabefelder minimieren: Titel, sicher bereinigter Text, Ziel-URL, Zeitpunkt und eigener Lesestatus reichen aus.

## Ereignisse

| Ereignis | Sichtbarkeit | Bedingung |
|---|---|---|
| Inhalt erstmals veröffentlicht | öffentlich | nur freigegebene Inhaltstypen |
| Inhalt aktualisiert | öffentlich | Redakteur aktiviert ausdrücklich „Als Neuigkeit veröffentlichen“ |
| Bestellstatus | persönlich | Kundenkonto ist sicher zugeordnet |
| Kontosicherheit | persönlich | serverseitig verifiziertes Kontoereignis |
| Manuelle Meldung | öffentlich oder persönlich | Rolle und Empfänger geprüft |

Gastbestellungen erhalten keine kontobezogene Meldung. Marketing, Profiling und Newsletter gehören nicht in Version 1; sie brauchen eine getrennte Rechtsgrundlage und Einwilligungsprüfung.

## Oberfläche und Barrierefreiheit

Die Glocke kann im Hauptmenü, Footer, als Floating-Element in einer Ecke oder über einen Plattformbaustein beziehungsweise Shortcode erscheinen. Verwende einen echten `button`, `aria-expanded`, eine beschriftete Liste, sichtbaren Fokus und vollständige Tastaturbedienung. Die Zahl zeigt nur ungelesene, für den aktuellen Betrachter sichtbare Meldungen. Die Oberfläche muss bei fehlender API oder unpassender App-Version sicher leer bleiben.

## Adapter

### WordPress und WooCommerce

Nutze ein eigenständiges Plugin für Tabellen, Backend, REST und Ereignisse. Ein Theme rendert nur eine dokumentierte Ausgabe-Schnittstelle oder einen Shortcode. WooCommerce-Statusmeldungen nur bei einer vorhandenen WordPress-Benutzer-ID erzeugen.

### JTL-Shop

Smarty-Templates liefern nur die Glocke. Backend, Tabellen, Berechtigungen und Kundenzuordnung gehören in ein separates JTL-Plugin, das mit dem Theme ausgeliefert wird. Niemals persönliche Meldungen aus Template-Variablen oder frei zugänglichen JSON-Dateien bauen.

### Shopware

Verwende Migrationen, Entity/Repository, ACL-geschützte Admin-Routen, Store-API und Event-Subscriber. Filtere den Sales-Channel und den aktuell authentifizierten Kunden auf dem Server; Twig und Storefront-JavaScript erhalten nur die bereinigte Darstellung.

### Shopify

Jede Auslieferung besteht aus MichaThemeV5 und der verpflichtenden MichaThemeV5-App. Das Theme liefert Liquid, Platzierung und App-Block. Die App besitzt die Datenhaltung, Admin-Oberfläche, signaturgeprüften App-Proxy und Webhooks. Webhooks prüfen den HMAC über den Rohbody, deduplizieren `X-Shopify-Webhook-Id` und antworten schnell. Der App-Proxy prüft seine Signatur und darf persönliche Daten nur für `logged_in_customer_id` desselben Shops ausliefern. Datenschutz-Webhooks für Auskunft und Löschung sind Teil der App-Konfiguration.

## Betrieb

Plane eine reversible Datenbankmigration, Backup vor Live-Änderungen, Staging, Feature-Flag, Monitoring für fehlgeschlagene Ereignisse und einen Rückfall auf eine leere Oberfläche. Definiere eine Aufbewahrungsfrist und biete Löschung oder Export persönlicher Meldungsdaten an. Deaktivieren darf Daten nicht stillschweigend löschen.

## Prüfungen vor Release

1. Syntax, Linter und Adaptertests ausführen.
2. Deduplizierung mit zweimaligem identischem Ereignis prüfen.
3. Testnutzer A darf die Inbox von B weder lesen noch als gelesen markieren.
4. Entwürfe und abgelaufene Einträge dürfen in keinem Feed erscheinen.
5. Ausgeloggt, angemeldet, Mobilansicht, Tastatur und reduzierter Bewegungsmodus prüfen.
6. Bei Shopify HMAC, App-Proxy-Signatur, Datenschutz-Löschung und Theme-App-Kompatibilität prüfen.
7. Produktiv nur nach Backup, Staging-Prüfung und dokumentiertem Rückfall veröffentlichen.
