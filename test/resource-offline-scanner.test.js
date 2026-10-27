import test from 'node:test';
import assert from 'node:assert/strict';
import { scanOfflineInventory } from '../src/resources/offline-scanner.js';
import { offlineFailure, applyOfflineResult } from '../src/resources/offline.js';

test('Privater Scanner bleibt offline, begrenzt und ohne geerbte Zugangsdaten',async()=>{
 const data={results:[]};
 const answer=await scanOfflineInventory('/verified/scanner','/private/work','/public/db',{run:async(binary,args,options)=>{
  assert.equal(binary,'/verified/scanner');
  for(const flag of ['--offline','--no-resolve','--no-ignore','--all-packages','--x-no-default-plugins','--x-plugins=sbom'])assert(args.includes(flag));
  assert.deepEqual(options.env,{PATH:'/usr/bin:/bin',HOME:'/private/work',GOMEMLIMIT:'384MiB'});
  assert.equal(options.timeout,32000);assert.equal(options.killSignal,'SIGKILL');assert.equal(options.maxBuffer,16*1024*1024);
  return {stdout:JSON.stringify(data)};
 }});
 assert.deepEqual(answer,data);
});
test('Scanner-Exit 1 mit vollständigen Testfunden wird ausgewertet',async()=>{
 const c={name:'example',version:'1.0.0',ecosystem:'npm',checks:{vulnerability:{status:'unchecked'}}};
 const data={results:[{packages:[{package:{name:'example',version:'1.0.0',ecosystem:'npm'},vulnerabilities:[{id:'GHSA-example-test',database_specific:{severity:'HIGH'}}]}]}]};
 const result=await scanOfflineInventory('scanner','/work','/db',{run:async()=>{throw Object.assign(new Error('privater Fehlertext'),{code:1,stdout:JSON.stringify(data)});}});
 assert.equal(applyOfflineResult([c],result).length,1);assert.equal(c.checks.vulnerability.status,'findings');
});
for(const [label,error,expected] of [
 ['Zeitlimit',{killed:true,signal:'SIGKILL'},'offline-timeout'],
 ['Ausgabegrenze',{code:'ERR_CHILD_PROCESS_STDIO_MAXBUFFER',killed:true},'offline-output-limit'],
 ['Prozessfehler',{code:2,stdout:'{"results":[]}'},'offline-process-failed'],
 ['fehlende Laufzeit',{code:'ENOENT'},'offline-process-failed'],
])test(label+' bleibt eindeutig ungeprüft und gibt keine Rohfehler weiter',async()=>{
 await assert.rejects(()=>scanOfflineInventory('scanner','/work','/db',{run:async()=>{throw Object.assign(new Error('secret-token /private/path'),error);}}),e=>{
  assert.equal(e.reasonCode,expected);const status=offlineFailure(e.reasonCode);assert.equal(status.status,'unchecked');assert(!JSON.stringify(status).includes('secret-token'));assert(!JSON.stringify(status).includes('/private/path'));return true;
 });
});
test('Ungültige oder fehlende Paketantwort wird nicht als leerer Erfolg behandelt',async()=>{
 await assert.rejects(()=>scanOfflineInventory('scanner','/work','/db',{run:async()=>({stdout:'not-json'})}),e=>e.reasonCode==='offline-response-incomplete');
 const data=await scanOfflineInventory('scanner','/work','/db',{run:async()=>({stdout:'{"results":[]}'})});
 assert.throws(()=>applyOfflineResult([{name:'example',version:'1',ecosystem:'npm'}],data));
});
test('Unbekannte Fehlertexte und Prototypnamen werden nie ausgegeben',()=>{
 for(const value of ['secret/path','__proto__','constructor',undefined])assert.equal(offlineFailure(value).reasonCode,'offline-incomplete');
});
