import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import {constants} from 'node:fs';
import {writePrivate, readSafe} from './files.js';

export const HOMEBREW_SOURCE = 'https://formulae.brew.sh/api/advisories.json';
export const HOMEBREW_MAX_BYTES = 96 * 1024 * 1024;
const AGE = 72 * 3600000;
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
const recent = (date, now, limit = AGE) => now - Date.parse(date) >= 0 && now - Date.parse(date) < limit;

/** Öffentlicher Gesamtkatalog, niemals eine Auswahl aus einem privaten Inventar. */
export function parseHomebrewIndex(raw) {
  const data = JSON.parse(raw);
  if (data?.meta?.schema_version !== '1.7.3' || data.meta.skipped_uncomparable !== 0
      || !Number.isSafeInteger(data.meta.count) || data.meta.count < 1 || data.meta.count > 100000
      || !data.advisories || typeof data.advisories !== 'object' || Array.isArray(data.advisories)) throw new Error('Herstellerkatalog unvollständig');
  const names = Object.keys(data.advisories);
  if (!names.length || names.length > 20000 || names.some(n => !/^[a-z0-9][a-z0-9+_.@-]{0,120}$/.test(n))) throw new Error('Herstelleridentität unklar');
  let count = 0;
  for (const name of names) {
    const rows = data.advisories[name];
    if (!Array.isArray(rows) || rows.length > 10000) throw new Error('Herstellerkatalog unvollständig');
    count += rows.length;
    for (const row of rows) {
      if (!row || !/^BREW-[A-Za-z0-9_.@+-]{1,240}$/.test(row.id || '') || !Array.isArray(row.affected)
          || !row.affected.length || row.affected.length > 32
          || row.affected.some(a => a?.package?.ecosystem !== 'Homebrew' || a.package.name !== name)) throw new Error('Herstellerzuordnung unklar');
    }
  }
  if (count !== data.meta.count) throw new Error('Herstellerkatalog unvollständig');
  return data.advisories;
}

async function readCatalog(directory) {
  const file = path.join(directory, 'advisories.json');
  const handle = await fs.open(file, constants.O_RDONLY | constants.O_NOFOLLOW);
  try {
    const stat = await handle.stat();
    if (!stat.isFile() || stat.size > HOMEBREW_MAX_BYTES) throw new Error('Herstellerkatalog zu groß');
    if (path.dirname(await fs.realpath(file)) !== await fs.realpath(directory)) throw new Error('Ungültiger Cachepfad');
    return await handle.readFile();
  } finally { await handle.close(); }
}

export async function loadHomebrewIndex(directory, now = Date.now()) {
  const meta = JSON.parse(await readSafe(directory, 'metadata.json'));
  if (meta.source !== HOMEBREW_SOURCE || !recent(meta.downloadedAt, now) || !recent(meta.lastModified, now)
      || !/^[a-f0-9]{64}$/.test(meta.sha256 || '') || !(meta.bytes > 0 && meta.bytes <= HOMEBREW_MAX_BYTES)) throw new Error('Herstellernachweis fehlt oder ist veraltet');
  const raw = await readCatalog(directory);
  if (raw.length !== meta.bytes || hash(raw) !== meta.sha256) throw new Error('Hersteller-Prüfsumme falsch');
  return {advisories: parseHomebrewIndex(raw), meta};
}

export async function refreshHomebrewIndex(directory, {request = fetch, now = Date.now()} = {}) {
  try {
    const cached = await loadHomebrewIndex(directory, now);
    if (recent(cached.meta.downloadedAt, now, 24 * 3600000)) return {status: 'current'};
  } catch {}
  const response = await request(HOMEBREW_SOURCE, {redirect: 'error', signal: AbortSignal.timeout(25000), headers: {'User-Agent': 'MGD-ResourceManager/public-catalog'}});
  if (!response.ok || Number(response.headers.get('content-length')) > HOMEBREW_MAX_BYTES) throw new Error('Herstellerkatalog nicht verfügbar');
  const lastModified = response.headers.get('last-modified');
  if (!recent(lastModified, now)) throw new Error('Herstellerkatalog veraltet');
  const chunks = []; let size = 0;
  for await (const bytes of response.body) {
    size += bytes.length;
    if (size > HOMEBREW_MAX_BYTES) throw new Error('Herstellerkatalog zu groß');
    chunks.push(Buffer.from(bytes));
  }
  const raw = Buffer.concat(chunks);
  parseHomebrewIndex(raw);
  // Erst vollständige Daten, dann die zugehörige Prüfsumme freigeben. Ein Abbruch bleibt ungeprüft.
  await writePrivate(path.join(directory, 'advisories.json'), raw);
  await writePrivate(path.join(directory, 'metadata.json'), JSON.stringify({source: HOMEBREW_SOURCE, downloadedAt: new Date(now).toISOString(), lastModified, bytes: size, sha256: hash(raw)}));
  return {status: 'updated'};
}
