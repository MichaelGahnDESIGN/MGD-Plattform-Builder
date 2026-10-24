# MGD Codex Workstation Installer

Dieser Prompt richtet einen neuen Mac als möglichst vollständige zweite ChatGPT-Codex-Entwicklungsmaschine für MichaelGahnDESIGN ein.

> Der Prompt ist absichtlich inventarbasiert. GitHub, das MGD Project Platform System und die vorhandene lokale Codex/Claude-Konfiguration dienen als Source of Truth. Keine statische Liste darf als vollständig angenommen werden.

## Installer-Prompt

```text
Du richtest diesen Mac als vollständige zweite Entwicklungsmaschine für MichaelGahnDESIGN ein.

ZIEL

Die ChatGPT-Codex-Umgebung dieses Macs soll möglichst dieselben Skills, Tools, MCPs, Agentenanweisungen und Entwicklungsmöglichkeiten besitzen wie meine primäre Entwicklungsmaschine.

Arbeite selbstständig und führe ungefährliche Installationen tatsächlich aus. Erstelle nicht nur eine Anleitung.

Nichts Bestehendes ungeprüft löschen oder überschreiben.
Keine kostenpflichtigen Dienste aktivieren.
Keine kostenpflichtigen API-Aufrufe durchführen.
Keine Secrets veröffentlichen.
Kein sudo und keine fremden Installationsskripte ungeprüft ausführen.

Mein GitHub Account:
MichaelGahnDESIGN
https://github.com/MichaelGahnDESIGN

SOURCE OF TRUTH

Verwende drei Quellen und führe die Ergebnisse zusammen:

1. Mein kompletter GitHub Account MichaelGahnDESIGN.
2. Das MGD Project Platform System:
   https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System
3. Die auf diesem Mac bereits vorhandene Codex-/Claude-Konfiguration.

Entferne Duplikate und dokumentiere Konflikte.

PHASE 1 — BESTANDSAUFNAHME

Prüfe insbesondere:

~/.codex/
~/.codex/skills/
~/.claude/
AGENTS.md
CLAUDE.md
MCP-Konfigurationen
Git
GitHub CLI
Node.js / npm
Python
PHP
Composer
Godot
Docker, falls vorhanden
weitere von Skills benötigte Tools

Nichts löschen. Dokumentiere kurz den Ausgangszustand.

PHASE 2 — ALLE EIGENEN SKILLS UND TOOLS FINDEN

Durchsuche meinen GitHub Account nach von mir erstellten Skills, Codex Skills, Claude Skills, Agent Skills, AI Skills, MCPs, Development Tools, Foundations und wiederverwendbaren Agentenwerkzeugen.

Erkenne sie unter anderem anhand von:

SKILL.md
AGENTS.md
CLAUDE.md
.codex/
.claude/
skills/
Repository-Name
Repository-Beschreibung
README.md

Namen wie MGD_*_SKILL, MGD-*-Skill, MGD_*Skill und MGD_*_TOOL sind besonders relevant.

Nicht jedes Repository ist ein Skill. Normale Websites, Spiele, Kundenprojekte, WordPress Plugins und Shopware Plugins dürfen nicht einfach als Skills installiert werden.

PHASE 3 — MGD PROJECT PLATFORM SYSTEM

Analysiere zusätzlich das Repository:

https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System

Lies insbesondere README.md, AGENTS.md, CLAUDE.md, WIKI/, templates/, platform/ sowie Dateien, die weitere MGD Repositories referenzieren.

Mindestens folgende dort bekannte Komponenten prüfen:

MGD_DEV_SKILL
MGD_Todo_SKILL
MGD_Backup_SKILL
MGD_Autopilot_SKILL
MGD_ProjectClean_SKILL
MGD_AI-PlayTest_SKILL
MGD_AI-Thread
MGD_Platform-Builder_TOOL
Projekt-Plattform-System

Zusätzlich mindestens suchen nach:

MGD_BugReport_SKILL
MGD_Prozesse_SKILL
MGD_AI-Project-Updater_SKILL
MGD-App-Updater-Skill
MGD_Blogpost-Skill

Diese Liste ist NICHT vollständig. GitHub ist die aktuelle Source of Truth.

PHASE 4 — EXTERNE GRUNDAUSSTATTUNG

Zusätzlich gehören zur gewünschten Codex-Workstation ausdrücklich:

Graphiphy
frontend-design
Godot Skills
Godot MCP beziehungsweise eine sinnvolle Godot-Agentenintegration

Ermittle bei externen Komponenten die korrekte vertrauenswürdige Quelle. Installiere niemals anhand eines ähnlichen Namens irgendein Repository.

Suche außerdem nach externen Skills, die von meinen MGD Skills, AGENTS.md, CLAUDE.md, Projektvorlagen oder dem Projekt-Plattform-System referenziert werden.

PHASE 5 — WORDPRESS UND WEB

WordPress-Entwicklung ist ein zentraler Arbeitsbereich.

Benötigte Unterstützung:

WordPress Core
Plugin Development
Theme Development
REST API
Hooks, Actions und Filters
Shortcodes
Custom Post Types und Taxonomies
WP-Cron
WP-CLI
WordPress Security
WordPress Coding Standards
Gutenberg / Block Editor
WooCommerce
Internationalisierung
Performance und Caching
Plugin Update-Systeme
PHP
MariaDB/MySQL
HTML
CSS
JavaScript

Prüfe zuerst eigene MGD Skills und bevorzuge sie vor redundanten externen Skills.

PHASE 6 — DIVI 5

Divi 5 ist besonders wichtig.

Benötigte aktuelle Unterstützung:

Divi 5
Divi 5 Developer API
Module Development
Extension Development
Design Variables
Presets
Responsive Systeme
Breakpoints
Theme Builder
Dynamic Content
Interactions
Conditions
CSS und JavaScript
WordPress Integration
Performance
Custom Modules

Divi 5 nicht mit veralteten Divi-4-Tutorials oder APIs vermischen. Aktuelle offizielle Dokumentation bevorzugen.

PHASE 7 — ELEMENTOR UND WOOCOMMERCE

Elementor:
Elementor Pro, Widgets, Custom Widgets, Containers, Responsive Design, Dynamic Content, Theme Builder, Forms, Hooks, CSS, JavaScript, WordPress Integration und Performance.

WooCommerce:
Produkte, Variationen, Cart, Checkout, Orders, Hooks, REST API, Custom Fields, Templates, Payments, Shipping, E-Mails, Plugin Development, Performance und Security.

PHASE 8 — SHOPWARE 6

Unterstützung für aktuelle Shopware-6-Entwicklung:

Plugin Development
App System
Symfony
PHP
Twig
Administration
Storefront
DAL
Entities und Repositories
Events und Subscribers
Services und Dependency Injection
Migrations
Scheduled Tasks
CLI
API
Sales Channels
Products und Variants
Custom Fields
Rule Builder
Cart und Checkout
Themes
SCSS
JavaScript
Administration Extensions
Update-Kompatibilität

PHASE 9 — JTL SHOP 5

Unterstützung für JTL-Shop 5:

Plugin Development
Templates
Smarty
PHP
Hooks und Events
Frontend
CSS
JavaScript
Datenbank
Updates
Plugin-Struktur
Child Templates
Performance
Kompatibilität

Keine JTL-Shop-4-Anleitungen als aktuelle API behandeln.

PHASE 10 — SHOPIFY

Unterstützung für:

Shopify
Liquid
Themes
Sections
Blocks
Metafields
Metaobjects
Shopify CLI
Admin API
Storefront API
Webhooks
Apps
Theme Development
JavaScript
CSS
Localization
Performance

PHASE 11 — PHP / SYMFONY / MARIADB

Vorbereiten für:

aktuelles PHP
Composer
Symfony
MariaDB/MySQL
PDO
REST/JSON APIs
Authentication
Authorization
RBAC
Capabilities
Sessions
CSRF
CORS
Rate Limiting
Migrations
Queues/Jobs
Cron
Security
Logging
Testing

Das Projekt-Plattform-System ist hierfür eine wichtige Foundation.

PHASE 12 — GODOT / GAME DEVELOPMENT

Ich entwickle mehrere Spiele mit Godot 4.x und GDScript.

Benötigt:

Godot 4
GDScript
2D und 3D
Scenes
Nodes
Resources
Signals
Navigation
Animation und AnimationTree
Shaders
Particles
UI
Input
Audio
Networking
Savegames
Localization
Performance
Profiling
Debugging
Testing
macOS Export
Windows Export
Mobile Export

Prüfe einen hochwertigen allgemeinen Godot Skill und einen stabilen sinnvollen Godot MCP. Vermeide mehrere widersprüchliche Godot Skills.

PHASE 13 — KOMPATIBILITÄT

Klassifiziere jeden Fund:

A = direkt Codex-kompatibel
B = mit kleiner Anpassung Codex-kompatibel
C = primär Claude, aber sinnvoll portierbar
D = Tool/Foundation, kein klassischer Skill
E = nicht sinnvoll für Codex

Verändere meine GitHub-Repositories nicht automatisch. Lokale Kompatibilitätsanpassungen dürfen dokumentiert vorgenommen werden.

PHASE 14 — INSTALLATIONSSTRUKTUR

Vermeide unnötige Kopien.

Bevorzugtes Modell:

GitHub
-> zentrale lokale Skill-Repositories
-> Symlinks oder geeignete Codex-Integration
-> ~/.codex/skills/

Die Skills müssen lokal funktionieren und dürfen keine NAS-Laufzeitabhängigkeit besitzen.

Bei überlappenden Funktionen gilt:

1. eigener aktueller MGD Skill
2. MGD Project Platform System / MGD Foundation
3. bereits bewährter externer Skill
4. neuer externer Skill

Keine unnötigen widersprüchlichen Duplikate installieren.

PHASE 15 — SICHERHEIT

Vor fremden Installationsskripten Quellcode prüfen.

Besonders prüfen:

sudo
curl | bash
wget | bash
rm -rf
LaunchAgents
Daemons
Cronjobs
SSH-Konfiguration
Git Credentials
API Keys
Tokens
Telemetry
externe Uploads
Änderungen außerhalb des Benutzerverzeichnisses

Bei erhöhten Rechten, Kosten, Secrets oder sicherheitskritischen Eingriffen stoppen und mich fragen.

PHASE 16 — REGISTRY

Erstelle:

~/.codex/MGD-SKILL-REGISTRY.md

Für jeden Eintrag:

Name
Zweck
SOURCE: MGD-GITHUB / MGD-PLATFORM-SYSTEM / EXISTING-MAC / EXTERNAL
TYPE: SKILL / MCP / TOOL / FOUNDATION / DEPENDENCY
Repository
lokaler Pfad
Version/Commit
Codex-Kompatibilität
Claude-Kompatibilität
Update-Methode
Abhängigkeiten
Status
Konflikte
Notizen

PHASE 17 — UPDATE-SYSTEM

Erstelle einen sicheren zentralen Update-Mechanismus.

Ein einzelner Befehl soll registrierte Skills prüfen, neue Commits erkennen, lokale Änderungen melden und sichere Updates ermöglichen.

Keine lokalen Änderungen überschreiben. Bei Dirty Working Tree stoppen. Keine normalen Projekt-Repositories aktualisieren.

PHASE 18 — VALIDIERUNG

Prüfe nach Installation:

alle registrierten Skills
kaputte Symlinks
fehlende SKILL.md
Duplikate
Namenskonflikte
Abhängigkeiten
Codex-Erkennung
Graphiphy
WordPress
Divi 5
Elementor
WooCommerce
Shopware 6
JTL Shop 5
Shopify
Godot
Godot MCP
vollständiges MGD Inventar

PHASE 19 — ABSCHLUSSBERICHT

Berichte kompakt:

Anzahl eigener MGD Skills
MGD Tools
externe Skills
MCPs
Foundations
bereits vorhandene Komponenten
aktualisierte Komponenten
nicht kompatible Komponenten
optionale nicht installierte Komponenten
Duplikate/Konflikte
fehlende Abhängigkeiten
Registry-Pfad
Update-Befehl

WICHTIG:

Führe die Einrichtung tatsächlich aus. Nicht nur erklären.

Arbeite alle ungefährlichen Schritte selbstständig ab.

Frage nur bei Kosten, Secrets/Zugangsdaten, sudo/Systemeingriffen, irreversiblen Änderungen, echten Sicherheitsrisiken oder wesentlichen Entscheidungen.
```

## Pflege

Wenn neue MGD Skills entstehen, muss dieser Installer nicht zwingend manuell um jeden Namen ergänzt werden. Seine Inventarisierungslogik soll neue passende Repositories über GitHub und das Project Platform System erkennen.

Die explizite Liste dient als Mindestbestand und als Regression Check.
