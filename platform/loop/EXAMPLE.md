# Beispiel: API-Migration mit Loop

**Ziel:** Alle `apiV1(` in `src/` auf `apiV2(` migrieren

**Prüfkriterium:** `rg "apiV1(" src/ | wc -l` = 0 UND `npm test` = Exit 0

---

## Runde 1

**Start:** 42 Hits für `apiV1(`

**Step:** Migriere `src/auth/` + Tests

**Ergebnis:** 28 Hits, Tests grün

**Nächster Step:** `src/payment/`

---

## Runde 2

**Start:** 28 Hits

**Step:** Migriere `src/payment/` + Tests

**Ergebnis:** 8 Hits, Tests grün

**Nächster Step:** `src/utils/`

---

## Runde 3

**Start:** 8 Hits

**Step:** Migriere `src/utils/` + Tests

**Ergebnis:** 0 Hits, Tests grün ✓

**Finale Prüfung:** ✓ Fertig
