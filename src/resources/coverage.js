// Prüfarten getrennt halten: eine Mindestanforderung ist keine installierte Version.
export function describeCoverage(c) {
  const v = c.checks.vulnerability;
  if (v.status === 'no-known-findings' || v.status === 'findings') return;
  if (v.reasonCode) return;
  const set = (status, reasonCode, reason) => Object.assign(v, { status, reasonCode, reason });
  if (c.kind === 'declaration' || (c.kind === 'runtime' && !c.observed))
    return set('declared', 'version-requirement', 'Versionsanforderung; die tatsächlich eingesetzte Version ist nicht nachgewiesen.');
  if (c.scope === 'sdk' || c.scope === 'local-path')
    return set('not-applicable', 'sdk-or-local', 'SDK oder lokales Projekt; Prüfung über dessen eigene Version und Abhängigkeiten.');
  if (c.kind === 'external') return set('not-applicable', 'external-service', 'Externer Dienst: API-Support und eigene Integration separat prüfen; keine lokale Paketversion.');
  if (['plugin', 'theme'].includes(c.kind)) {
    c.checks.code = { status: 'unchecked', reason: 'Eigener Code benötigt gesonderte Sicherheits- und Berechtigungsprüfungen.' };
    return set('not-applicable', 'own-code', 'Keine Paketdatenbank-Zuordnung; der eigene Code ist damit nicht sicherheitsgeprüft.');
  }
  if (c.kind === 'container') return set('unchecked', 'image-evidence-missing', 'Image-Digest und aktueller Scan der enthaltenen Pakete erforderlich.');
  if (['runtime','os','database','webserver'].includes(c.kind)) return set('unchecked', 'vendor-evidence-missing', 'Installationsstand erfasst; Herstellerpaket und Sicherheitskorrekturen separat prüfen.');
  if (c.kind === 'ci') return set('unchecked', 'action-release-unmapped', 'Action-Commit noch keiner eindeutigen Veröffentlichung zugeordnet.');
  if (!c.exact) return set('unchecked', 'exact-version-missing', 'Keine eindeutige Paketversion vorhanden.');
  if (!c.publicSource) return set('unchecked', 'provenance-unverified', 'Öffentlicher Paketursprung nicht bestätigt.');
  return set('unchecked', 'audit-unavailable', 'Keine vollständige Sicherheitsabfrage für dieses Paket vorhanden.');
}

export function coverageCounts(components) {
  const result = { checked: 0, gaps: 0, declared: 0, notApplicable: 0, development: 0, codeReview: 0 };
  for (const c of components) {
    const status = c.checks?.vulnerability?.status;
    if (['no-known-findings','findings'].includes(status)) result.checked++;
    else if (status === 'declared') result.declared++;
    else if (status === 'not-applicable') result.notApplicable++;
    else result.gaps++;
    if (c.dependencyGroup === 'development') result.development++;
    if (c.checks?.code?.status === 'unchecked') result.codeReview++;
  }
  return result;
}
