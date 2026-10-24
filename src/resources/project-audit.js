import { composerProvenance } from './provenance.js';
import { offlineAudit, offlineState } from './offline.js';
import { describeCoverage, coverageCounts } from './coverage.js';
import { osvAudit, supportAudit, supportProduct } from './audit.js';
import { actionChecks } from './actions.js';
import { evidenceState } from './evidence.js';

export async function auditProject(project, { request, cacheDirectory, offline, api } = {}) {
  const issues = [];
  await composerProvenance(project,{request,cacheDirectory});
  const eligible = project.components.filter(c=>c.kind==='package' && c.exact && c.publicSource && c.scope!=='lock-history');
  if (project.visibility === 'public' && request) {
    for (const i of await osvAudit(eligible,{request})) {
      const {component,...rest} = i;
      issues.push({...rest,name:component.name,version:component.version,file:component.file});
    }
  } else if (offline) issues.push(...await offlineAudit(project.components,offline));
  // Keine Paketnamen in Supportabfragen: supportAudit verwendet nur bekannte Produkte.
  if (request) await supportAudit(project.components,{request});
  issues.push(...await actionChecks(project,{request,api,cacheDirectory}));
  for (const c of project.components) {
    const evidence=evidenceState(c);
    if(evidence)c.checks.evidence=evidence;
    describeCoverage(c);
    if (c.checks.support.status==='unchecked' && !c.checks.support.reason) {
      c.checks.support = { status: c.checks.vulnerability.status==='declared' ? 'declared' : 'not-applicable',
        reason: supportProduct(c) ? 'Kein eindeutiger Supportnachweis verfügbar.' : 'Für diesen Eintrag ist kein allgemeiner Hersteller-Supportzyklus zugeordnet.' };
    }
    if (c.kind==='external' && c.name==='shopify-api' && /^\d{4}-(01|04|07|10)$/.test(c.version)) {
      const months = (new Date().getUTCFullYear()-Number(c.version.slice(0,4)))*12 + new Date().getUTCMonth()-(Number(c.version.slice(5))-1);
      c.checks.support = {status:months>=0 && months<12 ? 'supported' : 'unchecked',reason:months>=0 && months<12 ? 'Innerhalb der mindestens zwölfmonatigen stabilen API-Unterstützung; tatsächliche API-Nutzung nicht nachgewiesen.' : 'Supportplan beim Hersteller prüfen.',source:'Shopify',sourceUrl:'https://shopify.dev/docs/api/usage/versioning',checkedAt:new Date().toISOString()};
    }
    if (c.checks.support.status==='unsupported') issues.push({name:c.name,version:c.version,id:'support-'+c.name,source:'Support',severity:'high',title:'Support beendet',url:c.checks.support.sourceUrl});
  }
  project.coverageCounts = coverageCounts(project.components);
  project.offline = await offlineState(offline);
  return issues;
}
