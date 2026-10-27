# 0.6.0: Lizenz, „powered by“-Label, Whitelabel und Module

Ab **0.6.0 Pre-Alpha** ist der MGD-Plattform-Builder **dual lizenziert**:

| Teil | Lizenz |
|---|---|
| CLI, Validator, Schemas, Registries, Skripte, Dokumentation, Profilvorlagen | MIT |
| Starter-Templates, Referenzplattform, „powered by“-Label, Logos und alle daraus erstellten Projekte | [MGD-Lizenz](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/blob/main/MGD-Lizenz.md) |

Versionen bis einschließlich 0.5.1 wurden unter MIT veröffentlicht und bleiben für diese Versionen MIT. Die genaue Zuordnung steht in `LICENSING.md`.

> [!NOTE]
> Die MGD-Lizenz ist ein Entwurf von Michael Gahn DESIGN und wird anwaltlich geprüft. Maßgeblich ist immer die Fassung in `MGD-Lizenz.md`.

## Das Pflicht-Label

Jede Installation zeigt **„powered by: Michael Gahn DESIGN“** mit Logo (automatisch hell oder dunkel) und Link auf https://michael-gahn.de, der sich in einem neuen Tab öffnet:

* im Footer öffentlicher Seiten
* auf öffentlichen und privaten Landingpages (Startseite, Backoffice-Dashboard)
* auf allen Login-Seiten (auch Installer)
* im Footer des Backoffice
* in den Einstellungen
* auf der Seite **Einstellungen › Lizenz**

Gestaltbar ist nur die **Ausrichtung** (links, Mitte, rechts) unter Einstellungen › Lizenz. Label, Logo, Link, Lizenzseite und Lizenztext dürfen nicht entfernt, verändert oder ausgeblendet werden – außer mit einer Whitelabel-Lizenz.

## Einstellungen › Lizenz

Die Seite `/admin/license` zeigt:

* das Label
* den vollständigen Lizenztext aus `MGD-Lizenz.md`
* die aktuelle Domain
* den Whitelabel-Status (Lizenznehmer, Projekt, Domains)
* ob Lizenztext, Label-Code, Label-CSS und Logos unverändert sind

Admins können hier einen Whitelabel-Schlüssel eintragen oder entfernen.

## Integritätsprüfung

Der Starter vergleicht Prüfsummen (SHA-256) von Lizenztext, Label-Code, Label-CSS und Logos. Wurde etwas verändert und ist keine Whitelabel-Lizenz aktiv, sehen Admins auf jeder Backoffice-Seite einen Warnhinweis. In der Foundation schlägt `mgd-platform template check` (und damit die CI) fehl, wenn ein Template einen abweichenden Lizenztext oder veränderte Label-Dateien enthält.

> Offener Quellcode lässt sich immer verändern. Die Prüfung schafft Transparenz und Nachweisbarkeit, die Lizenz regelt, was erlaubt ist.

## Whitelabel-Lizenz

* **500 €, einmalig, pro Projekt bzw. Domain**
* Nachweis durch einen **digital signierten Lizenzschlüssel** (Ed25519) im Format `MGD1.<Daten>.<Signatur>`
* Der Schlüssel enthält Projekt-ID, Domains (auch `*.beispiel.de`), Lizenznehmer, Ausstellungsdatum und optional ein Ablaufdatum
* Eintragen unter **Einstellungen › Lizenz** oder in `config/config.php` unter `license.whitelabel_key`
* Mit gültigem Schlüssel für die eigene Domain entfällt das Label

Den Kauf wickelt künftig eine eigene Website von Michael Gahn DESIGN ab: https://michael-gahn.de

## Module

Module erweitern den Starter um Routen, Backoffice-Seiten und Tabellen. Sie liegen unter `modules/<id>/`:

```text
modules/mein-modul/
├── module.json      Manifest (id, name, version, license free|paid, entry, migrations, menu)
├── module.php       gibt function (Router $router, App $app, ModuleManifest $module) zurück
└── migrations/      optionale SQL-Migrationen (laufen beim Aktivieren)
```

* Einspielen per **FTP oder Git** – ausführbarer Code wird bewusst nicht über das Web hochgeladen
* Aktivieren und deaktivieren unter **Einstellungen › Module**
* **Kostenlose Module** (`"license": "free"`) kann jeder Admin aktivieren
* **Kostenpflichtige Module** (`"license": "paid"`) laufen nur mit einem signierten Modul-Schlüssel für die eigene Domain
* Ein fehlerhaftes Modul wird protokolliert und übersprungen, die Website läuft weiter

Schema: `schema/module-package.schema.json` · Anleitung mit Beispiel: `templates/php-mysql-starter/modules/README.md`

Kostenpflichtige Module und Templates von Michael Gahn DESIGN werden in einem privaten Repository gepflegt und nach dem Kauf ausgeliefert.
