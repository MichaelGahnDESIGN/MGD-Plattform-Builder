---
name: mgd-plugin-builder
description: Entwickelt Michaels MGD-Plugins für WordPress, Shopware, JTL-Shop und Shopify mit passenden Kartenmotiven, Icons, Katalogmetadaten, Dokumentation und plattformgerechten Updates. Bei neuen MGD-Plugins und Änderungen an deren Katalog oder Releaseweg automatisch berücksichtigen.
---

# MGD Plugin Builder

Michael soll Standards seiner Plugin-Sammlung nicht bei jeder Entwicklung erneut nennen müssen. Nutze bestehende Projektregeln, Komponenten und Releasewege. Dieser Skill erteilt keine zusätzliche Veröffentlichungs-, Server- oder Kontofreigabe.

## Sammlung und Bilder

- Pro Plugin ein eigenes Icon und ein erkennbar passendes Hintergrundmotiv. Bestehende Icon-Familien erhalten; keine austauschbaren KI-Dekorationen. Farbgebung und Motive als zusammengehörige Serie gestalten. Bilder mit dem verfügbaren Bildwerkzeug erzeugen oder belegbar geeignete vorhandene Assets verwenden; Herkunft und Eingaben dokumentieren.
- Kartenbilder lokal ausliefern, als dekorativ kennzeichnen und für Screenreader nicht doppelt vorlesen. Kategorie und Version kontrastreich auf deckendem Grund. Systemfarben respektieren. Für den aktuellen WordPress-Katalog: WebP 1080×360, höchstens 80 KB; die sieben vorhandenen Motive stehen im Maintenance-Repository unter `mg-maintenance-studio/assets/plugin-catalog/backgrounds/`.
- Bei neuen Einträgen lokale Katalogdaten, feste Bildzuordnung, Paketprüfung und zentralen Katalog von `theme.michael-gahn.de` gemeinsam vorbereiten. Vorher prüfen, welche Quelle tatsächlich erreichbar ist. Eine lokale JSON-Änderung ist kein Plattform-Deployment. Kostenpflichtige Einträge mitdenken, aber nicht automatisch installieren. Keine Kundendaten oder Tokens im Feed.
- Die öffentliche Katalogdarstellung muss von jedem aktualisierten Verbraucher konsistent geladen werden. Bei Änderungen an gemeinsamem PHP-Code Klassenkollisionen und Ladereihenfolge mit einer älteren Ausgabe testen; nötigenfalls Klassenrevision erhöhen. Dasselbe geprüfte Bundle in alle betroffenen Verbraucher übernehmen.

## Plattform und Vertrieb

- **WordPress/GitHub:** native Updateverwaltung, geprüfte Release-Assets, Nonce und Rechteprüfung; keine automatischen Aktivierungen. Icons, Hersteller-/Dokumentationslinks und Plugin-Details konsistent pflegen.
- **WordPress.org:** separaten Vertriebskandidaten verwenden. WordPress.org übernimmt Updates; keinen GitHub-Installer oder externen Empfehlungskatalog hinzufügen. readme.txt, Slug/Textdomain, GPL-Lizenzen und Verzeichnis-Assets an den aktuellen offiziellen Vorgaben prüfen. Plugin Check auf der fertigen ZIP ausführen; Fehler und begründete Warnungen offen berichten. Keine bestandene Prüfung oder Einreichung erfinden.
- **Shopware:** uploadfertige ZIP mit kompilierten Administration-/Storefront-Assets und stabiler Pluginwurzel. Updateerkennung und Installation prüfen, ohne manuellen Build im Shop. Private Repositories privat lassen; Tokens ausschließlich serverseitig und geschützt verwalten.
- **JTL-Shop:** native Pluginstruktur, Manifestversion und unterstützten Aktualisierungsweg prüfen; kein WordPress-Paket wiederverwenden.
- **Shopify:** App-/Theme-App-Extension-Deployment und Berechtigungen gemäß tatsächlicher Architektur prüfen. Keine universelle ZIP-Installation oder GitHub-In-App-Updates versprechen.

## Fertigstellung ohne Zusatzarbeit für Michael

Im Projekt eine kleine `mgd-plugin.json` mit Plattform, Vertriebskanal und Pfaden zu Icon, Kartenbild, Readme, Changelog und Dokumentation anlegen. `scripts/check_plugin_ready.py` in den vorhandenen CI-Releaseweg integrieren. Das Skript prüft Dateien und Paketgrenzen; es ersetzt keine Funktions-, Sicherheits- oder Sichtprüfung. Die kanonische Quelle dieses Skills liegt im MGD-Plattform-Builder unter `platform/skills/mgd-plugin-builder/`. Die erste Fassung im Maintenance-Repository bleibt als historischer Stand erhalten.

Readme, Wiki bzw. versioniertes Handbuch, Änderungsprotokoll und Versionsangaben synchron halten. ZIP-Inhalte und Secrets prüfen, Desktop und Mobil rendern. Gezielte vorhandene Tests nutzen. Projektübliche PR-/Release-Schritte innerhalb der vorhandenen Freigabe erledigen. Echte offene Abhängigkeiten klar nennen; keine Veröffentlichung nur wegen eines Versionsbumps vortäuschen.

Für den System- und Abhängigkeitsmanager bei entsprechendem Auftrag [references/ressourcen-updater.md](references/ressourcen-updater.md) lesen. Ein Builder-Skill ist kein dauerhafter Hintergrunddienst.

## Automatische Ressourcenpflege

Wenn `mgd-resources` installiert ist, neue MGD-Plugin-Projekte mit `mgd-resources register <Projektordner>` in die private lokale Registrierung aufnehmen. Ein bereits erfasster übergeordneter Ordner genügt. Keine Kundendaten, lokalen Pfade oder Prüfergebnisse ins öffentliche Repository kopieren. Abhängigkeiten im vorhandenen CI-Weg prüfen und passende Dependabot-Einträge vorbereiten; bestehende Regeln erhalten. Den Ressourcen-Skill `mgd-system-resources` für Quellen, Prüflücken und sichere Updates nutzen. Eine regelmäßige Prüfung wird nur behauptet, wenn die Automation tatsächlich eingerichtet ist.
