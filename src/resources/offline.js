import fs from 'node:fs/promises';
import { createReadStream } from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { readSafe } from './files.js';

export const SCANNER_VERSION = '2.7.0';
export const SCANNER_HASHES = new Set([
  'd4926ef8da134332543be276dff157e4c170f99ad91fc48e89448a8816bdf70b',
  '160c6253e2922e9e0a6a9771c5c4c40a4b3c6090b34a3cdd5e80c96cc2e4218b',
]);
const PURL = { npm: 'npm', Packagist: 'composer', Pub: 'pub' };
const exec = promisify(execFile);
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

/** Nur eine bereinigte SBOM; keine Projektdateien, Plugins, Resolver oder Build-Skripte. */
export async function offlineAudit(components, config, { run = exec } = {}) {
  const eligible = components.filter(c=>c.kind==='package' && c.exact && c.publicSource && PURL[c.ecosystem] && c.scope!=='lock-history');
  if (!eligible.length) return [];
  let dir;
  try {
    if (!config?.binary || !config?.databaseDirectory || !config?.workdir) throw new Error('Offline-Prüfung fehlt');
    await regular(config.binary, 80*1024*1024);
    if (!SCANNER_HASHES.has(await sha256(config.binary))) throw new Error('Scanner-Prüfsumme unbekannt');
    const manifest = JSON.parse(await readSafe(config.databaseDirectory,'manifest.json'));
    const ecosystems = [...new Set(eligible.map(c=>c.ecosystem))];
    const databaseAt = {};
    for (const eco of ecosystems) {
      const item = manifest[eco], age = Date.now() - Date.parse(item?.downloadedAt);
      if (!item || !(age >= 0 && age < 72*3600000) || !/^[a-f0-9]{64}$/.test(item.sha256 || '')) throw new Error('Datenbank fehlt oder veraltet');
      const file = path.join(config.databaseDirectory,'osv-scalibr',eco,'all.zip');
      await regular(file,320*1024*1024);
      if ((await sha256(file)) !== item.sha256) throw new Error('Datenbank-Prüfsumme falsch');
      databaseAt[eco] = item.downloadedAt;
    }
    if ((await fs.lstat(config.workdir)).isSymbolicLink()) throw new Error('Ungültiger Arbeitsordner');
    dir = await fs.mkdtemp(path.join(config.workdir,'offline-'));
    await fs.chmod(dir,0o700);
    const unique = [...new Map(eligible.map(c=>[identity(c),c])).values()];
    const bom = { bomFormat:'CycloneDX', specVersion:'1.5', version:1, components: unique.map(c=>({ type:'library', name:c.name, version:c.version,
      purl:'pkg:'+PURL[c.ecosystem]+'/'+c.name.split('/').map(encodeURIComponent).join('/')+'@'+encodeURIComponent(c.version) })) };
    await fs.writeFile(path.join(dir,'inventory.cdx.json'),JSON.stringify(bom),{mode:0o600});
    await fs.writeFile(path.join(dir,'osv-scanner.toml'),'',{mode:0o600});
    const args = ['scan','source','--offline','--config='+path.join(dir,'osv-scanner.toml'),'--local-db-path='+config.databaseDirectory,'--no-resolve','--no-ignore','--no-call-analysis=go','--no-call-analysis=rust','--all-packages','--format=json','--verbosity=error','--x-no-default-plugins','--x-plugins=sbom',dir];
    let stdout;
    try { ({ stdout } = await run(config.binary,args,{cwd:dir,env:{PATH:'/usr/bin:/bin',HOME:dir,GOMEMLIMIT:'256MiB'},timeout:32000,maxBuffer:16*1024*1024,encoding:'utf8'})); }
    catch(e) { if (e.code === 1 && typeof e.stdout === 'string') stdout=e.stdout; else throw e; }
    const data = JSON.parse(stdout);
    return applyOfflineResult(eligible,data,{checkedAt:new Date().toISOString(),databaseAt});
  } catch {
    for (const c of eligible) c.checks.vulnerability = { status:'unchecked',source:'OSV-Offline',reasonCode:'offline-incomplete',reason:'Offline-Datenbank fehlt, ist veraltet oder die Prüfung war unvollständig.' };
    return [];
  } finally { if (dir) await fs.rm(dir,{recursive:true,force:true}); }
}
