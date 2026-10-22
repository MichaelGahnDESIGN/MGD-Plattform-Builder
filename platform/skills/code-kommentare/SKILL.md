---
name: code-kommentare
description: >-
  Schreibt und überarbeitet Code-Kommentare in Michaels kurzem, praktischem
  deutschen Stil. Für /code-kommentare, $code-kommentare und Codearbeiten mit
  dieser Stilvorgabe: natürliche Abschnittstitel, echte Gründe und Hinweise,
  passende Kommentarsyntax; keine Änderungen an der Programmlogik.
---

# /code-kommentare

Schreibe Kommentare so, wie Michael sie beim Arbeiten selbst hinterlassen würde:
kurz, direkt, praktisch, auf Deutsch. Der Stil gilt über alle Programmiersprachen
hinweg, wenn dieser Skill gewählt wurde oder die geltenden Nutzerregeln ihn vorgeben.
Andere Projekte erhalten dadurch keine ungefragte Stilvorgabe.

## Ton und Inhalt

- Benenne den Bereich: „Glass-Effekt Header“, „Warenkorb Button“,
  „Endlos Animation Logo“. Ein kurzer Satz oder ein Stichwort reicht oft.
- Erkläre bei Bedarf den Grund, die Herkunft, eine Abhängigkeit oder einen
  Sonderfall. Nenne Dateien und Pfade nur, wenn sie wirklich stimmen.
- Keine Selbstbeschreibung als KI, keine künstlichen Schritt-für-Schritt-Romane
  und keine Kommentare zu jeder offensichtlichen Zeile.
- Erforderliche Hinweise zu Sicherheit, Ein-/Ausgaben und Grenzen bleiben erhalten.
  Schreibe sie einfach: „PIN nur als Hash speichern, nie zurück ans Frontend“.
- Kleine natürliche Tippfehler sind ausschließlich in freien Kommentaren erlaubt,
  etwa ein gelegentliches ie/ei. Nicht systematisch einbauen oder Fehlerquoten
  erzeugen. Vorhandene harmlose Tippfehler müssen nicht glattgebügelt werden.
- Funktionscode, Bezeichner, Dateipfade, Befehle, Direktiven, Typangaben,
  Lizenztexte, UI-Texte und Dokumentation bleiben korrekt. Auch innerhalb eines
  Kommentars dürfen ausführbare Beispiele oder Werkzeuganweisungen keine
  absichtlichen Fehler bekommen.

## Syntax und Beispiele

Nutze die Syntax der jeweiligen Sprache. In CSS ausschließlich `/* ... */`,
kein `//`. In JavaScript/PHP passt `//`, in Python `#`, in HTML `<!-- ... -->`.
Technisch benötigte Docblocks, Annotationen und Lizenzhinweise nicht entfernen.
Die folgenden Zeilen zeigen den Ton; sie behaupten keine vorhandene Einbindung.

```js
// Hintergrund für Seite - Animation aus hintergrund.css
// Wall of Text Limitierung Startseite
// Glass-FX allgemein
// Glass-Effekt Header
// WTF Modal Effekt
// three.js lokal eingebettet > /js/assets/three.js
// CSS aus custom.css eingebettet
```

```css
/* Endlos Animation Logo */
/* Basic_BTN für main Button von XYZ */
/* Warenkorb Button */
```

## Divi und Ablageorte

Prüfe zuerst native Divi-Einstellungen. Wenn im konkreten Projekt vereinbart,
liegt zusätzliches globales Gestaltungs-CSS unter **Divi → Theme-Optionen →
Individuelles CSS (Code-Box)**. Das ist ein CSS-Feld, kein allgemeiner Code-Editor.
Die projektspezifische Ablageentscheidung gehört in dessen Dokumentation und wird
nicht auf fremde Projekte übertragen.

JavaScript gehört in die dafür vorgesehene Integration oder wird als lokales
Plugin-Asset eingebunden; PHP und Fachlogik gehören ins zuständige Plugin.
Modulbezogene Plugin-Assets bleiben beim Modul. Der Skill allein beauftragt keine
Live-Änderung, CSS-Migration, Installation oder Veröffentlichung.

## Anwendung und Prüfung

1. Lies die geltenden Regeln und den betroffenen Code. Ändere nur Kommentare im
   beauftragten Bereich; keine flächendeckende Umschreibung fremder Dateien.
2. Formuliere kurze Bereichstitel und nötige Erklärungen. Erfinde keine Herkunft,
   Testergebnisse oder Gründe. Keine Zugangsdaten oder privaten Daten in Beispiele.
3. Prüfe Diff und Syntax: Kommentarzeichen, Docblocks und Compiler-/Linter-Direktiven
   dürfen nichts beschädigen. Prüfe, dass Programmlogik und ausgelieferte Texte
   unverändert bleiben, wenn nur Kommentare beauftragt sind. Verwende vorhandene
   Syntaxprüfungen passend zum tatsächlichen Umfang.

Claude Code: `/code-kommentare [Datei oder Bereich]`.
Codex: `$code-kommentare [Datei oder Bereich]` oder über den Skill-Selektor.
