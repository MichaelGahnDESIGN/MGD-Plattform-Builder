import fs from "node:fs/promises";
import path from "node:path";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import { SKIP, walk, readSafe } from "./files.js";
import { component, parsePackages, parseDeclarations } from "./parsers.js";
const exec = promisify(execFile);
const LOCKS = new Set([
  "package-lock.json",
  "npm-shrinkwrap.json",
  "pnpm-lock.yaml",
  "yarn.lock",
  "composer.lock",
  "Pipfile.lock",
  "uv.lock",
  "poetry.lock",
  "Cargo.lock",
  "go.sum",
  "pubspec.lock",
  "packages.lock.json",
  "Gemfile.lock",
  "gradle.lockfile",
  "bom.json",
  "trivy.json",
]);
const MANIFESTS = new Set([
  "package.json",
  "composer.json",
  "pyproject.toml",
  "Cargo.toml",
  "go.mod",
  "Gemfile",
  "pubspec.yaml",
  "pom.xml",
  "requirements.txt",
  "resources.observations.json",
]);
export const CATEGORIES = [
  "package",
  "runtime",
  "cms",
  "plugin",
  "theme",
  "container",
  "ci",
  "os",
  "database",
  "webserver",
  "external",
];

export async function repository(root) {
  try {
    const { stdout } = await exec(
      "git",
      ["-C", root, "config", "--get", "remote.origin.url"],
      {
        timeout: 5000,
        maxBuffer: 16384,
        env: {
          ...process.env,
          PATH:
            process.platform === "win32"
              ? process.env.PATH
              : "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin",
          NoDefaultCurrentDirectoryInExePath: "1",
          GIT_CONFIG_COUNT: "0",
        },
      },
    );
    // Niemals die rohe Remote-URL zurückgeben: sie könnte eingebettete Zugangsdaten enthalten.
    const m = stdout
      .trim()
      .match(
        /^(?:https:\/\/github\.com\/|git@github\.com:)([A-Za-z0-9_.-]+)\/([A-Za-z0-9_.-]+?)(?:\.git)?$/,
      );
    return m ? m[1] + "/" + m[2] : null;
  } catch {
    return null;
  }
}

export async function discover(
  roots,
  { maxProjects = 120, maxDepth = 5 } = {},
) {
  const projects = [],
    seen = new Set(),
    unavailable = [];
  async function visit(dir, depth) {
    if (projects.length >= maxProjects) return;
    let stat, entries;
    try {
      stat = await fs.lstat(dir);
      if (!stat.isDirectory() || stat.isSymbolicLink()) return;
      dir = await fs.realpath(dir);
      if (seen.has(dir)) return;
      seen.add(dir);
      entries = await fs.readdir(dir, { withFileTypes: true });
    } catch {
      return;
    }
    if (
      entries.some((e) => e.name === ".git") ||
      entries.some((e) => MANIFESTS.has(e.name) && e.isFile())
    ) {
      projects.push({
        root: dir,
        name: path.basename(dir),
        repository: await repository(dir),
      });
      return;
    }
    if (depth >= maxDepth) return;
    for (const e of entries)
      if (
        e.isDirectory() &&
        !e.isSymbolicLink() &&
        !e.name.startsWith(".") &&
        !SKIP.has(e.name)
      )
        await visit(path.join(dir, e.name), depth + 1);
  }
  for (const root of roots) {
    const resolved = path.resolve(root);
    try {
      const stat = await fs.lstat(resolved);
      if (!stat.isDirectory() || stat.isSymbolicLink())
        throw new Error("Kein regulärer Ordner");
      await fs.access(resolved, fs.constants.R_OK);
      await visit(resolved, 0);
    } catch {
      unavailable.push(resolved);
    }
  }
  return { projects, unavailable, truncated: projects.length >= maxProjects };
}

export async function inspect(project) {
  const { files, warnings } = await walk(project.root);
  const components = [],
    manifests = [],
    unsupported = [];
  for (const file of files) {
    const base = path.basename(file);
    const lock =
      LOCKS.has(base) ||
      base.endsWith(".cdx.json") ||
      /^requirements[-.].*\.txt$/.test(base);
    const declaration =
      MANIFESTS.has(base) ||
      base.startsWith("Dockerfile") ||
      /(?:docker-)?compose.*\.ya?ml$/.test(base) ||
      (file.split(path.sep).includes(".github") && /\.ya?ml$/.test(base)) ||
      base === "version.php" ||
      base === "info.xml" ||
      /^shopify.*\.toml$/.test(base);
    if (!lock && !declaration) {
      if (
        [
          "pnpm-lock.yaml",
          "yarn.lock",
          "bun.lock",
          "bun.lockb",
          "Paket.lock",
          "Podfile.lock",
        ].includes(base)
      )
        unsupported.push(file);
      continue;
    }
    try {
      const text = await readSafe(project.root, file);
      if (base !== "version.php") manifests.push(file);
      if (lock) components.push(...parsePackages(file, text));
      if (declaration) components.push(...parseDeclarations(file, text));
    } catch {
      warnings.push("Nicht ausgewertet: " + file);
    }
  }
  // Eigene Plugin-Header nur in den üblichen Einstiegsdateien lesen; PHP wird nie ausgeführt.
  for (const file of files.filter(
    (f) =>
      f.endsWith(".php") &&
      (f.split(path.sep).length <= 2 ||
        path.basename(f) === path.basename(path.dirname(f)) + ".php"),
  )) {
    try {
      const text = await readSafe(project.root, file);
      const n = text.match(/Plugin Name:\s*([^\r\n]+)/),
        v = text.match(/\bVersion:\s*([\d.]+)/);
      if (n && v)
        components.push(
          component(n[1].trim(), v[1], "", "plugin", file, { observed: false }),
        );
    } catch {}
  }
  for (const file of files.filter(
    (f) => path.basename(f) === "style.css" && f.split(path.sep).length <= 3,
  )) {
    try {
      const text = await readSafe(project.root, file);
      const n = text.match(/Theme Name:\s*([^\r\n]+)/),
        v = text.match(/\bVersion:\s*([\d.]+)/);
      if (n && v)
        components.push(
          component(n[1].trim(), v[1], "", "theme", file, { observed: false }),
        );
    } catch {}
  }
  const unique = new Map();
  for (const c of components) {
    const key = [c.kind, c.ecosystem, c.name, c.version, c.file].join("|");
    unique.set(key, {
      ...c,
      checks: {
        vulnerability: { status: "unchecked" },
        support: { status: "unchecked" },
      },
    });
  }
  const items = [...unique.values()];
  // Eine Bereichsangabe bleibt als Deklaration erhalten; ein Lockfile liefert die konkreten Pakete.
  return {
    ...project,
    components: items,
    manifests: [...new Set(manifests)],
    warnings,
    unsupported,
    coverage: CATEGORIES.map((kind) => ({
      kind,
      count: items.filter((c) => c.kind === kind).length,
      status: items.some((c) => c.kind === kind) ? "observed" : "not-observed",
    })),
  };
}
