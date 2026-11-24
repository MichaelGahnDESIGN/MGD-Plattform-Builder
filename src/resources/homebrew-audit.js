import crypto from 'node:crypto';

const numeric = value => typeof value === 'string' && value.length <= 128 && /^\d+(?:\.\d+)*(?:_\d+)?$/.test(value);

/** Nur eindeutige numerische Homebrew-Versionen, inklusive Keg-Revision. Sonst offen lassen. */
export function compareBrewVersion(a, b) {
  if (!numeric(a) || !numeric(b)) return null;
  const parts = value => { const [version, revision = '0'] = value.split('_'); return [version.split('.').map(BigInt), BigInt(revision)]; };
  const [av, ar] = parts(a), [bv, br] = parts(b);
  for (let i = 0; i < Math.max(av.length, bv.length); i++) {
    const x = av[i] ?? 0n, y = bv[i] ?? 0n;
    if (x !== y) return x < y ? -1 : 1;
  }
  return ar === br ? 0 : ar < br ? -1 : 1;
}

export function homebrewIdentity(name, version, raw) {
  const receipt = JSON.parse(raw);
  const exact = numeric(version) && receipt?.source?.tap === 'homebrew/core'
    && receipt.source.spec === 'stable' && receipt.source.versions?.stable === version.split('_')[0]
    && receipt.source.versions.version_scheme === 0;
  return {vendor: 'Homebrew', formula: name, version, coreReceipt: exact,
    receiptSha256: crypto.createHash('sha256').update(raw).digest('hex')};
}

export function brewRangeAffected(version, range) {
  if (range?.type !== 'ECOSYSTEM' || !Array.isArray(range.events) || !range.events.length || range.events.length > 256) return null;
  let open = false, previous = null, included = false, affected = false;
  for (const event of range.events) {
    if (!event || Object.keys(event).length !== 1) return null;
    const kind = Object.keys(event)[0], boundary = event[kind];
    if (!['introduced', 'fixed', 'last_affected', 'limit'].includes(kind)) return null;
    const cmp = compareBrewVersion(version, boundary);
    if (cmp === null || (previous !== null && compareBrewVersion(previous, boundary) > 0)) return null;
    previous = boundary;
    if (kind === 'introduced') {
      if (open) return null;
      open = true;
      included = cmp >= 0;
    } else {
      if (!open) return null;
      if (included && (cmp < 0 || (kind === 'last_affected' && cmp === 0))) affected = true;
      open = false; included = false;
    }
  }
  return affected || (open && included);
}

export function assessBrewRecord(version, row, now = Date.now()) {
  if (row.withdrawn) return Number.isFinite(Date.parse(row.withdrawn)) && Date.parse(row.withdrawn) <= now ? false : null;
  let uncertain = false;
  for (const affected of row.affected) {
    if (affected.versions !== undefined && (!Array.isArray(affected.versions) || affected.versions.some(v => typeof v !== 'string'))) {
      uncertain = true;
    }
    if (Array.isArray(affected.versions) && affected.versions.includes(version)) return true;
    if (!Array.isArray(affected.ranges) || !affected.ranges.length) { uncertain = true; continue; }
    for (const range of affected.ranges) {
      const hit = brewRangeAffected(version, range);
      if (hit === true) return true;
      if (hit === null) uncertain = true;
    }
  }
  return uncertain ? null : false;
}

export function homebrewAudit(component, index, now = Date.now()) {
  const identity = component.hostIdentity;
  if (identity?.vendor !== 'Homebrew') return [];
  const unknown = (code, reason) => { component.checks.vulnerability = {status: 'unchecked', reasonCode: code, reason}; return []; };
  if (!identity.coreReceipt || identity.version !== component.version || identity.formula !== component.name)
    return unknown('homebrew-receipt-unconfirmed', 'Homebrew-Keg und offizieller Installationsbeleg sind nicht eindeutig zugeordnet.');
  if (!index) return unknown('homebrew-advisories-unavailable', 'Aktueller vollständiger Homebrew-Herstellerkatalog fehlt.');
  const rows = Object.hasOwn(index.advisories, component.name) ? index.advisories[component.name] : [];
  if (!rows.length) return unknown('homebrew-advisories-uncovered', 'Der Homebrew-Herstellerkatalog enthält für diese Formel keine auswertbaren Einträge; fehlende Einträge sind kein Sicherheitsnachweis.');
  const evaluations = rows.map(row => [row, assessBrewRecord(component.version, row, now)]);
  const matches = evaluations.filter(([,hit]) => hit === true);
  const incomplete = evaluations.some(([,hit]) => hit === null);
  component.checks.vulnerability = {
    status: incomplete ? 'unchecked' : matches.length ? 'findings' : 'no-known-findings',
    source: 'Homebrew-Herstellerkatalog / offline', checkedAt: new Date(now).toISOString(),
    databaseAt: {Homebrew: index.meta.lastModified},
    reason: incomplete ? 'Mindestens ein Hersteller-Versionsbereich ist nicht eindeutig vergleichbar.' : 'Installierter Keg anhand der erfassten Homebrew-Advisories geprüft; keine Aussage über unbekannte Lücken oder nicht erfasste eingebettete Komponenten.',
    ...(incomplete ? {reasonCode: 'homebrew-range-unconfirmed'} : {}),
  };
  component.hostEvidence = {receiptSha256: identity.receiptSha256, databaseSha256: index.meta.sha256,
    records: rows.length, scope: 'recorded-homebrew-advisories', includesRetainedKegs: true};
  return matches.map(([row]) => ({id: row.id, name: component.name, version: component.version, file: component.file,
    source: 'Homebrew (Hersteller)', severity: 'unknown', title: String(row.summary || row.id).slice(0,500),
    url: 'https://github.com/Homebrew/advisory-database', confidence: 'vendor-record', upstream: row.upstream || []}));
}
