# MGD Codex Workstation Installer

Ein allgemeiner, profilbasierter Installer-Prompt für ChatGPT Codex Workstations.

Er ist ausdrücklich **nicht** an ein einzelnes Spiel, einen einzelnen Entwickler oder MichaelGahnDESIGN gebunden. Dritte können eigene Quellen und Presets verwenden.

## Grundidee

```text
GitHub / lokale Quellen / Foundations
        ↓
Inventarisierung und Sicherheitsprüfung
        ↓
Skill Registry
        ↓
gewählte Presets
        ↓
lokale Skill-Repositories
        ↓
Codex / MCP Integration
```

## Installer-Prompt

```text
Du richtest diesen Computer als vollständige ChatGPT-Codex-Entwicklungsworkstation ein.

ZIEL

Ermittle zuerst die vorhandene Umgebung und installiere danach nur die Skills, Tools, MCPs und Abhängigkeiten, die zu den gewählten Profilen passen.

Der Installer muss für beliebige Nutzer funktionieren. Verwende keine personenbezogenen Annahmen.

Arbeite selbstständig bei ungefährlichen Schritten. Nichts ungeprüft löschen oder überschreiben. Keine kostenpflichtigen Dienste aktivieren. Keine Secrets veröffentlichen. Kein sudo und keine fremden Installationsskripte ungeprüft ausführen.

PHASE 1 — BESTANDSAUFNAHME

Prüfe ~/.codex, ~/.codex/skills, ~/.claude, AGENTS.md, CLAUDE.md, MCP-Konfigurationen sowie vorhandene Git-, GitHub-, Node-, Python-, PHP-, Composer-, Flutter-, Dart- und Godot-Installationen.

Erstelle ein Inventar. Nichts löschen.

PHASE 2 — QUELLEN

Ermittle Skills und Tools aus den konfigurierten Quellen:

1. angegebene GitHub Accounts und Organisationen
2. angegebene Foundation-Repositories
3. vorhandene lokale Codex-/Claude-Konfiguration
4. explizit angegebene externe Skills und MCPs

Erkenne Skills anhand von SKILL.md, AGENTS.md, CLAUDE.md, .codex/, .claude/, skills/, README und Repository-Metadaten.

Normale Anwendungs-Repositories sind nicht automatisch Skills.

PHASE 3 — PRESETS

Unterstütze kombinierbare Presets:

general
web-development
wordpress
ecommerce
php-backend
ui-ux
flutter
godot
game-development
full-stack
custom
mgd-workstation

Ein Preset ist eine Auswahl und kein Zwang, alle bekannten Tools zu installieren.

GENERAL:
Git/GitHub, allgemeine Agenten- und Sicherheitsgrundlagen.

UI-UX:
ui-ux-pro-max ausdrücklich prüfen.
frontend-design ausdrücklich prüfen.
Graphiphy ausdrücklich prüfen.
Weitere bereits konfigurierte UI/UX-, Design-System- und Frontend-Skills inventarisieren.

FLUTTER:
Flutter Skills ausdrücklich prüfen.
Dart Skills ausdrücklich prüfen.
Flutter UI, Architecture, Testing, macOS, Windows, iOS/iPadOS und Android Skills prüfen.
Flame Skills prüfen, sofern vorhanden oder gewünscht.
flutter_rust_bridge-bezogene Skills/Referenzen prüfen, sofern vorhanden.
Mehrere spezialisierte Flutter Skills einzeln in der Registry führen und nicht pauschal als "Flutter" zusammenfassen.

GODOT:
Godot 4.x, GDScript, 2D, 3D, Scenes, Nodes, Resources, Signals, Navigation, Animation, Shaders, Particles, UI, Input, Audio, Networking, Savegames, Localization, Performance, Debugging, Testing und Export.
Einen sinnvollen Godot MCP prüfen.
Keine Sammlung widersprüchlicher Godot Skills installieren.

WORDPRESS:
WordPress Core, Plugin/Theme Development, REST API, Hooks, Gutenberg, WP-CLI, Security, Coding Standards, Performance und Internationalisierung.
Divi 5 ausdrücklich getrennt von veralteten Divi-4-APIs behandeln.
Elementor/Elementor Pro und WooCommerce berücksichtigen.

ECOMMERCE:
WooCommerce, Shopware 6, JTL Shop 5 und Shopify sowie zugehörige aktuelle APIs und Entwicklungswerkzeuge.

PHP-BACKEND:
PHP, Composer, Symfony, MariaDB/MySQL, PDO, REST/JSON APIs, Authentication, Authorization, RBAC/Capabilities, Sessions, CSRF, CORS, Rate Limiting, Migrations, Jobs, Cron, Security, Logging und Testing.

GAME-DEVELOPMENT:
Godot und/oder Flutter/Flame nur entsprechend der ausgewählten Projekttechnologie. Keine Engine als globale Pflicht erzwingen.

FULL-STACK:
Kombiniert passende Web-, Backend-, UI/UX- und Datenbankgruppen.

CUSTOM:
Nur explizit konfigurierte Gruppen und Komponenten.

PHASE 4 — KOMPATIBILITÄT

Klassifiziere jeden Fund:

A = direkt Codex-kompatibel
B = mit kleiner lokaler Anpassung kompatibel
C = primär Claude, aber sinnvoll portierbar
D = Tool/Foundation
E = nicht sinnvoll

Verändere fremde oder eigene Remote-Repositories nicht automatisch.

PHASE 5 — SICHERHEIT

Vor fremden Installationsskripten prüfen: sudo, curl|bash, wget|bash, rm -rf, LaunchAgents, Daemons, Cronjobs, SSH-Konfiguration, Credentials, API Keys, Tokens, Telemetry, externe Uploads und Änderungen außerhalb des Benutzerverzeichnisses.

Bei Kosten, Secrets, erhöhten Rechten, irreversiblen Änderungen oder echten Sicherheitsrisiken stoppen und menschliche Freigabe einholen.

PHASE 6 — INSTALLATION

Bevorzuge eine zentrale lokale Quelle je Repository und eine geeignete Codex-Integration beziehungsweise Symlinks statt unnötiger Kopien.

Keine NAS- oder Netzlaufwerk-Laufzeitabhängigkeit als Standard.

Bei bereits vorhandenen Repositories Remote, Commit und lokale Änderungen prüfen. Dirty Working Trees niemals ungefragt überschreiben.

PHASE 7 — REGISTRY

Erstelle ~/.codex/MGD-SKILL-REGISTRY.md.

Für jeden Eintrag dokumentieren:

Name
Zweck
Source
Type: SKILL / MCP / TOOL / FOUNDATION / DEPENDENCY
Repository
lokaler Pfad
Version/Commit
Codex-Kompatibilität
Claude-Kompatibilität
Preset/Skill-Gruppe
Update-Methode
Abhängigkeiten
Status
Konflikte
Notizen

PHASE 8 — UPDATE-MECHANISMUS

Erstelle einen sicheren Update-Mechanismus für registrierte Komponenten.

Er soll Updates erkennen und anzeigen, aber keine lokalen Änderungen überschreiben. Normale Projekt-Repositories sind ausgeschlossen.

PHASE 9 — VALIDIERUNG

Prüfe Skills, Symlinks, SKILL.md, Namenskonflikte, Duplikate, Abhängigkeiten und Codex-Erkennung.

Validiere zusätzlich alle aktivierten Presets einzeln.

Für ui-ux beispielsweise explizit:
ui-ux-pro-max: Status
frontend-design: Status
Graphiphy: Status

Für flutter:
Liste aller gefundenen/installierten Flutter-, Dart-, Flame- und verwandten Skills.

PHASE 10 — ABSCHLUSS

Berichte installierte eigene und externe Skills, MCPs, Tools, Foundations, Updates, inkompatible Komponenten, Konflikte, fehlende Abhängigkeiten, Registry-Pfad und Update-Befehl.

Führe die ungefährlichen Arbeiten tatsächlich aus und liefere nicht nur eine Anleitung.
```

## MGD Preset

Das optionale `mgd-workstation` Preset bildet die persönliche MGD-Entwicklungsumgebung ab. Es ist **nicht** der allgemeine Standard.

Empfohlene Gruppen:

```yaml
profile:
  name: mgd-workstation

skill_groups:
  general: true
  ui_ux: true
  web_development: true
  wordpress: true
  ecommerce: true
  php_backend: true
  flutter: true
  godot: true
  game_development: true

sources:
  github_accounts:
    - MichaelGahnDESIGN

  foundations:
    - MichaelGahnDESIGN/Projekt-Plattform-System

external_required:
  - ui-ux-pro-max
  - Graphiphy
  - frontend-design
```

Für dieses Preset soll der GitHub Account dynamisch nach allen MGD Skills, Tools, MCPs und Foundations inventarisiert werden. Eine statische Liste ist nur Mindestbestand.

Bekannte MGD Komponenten umfassen unter anderem MGD_DEV_SKILL, MGD_Todo_SKILL, MGD_Backup_SKILL, MGD_Autopilot_SKILL, MGD_ProjectClean_SKILL, MGD_AI-PlayTest_SKILL, MGD_AI-Thread und MGD_Platform-Builder_TOOL. Weitere aktuelle Repositories müssen automatisch erkannt werden.

## Prinzip für Dritte

Andere Nutzer definieren eigene Profile, GitHub-Quellen und Skill-Gruppen. Das System darf ihnen weder MGD-spezifische Skills noch WordPress, Flutter oder Godot aufzwingen.
