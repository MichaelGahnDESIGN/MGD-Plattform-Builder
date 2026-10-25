import fs from 'node:fs/promises';
import { createReadStream } from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { scanOfflineInventory, OfflineScannerFailure } from './offline-scanner.js';
import { readSafe } from './files.js';

export const SCANNER_VERSION = '2.7.0';
export const SCANNER_HASHES = new Set([
  'd4926ef8da134332543be276dff157e4c170f99ad91fc48e89448a8816bdf70b',
  '160c6253e2922e9e0a6a9771c5c4c40a4b3c6090b34a3cdd5e80c96cc2e4218b',
]);
const PURL = { npm: 'npm', Packagist: 'composer', Pub: 'pub' };
const identity = p => [p.ecosystem,p.name,String(p.version).replace(/^v/,'')].join('|');
export async function sha256(file) {
  const hash = crypto.createHash('sha256');
  for await (const part of createReadStream(file)) hash.update(part);
  return hash.digest('hex');
}
async function regular(file, max) {
  const stat = await fs.lstat(file);
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size > max) throw new Error('Ungültige Offline-Datei');
}

export async function offlineState(config) {
  if (!config?.binary || !config?.databaseDirectory) return { status: 'missing', reason: 'Offline-Prüfung noch nicht eingerichtet.' };
  try {
    const manifest = JSON.parse(await readSafe(config.databaseDirectory, 'manifest.json'));
    const ecosystems = {};
    for (const eco of Object.keys(PURL)) {
      const item = manifest[eco], age = Date.now() - Date.parse(item?.downloadedAt);
      let present = false;
      try { const stat=await fs.lstat(path.join(config.databaseDirectory,'osv-scalibr',eco,'all.zip')); present=stat.isFile()&&!stat.isSymbolicLink()&&stat.size===item?.bytes&&/^[a-f0-9]{64}$/.test(item?.sha256||''); } catch {}
      ecosystems[eco] = { status: present && age >= 0 && age < 72*3600000 ? 'ready' : 'stale', downloadedAt: item?.downloadedAt || null };
    }
    return { status: Object.values(ecosystems).every(e=>e.status==='ready') ? 'ready' : 'partial', version: SCANNER_VERSION, ecosystems };
  } catch { return { status: 'missing', reason: 'Offline-Datenbank nicht lesbar.' }; }
}

export function applyOfflineResult(components, data, { checkedAt, databaseAt } = {}) {
  if (!Array.isArray(data?.results)) throw new Error('Scannerantwort unvollständig');
  const results = new Map();
  for (const r of data.results) {
    if (!Array.isArray(r.packages)) throw new Error('Paketantwort unvollständig');
    for (const p of r.packages) {
      if (!p.package?.name || !p.package?.version || !p.package?.ecosystem || (p.vulnerabilities && !Array.isArray(p.vulnerabilities))) throw new Error('Paketantwort ungültig');
      if(results.has(identity(p.package)))throw new Error('Doppelter Paketstatus');
      results.set(identity(p.package), p);
    }
  }
    if (components.some(c=>!results.has(identity(c)))) throw new Error('Nicht alle Pakete vom Scanner bestätigt');
  const issues = [];
  for (const c of components) {
    const found = (results.get(identity(c)).vulnerabilities || []).filter(v=>!v.withdrawn);
    for (const v of found) {
      if (!/^[A-Za-z0-9_.-]{1,120}$/.test(v.id)) throw new Error('Ungültiger Fund');
      const raw = String(v.database_specific?.severity || '').toLowerCase();
      const severity = raw === 'moderate' ? 'medium' : ['critical','high','medium','low'].includes(raw) ? raw : 'unknown';
      issues.push({ name: c.name, version: c.version, id: v.id, title: String(v.summary || v.id).slice(0,500), severity, source: 'OSV-Offline', url: 'https://osv.dev/vulnerability/'+encodeURIComponent(v.id), fixed: [], file: c.file });
    }
    c.checks.vulnerability = { status: found.length ? 'findings' : 'no-known-findings', source: 'OSV-Offline', checkedAt, databaseAt };
  }
  return issues;
}

const FAILURE_REASONS = {
  'offline-not-configured': 'Offline-Prüfung noch nicht vollständig eingerichtet.',
  'offline-scanner-unavailable': 'Der festgelegte Offline-Scanner ist nicht verfügbar.',
  'offline-scanner-integrity': 'Die Prüfsumme des Offline-Scanners stimmt nicht überein.',
  'offline-database-unavailable': 'Die benötigte Offline-Datenbank ist nicht vollständig verfügbar.',
  'offline-database-stale': 'Die benötigte Offline-Datenbank fehlt oder ist älter als 72 Stunden.',
  'offline-database-integrity': 'Die Prüfsumme der Offline-Datenbank stimmt nicht überein.',
  'offline-workdir-unavailable': 'Der private Arbeitsordner ist nicht verfügbar.',
  'offline-timeout': 'Der Offline-Abgleich hat das feste Zeitlimit erreicht. Die Pakete bleiben ungeprüft.',
  'offline-output-limit': 'Die Scanner-Ausgabe überschreitet das feste Größenlimit.',
  'offline-process-failed': 'Der Offline-Scanner konnte den Abgleich nicht abschließen.',
  'offline-response-incomplete': 'Der Scanner hat nicht alle angefragten Pakete vollständig bestätigt.',
  'offline-incomplete': 'Die Offline-Prüfung war unvollständig.',
};

// Nur feste Meldungen ausgeben, niemals externe Fehlertexte oder private Dateipfade.
export function offlineFailure(code) {
  const reasonCode = Object.hasOwn(FAILURE_REASONS,code) ? code : 'offline-incomplete';
  return { status:'unchecked',source:'OSV-Offline',reasonCode,reason:FAILURE_REASONS[reasonCode] };
}

/** Nur eine bereinigte SBOM; keine Projektdateien, Plugins, Resolver oder Build-Skripte. */
export async function offlineAudit(components, config, { run } = {}) {
  const eligible = components.filter(c=>c.kind==='package' && c.exact && c.publicSource && PURL[c.ecosystem] && c.scope!=='lock-history');
  if (!eligible.length) return [];
  let dir, failure = 'offline-not-configured';
  try {
    if (!config?.binary || !config?.databaseDirectory || !config?.workdir) throw new Error('Offline-Prüfung fehlt');
    failure = 'offline-scanner-unavailable';
    await regular(config.binary, 80*1024*1024);
    failure = 'offline-scanner-integrity';
    if (!SCANNER_HASHES.has(await sha256(config.binary))) throw new Error('Scanner-Prüfsumme unbekannt');
    failure = 'offline-database-unavailable';
    const manifest = JSON.parse(await readSafe(config.databaseDirectory,'manifest.json'));
    const ecosystems = [...new Set(eligible.map(c=>c.ecosystem))];
    const databaseAt = {};
    for (const eco of ecosystems) {
      const item = manifest[eco], age = Date.now() - Date.parse(item?.downloadedAt);
      failure = 'offline-database-stale';
      if (!item || !(age >= 0 && age < 72*3600000) || !/^[a-f0-9]{64}$/.test(item.sha256 || '')) throw new Error('Datenbank fehlt oder veraltet');
      const file = path.join(config.databaseDirectory,'osv-scalibr',eco,'all.zip');
      failure = 'offline-database-unavailable';
      await regular(file,320*1024*1024);
      failure = 'offline-database-integrity';
      if ((await sha256(file)) !== item.sha256) throw new Error('Datenbank-Prüfsumme falsch');
      databaseAt[eco] = item.downloadedAt;
    }
    failure = 'offline-workdir-unavailable';
    if ((await fs.lstat(config.workdir)).isSymbolicLink()) throw new Error('Ungültiger Arbeitsordner');
    dir = await fs.mkdtemp(path.join(config.workdir,'offline-'));
    await fs.chmod(dir,0o700);
    const unique = [...new Map(eligible.map(c=>[identity(c),c])).values()];
    const bom = { bomFormat:'CycloneDX', specVersion:'1.5', version:1, components: unique.map(c=>({ type:'library', name:c.name, version:c.version,
      purl:'pkg:'+PURL[c.ecosystem]+'/'+c.name.split('/').map(encodeURIComponent).join('/')+'@'+encodeURIComponent(c.version) })) };
    await fs.writeFile(path.join(dir,'inventory.cdx.json'),JSON.stringify(bom),{mode:0o600});
    await fs.writeFile(path.join(dir,'osv-scanner.toml'),'',{mode:0o600});
    const data = await scanOfflineInventory(config.binary,dir,config.databaseDirectory,{run});
    failure = 'offline-response-incomplete';
    return applyOfflineResult(eligible,data,{checkedAt:new Date().toISOString(),databaseAt});
  } catch (error) {
    const code = error instanceof OfflineScannerFailure ? error.reasonCode : failure;
    for (const c of eligible) c.checks.vulnerability = offlineFailure(code);
    return [];
  } finally { if (dir) await fs.rm(dir,{recursive:true,force:true}); }
}
