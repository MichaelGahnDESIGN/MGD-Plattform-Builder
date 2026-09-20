# Betrieb, Staging, Deployment, Backup und Monitoring

Ein System ist erst dann professionell betreibbar, wenn nicht nur der Code, sondern auch Deployment, Wiederherstellung und Beobachtbarkeit dokumentiert sind.

## Umgebungen

Empfohlen ist mindestens:

```text
Local / Development
        ↓
Staging
        ↓
Production
```

Produktivdaten sollen nicht ungeprüft in Staging kopiert werden. Für Tests werden möglichst synthetische, anonymisierte oder minimierte Daten genutzt.

## Deployment Flow

```text
Änderung
→ Tests
→ Review
→ Backup- und Rollback-Prüfung
→ Staging
→ Freigabe
→ Production Deployment
→ Smoke Tests
→ Monitoring
→ Dokumentation
```

## High-Risk Deployments

Strengere Gates gelten für Datenbankmigrationen, Auth- und Permission-Änderungen, Zahlungen, Datenschutz- und Exportfunktionen sowie größere Datentransformationen.

## Rollback

Vor dem Deployment muss klar sein, was Rollback konkret bedeutet. Bei Datenbankänderungen ist ein einfacher Code-Rollback oft nicht ausreichend. In solchen Fällen kann ein kontrollierter Forward Fix sicherer sein.

## Backup

Ein Backup ist erst dann vertrauenswürdig, wenn ein Restore erfolgreich getestet wurde.

Zu sichern sind je nach Projekt:

* Datenbank
* nicht rekonstruierbare Uploads
* relevante Konfiguration
* rechtliche oder dokumentarische Versionen
* kritisches Recovery-Material über getrennte sichere Prozesse

Produktionsdumps gehören nicht in Git.

## Restore Test

Ein Restore Test erfolgt isoliert:

1. Datenbank und Dateien wiederherstellen.
2. Integrität prüfen.
3. Migrationen anwenden.
4. Smoke Tests ausführen.
5. Ergebnis dokumentieren.
6. Testumgebung bereinigen.

## Ransomware Resilience

Ein kompromittierter Produktionszugang darf nicht alle Sicherungen löschen können. Getrennte Credentials sowie mindestens eine getrennte, versionierte oder offline gehaltene Kopie sind empfehlenswert.

## Monitoring

Monitoring sollte technische Fehler, Jobs, Queue-Zustände, Verfügbarkeit, Security Events und für das Produkt kritische Geschäftsprozesse sichtbar machen.

## Release Gates

Ein Release Gate ist eine Bedingung, die vor einem Release erfüllt sein muss. Beispiele sind Restore Test, Permission Review, Migration Test, Security Check oder Consumer Flow Review.

Weiter: [[14-Governance-Dokumentation-und-Releases]]