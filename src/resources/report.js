import fs from "node:fs/promises";
import crypto from "node:crypto";

export async function renderReport(report) {
  const css = await fs.readFile(
    new URL("./report.css", import.meta.url),
    "utf8",
  );
  const script = await fs.readFile(
    new URL("./report-browser.js", import.meta.url),
    "utf8",
  );
  const hash = (text) =>
    crypto.createHash("sha256").update(text).digest("base64");
  const data = JSON.stringify(report)
    .replace(/</g, "\\u003c")
    .replace(/\u2028/g, "\\u2028")
    .replace(/\u2029/g, "\\u2029");
  return `<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'sha256-${hash(script)}'; style-src 'sha256-${hash(css)}'; base-uri 'none'; form-action 'none'"><title>MGD · System- und Abhängigkeitsmanager</title><style>${css}</style><body><a class="skip" href="#results">Zum Prüfbericht</a><header><span class="brand">MGD</span><div><p class="eyebrow">TECHNIK IM BLICK</p><h1>System- und Abhängigkeitsmanager</h1></div><span id="date"></span></header><main><section class="intro"><div><h2>Dein technischer Bestand.</h2><p>Abhängigkeiten, Support und bekannte Sicherheitsmeldungen – mit sichtbaren Prüflücken.</p></div><span id="overall" class="badge"></span></section><section id="stats" class="stats" aria-label="Prüfübersicht"></section><p class="notice">Dieser Bericht verändert keine Projekte oder Server. Angaben aus Manifesten bestätigen keine produktive Installation. „Keine bekannten Funde“ gilt ausschließlich für die angegebene Quelle und geprüfte Version.</p><section class="controls" aria-label="Ergebnisse filtern"><label>Suche<input type="search" id="search" placeholder="Paket, Projekt oder Meldung"></label><label>Projekt<select id="project"><option value="">Alle Projekte</option></select></label><label>Ansicht<select id="view"><option value="issues">Sicherheits- und Supportfunde</option><option value="components">Alle Komponenten</option><option value="coverage">Prüfabdeckung</option></select></label><label>Darstellung<select id="theme"><option value="system">System</option><option value="light">Hell</option><option value="dark">Dunkel</option></select></label></section><section id="results" tabindex="-1"><p id="count" role="status" aria-live="polite"></p><div id="rows"></div></section><section class="legend"><h2>Was die Zustände bedeuten</h2><p><strong>Nicht geprüft:</strong> Version, Quelle oder Berechtigung fehlt. <strong>Support geprüft:</strong> Lebenszyklus abgeglichen; kein Nachweis über Sicherheitslücken oder Provider-Backports. <strong>Quellcode:</strong> deklarierter Stand; kann von laufenden Umgebungen abweichen.</p></section></main><footer><span>Lokaler Bericht · keine externen Skripte oder Schriften</span><a href="https://michael-gahn.de" target="_blank" rel="noopener noreferrer">powered by: Michael Gahn DESIGN <span class="sr">(neuer Tab)</span></a></footer><script type="application/json" id="data">${data}</script><script>${script}</script></body></html>`;
}
