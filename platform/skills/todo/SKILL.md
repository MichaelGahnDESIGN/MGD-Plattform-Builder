---
name: todo
description: >-
  Universelles TODO-Management für KI-Agenten: TODO.html lesen, Todos hinzufügen,
  Status aktualisieren, in freien Kategorien/Unterkategorien organisieren, debuggen,
  aus Projekt-Quellen synchronisieren, erledigte aus- und einblenden, alte
  erledigte Todos aufräumen und ältere Dateien aufs aktuelle Format bringen.
  Kompatibel mit Claude Code und ChatGPT Codex.
---

# /todo — Universelles TODO-Management

Verwalte Projekt-Todos in einer selbst-gehosteten `TODO.html`-Datei, die
sortier- und durchsuchbar ist, Todos in frei anlegbaren Kategorien und
Unterkategorien organisiert, und lokal im Projektordner liegt.

> Leitsatz: „Kein Todo geht verloren. Alles landet in der HTML. Status immer aktuell."

---

## Konfiguration (einmalig setzen)

Der Pfad zur TODO-Datei wird im Projekt gepflegt. Standard:

```
PROJEKT/TODO/TODO.html
```

Mit `/todo-pfad <neuer-pfad>` kann der Pfad geändert werden.
Der aktuelle Pfad wird in `PROJEKT/TODO/.todo-config` gespeichert.

---

## Format (ab Version 1.0.0)

**IDs:** `TNNN`, ohne Bindestrich (`T001`, `T002`, … `T199`, `T1200`, …).
Fortlaufend, nie wiederverwendet, auch nicht nach `/todo-close`.

> [!WARNING]
> **⚠️ FALLSTRICK** — Vor Version 1.0.0 lauteten IDs `T-NNN` (mit
> Bindestrich) und Kategorien kamen aus einem festen Enum
> (`app|editor|backend|infra|ux|playtest|doku`). Eine `TODO.html` in
> diesem Altformat NICHT von Hand anfassen — `/todo-migrate` aufrufen
> (siehe unten).

**Kategorien sind Freitext**, keine feste Liste — anlegen, was zum
Projekt passt: „Sicherheit", „Datenschutz", „Features", „Backend", was
auch immer gebraucht wird. Jede Kategorie kann **optional** eine
Unterkategorie haben (z. B. „Sicherheit" › „Zugriffsrechte"). Beides wird
in der Tabelle als `data-kategorie`/`data-unterkategorie` gespeichert; die
Filter-Dropdowns in der HTML füllen sich beim Öffnen automatisch aus den
tatsächlich vorkommenden Werten — es muss nirgends eine feste Liste
gepflegt werden.

**Notizen** können kurz bleiben (ein Satz reicht) oder eine ausklappbare
Langbeschreibung bekommen: `<details><summary>Kurzbeschreibung</summary>
Langbeschreibung…</details>`. Die Kurzbeschreibung ist immer sichtbar,
die Langbeschreibung klappt per Klick auf — gut für Belege,
Datei:Zeile-Referenzen oder Entscheidungsgründe, die beim schnellen
Überfliegen der Liste nicht stören sollen.

**Zeitstempel:** Jede Zeile trägt `data-erstellt="YYYY-MM-DDTHH:MM"` (Anlegen)
und, sobald sie erledigt ist, `data-erledigt="YYYY-MM-DDTHH:MM"`. Die sichtbare
Datumsspalte zeigt das Anlegedatum, bei erledigten Zeilen darunter zusätzlich
`✓ Erledigungsdatum`. Beide Attribute werden gebraucht, weil „seit wann erledigt"
und „seit wann offen" verschiedene Fragen sind — das Aufräumen unten hängt am
Erledigungsdatum. Ältere Dateien ohne diese Attribute bleiben gültig: fehlt
`data-erstellt`, gilt der Text der Datumsspalte; fehlt `data-erledigt`, hat die
Zeile kein bekanntes Erledigungsdatum und wird vom Aufräumen **nie** erfasst.

**Erledigte aus- und einblenden:** Der Knopf „✓ Erledigte ausblenden" blendet
alle erledigten Zeilen aus, ohne sie anzufassen. Wirkt zusätzlich zu Suche und
Filtern, Standard ist eingeblendet.

**Aufräumen:** Der Knopf „🧹 Aufräumen" entfernt erledigte Todos, deren
Erledigung mehr als einen Monat zurückliegt. Weil eine statische HTML sich nicht
selbst speichern kann, entfernt der Knopf die Zeilen im Browser und lädt
anschließend eine bereinigte `TODO.html` herunter, die die bestehende Datei
ersetzen muss — ohne diesen Schritt sind die Zeilen beim nächsten Öffnen wieder
da. Wer die Datei direkt an Ort und Stelle bereinigen will, nimmt
`/todo-cleanup`.

**Gruppieren:** Der Knopf „🗂 Gruppieren" in der HTML zeigt die Todos als
Baum nach Kategorie › Unterkategorie an, statt als flache sortierbare
Liste. Rein clientseitig, keine Einstellung — jeder Betrachter der Datei
schaltet es für sich selbst um.

---

## Befehle

### `/todo-setup` — Neues Projekt einrichten (einmalig)

**Verwende diesen Befehl in jedem neuen Projekt als erstes.** Er richtet alles
automatisch ein, sodass danach alle anderen `/todo-*`-Befehle funktionieren.

Ablauf:
1. Prüfe ob bereits eine TODO.html im Projekt existiert (via `.todo-config` oder
   Default-Pfad `PROJEKT/TODO/TODO.html`)
2. Falls bereits vorhanden: kurze Bestätigung ausgeben, Setup überspringen.
   Prüfe dabei kurz, ob es sich um das AKTUELLE Format handelt (Stichprobe:
   enthält die Datei `data-kategorie` oder noch das alte `data-kat`?) — falls
   Altformat, `/todo-migrate` vorschlagen statt stillschweigend weiterzumachen.
3. Falls nicht vorhanden:
   a. Frage nach dem gewünschten Pfad (Default: `PROJEKT/TODO/TODO.html`, Enter = übernehmen)
   b. Erstelle den Ordner falls nötig
   c. Lade das Template von GitHub herunter:
      ```
      https://raw.githubusercontent.com/MichaelGahnDESIGN/MGD_Todo_SKILL/main/todo/TODO.template.html
      ```
      Falls kein Internetzugang: Erstelle eine minimale TODO.html inline (gleiche
      Struktur, leerer tbody).
   d. Passe den Titel in der HTML an (ersetze „PROJEKT TODO" / „PROJEKT — TODO"
      durch den Projektnamen, abgeleitet aus dem Verzeichnisnamen oder
      `package.json`/`pubspec.yaml`)
   e. **Beispielzeilen entfernen:** Das Template liefert zwei Demo-Todos mit
      (`data-kategorie="beispiel"`, IDs `T001`/`T002`) — beide `<tr>`-Blöcke
      löschen, damit die Liste leer startet und das erste echte `/todo-add`
      wieder `T001` vergibt. Den erklärenden Kommentarblock im
      `<tbody id="todoBody">` **stehen lassen**: er dokumentiert das
      Zeilenformat für spätere Handbearbeitung. Die Regel „niemals löschen"
      gilt für echte Todos, nicht für die Platzhalter der Vorlage.
   f. Schreibe den Pfad in `.todo-config`
   g. Gib Erfolgsmeldung aus: Pfad, nächster Schritt (`/todo-add "Erstes Todo"`)

Hinweis für Codex: Template via `curl` laden, Ordner via `mkdir -p` anlegen.

---

### `/todo` — Übersicht offener Todos

Lies die TODO.html und zeige eine kompakte Übersicht:
- Alle offenen Todos (status="offen" oder status="progress"), sortiert nach Priorität
- Gesamtstatistik: N Offen | N In Arbeit | N Erledigt | N Gesamt
- Jeweils: ID · Titel · Kategorie[ › Unterkategorie] · Priorität

### `/todo-pfad <pfad>` — Pfad zur TODO.html setzen

Speichert den neuen Pfad in `PROJEKT/TODO/.todo-config`.
Format: absoluter oder relativer Pfad ab Projekt-Root.

### `/todo-update` — Todos manuell aktualisieren

Interaktiver Modus:
1. Zeige aktuelle offene Todos
2. Frage: Welches Todo aktualisieren? (ID oder Titel)
3. Welcher neue Status? (offen / progress / done)
4. Optional: Kategorie/Unterkategorie ändern?
5. Optional: Notiz ergänzen? (als reiner Text anhängen, oder in eine
   `<details>`-Langbeschreibung umwandeln, wenn es umfangreicher wird)
6. Schreibe Änderungen in TODO.html (HTML-Attribute aktualisieren, Badge-Klasse
   tauschen, `row-done` ergänzen)

### `/todo-add <titel>` — Neues Todo hinzufügen

Parameter:
- `<titel>` — Pflicht, kurzer Titel des Todos
- Optional via interaktivem Dialog:
  - **Kategorie** — Freitext. Zeige zuerst die in der Datei bereits
    vorkommenden Kategorien zur Wiederverwendung an (aus den
    `data-kategorie`-Werten aller `<tr>` ableiten), erlaube aber jederzeit
    eine neue.
  - **Unterkategorie** — optional, Freitext, nur sinnvoll innerhalb der
    gewählten Kategorie. Genauso: vorhandene Unterkategorien dieser
    Kategorie zur Wiederverwendung anzeigen, neue erlauben, oder ganz
    weglassen.
  - Priorität (kritisch / hoch / mittel / niedrig)
  - Notizen — kurz reicht; bei Bedarf `<details>` für eine Langbeschreibung
    anbieten (siehe Format oben)
  - Quelle (z. B. PlayTest v0.1.4, CODEX-TASKS, Manuell)

Generiert die nächste freie `TNNN`-ID (höchste vorhandene Nummer + 1, gleiche
Ziffernanzahl solange die Zählung das hergibt) und fügt eine neue `<tr>`-Zeile
im `<tbody id="todoBody">` ein. Setzt `data-erstellt` auf den aktuellen
Zeitpunkt (`YYYY-MM-DDTHH:MM`) und die sichtbare Datumsspalte auf das heutige
Datum. `data-erledigt` bleibt weg, solange das Todo offen ist.

### `/todo-close <id>` — Todo als erledigt markieren

Beispiel: `/todo-close T004`

Ändert in der TODO.html:
- `data-status="done"`
- `data-erledigt` auf den aktuellen Zeitpunkt (`YYYY-MM-DDTHH:MM`) setzen —
  **nicht vergessen**, sonst wird die Zeile vom Aufräumen nie erfasst
- In der Datumsspalte `<span class="datum-erledigt">✓ YYYY-MM-DD</span>` ergänzen
- Badge-Klasse → `s-done`, Text → `✓ Erledigt`
- Zeile bekommt Klasse `row-done`
- `titel-col` erhält Durchstreichung via CSS (automatisch durch row-done)

### `/todo-debug` — Validierung und Diagnose

Prüft die TODO.html auf:
- Gültige HTML-Struktur (öffnende/schließende Tags korrekt, auch `<details>`/`<summary>`)
- Alle `<tr>` haben `data-status`, `data-prio`, `data-kategorie`
  (`data-unterkategorie` ist optional, kein Pflichtfeld)
- Keine doppelten IDs
- **Kein Vorkommen des Altformats mehr** — IDs mit Bindestrich (`T-\d+`) oder
  das alte Attribut `data-kat`. Findet sich eines, `/todo-migrate` vorschlagen.
- Alle Badge-Klassen sind bekannte Klassen (`s-*`, `p-*`, `k-cat`, `k-subcat`)
- **Zeitstempel:** jede Zeile hat `data-erstellt`; jede Zeile mit
  `data-status="done"` hat zusätzlich `data-erledigt`. Fehlende Werte melden
  (sie sind kein Fehler in Altdateien, verhindern aber das Aufräumen) und
  anbieten, sie aus der sichtbaren Datumsspalte nachzutragen.
- Nächste freie ID ausgeben
- Statistik: Offen / In Arbeit / Erledigt / Gesamt, sowie Anzahl verschiedener
  Kategorien und Unterkategorien

### `/todo-migrate` — Bestehende TODO.html aufs aktuelle Format bringen (einmalig pro Projekt)

Bringt eine bestehende `TODO.html` auf den aktuellen Stand — unabhängig davon,
wie alt sie ist.

**Zuerst den Stand der Datei bestimmen**, denn „schon migriert" ist keine
Ja/Nein-Frage. Prüfe der Reihe nach:

| Stand | Erkennungsmerkmal | Was zu tun ist |
| --- | --- | --- |
| **Altformat** (vor 1.0) | IDs `T-NNN` oder Attribut `data-kat` vorhanden | Schritte 1–9, vollständige Migration |
| **1.0er Stand** | IDs `TNNN` und `data-kategorie` vorhanden, aber **kein** `data-erstellt` oder **kein** `id="btnCleanup"` | Schritte 1, 2, 5, 8, 9 — Kopf/Fuß/Skript erneuern und Zeitstempel nachtragen. IDs und Kategorien sind bereits richtig. |
| **Aktuell** | `data-erstellt` **und** `id="btnCleanup"` vorhanden | Nichts ändern, Stand melden, fertig |

Diese Unterscheidung ist der Kern des Befehls: eine Datei im 1.0er Stand sieht
strukturell korrekt aus, hat aber weder Zeitstempel noch die Knöpfe zum
Ausblenden und Aufräumen. Wer hier pauschal „schon migriert" meldet, lässt sie
für immer auf dem alten Funktionsstand stehen.

Ablauf:
1. **Sicherung anlegen:** Kopiere die aktuelle Datei nach
   `<pfad>.vor-migration-<YYYY-MM-DD>` (lokal, nicht Teil eines Commits — nur
   ein Rückweg, falls etwas schiefgeht).
2. **Kopf/Fuß/Skript ersetzen:** Lade `todo/TODO.template.html` von GitHub
   (oder aus einem lokalen Klon des Skills) und übernimm daraus alles
   AUSSERHALB von `<tbody id="todoBody">…</tbody>` — also `<head>`, CSS,
   Filter-Steuerung im Header, Tabellenkopf, Footer und `<script>`. Das ist
   zuverlässiger, als das alte Skript Stück für Stück zu patchen, weil Kopf/
   Fuß/Skript generisch sind und keine Projektdaten enthalten — außer dem
   Site-Titel: den aus der alten Datei lesen (bisheriger Projektname) und im
   neuen Kopf/Footer wieder einsetzen, statt auf den Platzhalter „PROJEKT"
   zurückzufallen.
3. **IDs umstellen:** Ersetze im gesamten Dokument (auch in Notizen-Freitext,
   nicht nur in `id-col`) jedes Vorkommen von `T-` gefolgt von 3 oder mehr
   Ziffern (`T-(\d{3,})`) durch `T$1` — der Bindestrich fällt weg. Das trifft
   auch Querverweise wie „siehe T-142" → „siehe T142". Vor dem endgültigen
   Schreiben kurz gegenprüfen, dass kein Treffer aus einem erkennbar fremden
   Kontext stammt (z. B. eine Ticket-ID eines anderen Systems, die zufällig
   demselben Muster entspricht) — im Zweifel dem Nutzer die Fundstelle zeigen.
4. **Kategorien übertragen:** Für jede `<tr>`: der alte `data-kat`-Wert wird
   zu `data-kategorie` mit demselben Wert als Ausgangspunkt (kein Zwang, ihn
   sofort umzubenennen). Der alte Badge
   `<span class="badge k-app">App-Feature</span>` wird zu
   `<span class="badge k-cat">App-Feature</span>` — Klasse wechselt (`k-app`
   → `k-cat`, alle alten `k-*`-Klassen verschwinden), der sichtbare Text
   bleibt unverändert. `data-unterkategorie` bleibt für migrierte Zeilen
   zunächst weg — Unterkategorien sind eine neue Möglichkeit, keine Pflicht,
   und werden bei Bedarf später ergänzt.
5. **Zeitstempel nachtragen:** Für jede Zeile `data-erstellt` aus der
   sichtbaren Datumsspalte ableiten (`YYYY-MM-DD` → `YYYY-MM-DDT00:00`).
   `data-erledigt` lässt sich aus Altdaten **nicht** rekonstruieren — es gibt
   dort kein Erledigungsdatum. Erledigte Zeilen bleiben deshalb ohne dieses
   Attribut und werden vom Aufräumen nie erfasst. Das ist beabsichtigt: lieber
   eine alte Zeile zu viel behalten als eine fälschlich entfernen. Dem Nutzer
   anbieten, für erledigte Altzeilen ersatzweise das Anlegedatum als
   Erledigungsdatum zu setzen — nur auf ausdrücklichen Wunsch, weil es eine
   Schätzung ist.
6. **Notizen unverändert lassen:** bestehender Freitext bleibt gültiger
   Klartext. Die `<details>`-Kurz-/Lang-Form ist optional und wird nicht
   nachträglich erzwungen.
7. **Nach der reinen Struktur-Umstellung** (entfällt beim 1.0er Stand) dem Nutzer anbieten, thematisch
   verwandte Todos in neue, sprechendere Kategorien umzuhängen (z. B. alle
   `backend`-Zeilen, die inhaltlich um Sicherheit oder Datenschutz kreisen,
   in eine eigene Kategorie „Sicherheit"/„Datenschutz" verschieben) — das
   aber nur **vorschlagen**, nicht automatisch tun, weil es inhaltliches
   Wissen über die einzelnen Todos braucht, das eine reine Struktur-Migration
   nicht hat.
8. **Validieren:** Anzahl `<tr>` mit `id-col` vorher == nachher (keine Zeile
   verloren, keine doppelt). Danach `/todo-debug` durchlaufen lassen.
9. **Bericht:** Anzahl migrierter Zeilen, welche Kategorien jetzt vorhanden
   sind, was optional noch zu tun ist (Unterkategorien ergänzen, Kategorien
   umbenennen/bündeln).

### `/todo-cleanup` — Alte erledigte Todos aus der Datei entfernen

Das Gegenstück zum Knopf „🧹 Aufräumen" in der HTML, aber direkt an der Datei —
ohne Browser, ohne Download, ohne Ersetzen von Hand. Sinnvoll, wenn die Liste
über Monate gewachsen ist.

Ablauf:
1. **Sicherung anlegen:** Kopiere die Datei nach
   `<pfad>.vor-cleanup-<YYYY-MM-DD>`. Ohne diese Kopie nicht fortfahren.
2. **Stichtag bestimmen:** einen Monat vor heute. Über ein Argument
   veränderbar, z. B. `/todo-cleanup 3monate` oder `/todo-cleanup 2026-01-01`.
3. **Kandidaten sammeln:** alle `<tr>` mit `data-status="done"`, deren
   `data-erledigt` gesetzt, parsbar und älter als der Stichtag ist. Zeilen ohne
   `data-erledigt` werden **nie** erfasst — im Zweifel bleibt eine Zeile stehen.
4. **Vorlegen statt einfach löschen:** ID, Titel und Erledigungsdatum der
   Kandidaten auflisten und bestätigen lassen. Bei mehr als 20 Treffern nur die
   ersten 20 zeigen und die Gesamtzahl nennen.
5. **Entfernen** und Ergebnis prüfen: Zeilenzahl vorher minus Kandidaten muss
   der Zeilenzahl nachher entsprechen. Danach `/todo-debug` durchlaufen lassen.
6. **Bericht:** wie viele Zeilen entfernt wurden, welche IDs, wo die Sicherung
   liegt.

> Entfernte IDs werden **nicht** neu vergeben. Die Zählung läuft weiter bei der
> höchsten je genutzten Nummer, damit alte Querverweise („siehe T042") nicht
> plötzlich auf ein fremdes Todo zeigen. `/todo-add` leitet die nächste ID
> deshalb nicht allein aus den vorhandenen Zeilen ab, sondern berücksichtigt
> auch die Sicherungsdateien im TODO-Ordner, falls vorhanden.

### `/todo-sync` — Aus Projekt-Quellen synchronisieren

Liest bekannte Todo-Quellen im Projekt und schlägt neue Todos vor, die noch nicht
in der HTML stehen:
1. `PlayTest/Test-Todo.md` — offene Checkboxen (`- [ ]`)
2. `AI/CODEX-TASKS/*.md` — Aufgaben-Titel aus H2/H3-Überschriften
3. `PROJEKT/TODO/*.md` — weitere Todo-Dateien

Für jeden gefundenen, noch nicht in der HTML vorhandenen Punkt:
- Zeige Vorschau: Titel + Quelle + vorgeschlagene Kategorie (aus dem
  Dateipfad/Kontext abgeleitet, z. B. `PlayTest/*` → Kategorie „playtest")
- Frage ob hinzufügen (y/n/alle)
- Falls ja: `/todo-add` intern aufrufen

### `/todo-export` — Markdown-Export

Gibt alle offenen Todos als Markdown-Tabelle aus (für Commits, PRs, Mails).
Kategorie und Unterkategorie als ein Breadcrumb-Feld, analog zur HTML-Ansicht:

```markdown
| ID   | Titel | Prio | Kategorie | Status |
|------|-------|------|-----------|--------|
| T004 | Freunde-System (Backend + UI) | Hoch | Features › Sozial | Offen |
...
```

---

## HTML-Format der TODO.html

Die TODO.html enthält alle Todos als `<tr>`-Zeilen in `<tbody id="todoBody">`.
Jede Zeile hat folgende data-Attribute für Filter/Sort:

```html
<tr data-status="offen|progress|done"
    data-prio="kritisch|hoch|mittel|niedrig"
    data-kategorie="beliebiger-freitext-slug"
    data-unterkategorie="optional-auch-freitext"
    data-erstellt="YYYY-MM-DDTHH:MM"
    data-erledigt="YYYY-MM-DDTHH:MM">
  <td class="id-col">TNNN</td>
  <td class="titel-col">Titel des Todos</td>
  <td><span class="badge k-cat">Kategorie</span> <span class="crumb-sep">›</span> <span class="badge k-subcat">Unterkategorie</span></td>
  <td><span class="badge p-PRIO">Priorität</span></td>
  <td><span class="badge s-STATUS">Status</span></td>
  <td class="quelle-col">Quelle</td>
  <td class="notizen-col">Kurzer Text — oder <details><summary>Kurzbeschreibung</summary>Langbeschreibung…</details></td>
  <td class="datum-col">YYYY-MM-DD<span class="datum-erledigt">✓ YYYY-MM-DD</span></td>
</tr>
```

`data-unterkategorie` und der zugehörige `crumb-sep`/`k-subcat`-Teil in der
Kategorie-Zelle entfallen komplett, wenn ein Todo keine Unterkategorie hat.

`data-erledigt` und das `datum-erledigt`-Span stehen **nur** bei erledigten
Zeilen. Erledigte Zeilen haben zusätzlich `class="row-done"` auf dem `<tr>`.

---

## Sicherheitsregeln

- **Keine Secrets** in TODO-Einträgen: keine Passwörter, Tokens, Zugangsdaten,
  API-Keys, personenbezogene Daten.
- **Keine externen Dependencies**: TODO.html ist vollständig self-contained
  (inline CSS + JS, keine CDN-Links).
- **Offene Todos nie löschen**: Ein Todo verschwindet nicht, weil es unbequem
  ist — es wird auf "done" gesetzt. Wer eine Zeile für gegenstandslos hält,
  markiert sie als erledigt und schreibt den Grund in die Notiz.
- **Erledigtes darf altern**: Erledigte Todos dürfen nach einer Aufbewahrungs-
  frist raus (Knopf „🧹 Aufräumen" oder `/todo-cleanup`, Standard ein Monat ab
  Erledigung). Beide Wege legen vorher eine Sicherung an — der Knopf als
  Download der bereinigten Datei, `/todo-cleanup` als Kopie neben der Datei.
  Zeilen ohne Erledigungsdatum werden dabei nie erfasst. IDs werden nach dem
  Entfernen nicht neu vergeben.
- **Lokal first**: TODO.html bleibt im Repo (committed), PlayTest-Artefakte bleiben lokal.

---

## Nutzung in ChatGPT Codex

Diese Datei ist self-contained für Codex. Codex nutzt Shell-Tooling statt
Claude-spezifischer APIs. Die Schritt-Logik ist dieselbe; Befehle via
`codex --instructions <pfad-zu-SKILL.md> "/todo-<befehl>"`.

Pfad-Auflösung für Codex: Lies zuerst `PROJEKT/TODO/.todo-config` (Pfad),
falls nicht vorhanden default `PROJEKT/TODO/TODO.html` ab Repo-Root.

---

## Integration mit anderen Skills

- **`/playtest`** → nach Playtest: `/todo-sync` aufrufen, neue Bugfix-Todos importieren
- **`/dev`** → vor Deploy: `/todo` aufrufen, kritische offene Todos prüfen
- **`/projectclean`** → `/todo-export` für Release-Notes Anhang
- **Superpower (Claude)** → `/todo-add` direkt aus Superpower-Findings aufrufen
- **Playwright** → nach Testlauf fehlgeschlagene Tests als Todos via `/todo-add` eintragen


---

## Zusammenspiel mit anderen Skills

Keiner davon ist Voraussetzung — dieser Skill funktioniert allein. Wo einer
vorhanden ist, nutz ihn statt einer Eigenbaulösung.

> [!TIP]
> **🤖 FÜR KI-AGENTEN** — Wenn diese Skills im Projekt verfügbar sind, ruf sie
> an den genannten Stellen auf, statt die Aufgabe selbst nachzubauen.

| Skill | Wofür hier | Quelle |
|---|---|---|
| `/todo` | Befunde festhalten, die über diesen Lauf hinaus wichtig bleiben | [MGD_Todo_SKILL](https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL) |
| `/graphify` | Wer Todos aus einem fremden Projekt ableiten soll, findet über den Graphen schneller die relevanten Stellen. | [graphify](https://github.com/Graphify-Labs/graphify) |
| `/autopilot` | Beim Abarbeiten einer Todo-Liste in einem Durchlauf. | [MGD_Autopilot_SKILL](https://github.com/MichaelGahnDESIGN/MGD_Autopilot_SKILL) |

### Empfehlung bei längeren Läufen

> [!TIP]
> **🤖 FÜR KI-AGENTEN** — Zeichnet sich ab, dass die Aufgabe über mehrere
> Schritte geht und der Nutzer nicht daneben sitzt, weis **einmal** auf
> `/autopilot` hin:
>
> > Das hier läuft über mehrere Schritte. Mit `/autopilot` bekommt der Ablauf
> > eine Abbruchbedingung, eine Grundlinie und eine Prüfung nach jeder Änderung —
> > soll ich so weitermachen?
>
> Einmal fragen, Antwort akzeptieren, nicht wiederholen. Bei kurzen, klar
> umrissenen Aufgaben ganz weglassen.
