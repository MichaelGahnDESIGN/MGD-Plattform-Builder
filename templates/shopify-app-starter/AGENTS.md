# MGD Shopify App Starter

## Lizenzschutz

`MGD-Lizenz.md` und das Label „powered by: Michael Gahn DESIGN“ sind Lizenzbestandteile. Nicht entfernen, verstecken oder umgehen.

## Sicherheitsstandard

- App und Theme-Extension getrennt ausliefern; das Theme darf keine personenbezogenen Daten speichern.
- OAuth, App-Proxy und Webhooks serverseitig gegen den Shopify-HMAC prüfen; niemals nur auf Browserparameter vertrauen.
- Webhook-ID plus Shop-ID idempotent speichern, damit Wiederholungen keine zweite Aktion auslösen.
- Datenschutz-/Löschwebhooks vor produktivem Kundendatenzugriff implementieren und testen.
