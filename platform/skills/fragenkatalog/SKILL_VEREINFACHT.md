---
name: fragenkatalog
description: Stelle Design- und Projekt-Fragen, bekomm Antworten aus verschiedenen Experten-Sichten.
---

# /fragenkatalog — Fragen, die Experten beantworten

Manche Fragen sind schwer: Wie sicher ist sicher genug? Was kostet datenschutz? Wie schreib ich die Nutzungsbedingungen?

Der Fragenkatalog sammelt diese Fragen und antwortet aus verschiedenen Blickwinkeln.

## Wie es funktioniert

**Schritt 1: Frage stellen**
```
/fragenkatalog Wie sicher muss die Login-Funktion sein?
```

**Schritt 2: Experten-Sicht wählen**
Beispiele:
- **Product** — "Was bringt uns Geld/User?"
- **Security** — "Welche Angriffe könnte es geben?"
- **Legal** — "Was sagt das Gesetz?" (⚖️ nur mit Disclaimer)
- **Design** — "Wie macht man es benutzerfreundlich?"
- **Operations** — "Wie überwachen wir das?"

**Schritt 3: Antwort lesen**

Die gleiche Frage sieht aus verschiedenen Winkeln anders aus — und alle sind richtig.

## Beispiel

```
/fragenkatalog Wie viel Speicher speichern wir?

→ Product antwortet: "Nur das Nötigste, sonst teuer."
→ Legal antwortet: "Nicht mehr als für den Zweck nötig (GDPR)."
→ Operations antwortet: "Bei 1 Mio. User brauchen wir X TB, wir haben Y TB."
→ Security antwortet: "Sensible Daten getrennt lagern — separate Datenbank."
```

Jede Antwort ist wahr — sie kommen nur aus anderen Richtungen.

## Fragen-Bereiche

- **Design** — UX, Farben, Bedienbarkeit
- **Architektur** — Tech-Stack, Datenbank-Design
- **Kosten** — Was kostet das?
- **Datenschutz** — GDPR, Speicherung
- **Sicherheit** — Verschlüsselung, Anmeldung
- **Operations** — Backup, Monitoring, Fehlersuche

## Häufig gefragt

**F: Sind die Antworten Jura-Rat?**  
A: Nein. Nur Orientierung. Für echte rechtliche Fragen brauchst du einen Anwalt. Der Katalog hat deshalb ⚖️-Markierungen bei juristischen Fragen.

**F: Kann ich neue Fragen hinzufügen?**  
A: Ja. Die Fragen wachsen mit deinem Projekt. `/fragenkatalog add` für neue Fragen.

