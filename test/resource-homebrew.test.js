import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {compareBrewVersion, homebrewIdentity, brewRangeAffected, assessBrewRecord, homebrewAudit} from '../src/resources/homebrew-audit.js';
import {parseHomebrewIndex, refreshHomebrewIndex, loadHomebrewIndex, HOMEBREW_SOURCE} from '../src/resources/homebrew-index.js';
import {coverageCounts} from '../src/resources/coverage.js';
import {auditProject} from '../src/resources/project-audit.js';

const now = Date.UTC(2026,10,10,12);
const lastModified = new Date(now - 3600000).toUTCString();
const row = (events = [{introduced:'0'},{fixed:'1.2.3_2'}]) => ({id:'BREW-example-CVE-2099-1234',summary:'Synthetischer Testbefund',affected:[{package:{ecosystem:'Homebrew',name:'example'},ranges:[{type:'ECOSYSTEM',events}]}]});
const catalog = rows => ({meta:{schema_version:'1.7.3',skipped_uncomparable:0,count:rows.length},advisories:{example:rows}});
const receipt = (over = {}) => JSON.stringify({source:{tap:'homebrew/core',spec:'stable',versions:{stable:'1.2.3',version_scheme:0},...over}});
const component = version => ({name:'example',version,kind:'runtime',observed:true,file:'Installationsbeleg',hostIdentity:homebrewIdentity('example',version,receipt()),checks:{vulnerability:{status:'unchecked'},support:{status:'not-applicable'}}});
const index = rows => ({advisories:{example:rows},meta:{lastModified,sha256:'a'.repeat(64)}});
async function temporary(fn) {
  const dir = await fs.realpath(await fs.mkdtemp(path.join(os.tmpdir(),'mgd-homebrew-')));
  try { return await fn(dir); } finally { await fs.rm(dir,{recursive:true,force:true}); }
}
const response = data => new Response(JSON.stringify(data), {headers:{'last-modified':lastModified,'content-type':'application/json'}});

test('Homebrew-Vergleich berücksichtigt Revisionen und numerische Datumssegmente', () => {
  assert.equal(compareBrewVersion('1.2.3_5','1.2.3_6'),-1);
  assert.equal(compareBrewVersion('1.2.3_7','1.2.3_6'),1);
  assert.equal(compareBrewVersion('2026.10.21','2026.7.4'),1);
  assert.equal(compareBrewVersion('1.2.0','1.2'),0);
  for (const version of ['1.2rc1','HEAD','r3222','1.2_foo','1'.repeat(129)]) assert.equal(compareBrewVersion(version,'1.2'),null);
});

test('Versionen an eingeführten, festen, letzten und exklusiven Grenzen', () => {
  const range = events => ({type:'ECOSYSTEM',events});
  assert.equal(brewRangeAffected('1.2.3_1',range([{introduced:'0'},{fixed:'1.2.3_2'}])),true);
  assert.equal(brewRangeAffected('1.2.3_2',range([{introduced:'0'},{fixed:'1.2.3_2'}])),false);
  assert.equal(brewRangeAffected('1.2',range([{introduced:'1'},{last_affected:'1.2'}])),true);
  assert.equal(brewRangeAffected('1.2',range([{introduced:'1'},{limit:'1.2'}])),false);
  assert.equal(brewRangeAffected('3',range([{introduced:'1'},{fixed:'2'},{introduced:'4'}])),false);
  assert.equal(brewRangeAffected('5',range([{introduced:'1'},{fixed:'2'},{introduced:'4'}])),true);
  for(const events of [[],[{fixed:'2'}],[{introduced:'3'},{fixed:'2'}],[{introduced:'1'},{introduced:'2'}],[{introduced:'0',fixed:'2'}],[{introduced:'1.0rc1'}]])
    assert.equal(brewRangeAffected('1',range(events)),null);
  assert.equal(brewRangeAffected('1',{type:'GIT',events:[{introduced:'0'}]}),null);
  assert.equal(brewRangeAffected('1',range([{introduced:'0'},{fixed:'2'},{introduced:'HEAD'}])),null);
});

test('Unbekannte Teilbereiche und uneindeutige Rücknahmen bleiben offen', () => {
  const r=row();r.affected.push({package:{ecosystem:'Homebrew',name:'example'},ranges:[{type:'GIT',events:[{introduced:'a'.repeat(40)}]}]});
  assert.equal(assessBrewRecord('1.2.3_3',r,now),null);
  assert.equal(assessBrewRecord('1.2.3_1',r,now),true);
  assert.equal(assessBrewRecord('1.2.3_1',{...row(),withdrawn:'invalid'},now),null);
  assert.equal(assessBrewRecord('1.2.3_1',{...row(),withdrawn:new Date(now+1).toISOString()},now),null);
  assert.equal(assessBrewRecord('1.2.3_1',{...row(),withdrawn:new Date(now-1).toISOString()},now),false);
  const malformed=row();malformed.affected[0].versions={invalid:true};
  assert.equal(assessBrewRecord('1.2.3_3',malformed,now),null);
});

test('Nur passender stabiler Core-Beleg bestätigt die Keg-Identität', () => {
  assert.equal(homebrewIdentity('example','1.2.3_2',receipt()).coreReceipt,true);
  for(const override of [{tap:'private/tap'},{spec:'head'},{versions:{stable:'9.9.9',version_scheme:0}},{versions:{stable:'1.2.3',version_scheme:1}}])
    assert.equal(homebrewIdentity('example','1.2.3',receipt(override)).coreReceipt,false);
});

test('Fehlende Katalogeinträge schließen keine Prüflücke', () => {
  const c=component('1.2.3_3');homebrewAudit(c,index([]),now);
  assert.equal(c.checks.vulnerability.status,'unchecked');
  assert.equal(c.checks.vulnerability.reasonCode,'homebrew-advisories-uncovered');
  assert.equal(coverageCounts([c]).gaps,1);
  homebrewAudit(c,null,now);assert.equal(c.checks.vulnerability.status,'unchecked');
});

test('Echter Befund, feste Version und unvollständiger Bereich getrennt', () => {
  const affected=component('1.2.3_1'),fixed=component('1.2.3_2');
  assert.equal(homebrewAudit(affected,index([row()]),now).length,1);
  assert.equal(affected.checks.vulnerability.status,'findings');
  assert.equal(homebrewAudit(fixed,index([row()]),now).length,0);
  assert.equal(fixed.checks.vulnerability.status,'no-known-findings');
  assert.equal(fixed.hostEvidence.scope,'recorded-homebrew-advisories');
  const uncertain=component('1.2.3_2');homebrewAudit(uncertain,index([row([{introduced:'HEAD'}])]),now);
  assert.equal(uncertain.checks.vulnerability.status,'unchecked');
  const wrong=component('1.2.3_2');wrong.hostIdentity.version='9.9';homebrewAudit(wrong,index([row()]),now);
  assert.equal(wrong.checks.vulnerability.status,'unchecked');
});

test('Katalog zählt alle Datensätze und prüft Formelzuordnung', () => {
  assert.equal(parseHomebrewIndex(JSON.stringify(catalog([row()]))).example.length,1);
  for(const mutate of [d=>d.meta.count++,d=>d.meta.skipped_uncomparable++,d=>d.meta.schema_version='2',d=>d.advisories.example[0].affected[0].package.name='foreign',d=>d.advisories.example[0].id='../../bad']) {
    const d=catalog([row()]);mutate(d);assert.throws(()=>parseHomebrewIndex(JSON.stringify(d)));
  }
});

test('Fester öffentlicher Abruf erhält keine Inventardaten, Cache bleibt privat', async () => temporary(async dir => {
  const calls=[];const request=async(...args)=>{calls.push(args);return response(catalog([row()]));};
  assert.equal((await refreshHomebrewIndex(dir,{request,now})).status,'updated');
  assert.equal(calls[0][0],HOMEBREW_SOURCE);assert.equal(calls[0][1].body,undefined);assert.equal(calls[0][1].redirect,'error');
  assert.equal((await loadHomebrewIndex(dir,now)).advisories.example.length,1);
  assert.equal((await fs.stat(path.join(dir,'advisories.json'))).mode&0o777,0o600);
  assert.equal((await refreshHomebrewIndex(dir,{request:()=>assert.fail('Kein erneuter Download'),now:now+1000})).status,'current');
  await fs.appendFile(path.join(dir,'advisories.json'),' ');await assert.rejects(loadHomebrewIndex(dir,now),/Prüfsumme/);
}));

test('Veraltete, zukünftige, zu große und umgeleitete Quellen bleiben ungeprüft', async () => temporary(async dir => {
  for(const headers of [{'last-modified':new Date(now-73*3600000).toUTCString()},{'last-modified':new Date(now+3600000).toUTCString()},{'last-modified':lastModified,'content-length':String(97*1024*1024)}])
    await assert.rejects(refreshHomebrewIndex(dir,{request:async()=>new Response('{}',{headers}),now}));
  await assert.rejects(refreshHomebrewIndex(dir,{request:async()=>new Response('',{status:302,headers:{location:'https://example.invalid'}}),now}));
  await assert.rejects(loadHomebrewIndex(dir,now));
  await refreshHomebrewIndex(dir,{request:async()=>response(catalog([row()])),now});
  await assert.rejects(loadHomebrewIndex(dir,now+73*3600000));
  await assert.rejects(loadHomebrewIndex(dir,now-1));
}));

test('Symlinks und abgebrochene Inhalte liefern keine Freigabe', async () => temporary(async dir => {
  await refreshHomebrewIndex(dir,{request:async()=>response(catalog([row()])),now});
  await fs.rename(path.join(dir,'advisories.json'),path.join(dir,'real.json'));
  await fs.symlink(path.join(dir,'real.json'),path.join(dir,'advisories.json'));
  await assert.rejects(loadHomebrewIndex(dir,now));
  await assert.rejects(refreshHomebrewIndex(path.join(dir,'partial'),{request:async()=>new Response('{',{headers:{'last-modified':lastModified}}),now}));
  await assert.rejects(loadHomebrewIndex(path.join(dir,'partial'),now));
}));

test('Größenlimit gilt auch ohne Content-Length; fremde Cachequellen werden abgewiesen', async () => temporary(async dir => {
  const chunk=Buffer.alloc(32*1024*1024);
  const oversized={ok:true,headers:new Headers({'last-modified':lastModified}),body:(async function*(){for(let i=0;i<4;i++)yield chunk;})()};
  await assert.rejects(refreshHomebrewIndex(dir,{request:async()=>oversized,now}),/zu groß/);
  await assert.rejects(loadHomebrewIndex(dir,now));
  await refreshHomebrewIndex(dir,{request:async()=>response(catalog([row()])),now});
  const file=path.join(dir,'metadata.json'),meta=JSON.parse(await fs.readFile(file,'utf8'));
  meta.source='https://example.invalid/advisories.json';await fs.writeFile(file,JSON.stringify(meta));
  await assert.rejects(loadHomebrewIndex(dir,now));
}));

test('Projektprüfung übernimmt Herstellerbefunde ohne zusätzliche Netzwerkabfrage', async () => {
  const c=component('1.2.3_1');
  const p={name:'synthetic-host',components:[c],repository:null,root:null,visibility:null};
  const issues=await auditProject(p,{homebrewIndex:index([row()])});
  assert.equal(issues.length,1);assert.equal(p.coverageCounts.checked,1);assert.equal(p.offline.status,'missing');
  assert.equal(c.checks.vulnerability.source,'Homebrew-Herstellerkatalog / offline');
});
