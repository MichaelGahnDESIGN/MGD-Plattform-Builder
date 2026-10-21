import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import YAML from "yaml";
import { foundationRoot } from "./paths.js";

/**
 * Mandatory companions: living documentation, Graphify (the machine-generated map of the
 * living documentation) and the Todo skill. This module reads the registry and inspects what
 * is installed; `companion-install.js` changes things.
 */

export const SCOPES = ["project", "user"];
export const AGENTS = ["claude", "codex"];

/** Skill folders per agent. Codex discovers `.agents/skills`; `.codex/skills` is the legacy location. */
export const AGENT_SKILL_DIRS = { claude: [".claude"], codex: [".agents", ".codex"] };
export const INSTALL_DIR = { claude: ".claude", codex: ".agents" };

const VERSION_PATTERN = /(\d+)\.(\d+)\.(\d+)/;
const TOOL_TIMEOUT_MS = 15000;
const DOCUMENT_EXTENSIONS = new Set([".md", ".html"]);

export async function loadCompanionRegistry() {
  const file = path.join(foundationRoot, "registry", "companions.yml");
  return YAML.parse(await fs.readFile(file, "utf8"));
}

export function runProcess(command, args, options = {}) {
  // Windows looks in the working directory before PATH unless told otherwise; a project must not win that lookup.
  const env = { ...(options.env || process.env), NoDefaultCurrentDirectoryInExePath: "1" };
  return new Promise((resolve) => {
    execFile(command, args, { timeout: TOOL_TIMEOUT_MS, ...options, env }, (error, stdout, stderr) => {
      const code = error ? (typeof error.code === "number" ? error.code : 1) : 0;
      resolve({ code, stdout: String(stdout || ""), stderr: String(stderr || ""), error });
    });
  });
}

async function exists(target) {
  return fs.access(target).then(() => true).catch(() => false);
}

/** Resolves a project-relative path and refuses anything that escapes the project root. */
export function resolveInside(root, relative) {
  if (typeof relative !== "string" || relative.length === 0 || path.isAbsolute(relative)) {
    throw new Error("Companion path must be a relative path inside the project: " + relative);
  }
  const resolved = path.resolve(root, relative);
  const inside = path.relative(root, resolved);
  if (inside === "" || inside.startsWith("..") || path.isAbsolute(inside)) {
    throw new Error("Companion path leaves the project directory: " + relative);
  }
  return resolved;
}

/** Where the companion's artifact lives. Graphify's output folder is fixed by the tool itself. */
export function companionSettings(companion, profile) {
  const configured = ((profile || {}).companions || {})[companion.project.profile_key] || {};
  const fixed = companion.kind === "tool";
  return { path: fixed ? companion.project.default_path : configured.path || companion.project.default_path };
}

/** The first MAJOR.MINOR.PATCH in a text, or null. */
export function parseVersion(text) {
  const match = VERSION_PATTERN.exec(text || "");
  return match ? match[0] : null;
}

/** Refuses to write through a symbolic link anywhere between the project root and the target. */
export async function assertNoSymlink(root, target) {
  const relative = path.relative(root, target);
  if (relative.startsWith("..") || path.isAbsolute(relative)) return; // outside the project (user scope): not checked
  let current = root;
  for (const segment of relative.split(path.sep)) {
    current = path.join(current, segment);
    const stat = await fs.lstat(current).catch(() => null);
    if (!stat) return; // does not exist yet, so nothing below it can be a link
    if (stat.isSymbolicLink()) throw new Error("Refusing to write through the symbolic link " + path.relative(root, current));
  }
}

export function compareVersions(found, minimum) {
  const a = VERSION_PATTERN.exec(found || "");
  const b = VERSION_PATTERN.exec(minimum || "");
  if (!a || !b) return -1;
  for (let index = 1; index <= 3; index += 1) {
    const difference = Number(a[index]) - Number(b[index]);
    if (difference !== 0) return difference;
  }
  return 0;
}

function skillCandidates(companion, skill, index) {
  return index === 0 ? [skill.name, ...(companion.aliases || [])] : [skill.name];
}

/** Finds every place where one skill is installed, for any agent and scope. */
export async function findSkill(companion, skill, index, { root, home }) {
  const found = [];
  const bases = { project: root, user: home };
  for (const scope of SCOPES) {
    for (const agent of AGENTS) {
      for (const dir of AGENT_SKILL_DIRS[agent]) {
        for (const name of skillCandidates(companion, skill, index)) {
          const folder = path.join(bases[scope], dir, "skills", name);
          if (await exists(path.join(folder, "SKILL.md"))) found.push({ agent, scope, dir: folder });
        }
      }
    }
  }
  return found;
}

async function inspectSkills(companion, context) {
  const skills = [];
  for (const [index, skill] of companion.skills.entries()) {
    skills.push({ ...skill, required: skill.required !== false, locations: await findSkill(companion, skill, index, context) });
  }
  const installed = skills.filter((skill) => skill.required).every((skill) => skill.locations.length > 0);
  const agents = AGENTS.filter((agent) =>
    skills.filter((skill) => skill.required).every((skill) => skill.locations.some((location) => location.agent === agent))
  );
  return { skills, installed, agents };
}

async function isExecutable(file) {
  return fs.access(file, fs.constants.X_OK).then(() => true).catch(() => false);
}

/**
 * Every executable called `command` in the user's own program folders: the absolute entries of PATH and
 * the folders uv and pipx install tools into. Nothing inside the project is ever looked at (not even a PATH entry that
 * points into it), because a read-only command must not run a program that a cloned repository brought along.
 */
export async function findCommands(command, { root, home, env = process.env }) {
  const suffixes = process.platform === "win32" ? [".exe", ".cmd", ""] : [""];
  const insideProject = (folder) => Boolean(root) && !path.relative(root, folder).startsWith("..");
  // Where uv and pipx put tools: their documented defaults and the environment variables that move them.
  const toolFolders = [env.UV_TOOL_BIN_DIR, env.XDG_BIN_HOME, env.PIPX_BIN_DIR, path.join(home, ".local", "bin")];
  const folders = [
    ...String(env.PATH || "").split(path.delimiter),
    ...toolFolders
  ].filter((entry) => entry && path.isAbsolute(entry) && !insideProject(entry));
  const found = [];
  for (const folder of folders) {
    for (const suffix of suffixes) {
      const candidate = path.join(folder, command + suffix);
      if (!found.includes(candidate) && await isExecutable(candidate)) found.push(candidate);
    }
  }
  return found;
}

export async function findCommand(command, context) {
  return (await findCommands(command, context))[0] || null;
}

const PYTHON_CANDIDATES = process.platform === "win32"
  ? ["python"]
  : ["python3.13", "python3.12", "python3.11", "python3.10", "python3"];
const PYTHON_VERSION = /Python (\d+)\.(\d+)\.(\d+)/;

/** Finds the first Python interpreter that satisfies "MAJOR.MINOR" (for example "3.10"). */
export async function findPython(minimum, context) {
  const [major, minor] = minimum.split(".").map(Number);
  for (const name of PYTHON_CANDIDATES) {
    const executable = await findCommand(name, context);
    if (!executable) continue;
    const result = await context.run(executable, ["--version"]);
    const match = PYTHON_VERSION.exec(result.stdout + " " + result.stderr);
    if (result.code !== 0 || !match) continue;
    if (Number(match[1]) > major || (Number(match[1]) === major && Number(match[2]) >= minor)) {
      return { path: executable, version: match[1] + "." + match[2] + "." + match[3] };
    }
  }
  return null;
}

export const PROJECT_VENV = path.join(".mgd", "graphify-venv");

/**
 * Reads the version of a project-local virtual environment from its package metadata. Nothing in the
 * environment is executed, and symbolic links are not followed.
 */
export async function inspectProjectVenv(root, pkg) {
  const venv = path.join(root, PROJECT_VENV);
  for (const link of [path.join(root, ".mgd"), venv]) {
    const stat = await fs.lstat(link).catch(() => null);
    if (!stat || stat.isSymbolicLink() || !stat.isDirectory()) return null;
  }
  const libraries = process.platform === "win32" ? [path.join(venv, "Lib")] : await fs.readdir(path.join(venv, "lib")).then((names) => names.map((name) => path.join(venv, "lib", name))).catch(() => []);
  for (const library of libraries) {
    const names = await fs.readdir(path.join(library, "site-packages")).catch(() => []);
    const dist = names.find((name) => name.toLowerCase().startsWith(pkg.name.toLowerCase() + "-") && name.endsWith(".dist-info"));
    const match = dist && VERSION_PATTERN.exec(dist);
    if (match) return { version: match[0], versionOk: compareVersions(match[0], pkg.min_version) >= 0 };
  }
  return null;
}

/**
 * Looks at every copy of the tool in the user's program folders (an old one earlier on PATH must not hide a
 * new one) and returns the first copy that is new enough, otherwise the first copy that runs at all. A
 * project-local environment counts as installed by its metadata, but is never executed by this CLI.
 */
export async function inspectTool(companion, context) {
  const { package: pkg } = companion;
  let fallback = null;
  for (const executable of await findCommands(pkg.command, context)) {
    const result = await context.run(executable, ["--version"]);
    const version = parseVersion(result.stdout + " " + result.stderr);
    if (result.code !== 0 || !version) continue;
    const found = { installed: true, executable, version, versionOk: compareVersions(version, pkg.min_version) >= 0 };
    if (found.versionOk) return found;
    fallback = fallback || found;
  }
  const venv = await inspectProjectVenv(context.root, pkg);
  if (venv && venv.versionOk) return { installed: true, executable: null, projectVenv: true, ...venv };
  return fallback || { installed: false, executable: null, version: null, versionOk: false };
}

async function listDocuments(directory) {
  const entries = await fs.readdir(directory, { withFileTypes: true }).catch(() => []);
  return entries.filter((entry) => entry.isFile() && DOCUMENT_EXTENSIONS.has(path.extname(entry.name).toLowerCase()));
}

/** The project artifact (docs folder, TODO.html, graphify-out) that proves the companion is in use. */
export async function inspectProjectArtifact(companion, settings, root) {
  const relative = settings.path;
  const target = resolveInside(root, relative);
  const isFile = path.extname(relative) !== "";
  if (isFile) return { path: relative, present: await exists(target) };

  const marker = companion.project.marker;
  if (await exists(path.join(target, marker))) return { path: relative, present: true };
  const documentsAllowed = path.extname(marker).toLowerCase() === ".md";
  return { path: relative, present: documentsAllowed && (await listDocuments(target)).length > 0 };
}

export async function inspectCompanions({ root, profile = {}, registry, home = os.homedir(), run = runProcess, env = process.env } = {}) {
  const context = { root, home, run, env };
  const results = [];
  for (const companion of registry.companions) {
    const settings = companionSettings(companion, profile);
    const tool = companion.kind === "tool" ? await inspectTool(companion, context) : null;
    const skills = companion.kind === "skill" ? await inspectSkills(companion, context) : null;
    // A bad path in the profile is a finding about that companion, not a crash of the whole command.
    const project = await inspectProjectArtifact(companion, settings, root)
      .catch((error) => ({ path: settings.path, present: false, error: error.message }));
    const installed = tool ? tool.installed && tool.versionOk : skills.installed;
    results.push({ companion, settings, tool, skills, project, installed, ok: installed && project.present });
  }
  return results;
}
