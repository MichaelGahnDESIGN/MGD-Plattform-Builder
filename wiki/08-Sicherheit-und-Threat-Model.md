# Sicherheit und Threat Model

Sicherheit ist ein Bestandteil der Architektur und des Betriebs. Sie wird nicht erst kurz vor dem Release ergänzt.

## Schutzziele

Geschützt werden insbesondere Konten, Sessions, private Daten, privilegierte Aktionen, Inhaltsintegrität, Zahlungen und Entitlements, Secrets, Backups sowie die Deployment-Kette.

## Baseline Controls

Die Foundation erwartet je nach System unter anderem:

* HTTPS
* sichere Session-Cookies
* Session Rotation
* serverseitige Autorisierung
* CSRF-Schutz, wo relevant
* parametrisierte Datenbankzugriffe
* korrektes Output Encoding und Sanitizing
* Rate Limiting
* MFA für privilegierte Rollen
* getrennte Secrets
* Audit Logging
* Security Event Logging
* getestete Backups und Restores
* Incident Response

## Fail Closed

Kann eine Berechtigung nicht verlässlich geprüft werden, darf eine privilegierte Aktion nicht trotzdem ausgeführt werden.

## Threat Actors

Das Threat Model betrachtet mindestens:

**Anonyme Angreifer**, etwa für Brute Force, Injection, Enumeration, Scraping oder Denial of Service.

**Böswillige oder kompromittierte Nutzer**, etwa für horizontale Zugriffe, ID-Manipulation, Spam oder Missbrauch von Geschäftslogik.

**Kompromittierte privilegierte Konten**, die sensible Datensätze lesen oder Berechtigungen verändern könnten.

**Kompromittierte Abhängigkeiten oder Hosts**, die Code, Secrets oder Daten offenlegen könnten.

**Manipulierte AI-Agenten**, die durch Prompt Injection oder untrusted content zu unzulässigen Aktionen verleitet werden könnten.

## Typische Angriffsklassen

Account Takeover, Broken Access Control beziehungsweise IDOR, Injection, XSS, CSRF, unsichere Uploads, SSRF, Path Traversal, Replay-Angriffe, Webhook Spoofing, Token Leakage, Business Logic Abuse, Backup-Zerstörung und CI/CD-Kompromittierung.

## Security Invariants

Ein Projekt soll Aussagen definieren, die immer wahr bleiben müssen. Beispiele:

1. Private Ressourcen sind nicht über alternative Endpoints erreichbar.
2. Eine geänderte ID darf keine fremden Daten offenlegen.
3. Entzogene Privilegien wirken zeitnah.
4. Privilegierte Aktionen sind auditierbar.
5. Payment-Status stammt aus verifizierten serverseitigen Quellen.
6. Secrets gelangen nicht in Client Bundles oder Git History.

## Logging

Passwörter, vollständige Tokens, Session Cookies, Payment Secrets oder komplette private Nachrichteninhalte sollen nicht standardmäßig geloggt werden.

## Security Evidence

Ein reifer Betrieb kann zeigen, wann der letzte Restore-Test, Permission Review, Dependency Review und Security Review stattgefunden hat und wie Incidents aufgearbeitet wurden.

Weiter: [[09-AI-Agenten-und-Automation]] · [[11-Betrieb-Staging-Deployment-Backup-und-Monitoring]]