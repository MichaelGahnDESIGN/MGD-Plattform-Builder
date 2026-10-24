import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {parsePackages} from '../src/resources/parsers.js';
import {composerProvenance,sameComposerIdentity,expandComposerVersions} from '../src/resources/provenance.js';
import {describeCoverage,coverageCounts} from '../src/resources/coverage.js';
import {applyOfflineResult,offlineAudit,offlineState} from '../src/resources/offline.js';
import {refreshOfflineCache} from '../src/resources/offline-cache.js';
import {actionChecks} from '../src/resources/actions.js';
import {evidenceState} from '../src/resources/evidence.js';
const sha='a'.repeat(40);
const pkg=()=>({name:'vendor/library',version:'v1.2.3',kind:'package',ecosystem:'Packagist',exact:true,origin:{source:{url:'https://github.com/vendor/library.git',reference:sha}},checks:{vulnerability:{status:'unchecked'},support:{status:'unchecked'}}});
const temp=async fn=>{const root=await fs.realpath(await fs.mkdtemp(path.join(os.tmpdir(),'mgd-coverage-')));try{return await fn(root);}finally{await fs.rm(root,{recursive:true,force:true});}};

test('Composer prüft Version UND Quelle/Commit statt nur Paketnamen',()=>{
 const c=pkg();assert.equal(sameComposerIdentity(c,{version:'1.2.3',source:c.origin.source}),true);
 assert.equal(sameComposerIdentity(c,{version:'1.2.3',source:{...c.origin.source,reference:'b'.repeat(40)}}),false);
 assert.equal(sameComposerIdentity(c,{version:'1.2.4',source:c.origin.source}),false);
 assert.equal(sameComposerIdentity(c,{version:'1.2.3',source:{...c.origin.source,url:'https://user:secret@github.com/vendor/library'}}),false);
});
test('Composer übernimmt geerbte Metadaten und entfernt __unset',()=>{
 const versions=expandComposerVersions({minified:'composer/2.0',packages:{'vendor/library':[{name:'vendor/library',version:'1.2.3',source:{reference:sha}},{version:'1.2.2',source:'__unset'}]}},'vendor/library');
 assert.equal(versions[1].name,'vendor/library');assert.equal(versions[1].source,undefined);
});
test('Private Herkunftsprüfung verwendet nur zuvor öffentlichen Cache',()=>temp(async root=>{
 let calls=0;const request=async()=>{calls++;return{packages:{'vendor/library':[{name:'vendor/library',version:'1.2.3',source:pkg().origin.source}]}};};
 let c=pkg();await composerProvenance({visibility:'private',components:[c]},{request,cacheDirectory:root});assert.equal(c.publicSource,undefined);assert.equal(calls,0);
 await composerProvenance({visibility:'public',components:[pkg()]},{request,cacheDirectory:root});assert.equal(calls,1);
 c=pkg();await composerProvenance({visibility:'private',components:[c]},{request,cacheDirectory:root});assert.equal(c.publicSource,true);assert.equal(calls,1);
}));
test('Parser behält require-dev und verwirft Authentifizierungsdaten in Herkunfts-URLs',()=>{
 const [c]=parsePackages('composer.lock',JSON.stringify({'packages-dev':[{name:'vendor/library',version:'1.2.3',source:{url:'https://secret:token@github.com/vendor/library',reference:sha}}]}));
 assert.equal(c.dependencyGroup,'development');assert.equal(c.origin.source,undefined);assert(!JSON.stringify(c).includes('token'));
});
test('Deklaration und eigene Codeprüfung werden nicht zu einem grünen Paketbefund',()=>{
 const a={...pkg(),kind:'declaration'},b={...pkg(),kind:'plugin'};describeCoverage(a);describeCoverage(b);
 assert.equal(a.checks.vulnerability.status,'declared');assert.equal(b.checks.code.status,'unchecked');
 assert.deepEqual(coverageCounts([a,b]),{checked:0,gaps:0,declared:1,notApplicable:1,development:0,codeReview:1});
});
test('Offline-Antwort muss jedes angefragte Paket bestätigen',()=>{
 const c=pkg();assert.throws(()=>applyOfflineResult([c],{results:[]}));assert.equal(c.checks.vulnerability.status,'unchecked');
 const result={results:[{packages:[{package:{name:c.name,version:'1.2.3',ecosystem:'Packagist'}}]}]};
 assert.equal(applyOfflineResult([c],result,{checkedAt:'now'}).length,0);assert.equal(c.checks.vulnerability.status,'no-known-findings');
});
test('Offline-Funde werden nicht als erfolgreich leere Prüfung ausgegeben',()=>{
 const c=pkg(),data={results:[{packages:[{package:{name:c.name,version:'1.2.3',ecosystem:'Packagist'},vulnerabilities:[{id:'GHSA-test-1234',database_specific:{severity:'HIGH'}}]}]}]};
 const findings=applyOfflineResult([c],data);assert.equal(findings[0].severity,'high');assert.equal(c.checks.vulnerability.status,'findings');
});
test('Fehlende Scanner-/Datenbankkonfiguration bleibt unbekannt, kein Ersatz-Netzaufruf',async()=>{
 const c={...pkg(),publicSource:true};let called=false;await offlineAudit([c],null,{run:async()=>{called=true;}});
 assert.equal(called,false);assert.equal(c.checks.vulnerability.reasonCode,'offline-incomplete');assert.equal((await offlineState(null)).status,'missing');
});
test('Action-Pin muss einem exakten öffentlichen Release entsprechen',()=>temp(async root=>{
 const c={...pkg(),name:'vendor/action',version:sha,kind:'ci'},urls=[];
 const api=async p=>{urls.push(p);return p.endsWith('/git/matching-refs/tags/')?[{ref:'refs/tags/v1.2.3',object:{type:'commit',sha}}]:{private:false};};
 const request=async()=>({results:[{}]});
 await actionChecks({components:[c]},{api,request,cacheDirectory:root});
 assert.equal(c.checks.vulnerability.status,'no-known-findings');assert.equal(c.version,sha);assert.equal(c.actionResolution.version,'1.2.3');
 assert(urls.every(u=>u.startsWith('repos/vendor/action')));
}));
test('Unbekannte Action-Commits werden nicht anhand eines ungefähren Tags freigegeben',async()=>{
 const c={...pkg(),name:'vendor/action',version:sha,kind:'ci'};let sent=false;
 await actionChecks({components:[c]},{api:async p=>p.includes('/git/')?[]:{private:false},request:async()=>{sent=true;}});
 assert.equal(sent,false);assert.equal(c.checks.vulnerability.status,'unchecked');
});
test('Alte oder nicht identifizierte Scannerimporte bestätigen keinen aktuellen Befund',()=>{
 const c={scope:'scanner-import',scannerTimestamp:new Date().toISOString(),scannerSubject:'sha256:'+'a'.repeat(64)};
 assert.equal(evidenceState(c).status,'reported');assert.equal(evidenceState({...c,scannerSubject:null}).status,'unchecked');
 assert.equal(evidenceState({...c,scannerTimestamp:'2020-01-01'}).status,'unchecked');
});
test('Datenbank-Download verwirft übergroße Antwort vor dem eigentlichen Abruf',()=>temp(async root=>{
 let calls=0;await assert.rejects(()=>refreshOfflineCache(root,{fetcher:async()=>{calls++;return new Response(null,{headers:{'Content-Length':String(400*1024*1024)}});}}));assert.equal(calls,1);
}));

test('Annotierte Action-Tags müssen auf genau denselben Commit zeigen',async()=>{
 const c={...pkg(),name:'vendor/action',version:sha,kind:'ci'};
 const api=async p=>p.endsWith('/git/matching-refs/tags/')?[{ref:'refs/tags/v1.2.3',object:{type:'tag',sha:'b'.repeat(40)}}]:p.includes('/git/tags/')?{object:{type:'commit',sha}}:{private:false};
 await actionChecks({components:[c]},{api,request:async()=>({results:[{}]})});
 assert.equal(c.actionResolution.version,'1.2.3');assert.equal(c.version,sha);
});
test('Doppelte Offline-Paketantworten gelten als unvollständig',()=>{
 const c=pkg(),entry={packages:[{package:{name:c.name,version:'1.2.3',ecosystem:'Packagist'}}]};
 assert.throws(()=>applyOfflineResult([c],{results:[entry,entry]}));assert.equal(c.checks.vulnerability.status,'unchecked');
});
