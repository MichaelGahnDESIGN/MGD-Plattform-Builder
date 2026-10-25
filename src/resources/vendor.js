/** Herstellerhinweise sind eine eigene Nachweisart; sie ersetzen keine Provider-Backports. */
export async function vendorEvidence(c,{fetcher=fetch}={}) {
 if(!c.observed||!['php','nodejs','mariadb','ubuntu'].includes(c.name))return [];
 const sources={php:'https://www.php.net/security/',nodejs:'https://nodejs.org/en/blog/vulnerability',mariadb:'https://mariadb.com/docs/release-notes/security',ubuntu:'https://ubuntu.com/security/notices'};
 const reasons={php:'Upstream-Version erfasst; Herkunft und Sicherheitskorrekturen des konkreten Provider-Builds sind nicht vollständig belegt.',nodejs:'Laufender Node-Prozess erfasst; ein vollständiger Herstellerabgleich einschließlich gebündelter Bibliotheken fehlt.',mariadb:'Datenbankversion beobachtet; zurückportierte Korrekturen kann nur der Betreiber des Datenbankservers bestätigen.',ubuntu:'Distributionsversion erfasst; installierte DEB-Versionen, laufender Kernel und Livepatch-Stand fehlen.'};
 c.checks.vendor={status:'unchecked',source:c.name,sourceUrl:sources[c.name],reason:reasons[c.name]};
 if(c.checks.vulnerability.status==='unchecked')Object.assign(c.checks.vulnerability,{reasonCode:'provider-evidence-missing',reason:reasons[c.name]});
 if(c.name!=='mariadb'||!/^10\.11\.\d+$/.test(c.version))return [];
 try {
  const response=await fetcher('https://raw.githubusercontent.com/mariadb-corporation/mariadb-docs/main/release-notes/community-server/10.11/what-is-mariadb-1011.md',{redirect:'error',signal:AbortSignal.timeout(7000),headers:{'User-Agent':'MGD-ResourceManager/0.10'}});
  if(!response.ok)throw new Error('Herstellerquelle fehlt');
  let text='';for await(const bytes of response.body){text+=Buffer.from(bytes).toString('utf8');if(text.length>1024*1024)throw new Error('Herstellerquelle zu groß');}
  const entries=[];
  for(const line of text.split('\n')) {
   const id=line.match(/CVE-\d{4}-\d{4,}/)?.[0],version=line.match(/10\.11\.(\d+)/)?.[1];
   if(line.trim().startsWith('|')&&id&&version&&Number(version)>Number(c.version.split('.')[2]))entries.push({id,fixed:'10.11.'+version});
  }
  if(!entries.length)return [];
  c.checks.vendor={status:'reported',source:'MariaDB',sourceUrl:sources.mariadb,checkedAt:new Date().toISOString(),reason:entries.length+' Herstellerkorrekturen liegen nach der beobachteten Upstream-Version. Hoster-Backports und konkrete Betroffenheit sind ungeklärt.',advisories:entries};
  return [{id:'vendor-mariadb-backports',name:c.name,version:c.version,source:'MariaDB (Hersteller)',severity:'high',confidence:'upstream-only',title:'Möglicher Herstellerbefund: neuere MariaDB-Sicherheitskorrekturen; Hoster-Backports ungeklärt',url:'https://github.com/mariadb-corporation/mariadb-docs/blob/main/release-notes/community-server/10.11/what-is-mariadb-1011.md',file:c.file}];
 }catch{return [];}
}
