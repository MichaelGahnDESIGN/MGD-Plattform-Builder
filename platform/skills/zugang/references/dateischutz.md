# Dateischutz passend zum Projekt

## Git

Ergänze die vorhandene `.gitignore` ohne andere Regeln zu verändern. Für eine neue `.env` und
zum Beispiel `private/zugaenge.md` im Repository sind dies Regeln relativ zur Repository-Wurzel:

```gitignore
/.env
/.env.*
!/.env.example
/private/zugaenge.md
/private/zugaenge.md.*
```

Die Ausnahme `.env.example` nur verwenden, wenn diese Datei ausschließlich leere Platzhalter
enthält; eine bestehende Geheimnisdatei niemals pauschal freigeben. Auch tatsächlich vorhandene
Editor-Backups und Kopien berücksichtigen. Pfade korrekt escapen (`[`, `*`, `?`, `!`, `#`, Leerzeichen)
und die Wirkung testen. Keine pauschale Regel `*.md`, die die Projektdokumentation verschluckt.
Bei verschachtelten Repositories und nachfolgenden Negationsregeln die tatsächliche Wirkung prüfen.

Nutze `git ls-files --error-unmatch -- <pfad>` für den Index und `git check-ignore -v -- <pfad>`
für die Regeln. Erfolgreiches Ignore allein beweist keine Bereinigung des Index oder der Historie.
Wenn frühere Versionierung bekannt wird, eine mögliche Offenlegung melden und Rotation/Bereinigung
gezielt planen, nicht automatisch durchführen. `git add -f` kann Ignore-Regeln bewusst umgehen.

## Uploads und Build

Bevorzuge eine Veröffentlichungsliste ausschließlich für benötigte Website-Dateien. Bei vorhandener
Pipeline die wirklich genutzten Ausschlüsse ergänzen: z. B. Docker-Build-Kontext, CI-Artefakte,
FTP-Client oder rsync. Dateien mit Kennwörtern nicht in öffentliche Webverzeichnisse kopieren;
Deployment-Secrets gehören in die separate Secret-Verwaltung der Zielumgebung.

Für rsync kann ein explizit eingebundenes Filterfile senderseitiges Verbergen (`H`) und
empfängerseitigen Löschschutz (`P`) kombinieren. Beispiel, nur wenn der Projektordner Transferwurzel ist:

```text
H /.env*
P /.env*
H /private/zugaenge.md*
P /private/zugaenge.md*
```

Vorhandene Filterreihenfolge beachten: frühere Freigaben können spätere Ausschlüsse aushebeln.
Den konkreten Aufruf mit `--filter="merge .rsync-filter"` und `--dry-run --itemize-changes` prüfen.
Keine Testübertragung ans Produktivsystem erforderlich. Nicht geheimen Sentinel-Inhalt in einer
isolierten Kopie verwenden, um Regeln ohne echte Credentials zu testen.

Gibt es keine Upload-Pipeline, Grenze dokumentieren und passende Regeln vorbereiten. Keine
universelle Upload-Sperre behaupten: manuelles Kopieren, ZIP und Cloud-Sync umgehen Git-Regeln.

## Schreiben und Löschen

Rechte auf genau den betroffenen Dateien setzen, nie rekursiv auf dem Projekt oder Benutzerprofil.
Symlinks, ACLs, Eigentümer und Netzwerkvolume beachten. Originalrechte/Flags für die spätere
gezielte Rücknahme dokumentieren. Kein `sudo` oder systemweiter Berechtigungsumbau als Standard.

| Umgebung | Geeigneter Ansatz und Grenze |
|---|---|
| macOS | Nach Erstellung `chmod 400` und, sofern unterstützt, `chflags uchg` auf Quelle und dotenv-Datei. Zum bewussten Bearbeiten `chflags nouchg`, gezielt Schreibrecht geben, danach wieder sperren. Vorhandene Flags erhalten. |
| Linux | `chmod 400` schützt vor Schreiben; Löschen hängt vom Elternverzeichnis ab. `chattr +i` nur bei Unterstützung und bereits verfügbaren Rechten. Falls nicht möglich, Löschschutz ausdrücklich als nicht eingerichtet melden; keine Privilegienerhöhung erzwingen. |
| Windows | ReadOnly allein ist keine verlässliche Löschsperre oder Zugriffsbeschränkung. Geeignete dateibezogene NTFS-ACLs nur nach Prüfung von Benutzer, Vererbung und Wiederherstellung. Keine pauschalen Deny-Regeln oder Änderung des gesamten Projektordners. Nicht unterstützten Schutz ehrlich melden. |
| NAS/SMB/Cloud | Rechte und Flags können nur eingeschränkt gelten. Ausschlaggebend sind tatsächliche Tests über den verwendeten Zugriff; Serveradministratoren und andere Zugriffswege sind davon nicht zwingend erfasst. |

Schreibtest: Öffnen ausschließlich mit Schreibzugriff **ohne Truncate und ohne Schreiben** versuchen,
Handle bei Erfolg sofort schließen; Erfolg bedeutet fehlenden Schutz. Löschtest: eigene leere
Testdatei im gleichen Verzeichnis erstellen, dieselben Schutzmaßnahmen anwenden, Löschung versuchen,
danach nur diese Testdatei entsperren/aufräumen. Abweichungen und fehlende Rechte berichten.

Der Eigentümer oder Administrator kann viele Sperren aufheben. Schutz gegen versehentliche Aktionen,
Zugriffsbeschränkung, Verschlüsselung, Git-Ausschluss und Backup sind unterschiedliche Eigenschaften.
