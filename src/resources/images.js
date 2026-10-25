import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFile} from 'node:child_process';
import {promisify} from 'node:util';
import {readSafe,writePrivate} from './files.js';
import {sha256} from './offline.js';

export const TRIVY_VERSION='0.76.1';
export const TRIVY_HASHES=new Set(['5c03401caf9d3cc04d9e0c7f3abd102e97518e8237465cce702162b605535e8d','d1dfca6ae10a1ad4540bb94e64f0510077c47008920a9b554986b39dca573d83']);
export const allowedImage=ref=>typeof ref==='string'&&/^(?:docker\.io\/library\/)?(?:php|wordpress|mariadb|nginx):[A-Za-z0-9_.-]{1,100}(?:@sha256:[a-f0-9]{64})?$/.test(ref);
const filename=ref=>'image-'+crypto.createHash('sha256').update(ref).digest('hex')+'.json';
const normalized=ref=>ref.replace(/^docker\.io\/library\//,'');
const fresh=(date,now,limit)=>now-Date.parse(date)>=0&&now-Date.parse(date)<limit;

export function imageRecord(reference,data,databaseAt,now=Date.now()) {
 if(!allowedImage(reference)||data?.SchemaVersion!==2||data.ArtifactType!=='container_image'||data.Trivy?.Version!==TRIVY_VERSION||!Array.isArray(data.Results)||!fresh(databaseAt,now,72*3600000))throw new Error('Imageprüfung unvollständig');
 const name=normalized(reference).split(':')[0],pinned=reference.match(/@sha256:[a-f0-9]{64}$/)?.[0].slice(1);
 const digests=(data.Metadata?.RepoDigests||[]).filter(x=>new RegExp('^(?:docker.io/library/|library/)?'+name+'@sha256:[a-f0-9]{64}$').test(x));
 const digest=digests[0]?.split('@')[1];
 if(!digest||(pinned&&pinned!==digest)||normalized(data.ArtifactName)!==normalized(reference))throw new Error('Image-Digest passt nicht');
 const osResults=data.Results.filter(r=>r.Class==='os-pkgs'&&((Array.isArray(r.Packages)&&r.Packages.length>0)||(Number.isSafeInteger(r.PackageCount)&&r.PackageCount>0)));
 if(!osResults.length)throw new Error('Image-Pakete oder Supportnachweis fehlen');
 const issues=[];let packageCount=0;
 if(data.Metadata?.OS?.EOSL===true)issues.push({id:'image-eol',package:data.Metadata.OS.Family,installed:data.Metadata.OS.Name,fixed:'',severity:'high',title:'Herstellerunterstützung des Basis-Images beendet',url:'https://trivy.dev/docs/latest/guide/scanner/vulnerability/'});
 for(const result of data.Results) {
  if(result.Misconfigurations||result.Secrets)throw new Error('Unerwartete Scannerart');
  packageCount+=result.PackageCount??(result.Packages||[]).length;
  for(const v of result.Vulnerabilities||[]) {
   if(!/^[A-Za-z0-9][A-Za-z0-9._:-]{1,120}$/.test(v.VulnerabilityID))throw new Error('Ungültiger Imagebefund');
   issues.push({id:v.VulnerabilityID,package:String(v.PkgName).slice(0,200),installed:String(v.InstalledVersion).slice(0,100),fixed:String(v.FixedVersion||'').slice(0,100),severity:['LOW','MEDIUM','HIGH','CRITICAL'].includes(v.Severity)?v.Severity.toLowerCase():'unknown',title:String(v.Title||v.VulnerabilityID).slice(0,500),url:/^https:\/\/(?:avd\.aquasec\.com|nvd\.nist\.gov|security-tracker\.debian\.org)\//.test(v.PrimaryURL||'')?v.PrimaryURL:'https://avd.aquasec.com/nvd/'+encodeURIComponent(v.VulnerabilityID.toLowerCase())});
  }
 }
 return {reference,digest,scanner:TRIVY_VERSION,checkedAt:new Date(now).toISOString(),databaseAt,platform:'linux/amd64',packageCount,issues};
}

export async function imageAudit(c,config) {
 if(c.kind!=='container'||!config?.directory)return [];
 if(!allowedImage(c.name)){c.checks.vulnerability.reason='Kein freigegebenes öffentliches Basis-Image; privates Artefakt benötigt eigenen Nachweis.';return [];}
 try {
  let requests=[];try{requests=JSON.parse(await readSafe(config.directory,'requests.json'));}catch{}
  if(!requests.includes(c.name)&&requests.length<40)await writePrivate(path.join(config.directory,'requests.json'),JSON.stringify([...requests,c.name]));
  let item;try{item=JSON.parse(await readSafe(config.directory,filename(c.name)));}catch{if(c.name.includes('@sha256:'))item=JSON.parse(await readSafe(config.directory,filename(c.name.split('@')[0])));else throw new Error('Imagebericht fehlt');}
  const now=Date.now(),pinned=c.name.match(/@sha256:[a-f0-9]{64}$/)?.[0].slice(1);
  const matches=item.reference===c.name||(pinned&&normalized(item.reference).split('@')[0]===normalized(c.name).split('@')[0]&&item.digest===pinned);
  if(!matches||item.scanner!==TRIVY_VERSION||!/^sha256:[a-f0-9]{64}$/.test(item.digest)||!fresh(item.checkedAt,now,72*3600000)||!fresh(item.databaseAt,now,72*3600000)||!(item.packageCount>0)||!Array.isArray(item.issues))throw new Error('Imagebericht fehlt');
  if(pinned&&item.digest!==pinned)throw new Error('Anderes Image');
  c.imageEvidence={digest:item.digest,checkedAt:item.checkedAt,databaseAt:item.databaseAt,platform:item.platform,packageCount:item.packageCount,installed:false};
  c.checks.vulnerability={status:item.issues.length?'findings':'no-known-findings',source:'Trivy / OCI',checkedAt:item.checkedAt,databaseAt:{'Trivy':item.databaseAt},reason:'Referenziertes Basis-Image geprüft; keine Aussage über tatsächlich laufende Container.'};
  const rank={critical:4,high:3,medium:2,low:1,unknown:0};
  const sorted=[...item.issues].sort((a,b)=>(rank[b.severity]??0)-(rank[a.severity]??0));
  c.imageEvidence.issueCount=sorted.length;c.imageEvidence.examples=sorted.slice(0,8);
  if(!sorted.length)return [];
  return [{id:'image-'+item.digest.slice(7,19),name:c.name,version:item.digest,file:c.file,source:'Trivy / OCI',environment:'declared-image',severity:sorted[0].severity,url:sorted[0].url,title:`Referenz-Image: ${sorted.length} bekannte Paketbefunde; keine laufende Installation bestätigt.`,findingCount:sorted.length}];
 }catch{c.checks.vulnerability={status:'unchecked',reasonCode:'image-audit-incomplete',reason:'Öffentliches Referenz-Image registriert; vollständiger aktueller Scan fehlt oder das Image ist nicht erreichbar.'};return [];}
}

/** Ein öffentliches Image, ohne Docker-Daemon, Projektdateien, Secretsuche oder Containerstart. */
export async function refreshImageEvidence(config,{run=promisify(execFile),now=Date.now()}={}) {
 if(!config?.binary||!config?.directory||!config?.cacheDirectory)throw new Error('Image-Laufzeit fehlt');
 const stat=await fs.lstat(config.binary);if(!stat.isFile()||stat.isSymbolicLink()||stat.size>320*1024*1024||!TRIVY_HASHES.has(await sha256(config.binary)))throw new Error('Image-Scanner nicht bestätigt');
 let requests=[];try{requests=JSON.parse(await readSafe(config.directory,'requests.json'));}catch{}
 if(!Array.isArray(requests)||requests.length>40||requests.some(ref=>!allowedImage(ref)))throw new Error('Imageauswahl ungültig');
 let attempts={};try{attempts=JSON.parse(await readSafe(config.directory,'attempts.json'));}catch{}
 let selected;
 for(const ref of requests) {
  let record;try{record=JSON.parse(await readSafe(config.directory,filename(ref)));}catch{}
  if(!fresh(record?.checkedAt,now,24*3600000)&&!fresh(attempts[ref]?.at,now,3600000)){selected=ref;break;}
 }
 if(!selected)return {status:'current'};
 const dir=await fs.mkdtemp(path.join(config.directory,'scan-'));await fs.chmod(dir,0o700);
 try {
  const deadline=Date.now()+31000;
  const common=['image','--image-src','remote','--platform','linux/amd64','--scanners','vuln','--offline-scan','--disable-telemetry','--config','','--ignorefile','','--max-image-size','1500MB','--parallel','2','--timeout','28s','--cache-dir',config.cacheDirectory,'--quiet'];
  const options=()=>({cwd:dir,env:{PATH:'/usr/bin:/bin',HOME:dir,XDG_CONFIG_HOME:dir,GOMEMLIMIT:'768MiB'},encoding:'utf8',timeout:Math.max(1,deadline-Date.now()),killSignal:'SIGKILL',maxBuffer:16*1024*1024});
  // Erster Durchgang bestätigt nur die Image-Identität. Die Schweregrad-Auswahl ist kein Prüfergebnis.
  const metadata=JSON.parse((await run(config.binary,[...common,'--format','json','--list-all-pkgs=false','--severity','UNKNOWN',selected],options())).stdout);
  const digests=metadata.Metadata?.RepoDigests||[],name=normalized(selected).split(':')[0];
  const digest=digests.find(d=>new RegExp('^(?:docker.io/library/|library/)?'+name+'@sha256:[a-f0-9]{64}$').test(d))?.split('@')[1];
  if(!digest)throw new Error('Image-Identität fehlt');
  const pinned=normalized(selected).split('@')[0]+'@'+digest;
  // Vollständiger zweiter Abgleich gegen genau denselben Digest, ohne Filter oder große Beschreibungsfelder.
  const template=await fs.readFile(new URL('./image-findings.tpl',import.meta.url),'utf8');
  const findings=JSON.parse((await run(config.binary,[...common,'--skip-db-update','--format','template','--template',template,pinned],options())).stdout);
  const data={...metadata,Results:findings.Results};
  const db=JSON.parse(await readSafe(config.cacheDirectory,'db/metadata.json'));
  const record=imageRecord(selected,data,db.UpdatedAt,now);
  await writePrivate(path.join(config.directory,filename(selected)),JSON.stringify(record));
  attempts[selected]={at:new Date(now).toISOString(),status:'checked'};
  return {status:'updated',reference:selected,findings:record.issues.length};
 }catch {
  attempts[selected]={at:new Date(now).toISOString(),status:'failed'};
  return {status:'failed',reference:selected,reason:'Image nicht erreichbar, Datenbank veraltet oder begrenzte Prüfung unvollständig.'};
 }finally{await writePrivate(path.join(config.directory,'attempts.json'),JSON.stringify(attempts));await fs.rm(dir,{recursive:true,force:true});}
}
