# Loop-Protokoll für messbare Aufgaben

Messbare Aufgaben (klares Ziel + Prüfkriterium) laufen nach diesem Protokoll, um Token zu sparen und Kontrolle zu behalten.

## Regeln

| Regel | Details |
|-------|---------|
| **Zustand** | Jede Runde schreibt den aktuellen Stand in `.loop/STATE.md` (nicht gecheckt) |
| **Schritte** | Pro Runde genau ein kleiner Schritt; kein Sprung auf fertig |
| **Pilot** | Pilot-Ausführung läuft max. **3 Runden**, dann Pause für Feedback |
| **Stoppregeln** | Diese Aktionen verlassen den Loop: Push, Deploy, Zahlungen, Produktionseinstellungen, Rechts-/Datenschutz-/Kostentscheidungen → Frage immer Michael |
| **Modell** | Executor (günstig) für Umsetzung; Haiku für Loop-Prüfung; Michael (teuer) für Plan + Abnahme |

## Ablauf

```
Michael: "Mach das fertig (messbare Aufgabe)"
  ↓
Claude: Plant, delegiert an Executor, Step 1
  ↓
Loop: STATE.md schreiben, nächster Turn automatisch?
  ↓
Nach 3 Runden oder Stopp-Kriterium: Bricht auf, zeigt Fortschritt
  ↓
Michael: Reviews, gibt nächstes Ziel oder genehmigt
```

## Dateien

- `.loop/STATE.md` – aktueller Stand, Fortschritt, nächster Step
- `platform/loop/` – Vorlagen und Beispiele
