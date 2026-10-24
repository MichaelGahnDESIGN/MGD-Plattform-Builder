import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { writePrivate, readSafe, isInside } from './files.js';
import { sha256 } from './offline.js';

const ECOSYSTEMS = ['Packagist','npm','Pub'];
/** Datenbank-Download ist vom privaten Abgleich getrennt; URL enthält keine Projektdaten. */
export async function refreshOfflineCache(directory, { fetcher = fetch, now = Date.now() } = {}) {
  await fs.mkdir(directory,{recursive:true,mode:0o700});
  if ((await fs.lstat(directory)).isSymbolicLink()) throw new Error('Ungültiger Datenbankordner');
  let manifest = {};
  try { manifest = JSON.parse(await readSafe(directory,'manifest.json')); } catch {}
  let eco;
  for (const name of ECOSYSTEMS) {
    let present=false;
    try { const stat=await fs.lstat(path.join(directory,'osv-scalibr',name,'all.zip')); present=stat.isFile()&&!stat.isSymbolicLink()&&stat.size===manifest[name]?.bytes&&/^[a-f0-9]{64}$/.test(manifest[name]?.sha256||''); } catch {}
    const age=now-Date.parse(manifest[name]?.downloadedAt);
    if(!present || !(age>=0&&age<86400000)) { eco=name; break; }
  }
  if (!eco) return {status:'current'};
  const url = 'https://osv-vulnerabilities.storage.googleapis.com/'+eco+'/all.zip';
  const destination = path.join(directory,'osv-scalibr',eco,'all.zip');
  const headers = await fetcher(url,{method:'HEAD',redirect:'error',signal:AbortSignal.timeout(8000)});
  if (!headers.ok) throw new Error('Datenbankquelle nicht erreichbar');
  const etag = headers.headers.get('etag');
  const modified = headers.headers.get('last-modified');
  let exists = false;
  try { const stat=await fs.lstat(destination); exists=stat.isFile()&&!stat.isSymbolicLink(); } catch {}
  if (exists && etag && etag === manifest[eco]?.etag && (await sha256(destination)) === manifest[eco]?.sha256) {
    manifest[eco].downloadedAt = new Date(now).toISOString();
    await writePrivate(path.join(directory,'manifest.json'),JSON.stringify(manifest));
    return {status:'verified',ecosystem:eco};
  }
  if (Number(headers.headers.get('content-length'))>320*1024*1024) throw new Error('Datenbankgröße überschritten');
  await fs.mkdir(path.dirname(destination),{recursive:true,mode:0o700});
  if(!isInside(await fs.realpath(directory),await fs.realpath(path.dirname(destination))))throw new Error('Datenbankpfad außerhalb des Speichers');
  for(const entry of await fs.readdir(path.dirname(destination),{withFileTypes:true})) {
    if(!entry.isFile()||!/^all\.zip\.download-[a-f0-9]{12}$/.test(entry.name))continue;
    const file=path.join(path.dirname(destination),entry.name);
    if((await fs.lstat(file)).mtimeMs < now-2*3600000)await fs.rm(file);
  }
  const temp=destination+'.download-'+crypto.randomBytes(6).toString('hex');
  let file;
  try {
    const response = await fetcher(url,{redirect:'error',signal:AbortSignal.timeout(30000)});
    if (!response.ok) throw new Error('Datenbankdownload fehlgeschlagen');
    file = await fs.open(temp,'wx',0o600);
    let size=0; const digest=crypto.createHash('sha256'), md5=crypto.createHash('md5');
    for await (const part of response.body) {
      size+=part.length;
      if (size>320*1024*1024) throw new Error('Datenbankgröße überschritten');
      digest.update(part);md5.update(part);await file.writeFile(part);
    }
    const checksum=(response.headers.get('x-goog-hash')||'').match(/(?:^|,\s*)md5=([^,]+)/)?.[1];
    const actualMd5=md5.digest('base64');
    if (checksum && actualMd5!==checksum) throw new Error('Transportprüfsumme falsch');
    if (size<22) throw new Error('Datenbank leer');
    await file.close();file=null;
    await fs.rename(temp,destination);
    manifest[eco]={downloadedAt:new Date(now).toISOString(),sha256:digest.digest('hex'),bytes:size,etag:response.headers.get('etag')||etag,lastModified:modified};
    await writePrivate(path.join(directory,'manifest.json'),JSON.stringify(manifest));
    return {status:'updated',ecosystem:eco,bytes:size};
  } finally { if(file)await file.close();await fs.rm(temp,{force:true}); }
}
