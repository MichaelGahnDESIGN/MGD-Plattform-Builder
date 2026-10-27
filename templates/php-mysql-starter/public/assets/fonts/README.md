# Schriften (selbst gehostet)

Schriften müssen **lokal** in diesem Ordner liegen. Keine Google-Fonts-CDN oder andere externe Schriftquellen
(Datenschutz: sonst wird die IP-Adresse der Besucher an Dritte übertragen; außerdem blockiert die CSP externe Fonts).

1. Schrift mit passender Lizenz (z. B. SIL Open Font License) als `.woff2` herunterladen.
2. Datei hierher hochladen, z. B. `public/assets/fonts/inter-variable.woff2`.
3. Backoffice → **Einstellungen → Design**:
   - „Schriftdatei (lokal)“: `/assets/fonts/inter-variable.woff2`
   - „Schriftfamilie“: `"Site Font", system-ui, sans-serif`
4. Schrift unter **Credits → Komponenten** (Kategorie „Schrift“) mit Lizenz eintragen.
