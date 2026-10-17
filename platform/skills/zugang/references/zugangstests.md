# Minimale Zugangstests

Nutze einen vom Anbieter dokumentierten, möglichst nebenwirkungsarmen Authentifizierungsnachweis.
Vor dienstspezifischen Implementierungen bei Bedarf die aktuelle offizielle Dokumentation lesen.
Kein allgemeines Portscanning, keine Konfigurationssuche und keine Log- oder Datenbank-Dumps.

## HTTPS-Webanmeldung und API

TLS-Zertifikat und erwarteten Host prüfen. Eine Weiterleitung zu einem anderen Origin
(Schema, Host, Port) darf keine Credentials oder Authorization-Header erhalten. SSO-Ziele vor
Übermittlung eigens prüfen; nicht anhand einer beliebigen Weiterleitung autorisieren.

Credentials direkt aus der lokalen dotenv-Datei in den HTTP-Client laden, ohne Shellargumente,
Debuglogging oder persistente Cookie-Jars. API-Token nur in vorgesehenen Headern/Mechanismen,
nicht in Query-Strings. Beim Browser nur Werkzeuge verwenden, die Secrets aus einer lokalen
Quelle ohne Klartext im Tool-Aufruf, Screenshot oder Trace einsetzen können. Falls das nicht
möglich ist, übernimmt der Nutzer die Eingabe; nur den angemeldeten Zustand danach prüfen.

Bei Formularen das echte Formular samt CSRF-/Test-Cookie-Verfahren beachten. Nie Schutzmechanismen
umgehen. HTTP 200 kann die Login-Seite sein. Erfolg benötigt beispielsweise eine authentifizierte
Session und einen geschützten Zielbereich oder eine erfolgreiche dokumentierte Identitätsabfrage.
Antwortdaten nicht vollständig ausgeben, nur Status und Authentifizierungsnachweis.

WordPress: Benutzerkennwort am vorgesehenen Login und Anwendungskennwort an einer lesenden
REST-Identitätsabfrage getrennt prüfen, sofern jeweils vorhanden und beauftragt. `/wp-json/wp/v2/users/me`
ist ein möglicher API-Test. Ein Plugin-Fehler wie `shield_block_anon_restapi` beweist eine Sperre,
nicht automatisch ein ungültiges Kennwort. Eine Startseite im Wartungsmodus sagt nichts über den
Erfolg eines Admin-Logins aus. Keine Freigabe-PINs oder Security-Admin-Entsperrungen anwenden,
wenn dadurch Sicherheitszustand oder Sichtbarkeit geändert werden könnten.

## SSH und SFTP

Vorhandene Schlüssel und verifizierte `known_hosts` nutzen. Bei einem neuen Host Fingerprint aus
einem unabhängigen vertrauenswürdigen Kanal vergleichen. `ssh-keyscan` allein beweist die Identität
nicht; den exakt geprüften Schlüssel festhalten und mit `StrictHostKeyChecking=yes` verwenden.
Keine automatische Annahme mit `accept-new` nach einer separaten, ungebundenen Prüfung.

Minimaler SSH-Test: authentifizierte Verbindung mit einem harmlosen Befehl wie `true`.
SFTP: authentifizierte Sitzung öffnen und schließen; keine Dateiliste mit Kundendaten erforderlich.
Zeitlimit und bei vorhandenen Keys `BatchMode=yes`/`IdentitiesOnly=yes` nutzen. Neue Schlüssel,
`authorized_keys`-Änderungen und dauerhaft gespeicherte SSH-Aliase nur im eigenen Zusatzauftrag.
Passwortauthentifizierung nur über einen geeigneten Client mit in-memory Secret-Übergabe;
kein `sshpass -p` oder Klartext in Prozessen/Tools.

## Datenbanken

TLS oder vorhandenen verifizierten Tunnel verwenden. Mit dem vorgesehenen Konto verbinden und
nur `SELECT 1` ausführen, anschließend schließen. Keine Tabellen-/Kundendaten lesen, keine Migrationen.
Credentials über den Treiber im Prozess, nicht über Kommandozeilenargumente. Umgebungsvariablen
sind nicht automatisch vor anderen Prozessen/Diagnoseausgaben geschützt; minimal vererben.

## FTP, FTPS, Hosting und sonstige Dienste

Bei FTPS TLS-Zertifikat und verschlüsselten Kontroll-/Datenkanal verifizieren. Unverschlüsseltes FTP
nicht für den Credential-Test verwenden; sicheren Zugang mit dem Nutzer klären. Eine reine Anmeldung
reicht; keine Testdatei hochladen oder löschen. Panel-Logins wie HTTPS-Formulare behandeln.

## Ergebnis und Abbruch

Unterscheide **erfolgreich authentifiziert**, **explizit abgelehnt**, **durch Schutz/Transport blockiert**
und **nicht getestet**. HTTP 401/403 ohne eindeutige Antwort ist kein sicherer Nachweis eines falschen
Kennworts. Bei MFA/Captcha führt der Nutzer den vorgesehenen Schritt selbst aus; keine Wiederherstellungs-
oder Einmalcodes abfragen, speichern oder umgehen. Rate-Limits/Sperren respektieren.

Eigene Sitzungen abmelden, soweit sicher möglich. Wenn Logout nicht verifiziert werden kann,
Restzustand nennen; Speicherbereinigung allein widerruft keine serverseitige Sitzung.
