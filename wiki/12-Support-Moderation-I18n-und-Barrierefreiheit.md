# Support, Moderation, Internationalisierung und Barrierefreiheit

Diese Bereiche werden oft erst spät ergänzt, obwohl sie tief in Datenmodell, Rechte und UX eingreifen können.

## Support

Support benötigt ein klar begrenztes Berechtigungsmodell. Ein Support-Fall kann Accountstatus und relevante Kontextdaten anzeigen, ohne automatisch vollständige Billing-, Security- oder Verhaltensdaten offenzulegen.

Ein Support-System sollte mindestens Status, Zuständigkeit, Verlauf, interne Notizen, Nutzerkommunikation und relevante Audit-Informationen sauber trennen.

## Moderation

Moderation braucht nachvollziehbare Fälle statt versteckter Einzelaktionen.

Ein Moderationsfall kann enthalten:

* Meldung
* betroffene Inhalte oder Konten
* Evidenz
* Kategorie
* Priorität
* Bearbeitungsstatus
* Entscheidung
* Begründung
* Review oder Eskalation
* Audit

AI kann unterstützen, ersetzt aber nicht automatisch menschliche Verantwortung bei hochriskanten Entscheidungen.

## Internationalisierung

Texte sollen nicht hart in Businesslogik oder Templates verteilt werden, wenn ein Projekt mehrere Sprachen unterstützt.

Empfohlen sind Translation Keys mit nachvollziehbaren Freigabezuständen.

Eine aktivierte Sprache bedeutet nicht automatisch, dass das Produkt in diesem Land rechtlich oder kommerziell gestartet wurde.

## Barrierefreiheit

Barrierefreiheit betrifft nicht nur Kontrast. Relevante Themen sind Tastaturbedienung, Fokusführung, semantische Struktur, Formulare, Fehlermeldungen, Alternativtexte, Untertitel und ausreichende Touch Targets.

Accessibility soll als Qualitätsanforderung in Design, Entwicklung und Release-Gates berücksichtigt werden.

## Rollen und Zuständigkeiten

Support, Moderation, Übersetzung und Accessibility Reviews können in derselben Backoffice-Shell stattfinden, bleiben aber fachlich und über Capabilities getrennt.

Weiter: [[07-Datenschutz-und-Compliance]] · [[14-Governance-Dokumentation-und-Releases]]