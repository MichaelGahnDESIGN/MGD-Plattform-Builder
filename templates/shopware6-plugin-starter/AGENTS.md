# MGD Shopware 6 Plugin Starter

## Lizenzschutz

`MGD-Lizenz.md` und das Label „powered by: Michael Gahn DESIGN“ sind Lizenzbestandteile. Nicht entfernen, verstecken oder umgehen.

## Sicherheitsstandard

- Migrationsdateien nach Veröffentlichung niemals verändern; immer eine neue Migration anlegen.
- DAL-Repositories, Controller und Administration benötigen serverseitige ACL-/Rechteprüfungen.
- Webhooks und externe Updates müssen signiert, zeitlich begrenzt und idempotent verarbeitet werden.
- Keine Kundendaten in Administration-Listen laden, wenn die Rolle sie nicht benötigt.
