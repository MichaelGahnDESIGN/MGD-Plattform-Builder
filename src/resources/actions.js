import path from 'node:path';
import crypto from 'node:crypto';
import { readSafe, writePrivate } from './files.js';
import { osvAudit } from './audit.js';
import { pool } from './network.js';

const sha = value => /^[a-f0-9]{40}$/i.test(value || '');
const version = value => /^v?\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?$/.test(value || '');
export async function actionChecks(project, { api, request, cacheDirectory, budgetMs=6000 } = {}) {
  if (!api || !request) return [];
  const groups=new Map();
  for(const c of project.components.filter(c=>c.kind==='ci')) {
    if(!/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(c.name) || !/^[A-Za-z0-9_.-]{1,100}$/.test(c.version))continue;
    const key=c.name+'@'+c.version;
    if(!groups.has(key))groups.set(key,[]);groups.get(key).push(c);
  }
  const deadline=Date.now()+budgetMs, mapped=[];
  await pool([...groups],async([key,components])=>{
    const first=components[0],file='action-'+crypto.createHash('sha256').update(key).digest('hex')+'.json';
    let record;
    if(cacheDirectory)try{record=JSON.parse(await readSafe(cacheDirectory,file));}catch{}
    const age=Date.now()-Date.parse(record?.checkedAt);
    if(!(record?.key===key && age>=0 && age<86400000 && sha(record.commit) && version(record.version))) {
      if(Date.now()>deadline)return;
      try {
        const base='repos/'+first.name;
        const meta=await api(base);
        if(meta.private!==false)return;
        let commit=sha(first.version) ? first.version : (await api(base+'/commits/'+encodeURIComponent(first.version))).sha;
        if(!sha(commit))return;
        const tags=await api(base+'/git/matching-refs/tags/');
        if(!Array.isArray(tags) || tags.length>10000)return;
        // Ein unveränderlicher SHA kann ein annotiertes Tag-Objekt bezeichnen.
        if(sha(first.version)&&tags.some(t=>t.object?.type==='tag'&&t.object.sha===first.version)){
          const tag=await api(base+'/git/tags/'+first.version);
          if(tag.object?.type!=='commit'||!sha(tag.object.sha))return;
          commit=tag.object.sha;
        }
        let exact=tags.find(t=>t.object?.type==='commit' && t.object.sha===commit && version(t.ref?.replace(/^refs\/tags\//,'')));
        if(!exact) {
          const annotated=tags.filter(t=>t.object?.type==='tag'&&sha(t.object.sha)&&version(t.ref?.replace(/^refs\/tags\//,''))).sort((a,b)=>b.ref.localeCompare(a.ref,undefined,{numeric:true})).slice(0,40);
          await pool(annotated,async tag=>{
            if(exact || Date.now()>deadline)return;
            const cache='tag-'+crypto.createHash('sha256').update(first.name+'@'+tag.object.sha).digest('hex')+'.json';
            let target;
            if(cacheDirectory)try{const value=JSON.parse(await readSafe(cacheDirectory,cache));if(value.tag===tag.object.sha&&sha(value.commit))target=value.commit;}catch{}
            if(!target) {
              const value=await api(base+'/git/tags/'+tag.object.sha);
              if(value.object?.type!=='commit'||!sha(value.object.sha))return;
              target=value.object.sha;
              if(cacheDirectory)await writePrivate(path.join(cacheDirectory,cache),JSON.stringify({tag:tag.object.sha,commit:target}));
            }
            if(target===commit)exact=tag;
          });
        }
        if(!exact)return;
        record={key,commit,version:exact.ref.replace(/^refs\/tags\/v?/,''),checkedAt:new Date().toISOString()};
        if(cacheDirectory)await writePrivate(path.join(cacheDirectory,file),JSON.stringify(record));
      }catch{return;}
    }
    const candidate={...first,version:record.version,ecosystem:'GitHub Actions',checks:{vulnerability:{status:'unchecked'}}};
    mapped.push({candidate,components,record});
  });
  const findings=await osvAudit(mapped.map(x=>x.candidate),{request});
  for(const item of mapped)for(const c of item.components){
    c.checks.vulnerability={...item.candidate.checks.vulnerability};
    c.actionResolution={commit:item.record.commit,version:item.record.version,checkedAt:item.record.checkedAt};
  }
  return findings.flatMap(i=>{
    const item=mapped.find(x=>x.candidate===i.component);
    return item.components.map(c=>{const{component,...rest}=i;return{...rest,name:c.name,version:c.version,file:c.file};});
  });
}
