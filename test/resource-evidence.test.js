import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {refreshPublicProvenance} from '../src/resources/provenance-cache.js';
import {imageRecord,imageAudit,allowedImage} from '../src/resources/images.js';
import {vendorEvidence} from '../src/resources/vendor.js';

const now=Date.now(),digest='sha256:'+'a'.repeat(64);
const report=()=>({SchemaVersion:2,Trivy:{Version:'0.76.1'},ArtifactType:'container_image',ArtifactName:'nginx:alpine',Metadata:{RepoDigests:['nginx@'+digest],OS:{Family:'alpine'}},Results:[{Class:'os-pkgs',PackageCount:1,Vulnerabilities:[]}]});
const temp=async fn=>{const dir=await fs.realpath(await fs.mkdtemp(path.join(os.tmpdir(),'mgd-evidence-')));try{await fn(dir);}finally{await fs.rm(dir,{recursive:true,force:true});}};
test('Öffentlicher Katalog erhält keinen privaten Auswahlparameter und lädt vollständige Ranglistenseiten',()=>temp(async dir=>{
 const urls=[];const request=async url=>{urls.push(url);if(url.includes('/explore/')){const page=Number(new URL(url).searchParams.get('page'));return{packages:Array.from({length:100},(_,i)=>({name:'public/pkg'+((page-1)*100+i)}))};}const name=url.match(/p2\/(.*)\.json/)[1];return{packages:{[name]:[{name,version:'1.0.0',source:{url:'https://github.com/public/example',reference:'b'.repeat(40)}}]}};};
 await fs.writeFile(path.join(dir,'private-extra.json'),JSON.stringify({name:'secret/customer-code'}));
 const state=await refreshPublicProvenance(dir,{request,now,batchSize:2});
 assert.equal(state.total,500);assert.equal(state.ready,2);assert.equal(urls.length,7);assert(urls.every(u=>!u.includes('secret')&&!u.includes('customer')));
}));
test('Nur offizielle öffentliche Basis-Images sind für den Registry-Abruf zugelassen',()=>{
 assert(allowedImage('php:8.5-fpm@'+digest));assert(!allowedImage('private.example/secret:latest'));assert(!allowedImage('nginx:alpine --other'));assert(!allowedImage('nginx@'+digest));
});
test('Imagebericht benötigt vollständige Paketerkennung, passenden Digest und frische Datenbank',()=>{
 assert.equal(imageRecord('nginx:alpine',report(),new Date(now).toISOString(),now).packageCount,1);
 assert.throws(()=>imageRecord('nginx:alpine@sha256:'+'b'.repeat(64),report(),new Date(now).toISOString(),now));
 assert.throws(()=>imageRecord('nginx:alpine',{...report(),Results:[]},new Date(now).toISOString(),now));
 assert.throws(()=>imageRecord('nginx:alpine',report(),'2020-01-01',now));
});
test('Imagefund wird erhalten, ohne Installation auf dem Server zu behaupten',()=>temp(async dir=>{
 const data=report();data.Results[0].Vulnerabilities=[{VulnerabilityID:'CVE-2026-12345',PkgName:'example',InstalledVersion:'1',FixedVersion:'2',Severity:'HIGH',Title:'Beispiel'}];
 const record=imageRecord('nginx:alpine',data,new Date(now).toISOString(),now);
 const {createHash}=await import('node:crypto');await fs.writeFile(path.join(dir,'image-'+createHash('sha256').update('nginx:alpine').digest('hex')+'.json'),JSON.stringify(record));
 const c={kind:'container',name:'nginx:alpine',checks:{vulnerability:{status:'unchecked'}}};const issues=await imageAudit(c,{directory:dir});assert.equal(issues.length,1);assert.equal(c.checks.vulnerability.status,'findings');assert.equal(c.imageEvidence.installed,false);assert.equal(issues[0].environment,'declared-image');
}));
test('MariaDB-Hinweis behauptet ohne Backport-Nachweis keine bestätigte Serverlücke',async()=>{
 const c={name:'mariadb',version:'10.11.14',observed:true,checks:{vulnerability:{status:'unchecked'}}};
 const issues=await vendorEvidence(c,{fetcher:async()=>new Response('| CVE-2026-12345 | 8.0 | MariaDB 10.11.19 |')});
 assert.equal(issues[0].confidence,'upstream-only');assert.equal(c.checks.vulnerability.status,'unchecked');assert.equal(c.checks.vendor.status,'reported');
});
