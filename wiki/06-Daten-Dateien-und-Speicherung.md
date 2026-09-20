# Daten, Dateien und Speicherung

## Datenarchitektur

Daten gehören klaren Modulen und Verantwortlichkeiten. Ein Modul soll festlegen, welche Daten es besitzt, wer darauf zugreifen darf und wie lange sie benötigt werden.

## Datenklassifikation

Mindestens sinnvoll sind Kategorien wie:

* öffentlich
* intern
* vertraulich
* personenbezogen
* besonders sensibel
* sicherheitskritisch

Die Klassifikation beeinflusst Zugriff, Verschlüsselung, Logging, Export, Backup und Aufbewahrung.

## Trennung von Identität und Verhalten

Analytics oder Nutzungsdaten sollen nach Möglichkeit interne IDs verwenden, statt ständig Namen oder E-Mail-Adressen zu duplizieren.

## Dateien und Uploads

Uploads sind untrusted input. Ein sicheres Upload-System prüft Dateityp, Größe, tatsächlichen Inhalt, Speicherort und Zugriffsrechte.

Private Dateien gehören nicht in einen öffentlich erreichbaren statischen Ordner. Downloads müssen gegebenenfalls autorisiert oder über kurzlebige signierte URLs bereitgestellt werden.

## Datenbankmigrationen

Migrationen müssen versioniert, testbar und mit einer Rollback- oder Forward-Fix-Strategie versehen sein. Destruktive Schemaänderungen sollen vermieden werden, solange eine additive Migration möglich ist.

## Backups

Git ist kein Datenbank-Backup. Produktionsdaten, Uploads und personenbezogene Dumps gehören nicht in normale Git-Repositories.

## Löschung und Retention

Für personenbezogene und fachlich relevante Daten wird dokumentiert, warum sie gespeichert werden, wie lange sie benötigt werden und was bei Löschung oder Ablauf passiert.

Weiter: [[07-Datenschutz-und-Compliance]] · [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]