import fs from "node:fs/promises";
import path from "node:path";
import crypto from "node:crypto";
import { discover, inspect } from "./inventory.js";
import { readSafe, writePrivate } from "./files.js";
import { githubAudit, gh } from "./github.js";
import { auditProject } from "./project-audit.js";
import { coverageCounts } from "./coverage.js";
import { requestJson } from "./network.js";
import { evidenceState } from './evidence.js';
import { refreshOfflineCache } from './offline-cache.js';
import { renderReport } from "./report.js";
import { inspectHost } from "./host.js";

export async function loadConfig(file) {
  const root = await fs.realpath(path.dirname(path.resolve(file)));
  const data = JSON.parse(await readSafe(root, path.basename(file)));
  if (
    data.schemaVersion !== 1 ||
    !Array.isArray(data.roots) ||
    !data.roots.length ||
    data.roots.length > 30 ||
    !data.roots.every((r) => typeof r === "string" && r.length < 2000)
  )
    throw new Error("Ungültige Projektregistrierung");
  if (
    !Array.isArray(data.owners) ||
    !data.owners.every((v) => /^[A-Za-z0-9_.-]{1,100}$/.test(v))
  )
    throw new Error("GitHub-Eigentümer nicht eindeutig");
  return { ...data, roots: data.roots.map((r) => path.resolve(root, r)) };
}

export function summaryFor(projects, issues) {
  const components = projects.flatMap((p) => p.components);
  const coverage = coverageCounts(components);
  const unchecked = components.filter(
    (c) => c.checks.vulnerability.status === "unchecked",
  ).length;
  const projectsIncomplete = projects.filter(
    (p) =>
      p.warnings.length ||
      p.unsupported.length ||
      p.coverage.some((c) => c.status === "not-observed"),
  ).length;
  return {
    projects: projects.length,
    components: components.length,
    findings: issues.length,
    critical: issues.filter((i) => i.severity === "critical").length,
    high: issues.filter((i) => i.severity === "high").length,
    unsupported: components.filter(
      (c) => c.checks.support.status === "unsupported",
    ).length,
    unchecked,
    ...coverage,
    projectsIncomplete,
    status: issues.length
      ? "findings"
      : unchecked || coverage.codeReview || coverage.declared || !projects.length || projectsIncomplete
        ? "incomplete"
        : "no-known-findings",
  };
}

export async function scan({
  config,
  online = false,
  github = false,
  host = false,
  output,
  request,
  api,
} = {}) {
  if (online && config.offline?.databaseDirectory && config.offline.refresh !== false) {
    for(let i=0;i<3;i++) {
      try { if((await refreshOfflineCache(config.offline.databaseDirectory)).status==='current')break; }
      catch { break; } // Ein Ausfall bleibt über die Datums-/Prüfstatus sichtbar.
    }
  }
  const discovery = await discover(config.roots);
  const projects = [];
  for (const p of discovery.projects) projects.push(await inspect(p));
  if (host)
    projects.push({
      name: "Lokale Entwicklungsumgebung",
      root: null,
      repository: null,
      manifests: [],
      warnings: [],
      unsupported: [],
      coverage: [],
      components: await inspectHost(),
    });
  const issues = [];
  const repositoryAudits = new Map();
  for (const project of projects) {
    const cached = repositoryAudits.get(project.repository);
    if (cached) {
      project.github = cached.result;
      project.visibility = cached.visibility;
      project.defaultBranch = cached.defaultBranch;
    } else {
      project.github = github
        ? await githubAudit(project, {
            owners: config.owners,
            ...(api ? { api } : {}),
          })
        : { status: "unchecked", reason: "GitHub-Prüfung nicht eingeschaltet" };
      if (project.repository)
        repositoryAudits.set(project.repository, {
          result: project.github,
          visibility: project.visibility,
          defaultBranch: project.defaultBranch,
        });
    }
    for (const issue of cached ? [] : project.github.alerts || [])
      issues.push({
        ...issue,
        project: project.name,
        repository: project.repository,
      });
    for (const c of project.components)
      for (const issue of (evidenceState(c)?.status==='reported' ? c.importedFindings : []) || [])
        issues.push({
          ...issue,
          name: c.name,
          version: c.version,
          project: project.name,
          repository: project.repository,
          importedAt: c.scannerTimestamp,
        });
    if (online) {
      const checked = await auditProject(project, {
        request: request || requestJson,
        cacheDirectory: output ? path.join(output, 'provenance') : null,
        images: config.images,
        offline: config.offline ? { ...config.offline, workdir: output } : null,
        api: github ? (api || ((endpoint) => gh(endpoint,{root:project.root || process.cwd()}))) : null,
      });
      issues.push(...checked.map(i => ({...i, project: project.name, repository: project.repository})));
    }
  }

  const report = {
    schemaVersion: 1,
    generatedAt: new Date().toISOString(),
    scope:
      "Lokale Projektdateien und ausdrücklich hinterlegte Beobachtungen; keine automatische Serverabfrage",
    sources: {
      osv: online,
      github,
      support: online,
      supportAuthority: "endoflife.date: Drittanbieter-Aggregat",
    },
    discoveryTruncated: discovery.truncated,
    unavailableRoots: discovery.unavailable,
    projects,
    issues,
    summary: summaryFor(projects, issues),
  };
  if (discovery.truncated || discovery.unavailable.length)
    report.summary.status = "incomplete";
  if (output) {
    await writePrivate(
      path.join(output, "report.json"),
      JSON.stringify(report, null, 2) + "\n",
    );
    await writePrivate(
      path.join(output, "index.html"),
      await renderReport(report),
    );
  }
  return report;
}

export async function trackChanges(report, file) {
  const state = {
    unavailableRoots: report.unavailableRoots || [],
    discoveryTruncated: report.discoveryTruncated === true,
    issues: report.issues
      .map((i) => [
        i.repository,
        i.project,
        i.id,
        i.name,
        i.version,
        i.severity,
        i.observed,
      ])
      .sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))),
    coverage: report.projects
      .map((p) => [
        p.repository,
        p.warnings,
        p.unsupported,
        p.github.status,
        p.components
          .map((c) => [
            c.name,
            c.version,
            c.observed,
            c.checks.vulnerability.status,
            c.checks.support.status,
            c.checks.support.reason,
          ])
          .sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))),
      ])
      .sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))),
  };
  const fingerprint = crypto
    .createHash("sha256")
    .update(JSON.stringify(state))
    .digest("hex");
  let previous;
  try {
    previous = JSON.parse(await fs.readFile(file, "utf8"));
  } catch (e) {
    if (e.code !== "ENOENT")
      throw new Error("Benachrichtigungsstand nicht lesbar");
  }
  const changed = previous?.fingerprint !== fingerprint;
  await writePrivate(
    file,
    JSON.stringify(
      { fingerprint, checkedAt: report.generatedAt, summary: report.summary },
      null,
      2,
    ) + "\n",
  );
  return changed;
}
