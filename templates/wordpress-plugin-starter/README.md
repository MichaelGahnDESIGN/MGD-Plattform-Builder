# MGD WordPress Plugin Starter

Sicheres Startgerüst für ein klassisches WordPress-Plugin. Es legt **keine** eigenen Daten an und ruft keine externen Dienste auf.

1. Ordner, Plugin-Header und Textdomain umbenennen.
2. Erst Datenfluss, Rollen, Löschkonzept und Einstellungen in `docs/` festlegen.
3. Für schreibende Aktionen WordPress-Nonces **und** `current_user_can()` verwenden.
4. Plugin als ZIP installieren und in einer Testinstanz aktivieren.

## Enthalten

- minimale, nur für Administratoren sichtbare Einstellungsseite
- lokale Light-/Dark-Design-Tokens
- Versions- und Release-Notiz-Dateien
- Sicherheits- und Lizenzregeln für Agenten

## Nicht enthalten

Keine automatischen Updates, Telemetrie, externen Fonts, Tracking- oder Datenbanktabellen. Diese Punkte brauchen eine konkrete, dokumentierte Entscheidung.

## Prüfungen vor einer Veröffentlichung

`php -l mgd-wordpress-plugin.php`, WordPress-Aktivierung in einer Testinstanz und ein manueller Rechte-/Nonce-Test.
