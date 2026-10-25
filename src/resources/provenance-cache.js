import path from 'node:path';
import crypto from 'node:crypto';
import { readSafe, writePrivate } from './files.js';
import { pool } from './network.js';
import { composerRecord } from './provenance.js';

const NAME = /^[a-z0-9_.-]+\/[a-z0-9_.-]+$/;
const DAYS = 86400000;
const fileFor = name => crypto.createHash('sha256').update(name).digest('hex')+'.json';

async function publicRequest(url) {
  let response = await fetch(url, {redirect:'manual',signal:AbortSignal.timeout(8000),headers:{'User-Agent':'MGD-ResourceManager/0.10 (+https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder)'}});
  if(response.status===301&&new URL(url).hostname==='api.github.com'){
    const target=response.headers.get('location');
    if(!/^https:\/\/api\.github\.com\/repositories\/[0-9]+$/.test(target||''))throw new Error('Unbekannte Repository-Weiterleitung');
    response=await fetch(target,{redirect:'error',signal:AbortSignal.timeout(8000),headers:{'User-Agent':'MGD-ResourceManager/0.10'}});
  }
  if(!response.ok)throw new Error('Öffentlicher Katalog nicht erreichbar');
  const chunks=[];let size=0;
  for await(const bytes of response.body){size+=bytes.length;if(size>8*1024*1024)throw new Error('Katalogantwort zu groß');chunks.push(bytes);}
  return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

/** Fester öffentlicher Top-500-Bestand. Diese Wartung erhält keinerlei Projektinventar. */
export async function refreshPublicProvenance(directory,{request=publicRequest,now=Date.now(),batchSize=20,budgetMs=18000}={}) {
  let catalog;
  try{catalog=JSON.parse(await readSafe(directory,'public-catalog.json'));}catch{}
  const age=now-Date.parse(catalog?.listedAt);
  if(!(age>=0&&age<7*DAYS&&Array.isArray(catalog?.names)&&catalog.names.length===500&&catalog.names.every(n=>NAME.test(n)))) {
    const names=[];
    for(let page=1;page<=5;page++) {
      const data=await request('https://packagist.org/explore/popular.json?per_page=100&page='+page);
      if(!Array.isArray(data.packages)||data.packages.length!==100||data.packages.some(p=>!NAME.test(p.name)))throw new Error('Öffentliche Liste unvollständig');
      names.push(...data.packages.map(p=>p.name));
    }
    if(new Set(names).size!==500)throw new Error('Öffentliche Liste doppelt');
    catalog={listedAt:new Date(now).toISOString(),names};
    await writePrivate(path.join(directory,'public-catalog.json'),JSON.stringify(catalog));
  }
  const pending=[];let ready=0;let attempts={};try{attempts=JSON.parse(await readSafe(directory,'public-catalog-attempts.json'));}catch{}
  for(const name of catalog.names) {
    let value;try{value=JSON.parse(await readSafe(directory,fileFor(name)));}catch{}
    const age=now-Date.parse(value?.checkedAt);
    if(value?.name===name&&value.publicCatalogVersion===2&&Array.isArray(value.versions)&&age>=0&&age<5*DAYS)ready++;else if(!(now-Date.parse(attempts[name])>=0&&now-Date.parse(attempts[name])<DAYS))pending.push(name);
  }
  const deadline=Date.now()+budgetMs;let updated=0;
  await pool(pending.slice(0,Math.max(1,Math.min(100,batchSize))),async name=>{
    if(Date.now()>deadline)return;
    try {
      const record=composerRecord(await request('https://repo.packagist.org/p2/'+name+'.json'),name,new Date(now).toISOString());
      // Öffentlich belegter Projektumzug; keine Ableitung aus einem privaten Inventar.
      if(name==='vlucas/phpdotenv') {
        const old=await request('https://api.github.com/repos/vlucas/phpdotenv');
        const current=await request('https://api.github.com/repos/phpdotenv/phpdotenv');
        if(old.private===false&&current.private===false&&old.id===current.id&&current.full_name==='phpdotenv/phpdotenv')
          for(const version of record.versions)for(const type of ['source','dist']) {
            if(version[type]?.url.startsWith('https://github.com/phpdotenv/phpdotenv'))version[type].aliases=['https://github.com/vlucas/phpdotenv'];
            if(version[type]?.url.startsWith('https://api.github.com/repos/phpdotenv/phpdotenv/zipball/'))version[type].aliases=[version[type].url.replace('/phpdotenv/phpdotenv/','/vlucas/phpdotenv/')];
          }
      }
      record.publicCatalogVersion=2;
      await writePrivate(path.join(directory,fileFor(name)),JSON.stringify(record));updated++;delete attempts[name];
    }catch{attempts[name]=new Date(now).toISOString();} // Ein fehlender Ursprung bleibt eine Lücke; andere öffentliche Einträge weiter pflegen.
  });
  await writePrivate(path.join(directory,'public-catalog-attempts.json'),JSON.stringify(attempts));
  const state={status:updated?'updated':'current',source:'Packagist Top 500',listedAt:catalog.listedAt,checkedAt:new Date(now).toISOString(),ready:ready+updated,total:500,updated};
  await writePrivate(path.join(directory,'public-catalog-state.json'),JSON.stringify(state));
  return state;
}
