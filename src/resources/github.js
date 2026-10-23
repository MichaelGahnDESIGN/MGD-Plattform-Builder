import { execFile } from "node:child_process";
import { promisify } from "node:util";
import YAML from "yaml";
import path from "node:path";
import { yaml } from "./parsers.js";
import { trustedProgram, trustedEnvironment } from "./process.js";
const exec = promisify(execFile);
export const validRepo = (value) =>
  typeof value === "string" && /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/.test(value);

export async function gh(
  endpoint,
  {
    method = "GET",
    body,
    empty = false,
    paginate = false,
    root = process.cwd(),
  } = {},
) {
  if (
    !/^repos\/[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_./%?=&-]+)?$/.test(
      endpoint,
    ) ||
    endpoint.includes("..")
  )
    throw new Error("GitHub-Pfad nicht zugelassen");
  const args = [
    "api",
    "--hostname",
    "github.com",
    "--method",
    method,
    endpoint,
    "-H",
    "Accept: application/vnd.github+json",
  ];
  if (paginate) {
    if (method !== "GET" || body !== undefined)
      throw new Error("Pagination nur beim Lesen");
    args.push("--paginate", "--slurp");
  }
  let child;
  // Zugangsdaten bleiben in der bestehenden GitHub-Anmeldung; keine Tokens im Prozessargument.
  const options = {
    timeout: 25000,
    maxBuffer: 8 * 1024 * 1024,
    env: await trustedEnvironment(root),
  };
  const executable = await trustedProgram("gh", root);
  if (body !== undefined) {
    args.push("--input", "-");
    const { spawn } = await import("node:child_process");
    child = spawn(executable, args, {
      ...options,
      stdio: ["pipe", "pipe", "pipe"],
      shell: false,
    });
    child.stdin.on("error", () => {});
    child.stdin.end(JSON.stringify(body));
    let size = 0,
      stdout = "";
    return new Promise((resolve, reject) => {
      child.stdout.on("data", (data) => {
        size += data.length;
        if (size > options.maxBuffer) child.kill();
        else stdout += data;
      });
      child.stderr.resume();
      child.on("error", () =>
        reject(new Error("GitHub-Werkzeug nicht verfügbar")),
      );
      child.on("close", (code) => {
        if (code !== 0) reject(new Error("GitHub-Anfrage fehlgeschlagen"));
        else {
          try {
            resolve(empty ? null : stdout ? JSON.parse(stdout) : null);
          } catch {
            reject(new Error("Ungültige GitHub-Antwort"));
          }
        }
      });
    });
  }
  try {
    const { stdout } = await exec(executable, args, options);
    const data = empty ? null : stdout ? JSON.parse(stdout) : null;
    if (!paginate) return data;
    if (!Array.isArray(data) || !data.every(Array.isArray))
      throw new Error("Unvollständige GitHub-Seiten");
    return data.flat();
  } catch {
    throw new Error("GitHub-Anfrage nicht verfügbar oder nicht berechtigt");
  }
}

export async function githubAudit(project, { api = gh, owners = [] } = {}) {
  if (api === gh)
    api = (endpoint, options) =>
      gh(endpoint, { ...options, root: project.root || process.cwd() });
  if (
    !validRepo(project.repository) ||
    !owners.includes(project.repository.split("/")[0])
  )
    return { status: "unchecked", reason: "Repository nicht freigegeben" };
  try {
    const meta = await api("repos/" + project.repository);
    if (typeof meta.private !== "boolean")
      throw new Error("Repository-Sichtbarkeit nicht bestätigt");
    project.visibility = meta.private ? "private" : "public";
    project.defaultBranch = meta.default_branch;
    // gh folgt den Link-Cursorn; Dependabot unterstützt keine page-Nummern mehr.
    const data = await api(
      "repos/" +
        project.repository +
        "/dependabot/alerts?state=open&per_page=100",
      { paginate: true },
    );
    if (!Array.isArray(data)) throw new Error("Unvollständige Alerts");
    const alerts = [];
    for (const a of data)
      alerts.push({
        id: a.security_advisory?.ghsa_id || "GitHub-" + a.number,
        title: String(a.security_advisory?.summary || "Sicherheitsfund").slice(
          0,
          500,
        ),
        severity: ["critical", "high", "medium", "low"].includes(
          a.security_advisory?.severity,
        )
          ? a.security_advisory.severity
          : "unknown",
        name: String(a.dependency?.package?.name || "Unbekannt"),
        ecosystem: String(a.dependency?.package?.ecosystem || ""),
        file: String(a.dependency?.manifest_path || ""),
        fixed: a.security_vulnerability?.first_patched_version?.identifier
          ? [a.security_vulnerability.first_patched_version.identifier]
          : [],
        source: "GitHub",
        url:
          typeof a.html_url === "string" &&
          a.html_url.startsWith(
            "https://github.com/" + project.repository + "/security/",
          )
            ? a.html_url
            : undefined,
      });
    return {
      status: alerts.length ? "findings" : "no-open-alerts",
      alerts,
      checkedAt: new Date().toISOString(),
    };
  } catch {
    return {
      status: "unchecked",
      reason: "GitHub-Sicherheitsdaten nicht verfügbar oder nicht berechtigt",
    };
  }
}

export function dependabotConfig(project, previous = null) {
  const config = previous ? yaml(previous) : { version: 2, updates: [] };
  if (config?.version !== 2 || !Array.isArray(config.updates))
    throw new Error("Vorhandene Dependabot-Konfiguration nicht verändert");
  for (const registry of Object.values(config.registries || {})) {
    for (const key of ["token", "password"])
      if (
        registry[key] &&
        !/^\$\{\{\s*secrets\.[A-Za-z0-9_]+\s*\}\}$/.test(registry[key])
      )
        throw new Error("Zugangsdaten nicht als Secret-Referenz hinterlegt");
    if (registry.url) {
      const u = new URL(registry.url);
      if (u.username || u.password)
        throw new Error("Zugangsdaten in Registry-URL");
    }
  }
  const desired = [];
  const add = (ecosystem, file) => {
    const directory =
      "/" +
      path
        .dirname(file)
        .split(path.sep)
        .filter((p) => p !== ".")
        .join("/");
    if (
      !desired.some(
        (x) =>
          x["package-ecosystem"] === ecosystem && x.directory === directory,
      )
    )
      desired.push({
        "package-ecosystem": ecosystem,
        directory,
        schedule: {
          interval: "daily",
          time: "08:00",
          timezone: "Europe/Berlin",
        },
        "open-pull-requests-limit": 3,
        "commit-message": { prefix: "deps" },
      });
  };
  const map = {
    "package.json": "npm",
    "composer.json": "composer",
    "pyproject.toml": "pip",
    "requirements.txt": "pip",
    "Pipfile.lock": "pip",
    "Cargo.toml": "cargo",
    "go.mod": "gomod",
    Gemfile: "bundler",
    "pubspec.yaml": "pub",
    "pom.xml": "maven",
    "gradle.lockfile": "gradle",
    "packages.lock.json": "nuget",
  };
  for (const file of project.manifests || []) {
    const base = path.basename(file);
    if (map[base]) add(map[base], file);
    if (base.startsWith("Dockerfile")) add("docker", file);
    if (file.split(path.sep).includes(".github") && /\.ya?ml$/.test(base))
      add("github-actions", "workflow.yml");
  }
  const updates = [...config.updates];
  for (const next of desired)
    if (
      !updates.some(
        (e) =>
          e["package-ecosystem"] === next["package-ecosystem"] &&
          (e.directory === next.directory ||
            e.directories?.includes(next.directory)),
      )
    )
      updates.push(next);
  return {
    changed: !previous || updates.length !== config.updates.length,
    text: YAML.stringify({ ...config, updates }, { version: "1.1" }),
    count: updates.length,
  };
}

export async function setupGithub(
  project,
  { api = gh, owner, journal = async () => {} } = {},
) {
  if (api === gh)
    api = (endpoint, options) =>
      gh(endpoint, { ...options, root: project.root || process.cwd() });
  if (
    !validRepo(project.repository) ||
    project.repository.split("/")[0] !== owner
  )
    throw new Error("Repository außerhalb der Freigabe");
  const endpoint = "repos/" + project.repository;
  const meta = await api(endpoint);
  if (!meta.permissions?.admin)
    throw new Error("Keine Administratorberechtigung");
  if (meta.archived || meta.fork)
    throw new Error(
      "Archivierte Repositories und Forks werden nicht verändert",
    );
  const branch = meta.default_branch;
  if (!/^[A-Za-z0-9_./-]+$/.test(branch))
    throw new Error("Branch nicht unterstützt");
  let previous = null,
    sha;
  // Zuerst die Dateiliste prüfen: ein Lesefehler darf nicht wie eine fehlende Datei wirken.
  const tree = await api(
    endpoint + "/git/trees/" + encodeURIComponent(branch) + "?recursive=1",
  );
  if (tree.truncated || !Array.isArray(tree.tree))
    throw new Error("Repository-Baum nicht vollständig");
  const found = tree.tree.find(
    (e) =>
      e.path === ".github/dependabot.yml" ||
      e.path === ".github/dependabot.yaml",
  );
  if (found) {
    const f = await api(
      endpoint +
        "/contents/" +
        found.path +
        "?ref=" +
        encodeURIComponent(branch),
    );
    if (f.encoding !== "base64" || f.size > 262144)
      throw new Error("Konfiguration nicht lesbar");
    previous = Buffer.from(f.content, "base64").toString("utf8");
    sha = f.sha;
  }
  const configuration = dependabotConfig(
    {
      ...project,
      manifests: (project.manifests || []).filter((f) =>
        tree.tree.some((e) => e.path === f.split(path.sep).join("/")),
      ),
    },
    previous,
  );
  if (!configuration.count)
    return {
      repository: project.repository,
      status: "not-applicable",
      reason: "Kein unterstütztes Paketmanifest gefunden",
    };
  let result = {
    repository: project.repository,
    status: "configured",
    previousConfigSha: sha || null,
    securitySettings: {},
    checks:
      "Bestehende Repository-CI; keine automatische Zusammenführung eingerichtet",
  };
  try {
    const security = await api(endpoint + "/automated-security-fixes");
    result.securitySettings.automatedSecurityFixes = security;
    await journal({ ...result, stage: "before-security-settings" });
    await api(endpoint + "/vulnerability-alerts", {
      method: "PUT",
      empty: true,
    });
    await api(endpoint + "/automated-security-fixes", {
      method: "PUT",
      empty: true,
    });
    const verified = await api(endpoint + "/automated-security-fixes");
    if (!verified?.enabled) throw new Error("Sicherheits-PRs nicht bestätigt");
    result.securitySettings.status = "enabled";
    await journal({ ...result, stage: "security-settings-verified" });
  } catch {
    result.securitySettings.status = "unchecked";
    result.securitySettings.reason =
      "GitHub-Sicherheitsberechtigung fehlt oder Quelle nicht erreichbar";
    await journal({ ...result, stage: "security-settings-unconfirmed" });
  }
  if (!configuration.changed) return result;
  const ref = await api(
    endpoint + "/git/ref/heads/" + encodeURIComponent(branch),
  );
  const newBranch = "codex/resource-security-setup";
  const open = await api(
    endpoint +
      "/pulls?state=open&head=" +
      encodeURIComponent(owner + ":" + newBranch),
  );
  if (!Array.isArray(open)) throw new Error("PR-Liste nicht vollständig");
  const existing = open.find(
    (p) => p.head?.ref === newBranch && p.base?.ref === branch,
  );
  if (existing)
    return {
      ...result,
      status: "pull-request",
      pullRequest: existing.html_url,
      reused: true,
    };
  // Bestehende Branches werden nicht überschrieben; ein Konflikt stoppt dieses Repository.
  await api(endpoint + "/git/refs", {
    method: "POST",
    body: { ref: "refs/heads/" + newBranch, sha: ref.object.sha },
  });
  await journal({
    ...result,
    stage: "setup-branch-created",
    branch: newBranch,
    baseCommit: ref.object.sha,
  });
  await api(
    endpoint + "/contents/" + (found?.path || ".github/dependabot.yml"),
    {
      method: "PUT",
      body: {
        message: "chore: ergänze sichere Abhängigkeitsprüfungen",
        content: Buffer.from(configuration.text).toString("base64"),
        branch: newBranch,
        ...(sha ? { sha } : {}),
      },
    },
  );
  const pr = await api(endpoint + "/pulls", {
    method: "POST",
    body: {
      title: "Automatische Abhängigkeits- und Sicherheitsupdates vorbereiten",
      head: newBranch,
      base: branch,
      body:
        "Ergänzt die erkannten Paketökosysteme für tägliche Dependabot-Prüfungen. Vorhandene Konfigurationen bleiben erhalten. GitHub-Sicherheitseinstellungen: " +
        (result.securitySettings.status === "enabled"
          ? "Sicherheitsmeldungen und automatische Sicherheits-PRs bestätigt."
          : "Berechtigung oder Aktivierung noch nicht bestätigt; die Konfiguration allein ersetzt diese Freigabe nicht.") +
        " Updates durchlaufen die vorhandene CI; es gibt keine automatische Zusammenführung, Veröffentlichung oder Änderung produktiver Laufzeiten. Keine Zugangsdaten oder lokalen Projektpfade in der Konfiguration.",
    },
  });
  return { ...result, status: "pull-request", pullRequest: pr.html_url };
}
