# MGD Shopify App Starter

Startpunkt für eine serverseitige Shopify-App. Das Gerüst enthält bewusst keinen echten Client-ID-, Secret- oder Shop-Wert. `shopify.app.example.toml` bleibt eine Vorlage und darf nicht mit echten Zugangsdaten eingecheckt werden.

Die Theme-Extension ist nur Darstellung. Kundenzuordnung, Lesestatus, Webhooks und Administration gehören in die App. Vor dem Produktivstart OAuth, HMAC-Prüfung, Datenschutzwebhooks, Datenminimierung, Löschfristen und die gemeinsame App-/Theme-Kompatibilität aus `release-manifest.json` testen.
