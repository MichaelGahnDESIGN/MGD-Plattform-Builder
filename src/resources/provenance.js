import path from 'node:path';
import crypto from 'node:crypto';
import { writePrivate, readSafe } from './files.js';
import { pool } from './network.js';

const validName = n => /^[a-z0-9_.-]+\/[a-z0-9_.-]+$/.test(n);
const normalize = v => String(v).replace(/^v/, '');
const ref = x => /^[a-f0-9]{40}$/i.test(x || '') ? x.toLowerCase() : null;
function publicUrl(value) {
  try {
    const u = new URL(value);
    if (u.protocol !== 'https:' || u.username || u.password || u.port || u.search || u.hash) return null;
    if (!['github.com','api.github.com','codeload.github.com','gitlab.com','bitbucket.org'].includes(u.hostname)) return null;
    return u.href.replace(/\.git$/, '').replace(/\/$/, '');
  } catch { return null; }
}

// Composer-Metadaten erben unveränderte Felder von der vorherigen Version.
export function expandComposerVersions(data, name) {
  if (!Array.isArray(data?.packages?.[name])) return [];
  let previous = {};
  return data.packages[name].map(item => {
    if (data.minified !== 'composer/2.0') return item;
    const next = { ...previous };
    for (const [k,v] of Object.entries(item)) { if (v === '__unset') delete next[k]; else next[k] = v; }
    previous = next;
    return next;
  });
}

export function sameComposerIdentity(c, p) {
  if (normalize(c.version) !== normalize(p.version) || !c.origin) return false;
  return ['source','dist'].some(k => {
    const a = c.origin[k], b = p[k];
    return a && b && ref(a.reference) && ref(a.reference) === ref(b.reference)
      && publicUrl(a.url) && (publicUrl(a.url) === publicUrl(b.url) || (Array.isArray(b.aliases) && b.aliases.map(publicUrl).includes(publicUrl(a.url))));
  });
}

export function composerRecord(data, name, checkedAt = new Date().toISOString()) {
  const clean = value => publicUrl(value?.url) && ref(value?.reference) ? { url: publicUrl(value.url), reference: ref(value.reference) } : undefined;
  const versions = expandComposerVersions(data, name).filter(p => p.name === name).map(p => ({ version: p.version, source: clean(p.source), dist: clean(p.dist) }));
  if (versions.length > 12000 || !versions.length) throw new Error('Öffentliche Metadaten unvollständig');
  return { name, checkedAt, versions };
}

export async function composerProvenance(project, { request, cacheDirectory, budgetMs = 12000 } = {}) {
  const components = project.components.filter(c => c.kind === 'package' && c.ecosystem === 'Packagist' && c.exact && validName(c.name));
  const names = [...new Set(components.map(c => c.name))];
  const deadline = Date.now() + budgetMs;
  await pool(names, async name => {
    const key = crypto.createHash('sha256').update(name).digest('hex') + '.json';
    let record;
    if (cacheDirectory) {
      try { record = JSON.parse(await readSafe(cacheDirectory, key)); } catch {}
    }
    const age = Date.now() - Date.parse(record?.checkedAt);
    const fresh = age >= 0 && age < 7 * 86400000 && record?.name === name && Array.isArray(record.versions);
    // Private Paketlisten verlassen den Rechner nicht. Nur zuvor öffentlich bestätigte Metadaten verwenden.
    if (!fresh && project.visibility === 'public' && request && Date.now() < deadline) {
      try {
        const data = await request('https://repo.packagist.org/p2/' + name + '.json');
        record = composerRecord(data, name);
        if (cacheDirectory) await writePrivate(path.join(cacheDirectory, key), JSON.stringify(record));
      } catch { return; }
    } else if (!fresh) return;
    for (const c of components.filter(c => c.name === name)) {
      if (record.versions.some(p => sameComposerIdentity(c, p))) {
        c.publicSource = true;
        c.provenance = { status: 'verified', source: 'Packagist', checkedAt: record.checkedAt };
      }
    }
  });
}
