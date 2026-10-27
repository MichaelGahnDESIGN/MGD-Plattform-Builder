# System- und Abhängigkeitsmanager

Der MGD-Plattform-Builder stellt `mgd-platform resources` bereit; Michaels lokale Installation kann den Kurzaufruf `mgd-resources` verwenden.

- `scan --config <private-registrierung.json> --online --github --host --track` erstellt einen lokalen Bericht und erkennt Änderungen.
- `register <Projektordner>` nimmt neue Projekte ohne öffentliche Pfadlisten in die lokale Registrierung auf.
- `github --config <private-registrierung.json>` zeigt die geplanten Repository-Konfigurationen. Erst `--apply` erstellt die Konfigurations-PRs und versucht, GitHub-Sicherheitsmeldungen sowie Sicherheits-PRs einzuschalten. Fehlende Rechte bleiben sichtbar.

Erfasst werden Paketökosysteme, CMS/Plugins/Themes, Laufzeiten, Container, Betriebssystem-/Dienstbeobachtungen und CI-Werkzeuge. CycloneDX-SBOM und Trivy-Berichte können über `bom.json`, `*.cdx.json` und `trivy.json` einfließen. Eigene Serverbeobachtungen liegen optional in `resources.observations.json`; sie enthalten nur Komponentenname, Version, Umgebung und Prüfzeitpunkt, niemals Zugangsdaten.

Ein Manifest ist kein Beweis einer produktiven Installation. Private oder unbestätigte Paketquellen gehen nicht an OSV. Fehlende Quellen, nicht unterstützte Formate und unbekannte Versionen bleiben ungeprüft. GitHub-PRs durchlaufen die vorhandenen Tests; es gibt keine pauschale Zusammenführungs- oder Live-Freigabe. Für Infrastrukturänderungen müssen konkrete Zielumgebung, Sicherung, Test und Rückfall vorhanden sein.

Vollständige technische Dokumentation: [MGD-Plattform-Builder – Ressourcenmanager](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/blob/main/WIKI/06-OPERATIONS/RESOURCE-MANAGER.md).
