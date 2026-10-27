import { execFile } from "node:child_process";
import crypto from "node:crypto";
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

/** True when a `path.relative()` result points outside its base. A folder called `..bin` is inside; only `..` itself or `../x` is outside. */
export function isOutside(relative) {
  return relative === ".." || relative.startsWith(".." + path.sep) || path.isAbsolute(relative);
}

/** A value from an untrusted file, safe to print: control characters (terminal escapes, newlines) become "?". */
export function printable(value) {
  return String(value).replace(/[^\x20-\x7e\u00a0-\uffff]/g, "?").slice(0, 200);
}

const SEGMENT = /^[A-Za-z0-9_-][A-Za-z0-9_.-]*$/;
const RESERVED_SEGMENTS = new Set(["node_modules"]);

/**
 * Resolves a project-relative path from the profile (untrusted input). Every segment must be a plain name
 * (no dot-folders such as .git or .claude, no "..", no control characters), so the path can never escape the
 * project or point at tooling folders.
 */
export function resolveInside(root, relative) {
  if (typeof relative !== "string" || relative.length === 0 || path.isAbsolute(relative)) {
    throw new Error("Companion path must be a relative path inside the project: " + printable(relative));
  }
  const segments = relative.split("/");
  if (segments.some((segment) => !SEGMENT.test(segment) || RESERVED_SEGMENTS.has(segment))) {
    throw new Error("Companion path leaves the project directory or uses a reserved name: " + printable(relative));
  }
  const resolved = path.resolve(root, ...segments);
  const inside = path.relative(root, resolved);
  if (inside === "" || isOutside(inside)) {
    throw new Error("Companion path leaves the project directory: " + printable(relative));
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
  if (isOutside(relative)) return; // outside the project (user scope): not checked
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

const CASE_INSENSITIVE = process.platform === "darwin" || process.platform === "win32";
const canonical = (value) => (CASE_INSENSITIVE ? value.toLowerCase() : value);

/** True when `folder` is, or resolves into, the project: compared by real path, so symlinks, /var vs /private/var and case do not matter. */
export async function isInsideProject(root, folder) {
  if (!root) return false;
  const [realRoot, realFolder] = await Promise.all([fs.realpath(root).catch(() => path.resolve(root)), fs.realpath(folder).catch(() => path.resolve(folder))]);
  const relative = path.relative(canonical(realRoot), canonical(realFolder));
  return !isOutside(relative);
}

/** PATH entries a child process may use: absolute, existing, and not inside the project. */
export async function trustedPathEntries(root, env = process.env) {
  const entries = [];
  for (const entry of String(env.PATH || "").split(path.delimiter)) {
    if (!entry || !path.isAbsolute(entry) || entries.includes(entry)) continue;
    if (!await isInsideProject(root, entry)) entries.push(entry);
  }
  return entries;
}

/**
 * Wraps a process runner so every child gets a PATH without relative or in-project entries. Without this,
 * a bare `git`, `uv` or `python3` could resolve to a program inside the cloned repository.
 */
export function withTrustedPath(run, root, defaultEnv = process.env) {
  return async (command, args, options = {}) => {
    const base = options.env || defaultEnv;
    const PATH = (await trustedPathEntries(root, base)).join(path.delimiter);
    return run(command, args, { ...options, env: { ...base, PATH } });
  };
}

/**
 * Every executable called `command` in the user's own program folders: the absolute entries of PATH and
 * the folders uv and pipx install tools into. Nothing inside the project is ever looked at (not even a PATH entry that
 * points into it, however it is spelled), because a read-only command must not run a program that a cloned
 * repository brought along.
 */
export async function findCommands(command, { root, home, env = process.env }) {
  const suffixes = process.platform === "win32" ? [".exe", ".cmd", ""] : [""];
  // Where uv and pipx put tools: their documented defaults and the environment variables that move them.
  const toolFolders = [env.UV_TOOL_BIN_DIR, env.XDG_BIN_HOME, env.PIPX_BIN_DIR, path.join(home, ".local", "bin")];
  const folders = [];
  for (const entry of [...String(env.PATH || "").split(path.delimiter), ...toolFolders]) {
    if (entry && path.isAbsolute(entry) && !folders.includes(entry) && !await isInsideProject(root, entry)) folders.push(entry);
  }
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
 * Environments this CLI created are remembered in the user's own cache, never inside the project, because
 * anything inside a cloned repository can be forged. When the environment is created, a random token is
 * written into it and only the token's hash goes into the cache. A record is trusted only if the folder still
 * holds that token (a replaced or forged folder cannot know it) and is not a symbolic link. Inode numbers are
 * not used: Linux reuses them immediately.
 */
const TOKEN_FILE = ".mgd-venv-token";
const sha256 = (text) => crypto.createHash("sha256").update(text).digest("hex");
export function venvRecordFile(home) {
  return path.join(home, ".cache", "mgd-platform", "venvs.json");
}

async function readVenvRecords(home) {
  try {
    return JSON.parse(await fs.readFile(venvRecordFile(home), "utf8"));
  } catch {
    return {};
  }
}

export async function recordProjectVenv({ root, home, version }) {
  const venv = path.join(root, PROJECT_VENV);
  const token = crypto.randomBytes(24).toString("hex");
  await fs.writeFile(path.join(venv, TOKEN_FILE), token, { flag: "wx", mode: 0o600 });
  const records = await readVenvRecords(home);
  records[await fs.realpath(root)] = { token_sha256: sha256(token), version, recorded_at: new Date().toISOString() };
  await fs.mkdir(path.dirname(venvRecordFile(home)), { recursive: true });
  await fs.writeFile(venvRecordFile(home), JSON.stringify(records, null, 2) + "\n", "utf8");
}

/** The version of a project-local environment this CLI created. Nothing in the project is read for it or executed. */
export async function inspectProjectVenv(root, pkg, home) {
  if (!home) return null;
  const record = (await readVenvRecords(home))[await fs.realpath(root).catch(() => "")];
  if (!record || !record.version || !record.token_sha256) return null;
  const venv = path.join(root, PROJECT_VENV);
  const folder = await fs.lstat(venv).catch(() => null);
  if (!folder || folder.isSymbolicLink() || !folder.isDirectory()) return null;
  const file = path.join(venv, TOKEN_FILE);
  const tokenStat = await fs.lstat(file).catch(() => null);
  if (!tokenStat || !tokenStat.isFile() || tokenStat.size > 128) return null;
  const token = await fs.readFile(file, "utf8").catch(() => "");
  if (sha256(token) !== record.token_sha256) return null;
  const scripts = path.join(venv, process.platform === "win32" ? "Scripts" : "bin");
  const programs = await Promise.all([pkg.command, pkg.command + ".exe"].map((name) => fs.lstat(path.join(scripts, name)).then((stat) => stat.isFile()).catch(() => false)));
  if (!programs.some(Boolean)) return null; // deleted or replaced by something else: it is no longer an installation
  return { version: record.version, versionOk: compareVersions(record.version, pkg.min_version) >= 0 };
}

/**
 * Looks at every copy of the tool in the user's program folders (an old one earlier on PATH must not hide a
 * new one) and returns the first copy that is new enough, otherwise the first copy that runs at all. A
 * project-local environment counts as installed if this CLI created it (see inspectProjectVenv), but is
 * never executed by this CLI after that run.
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
  const venv = await inspectProjectVenv(context.root, pkg, context.home);
  if (venv && venv.versionOk) return { installed: true, executable: null, projectVenv: true, ...venv };
  return fallback || { installed: false, executable: null, version: null, versionOk: false };
}

async function listDocuments(directory) {
  const entries = await fs.readdir(directory, { withFileTypes: true }).catch(() => []);
  return entries.filter((entry) => entry.isFile() && DOCUMENT_EXTENSIONS.has(path.extname(entry.name).toLowerCase()));
}

const isRegularFile = (target) => fs.lstat(target).then((stat) => stat.isFile()).catch(() => false);

/**
 * The project artifact (docs folder, TODO.html, graphify-out) that proves the companion is in use. It must
 * really live inside the project: a symbolic link on the way, a marker file that is a link, or the wrong
 * file type does not count, so a link to a folder elsewhere cannot make `release-check` pass.
 */
export async function inspectProjectArtifact(companion, settings, root) {
  const relative = settings.path;
  const target = resolveInside(root, relative);
  try {
    await assertNoSymlink(root, target);
  } catch {
    return { path: relative, present: false, error: relative + " is or passes through a symbolic link; the artifact must live inside the project" };
  }
  if (path.extname(relative) !== "") return { path: relative, present: await isRegularFile(target) };

  const folder = await fs.lstat(target).catch(() => null);
  if (!folder || !folder.isDirectory()) return { path: relative, present: false };
  const marker = companion.project.marker;
  if (await isRegularFile(path.join(target, marker))) return { path: relative, present: true };
  const documentsAllowed = path.extname(marker).toLowerCase() === ".md";
  return { path: relative, present: documentsAllowed && (await listDocuments(target)).length > 0 };
}

export async function inspectCompanions({ root, profile = {}, registry, home = os.homedir(), run = runProcess, env = process.env } = {}) {
  const context = { root, home, run: withTrustedPath(run, root, env), env };
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
