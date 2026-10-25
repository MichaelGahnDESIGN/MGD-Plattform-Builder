# System- und Abhängigkeitsmanager

## Umsetzung und Rückfall

Ziel ist eine wiederverwendbare, lokale Bestands- und Sicherheitsprüfung des gesamten Software-Stacks. Paketabhängigkeiten, CMS/Erweiterungen, Laufzeiten, Container, CI-Werkzeuge und ausdrücklich hinterlegte Server-/Dienstversionen gehören zum selben Inventar. Eine fehlende Beobachtung bleibt unbekannt. Ein Manifest beschreibt keine produktive Installation.

Lieferbare Schritte: (1) begrenzte, rein lesende Projekterfassung und strukturierte Inventare; (2) aktuelle OSV-/GitHub-Funde und Supportstände; (3) lokaler HTML-/JSON-Bericht mit nachvollziehbarer Abdeckung; (4) sichere Dependabot-Konfigurationen und Sicherheits-PRs; (5) lokale Registrierung und geplante Folgeprüfungen. Belege: Parser-/Netzwerk-/Policy-Tests, synthetische Browserprüfung, echter lokaler Erstlauf, GitHub-PRs und deren Prüfungen.

Der Core bleibt projektneutral. Lokale Pfade, Repository-Zuordnungen und Berichte gehören in eine private Konfiguration außerhalb dieses öffentlichen Repositorys. Keine Secrets lesen, keine Projekt-Skripte ausführen, keine Container starten, keine produktiven Laufzeiten wechseln. Updatevorbereitung ist von Installation und Veröffentlichung getrennt. Vor risikobehafteten Installationen sind eine konkrete Zielumgebung, Sicherung, Tests und Rückfallweg erforderlich.

Rückfall: vorherige CLI-Version verwenden, neue geplante Prüfung deaktivieren und lokale Konfiguration beibehalten. Repository-Konfigurationen sind über eigene PRs/Git rücknehmbar; vorherige GitHub-Sicherheitseinstellungen werden lokal protokolliert. Keine Datenbankmigration und kein produktiver Neustart durch die Bestandsprüfung.

## Bedienung

```bash
mgd-platform resources init ./mein-projekt
mgd-platform resources register ./mein-projekt --owner example
mgd-platform resources scan ./mein-projekt --output ./private-reports
mgd-platform resources scan --config ~/.config/mgd/resources.json --output ./private-reports --online --github --host --track
mgd-platform resources github --config ~/.config/mgd/resources.json --output ./private-reports --owner example
mgd-platform resources github --config ~/.config/mgd/resources.json --output ./private-reports --owner example --apply
```

`init` wird auch von `mgd-platform init` aufgerufen. Es legt nur die lokale `.mgd/resources.json` an und überschreibt nichts. `register` nimmt einen beauftragten Projektordner in die private gemeinsame Registrierung auf. Neue Repositories unter registrierten Sammelordnern werden beim nächsten Lauf entdeckt. Der mitgelieferte Skill `mgd-plugin-builder` berücksichtigt das für neue Plugins automatisch; `mgd-system-resources` beschreibt Prüfung und Updatevorbereitung für Agenten.

Ohne `--online`, `--github` und `--host` bleibt der Scan bei Projektdateien. `--online` fragt OSV und Supportdaten ab, `--github` nutzt die vorhandene `gh`-Anmeldung und ausdrücklich erlaubte Eigentümer. `--host` ergänzt die tatsächlich laufende Node-Version, das lokale Betriebssystem und auf macOS installierte Homebrew-Versionen aus Installationsbelegen. Ein installierter Homebrew-Eintrag ist nicht zwingend die aktive Laufzeit eines Projekts.

`report.json` und `index.html` sind private Dateien mit Modus 0600. Die HTML-Datei direkt im Browser öffnen; sie braucht keinen Webserver, keine externen Skripte oder Schriften. Sie enthält Suche, Projektfilter, Komponenten, Sicherheits-/Supportfunde und Prüfabdeckung sowie helle und dunkle Darstellung. Berichte können interne Projektnamen enthalten und gehören nicht in öffentliche Artefakte.

`--track` vergleicht Funde, Versionen, Abdeckung und Quellenausfälle mit dem letzten Lauf. Zeitstempel allein erzeugen keine Änderung. Der Aufrufer erhält `changed: true|false`; eine geplante Agentenprüfung meldet nur relevante Änderungen oder nötige Entscheidungen. Zeitplanung übernimmt die vorhandene Automationsumgebung, kein versteckter Hintergrunddienst. Exitcode 2 bedeutet hohe/kritische Funde oder unvollständiges GitHub-Setup; ein Scan ohne solche Funde kann 0 liefern und trotzdem `status: incomplete` ausweisen. Deshalb stets Status und Abdeckung auswerten.

## Skills installieren

```bash
python3 scripts/install-resource-skills.py --skill mgd-system-resources --agent both
python3 scripts/install-resource-skills.py --skill mgd-plugin-builder --agent both
```

Codex nutzt `.agents/skills`, Claude Code `.claude/skills`; `--agent codex-legacy` unterstützt ältere `.codex/skills`-Installationen. Nicht doppelt installieren. `--project ./mein-projekt` begrenzt den Zielort auf ein vorhandenes Projekt. Die feste öffentliche Dateiliste kopiert keine privaten Daten. Vorhandene Ziele und verlinkte Ordner werden nicht überschrieben; vorhandene Anpassungen vor einem bewussten Update extern sichern.

## Erfasste Bereiche und Grenzen

| Bereich | Eingaben und Nachweis |
|---|---|
| JavaScript | npm-Locks, pnpm, Yarn 1 und neuere YAML-Locks; Paketbereiche aus package.json separat |
| PHP | composer.lock und composer.json, Shopware-Plugin-Metadaten |
| Python | Pipfile.lock, exakt gepinnte requirements, uv/Poetry-Lockblöcke |
| Weitere Pakete | Cargo.lock, go.sum, Gemfile.lock, pubspec.lock, NuGet packages.lock.json, gradle.lockfile |
| CMS/Erweiterungen | WordPress-Plugin-/Theme-Header und version.php, Shopware-Metadaten, JTL info.xml |
| Container/CI | Dockerfile FROM, Compose-Images, GitHub-Actions uses; deklarierter Stand, keine gestarteten Images |
| Betrieb/Services | datensparsame resources.observations.json für Laufzeiten, CMS, Plugins, Themes, OS, Datenbanken, Webserver, externe API-Versionen |
| Scannerimporte | CycloneDX JSON/PURLs und Trivy JSON SchemaVersion 2 unter trivy.json |

Die Parser lesen bekannte, begrenzte Formate und führen keine Paketmanager aus. TOML-Lockblöcke sind bewusst ein Teilparser; komplexe Varianten und nicht erkannte Formate brauchen einen Scannerimport. go.sum enthält auch historische Versionen und beweist keine aktuell aufgelöste Abhängigkeit. Range-Angaben und Tags wie `latest` bestätigen keine exakte Version. Ein Trivy-Import ist ein fremder Scan mit eigenem Zeitstempel; ein Import ohne Funde macht ungeprüfte Komponenten nicht automatisch sicher. Die Beobachtungsdatei wird ausschließlich direkt in der Projektwurzel ausgewertet; Beispiele in Unterordnern gelten nicht als Umgebungsnachweis. Beobachtungen älter als sieben Tage oder mit Datum in der Zukunft gelten nicht als aktuelle Laufzeitbestätigung.

OSV erhält ausschließlich exakte Pakete mit öffentlichem Herkunftsnachweis aus als öffentlich bestätigten GitHub-Repositories. Private Repository-/Paketnamen werden nicht an OSV übertragen. Composer- und andere Lockdateien ohne sicheren öffentlichen Herkunftsnachweis bleiben dort ungeprüft; GitHub-Alerts und ausdrücklich importierte Scannerberichte ergänzen die Abdeckung. GitHub-Meldungen gelten für den Default-Branch und nicht automatisch für jede lokale Kopie. Leere oder verweigerte API-Antworten dürfen keine Entwarnung ergeben. GitHub- und OSV-Seitennavigation wird vollständig abgearbeitet oder der Abruf als unvollständig ausgewiesen.

Entdeckung: maximal 120 Projekte, fünf Ordnerebenen; Dateisuche: acht Ebenen und 18.000 Dateien je Projekt. Vendor-, Git-, Build-, Secret- und Backup-Ordner werden ausgelassen. Symlinks werden nicht verfolgt, einzelne Eingabedateien sind auf 8 MiB begrenzt. Fehlende registrierte Ordner und abgeschnittene Suchen erscheinen als Prüflücke. Nicht beobachtete Bereiche bedeuten nicht, dass sie nicht existieren.

## Sichere Updatevorbereitung

`github` zeigt zuerst einen Plan. `--apply` prüft Eigentümer, Adminrechte, vollständigen Remote-Dateibaum und vorhandene Manifeste. Es aktiviert Sicherheitsmeldungen und Sicherheits-PRs, bestätigt den Zustand anschließend und schreibt ein privates Journal mit vorherigem Status und Basiscommit. Bestehende Dependabot-Regeln und Secret-Referenzen bleiben erhalten; Klartextzugänge werden abgelehnt. Neue Regeln prüfen täglich und begrenzen offene Versions-PRs auf drei pro Regel. YAML wird mit YAML-1.1-kompatiblen Zeichenketten erzeugt, damit GitHub beispielsweise Uhrzeiten korrekt liest.

Die Konfiguration geht über eine eigene PR. Ein vorhandener passender Setup-PR wird wiederverwendet; fremde oder alte Branches werden nicht überschrieben. Das Setup richtet keine automatischen Merges ein. `policy.js` bewertet separat, ob ein Sicherheitspatch überhaupt automatisierbar wäre: ausdrücklich freigegebenes Repository, vertrauenswürdige Quelle, gleiche Major-/Minorlinie, Lockdatei, erfolgreiche Pflichtchecks am aktuellen Commit, Sicherung und geprüfter Rückfall. Diese Bewertung ist kein Installationsdienst. Versions-PRs bleiben prüfbar; Server, Datenbanken, CMS-Installationen und kostenpflichtige Dienste werden nicht ungefragt geändert.

## Weiterentwicklung und Prüfung

`node --test test/resources.test.js` prüft Parser, private Quellen, Netzwerkgrenzen, API-Ausfälle, Pagination, Dateirechte/Symlinks, bestehende Konfigurationen, Update-Policy, XSS-Schutz und Benachrichtigungen. `npm run check` bindet diese Tests in die vorhandenen Foundation-Prüfungen ein. Die CI installiert aus der Lockdatei mit `npm ci --ignore-scripts`. Browserprüfung: Suche, Leerzustand, Fokus, 390-Pixel-Ansicht, Hell/Dunkel und axe; der Funktionslauf behält die Content-Security-Policy aktiv, nur die separate axe-Instrumentierung umgeht sie.

## Quellen

- [OSV API](https://google.github.io/osv.dev/api/)
- [OSV Batch-Abfragen und Pagination](https://google.github.io/osv.dev/post-v1-querybatch/)
- [GitHub Dependabot Alerts](https://docs.github.com/en/rest/dependabot/alerts)
- [Dependabot Optionen](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-options-reference)
- [Supportdaten von endoflife.date](https://endoflife.date/docs/api)

Supportdaten sind ein gepflegtes Drittanbieter-Aggregat, kein Herstellernachweis für einen konkreten Provider-Backport. Sie ersetzen keinen Schwachstellenscan. Alle Quellen können ausfallen; das Ergebnis ist dann unvollständig.


## Ausbau 0.9: Prüfabdeckung und Offline-Betrieb

Beauftragt: (1) nachvollziehbare Komponentenstatus und Composer-Ursprung, (2) private Offline-Prüfung mit geprüfter Scanner-Laufzeit und frischer Datenbank, (3) Action-Commit-Zuordnung und datierte Betriebs-/Importnachweise, (4) geschütztes Dashboard und zielgerichtetes Server-Deployment. Keine Paketinstallationen in überwachten Projekten und keine automatischen Update-Merges. Private Inventare bleiben außerhalb öffentlicher Artefakte.

Rückfall: alter Core-Pin und gesicherte App-/Konfigurationsdateien; neue Scanner-Laufzeit liegt separat. Phasen werden durch Parser-/Netzwerk-/Offline-Fehlertests, synthetische UI-Prüfung und reale Serverberichte belegt. Fehlende Datenbank, unbestätigte Paketidentität oder unvollständige Scannerantwort dürfen keinen erfolgreichen Komponentenstatus erzeugen.


### Bedienung seit 0.9.0

Der Ursprung von Composer-Lockpaketen wird nur bei öffentlichen Projekten über die feste Packagist-p2-Quelle ergänzt. Paketname, Version und Source-/Dist-Commit müssen übereinstimmen. Private Projekte nutzen ausschließlich bereits bestätigte öffentliche Metadaten aus dem lokalen Cache; sie lösen keine Registry-Anfragen nach ihren Paketnamen aus. Die Cache-Dateien enthalten nur bereinigte öffentliche URLs und haben dieselben Dateischutzregeln wie Berichte.

`coverageCounts` trennt checked, gaps, declared, notApplicable, development und codeReview. Ein eigener Plugin-Header ist kein Paket-Sicherheitsnachweis. `notApplicable` bedeutet eine andere nötige Prüfart, keine Sicherheitsfreiheit. Anforderungen werden nicht in installierte Versionen umgedeutet. Action-SHAs bleiben erhalten; nur ein exakter öffentlicher Release-/Commit-Nachweis ermöglicht den zusätzlichen OSV-Abgleich. Unbekannte und nicht zugeordnete Commits bleiben Prüflücken.

Offline-Scanner: `scripts/install-resource-offline.py --directory <privater-laufzeitordner> --platform linux-amd64` (beziehungsweise darwin-arm64). Nur Herstellerdateien von Version 2.7.0 mit fest hinterlegten SHA-256-Werten sind zugelassen. Die private Registrierung kann unter `offline` die Felder `binary` und `databaseDirectory` enthalten. Der Abgleich erzeugt eine minimale SBOM in einem privaten temporären Ordner und führt ausschließlich den SBOM-Extraktor aus: vollständiges `--offline`, keine Resolver, Call-Analysis, Plugins oder Projekt-Builds, eigene leere Scannerkonfiguration, bereinigte Prozessumgebung, Ausgabe- und Zeitlimit. Scanner-Datei und Datenbanken werden vor der Ausführung anhand ihrer Prüfsummen geprüft. Eine Ausgabe muss jeden angefragten Paketstand bestätigen; Exitcode 1 mit vollständigen Funden ist ein Ergebnis, sonstige Prozessfehler bleiben unbekannt.

Datenbanken liegen für diese Version unter `databaseDirectory/osv-scalibr/<Ökosystem>/all.zip` und werden über `manifest.json` mit Abrufdatum und SHA-256 gebunden. Der dokumentierte ältere Ordnername osv-scanner passt nicht zum getesteten 2.7.0-Laufzeitstand. Ein lokaler Online-Scan aktualisiert bei Bedarf die öffentlichen Datenbanken zuerst; dabei werden keine Paketnamen übertragen. Der Server nutzt dafür getrennte begrenzte Wartungsschritte im vorhandenen Zeitplan. Nach 72 Stunden ohne erfolgreichen Quellenabgleich bleibt die betroffene Offline-Abdeckung ungeprüft. Pakete ohne öffentlichen Identitätsnachweis bleiben ebenfalls offen. Ein Ausfall löst keinen Online-Ersatz für private Paketlisten aus.

Frische Fremdberichte mit gültigem Artefakt-Digest erscheinen als `reported`, nicht als eigener Scan. Veraltete/future-datierte oder nicht identifizierte Importe werden nicht für aktuelle Befunde verwendet. Die vorhandene Dateisuche und ihre Grenzen bleiben bestehen; zusätzliche Pfade sind nicht pauschal für Secrets-/Vendor-/Kundenverzeichnisse geöffnet.

OSV-Scanner ist ein eigenständiges Apache-2.0-Werkzeug aus https://github.com/google/osv-scanner. Es liegt außerhalb öffentlicher Webpfade und wird unabhängig von den überwachten Projekten gepflegt. Keine installierten Serverpakete werden aktualisiert, keine Container gestartet und keine Updates automatisch zusammengeführt.


### Betriebsabnahme 0.9.0

Die integrierte Serveransicht wurde am 24.10.2026 mit echten öffentlichen und privaten Repository-Prüfungen abgenommen. Private Berichte und Komponentenlisten bleiben ausschließlich im geschützten Projekt. Synthetische Linux-/macOS-Offline-Proben erkennen bekannte Testlücken; fehlende Datenbanken bestätigen keinen positiven Status. Die getrennte Datenbankpflege wurde mit echtem Download und anschließendem ETag-Abgleich ohne erneuten Download geprüft.

Bei einer reduzierten Serverauslieferung gehört die vollständige Importkette zum Laufzeitpaket: neben `src/resources/` insbesondere `src/lib/companions.js`, `src/lib/paths.js` und die festgelegten Lockfile-Abhängigkeiten. Vor dem Umschalten alle vier Einstiegsmodule inventory, project-audit, github und host mit der tatsächlichen Server-Node-Version importieren. Ein isolierter Offline-Test allein bestätigt diese Importkette nicht. Der vollständige Quell-/CLI-Release enthält diese Dateien bereits.

Die Abnahme umfasst Hell/Dunkel, schmale Ansicht, Tastaturbedienung, datierte Komponentenstatus, anonyme Zugriffssperren sowie die Erhaltung des täglichen Termins und der verschlüsselten Zugangskonfiguration. Eigene Codeprüfungen, unbekannte Paketquellen, Hersteller-Backports und Container ohne Artefaktnachweise bleiben offen bezeichnet.

### Weitere Nachweise (beauftragt 25.10.2026)

Öffentliche Herkunftsmetadaten werden getrennt anhand der vollständigen öffentlichen Top-500-Liste von Packagist gepflegt, ohne Eingabe privater Paketnamen. Private Abgleiche lesen diesen Cache lokal. Annotierte Action-Objekt-SHAs benötigen ihre eigene Auflösung zum Commit. Containerberichte müssen Image-Digest, Datenbank- und Prüfdatum sowie vollständige Paketerkennung bestätigen. Herstellerhinweise ersetzen keinen Backport-Nachweis. Umsetzung: gezielte Tests und Scannerprobe, bestehende Rückfalllaufzeit erhalten, versionierter PR-Weg und gesicherte Umstellung im verwaltenden Projekt.


### Betrieb seit 0.10.0

`cache-cli.js` akzeptiert intern zusätzlich `mode=public-provenance` mit einem privaten Cache-Verzeichnis. Es verarbeitet immer dieselbe öffentliche Packagist-Top-500-Liste und erhält niemals ein privates Inventar. Metadaten werden begrenzt, mit vier parallelen Abrufen, fünf Tagen Vorlauf und Fehlerabstand gepflegt. Ein belegter Repository-Umzug wird nur bei gleicher öffentlicher GitHub-Repository-ID akzeptiert. Private Herkunftsprüfungen lesen weiterhin ausschließlich den lokalen Cache.

`mode=images` pflegt ein öffentliches Basis-Image je Aufruf. Zulässig sind ausschließlich die offiziellen Docker-Hub-Basisfamilien php, wordpress, mariadb und nginx. `scripts/install-resource-images.py` installiert Trivy 0.76.1 aus Herstellerarchiven mit festgelegten Archiv- und Binär-SHA-256-Werten in einem eigenen Verzeichnis. Kein Systempaket und kein Docker-Daemon nötig. Die Laufzeitkonfiguration unter `images` enthält binary, directory und cacheDirectory; sie bleibt privat.

Zuerst wird die Image-Identität erfasst. Anschließend erfolgt ein vollständiger, ungefilterter Schwachstellenabgleich gegen genau denselben Digest; die Ausgabevorlage entfernt nur große Beschreibungs-/Konfigurationsfelder, keine Schweregrade oder unfixed-Befunde. Telemetrie, Resolver, Projektkonfiguration und Secretsuche sind abgeschaltet. Größen-, Speicher-, Zeit-, Ausgabe- und Datenbankaltergrenzen gelten. Kein Image wird gestartet. Cache-Nachweise gelten höchstens 72 Stunden, eine erneute Prüfung wird nach 24 Stunden vorbereitet. Fehlgeschlagene Versuche erhalten einen Abstand von einer Stunde.

Image-Funde werden pro Referenz gruppiert, mit Gesamtzahl und konkreten Beispielen. Sie belegen keine laufende Installation. Frische Containerbefunde können daher echte Risiken einer Entwicklungs-/Deployment-Deklaration zeigen, während die produktive Serverumgebung gesondert beurteilt wird. MariaDB-Herstellerkorrekturen nach einer beobachteten Upstream-Version werden als möglicher Befund ausgewiesen; ohne Provider-Nachweis ist eine Rückportierung weder bestätigt noch ausgeschlossen.
