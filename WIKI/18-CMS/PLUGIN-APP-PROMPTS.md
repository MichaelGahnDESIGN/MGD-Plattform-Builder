# Prompts für MGD Plugins und Apps

Diese Beispiele geben ChatGPT, Claude oder einem anderen Coding-Agenten einen klaren Auftrag.
Sie sind **keine** Freigabe für Live-Deployments, Marketplace-Uploads, externe Zugriffe oder das Speichern
von Zugangsdaten. Platzhalter in eckigen Klammern vor dem Senden ersetzen.

## Gemeinsamer MGD-Standard

Jede Erweiterung muss die folgenden Ergebnisse liefern – nicht nur eine ähnliche Oberfläche:

1. **Einheitliches Interface:** lokale MGD-Design-Tokens, Light/Dark, saubere Fokuszustände,
   sichtbare Lade-, Leer-, Fehler- und Erfolgszustände sowie mobile Nutzung. Keine externen Fonts,
   Icons oder Bilder als Standard.
2. **Plugin-Empfehlungen:** eine einheitliche MGD-Empfehlungskarte mit Name, Nutzen, Kategorie,
   Kompatibilität, Datenschutz-Hinweis, Quelle/Version und klarer Aktion. Ist der Katalog nicht
   verfügbar, zeigt die Oberfläche dies verständlich und bleibt ansonsten benutzbar.
3. **theme.michael-gahn.de:** nur über einen versionierten Adapter und eine dokumentierte,
   freigegebene Schnittstelle integrieren. Keine Endpunkte, Tokens oder Datenformate erfinden.
   Zugangsdaten gehören ausschließlich in serverseitige Secret-Verwaltung. Die Erweiterung muss
   ohne den Dienst sicher weiterarbeiten; Aktualisierung, Lizenz- oder Katalogabfragen bleiben
   standardmäßig aus beziehungsweise brauchen eine aktive Admin-Entscheidung.
4. **Sicherheit und Datenschutz:** serverseitige Berechtigungen, minimale Daten, keine Telemetrie
   ohne Entscheidung, dokumentierte Löschfrist. Webhooks/Callbacks müssen signiert und idempotent
   sein. Fehler dürfen weder Geheimnisse noch Kundendaten ausgeben.
5. **Abnahme:** Installieren/Aktualisieren, Rechte, Light/Dark, kleine Ansicht, Offline-/Fehlerfall,
   Katalogstatus und die echte Theme-Integration in einer Testumgebung prüfen. Erst danach eine
   Kompatibilitätsbehauptung, ein Release oder eine Veröffentlichung aussprechen.

## Universalprompt

```text
Erstelle eine [PLATTFORM]-Erweiterung namens [NAME] für [ZWECK].

Nutze den MGD [STARTER-ID] und arbeite in kleinen, getesteten Schritten. Baue kein Produkt nur
nach Annahmen: Lies zuerst die aktuelle offizielle Plattformdokumentation und prüfe die vorhandene
Projektarchitektur.

Pflicht:
- Einheitliches MGD-Backend mit lokalen Design-Tokens, Light/Dark, guten Fokuszuständen sowie
  Lade-, Leer-, Fehler- und Erfolgszustand. Keine externen Fonts, Icons oder Tracking-Skripte.
- Baue die gemeinsame MGD-Plugin-Empfehlungskarte: Name, kurzer Nutzen, Kategorie,
  Kompatibilität, Datenschutz-Hinweis, Quelle/Version und eindeutige Aktion. Bei nicht erreichbarem
  Katalog muss ein verständlicher Status erscheinen; die Erweiterung bleibt bedienbar.
- Integriere theme.michael-gahn.de ausschließlich über einen versionierten Adapter mit einer
  dokumentierten, freigegebenen Schnittstelle. Erfinde keine URL, kein Token und kein API-Format.
  Alle Zugangsdaten serverseitig verwalten. Ohne Dienst muss die Kernfunktion sicher weiterlaufen.
- Prüfe serverseitig Berechtigungen; speichere nur notwendige Daten; dokumentiere Zweck,
  Aufbewahrung und Löschung. Webhooks/Callbacks signiert und idempotent verarbeiten.
- Liefere: Architekturentscheidung, Migrationen/Uninstall-Konzept, Datenschutzhinweis,
  Tests, README, Changelog und einen klaren Rückfallweg.

Akzeptanz: [KONKRETE FUNKTIONEN UND ZIELGRUPPEN]. Nicht live deployen oder veröffentlichen,
bevor ich es ausdrücklich freigebe.
```

## WordPress-Plugin

```text
Erstelle mit `wordpress-plugin-starter` das WordPress-Plugin [NAME]. Es soll [FUNKTION] für
[ADMIN/REDAKTION/BESUCHER] erledigen. Nutze Settings API, serverseitige Capability-Prüfungen und
Nonces für jede schreibende Aktion. Ergänze die MGD-Plugin-Empfehlungskarte sowie einen optionalen,
deaktivierten-by-default Theme.Michael-Gahn-Adapter. Prüfe Aktivierung, Deaktivierung, Uninstall,
Rechte, REST/AJAX-Grenzen, mobile Adminansicht, Light/Dark und Fehlerfall ohne Katalogdienst.
```

## WooCommerce-Erweiterung

```text
Erstelle mit `woocommerce-extension-starter` die Erweiterung [NAME] für [FUNKTION]. Definiere
vor jedem Hook, ob Bestell-, Kunden- oder Produktdaten wirklich nötig sind. Gastbestellungen dürfen
nicht stillschweigend einem Konto zugeordnet werden; Zahlungserfolg wird nur serverseitig aus
verifizierten Ereignissen abgeleitet. Ergänze das einheitliche MGD-Interface, die Empfehlungskarte
und einen sicheren, optionalen Theme.Michael-Gahn-Adapter. Teste mit und ohne WooCommerce sowie
mit den Rollen Shop-Manager und Kunde.
```

## JTL-Shop 5 Plugin

```text
Erstelle mit `jtl-shop5-plugin-starter` das JTL-Shop-5-Plugin [NAME]. Prüfe zuerst die konkrete
JTL-Shop-5-Version und die aktuelle offizielle Plugin-Schnittstelle. Trenne Bootstrap, Backend,
Templates, Datenzugriff und Cache-Auswirkungen. Baue das einheitliche MGD-Interface und die
Empfehlungskarte ein. Einen Theme.Michael-Gahn-Adapter erst implementieren, wenn die freigegebene
Schnittstelle und die Kompatibilitätsversionen dokumentiert sind. Teste Pluginverwaltung,
Berechtigungen, Template-Ausgabe und die wirklich betroffenen Cache-Bereiche in einer Testinstanz.
```

## Shopware 6 Plugin

```text
Erstelle mit `shopware6-plugin-starter` das Shopware-6-Plugin [NAME] für [FUNKTION]. Halte
Administration, Storefront, DAL, Migrationen und Hintergrundaufgaben getrennt. Migrationsdateien
sind nach Veröffentlichung unveränderlich; ACL wird serverseitig geprüft. Ergänze das einheitliche
MGD-Interface, die Empfehlungskarte und einen optionalen versionierten Theme.Michael-Gahn-Adapter.
Prüfe Installation, Aktivierung, Migration, ACL, Verkaufskanal, Light/Dark und den Ausfall des
Katalog-/Theme-Dienstes in einer Testumgebung.
```

## Shopify-App

```text
Erstelle mit `shopify-app-starter` die serverseitige Shopify-App [NAME]. Theme-Extension und App
sind zwei getrennte Teile: Das Theme zeigt nur UI; Kundenbezug, Administration und Webhooks liegen
in der App. Prüfe OAuth, App-Proxy und Webhooks serverseitig per HMAC; dedupliziere Webhooks mit
Shop-ID und Ereignis-ID. Ergänze das einheitliche MGD-Interface, die Empfehlungskarte und einen
optional aktivierbaren Theme.Michael-Gahn-Adapter ohne erfundene API-Daten. Teste Datenschutz- und
Löschwebhooks, Berechtigungen, Theme/App-Kompatibilität, Offline- und Fehlerzustände.
```

## Prompt für ein einzelnes bestehendes Plugin

```text
Überarbeite [REPOSITORY/PLUGIN] nicht blind. Prüfe erst Architektur, aktuelle Plattformversion,
Git-Status, Tests und vorhandene Admin-Oberfläche. Ergänze dann den MGD-Integrationsstandard aus
`WIKI/18-CMS/PLUGIN-APP-PROMPTS.md`: einheitliche lokale UI, Empfehlungskarte und optionalen,
versionierten Adapter zu theme.michael-gahn.de. Bewahre bestehende Daten und Einstellungen,
füge eine Migration nur bei echtem Bedarf hinzu und liefere einen Rückfallweg. Dokumentiere exakt,
was lokal, in CI und im Zielshop getestet wurde. Keine Veröffentlichung ohne meine Freigabe.
```

## Was ein Agent vor der Umsetzung fragen muss

Fehlen diese Informationen, darf ein Agent nicht raten: Zielplattform/-version, Zielgruppe,
benötigte Daten, Rollen, erlaubte Theme-Schnittstelle, Katalogquelle, Kompatibilitätsversion,
Aufbewahrungsfrist und gewünschter Release-Kanal.
