import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { foundationRoot } from "./paths.js";
import { assertNoSymlink, compareVersions, inspectTool, parseVersion, runProcess } from "./companions.js";
import { GRAPHIFY_GIT_IGNORE, projectVenvDir, venvExecutable } from "./companion-plan.js";
import { describeResolved, resolveSource } from "./companion-source.js";

/**
 * Executes an install plan. Safety rules, in the spirit of `scripts/install-zugang.py`:
 * - skills come from a release tag of the owner's own repository (the newest one by default, the registry pin
 *   with --pinned) and the clone must match the commit that `git ls-remote` reported (a moved tag fails),
 * - the clone is verified before anything is checked out, and Git's LFS/filters are switched off,
 * - only regular files are copied (symlinks, VCS folders, dotenv files and oversized bundles are refused),
 * - everything is validated in memory before the first file is written,
 * - a skill folder appears atomically (written next to its destination, then renamed),
 * - existing files are never overwritten, and no write follows a symbolic link inside the project,
 * - installers (uv, pipx, python, pip, git) run in a neutral directory, never inside the project,
 * - no program that lives inside the project is executed, except the Graphify environment this very run created.
 */

const EXCLUDED_NAMES = new Set([".git", ".github", "node_modules", ".DS_Store"]);
const MAX_FILES = 500;
const MAX_BYTES = 8 * 1024 * 1024;
const MAX_SCAFFOLD_BYTES = 1024 * 1024;
const CLONE_TIMEOUT_MS = 120000;
const INSTALL_TIMEOUT_MS = 600000;
const REPO_PATTERN = /^https:\/\/github\.com\/[A-Za-z0-9-]+\/[A-Za-z0-9_.-]+$/;
const REF_PATTERN = /^v\d+\.\d+\.\d+$/;
const COMMIT_PATTERN = /^[0-9a-f]{40}$/;
const SOURCE_ACTIONS = new Set(["skill", "scaffold-todo"]);
const UNSAFE_GIT_ENV = /^(GIT_DIR|GIT_WORK_TREE|GIT_INDEX_FILE|GIT_OBJECT_DIRECTORY|GIT_ALTERNATE_OBJECT_DIRECTORIES|GIT_COMMON_DIR|GIT_NAMESPACE|GIT_CONFIG.*)$/;
const NO_LFS = ["-c", "filter.lfs.smudge=", "-c", "filter.lfs.process=", "-c", "filter.lfs.required=false"];

export function assertSafeSource(source) {
  if (!REPO_PATTERN.test(source.repo || "")) throw new Error("Refusing unsafe companion repository: " + source.repo);
  if (!REF_PATTERN.test(source.ref || "")) throw new Error("Companion ref must be a release tag like v1.2.3: " + source.ref);
  if (!COMMIT_PATTERN.test(source.commit || "")) throw new Error("Companion source needs a pinned 40-character commit SHA");
}

function gitEnvironment() {
  const env = Object.fromEntries(Object.entries(process.env).filter(([key]) => !UNSAFE_GIT_ENV.test(key)));
  return { ...env, GIT_TERMINAL_PROMPT: "0", GIT_LFS_SKIP_SMUDGE: "1" };
}

/** Resolves the release to install, clones it without checking it out, verifies the commit, then checks it out. */
export async function fetchSource(registrySource, { run = runProcess, owner, pinned = false } = {}) {
  assertSafeSource(registrySource);
  const source = await resolveSource(registrySource, { run, owner, pinned });
  assertSafeSource(source);
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-companion-"));
  const checkout = path.join(dir, "src");
  const cleanup = () => fs.rm(dir, { recursive: true, force: true });
  const options = { cwd: os.tmpdir(), env: gitEnvironment() };
  const fail = async (message) => {
    await cleanup();
    throw new Error(message);
  };

  const clone = await run("git", ["clone", "--quiet", "--no-checkout", "--depth", "1", "--branch", source.ref, "--", source.repo, checkout], { ...options, timeout: CLONE_TIMEOUT_MS });
  if (clone.code !== 0) await fail("git clone failed for " + source.repo + " " + source.ref + ": " + (clone.stderr.trim() || "git is not available"));

  const head = await run("git", ["-C", checkout, "rev-parse", "HEAD"], options);
  if (head.stdout.trim() !== source.commit) {
    await fail("Commit mismatch for " + source.repo + " " + source.ref + ": expected " + source.commit + " but found " + head.stdout.trim() + " (tag moved?)");
  }

  const populate = await run("git", ["-C", checkout, ...NO_LFS, "checkout", "--quiet", "--force", "HEAD"], { ...options, timeout: CLONE_TIMEOUT_MS });
  if (populate.code !== 0) await fail("git checkout failed for " + source.repo + " " + source.ref + ": " + populate.stderr.trim());
  return { dir: checkout, cleanup, ref: source.ref, commit: source.commit, mode: source.mode };
}

async function realpathInside(base, target, label) {
  const [realBase, realTarget] = await Promise.all([fs.realpath(base), fs.realpath(target)]);
  const relative = path.relative(realBase, realTarget);
  if (relative.startsWith("..") || path.isAbsolute(relative)) throw new Error(label + " leaves the companion source: " + target);
}

async function walk(base, relative, files, budget) {
  const absolute = path.join(base, relative);
  const stat = await fs.lstat(absolute);
  if (stat.isSymbolicLink()) throw new Error("Companion bundle contains a symbolic link: " + relative);
  if (stat.isDirectory()) {
    for (const name of await fs.readdir(absolute)) {
      // The repository's own root .gitignore is not part of a skill; nested ones are (they protect SECRETS/ folders).
      if (EXCLUDED_NAMES.has(name) || name.startsWith(".env") || (name === ".gitignore" && relative === "")) continue;
      await walk(base, path.join(relative, name), files, budget);
    }
    return;
  }
  if (!stat.isFile()) throw new Error("Companion bundle contains a non-regular file: " + relative);
  budget.bytes += stat.size;
  if (files.length + 1 > MAX_FILES || budget.bytes > MAX_BYTES) throw new Error("Companion bundle is larger than expected; refusing to install");
  files.push({ relative, data: await fs.readFile(absolute) });
}

/** Reads the allowlisted files of one skill into memory (nothing is written yet). */
export async function collectSkillFiles(sourceDir, skill) {
  const root = path.resolve(sourceDir, skill.path);
  if (path.relative(sourceDir, root).startsWith("..")) throw new Error("Skill path leaves the companion source: " + skill.path);
  await realpathInside(sourceDir, root, "Skill path");
  const files = [];
  const budget = { bytes: 0 };
  for (const entry of skill.include || ["."]) {
    await walk(root, entry === "." ? "" : entry, files, budget);
  }
  const manifest = files.find((file) => file.relative === "SKILL.md");
  const declared = manifest && /^name:\s*["']?([A-Za-z0-9_-]+)/m.exec(manifest.data.toString("utf8").slice(0, 2000));
  if (!declared || declared[1] !== skill.name) {
    throw new Error("Skill " + skill.name + " has no matching SKILL.md (found " + (declared ? declared[1] : "none") + ")");
  }
  return files;
}

/** Reads one scaffold file from a companion checkout: a regular file, inside the checkout, of sane size. */
async function readSourceFile(sourceDir, relative) {
  const absolute = path.resolve(sourceDir, relative);
  const stat = await fs.lstat(absolute);
  if (stat.isSymbolicLink() || !stat.isFile()) throw new Error("Companion scaffold file is not a regular file: " + relative);
  if (stat.size > MAX_SCAFFOLD_BYTES) throw new Error("Companion scaffold file is larger than expected: " + relative);
  await realpathInside(sourceDir, absolute, "Scaffold file");
  return fs.readFile(absolute, "utf8");
}

const pathExists = (target) => fs.lstat(target).then(() => true).catch(() => false);

/** Creates a new file exclusively; refuses to write through a symbolic link below the project root. */
async function writeNew(file, data, guardRoot) {
  if (guardRoot) await assertNoSymlink(guardRoot, file);
  await fs.mkdir(path.dirname(file), { recursive: true });
  if (guardRoot) await assertNoSymlink(guardRoot, file);
  await fs.writeFile(file, data, { flag: "wx" });
}

/** Writes the skill next to its destination and renames it into place, so no half-written skill can be left behind. */
async function writeSkill(dest, files, guardRoot) {
  if (guardRoot) await assertNoSymlink(guardRoot, dest);
  if (await pathExists(dest)) {
    if (await pathExists(path.join(dest, "SKILL.md"))) return "kept";
    throw new Error(dest + " exists but contains no SKILL.md (an interrupted install?). Move it away and run the command again.");
  }
  const partial = dest + ".partial-" + process.pid + "-" + Date.now();
  try {
    for (const file of files) await writeNew(path.join(partial, file.relative), file.data, guardRoot);
    await fs.rename(partial, dest);
  } catch (error) {
    await fs.rm(partial, { recursive: true, force: true });
    throw error;
  }
  return "installed";
}

/** Removes the example rows of the Todo template, like `/todo-setup` does. */
export function stripTodoExamples(html) {
  return html.replace(/<tr\b[^>]*\bdata-kategorie="beispiel"[^>]*>[\s\S]*?<\/tr>\s*/g, "");
}

const HTML_ESCAPES = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" };

/** Replaces the template's PROJEKT placeholders with the project name and the real file location. */
export function personalizeTodo(html, projectName, relativePath) {
  const name = projectName.replace(/[&<>"]/g, (char) => HTML_ESCAPES[char]);
  return html.split("PROJEKT/TODO/TODO.html").join(relativePath).split("PROJEKT").join(name);
}

export function renderScaffold(template, variables) {
  return Object.entries(variables).reduce((text, [key, value]) => text.split("{{" + key + "}}").join(value), template);
}

/** The project name from the profile, flattened to one short line (it ends up in generated files). */
export function projectName(context) {
  const raw = (context.profile.project && context.profile.project.name) || path.basename(context.root);
  return String(raw).replace(/[\u0000-\u001f\u007f]+/g, " ").trim().slice(0, 100) || "Project";
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

/** Appends the missing lines to a line-based config file; existing lines and order stay untouched. */
async function appendLines(file, lines, guardRoot) {
  await assertNoSymlink(guardRoot, file);
  const current = await fs.readFile(file, "utf8").catch(() => "");
  const present = new Set(current.split(/\r?\n/));
  const missing = lines.filter((line) => !present.has(line));
  if (missing.length === 0) return false;
  const prefix = current && !current.endsWith("\n") ? "\n" : "";
  await fs.writeFile(file, current + prefix + missing.join("\n") + "\n", "utf8");
  return true;
}

async function configureGraphify(action, root) {
  const ignored = await appendLines(path.join(root, ".graphifyignore"), action.ignore, root);
  const git = await appendLines(path.join(root, ".gitignore"), GRAPHIFY_GIT_IGNORE, root);
  return { status: ignored || git ? "installed" : "kept" };
}

async function runChecked(run, command, args, options) {
  const result = await run(command, args, { timeout: INSTALL_TIMEOUT_MS, ...options });
  if (result.code !== 0) {
    throw new Error(command + " " + args.join(" ") + " failed: " + (result.stderr.trim() || result.stdout.trim() || "exit " + result.code));
  }
  return result;
}

function graphifyCompanion(context) {
  return context.registry.companions.find((entry) => entry.kind === "tool");
}

/**
 * The Graphify copy to run: the one this run just created, otherwise the best copy in the user's own
 * program folders. A project-local environment from an earlier run is reported but never executed.
 */
export function resolveGraphify(context) {
  if (context.created && context.created.versionOk) return Promise.resolve(context.created);
  return inspectTool(graphifyCompanion(context), { root: context.root, home: context.home, env: context.env, run: context.run });
}

/** A fresh, empty location for the project-local environment: no symbolic links, nothing reused. */
async function prepareVenvLocation(root, venv) {
  await assertNoSymlink(root, venv);
  await fs.mkdir(path.dirname(venv), { recursive: true });
  await assertNoSymlink(root, venv);
  if (await pathExists(venv)) {
    throw new Error(path.relative(root, venv) + " already exists. This CLI never reuses an environment it did not just create; remove it and run the command again, or use --graphify user.");
  }
}

async function installGraphifyCli(action, context) {
  const { package: pkg } = graphifyCompanion(context);
  if (action.commands.length === 0 && action.method === "project") {
    throw new Error("No Python " + pkg.requires_python + " or newer was found, which the project-local Graphify needs. Use --graphify user (uv brings its own Python) or install Python " + pkg.requires_python + "+.");
  }
  if (action.commands.length === 0) {
    throw new Error("Neither uv nor pipx was found. Install one of them, or rerun with --graphify project (needs Python " + pkg.requires_python + "+).");
  }

  const venv = action.method === "project" ? projectVenvDir(context.root) : null;
  if (venv) await prepareVenvLocation(context.root, venv);
  try {
    for (const [command, args] of action.commands) await runChecked(context.run, command, args, { cwd: os.tmpdir() });
  } catch (error) {
    if (venv) await fs.rm(venv, { recursive: true, force: true });
    throw error;
  }

  if (venv) {
    // We created this environment a moment ago; this is the only program inside the project the CLI will run.
    const executable = venvExecutable(context.root, pkg.command);
    const result = await context.run(executable, ["--version"], { cwd: os.tmpdir() });
    const version = parseVersion(result.stdout + " " + result.stderr);
    context.created = { installed: Boolean(version), executable, version, versionOk: Boolean(version) && compareVersions(version, pkg.min_version) >= 0 };
  }
  const tool = await resolveGraphify(context);
  if (!tool.installed) throw new Error("Graphify was installed but the " + pkg.command + " command was not found afterwards.");
  if (!tool.versionOk) {
    throw new Error("Graphify " + tool.version + " at " + tool.executable + " is older than " + pkg.min_version + " and no newer copy was found. Remove the old copy or put the new one first on PATH.");
  }
  return "installed";
}

async function scaffoldTodo(action, context, state) {
  const source = state.sources.get(action.companionId);
  const html = await readSourceFile(source.dir, action.source);
  const relative = path.relative(context.root, action.dest).split(path.sep).join("/");
  await writeNew(action.dest, personalizeTodo(stripTodoExamples(html), projectName(context), relative), context.root);
  // The Todo skill always reads TODO/.todo-config from the project root.
  const config = path.join(context.root, "TODO", ".todo-config");
  if (!await pathExists(config)) await writeNew(config, relative + "\n", context.root);
  return { status: "installed", detail: releaseDetail(state, action.companionId) };
}

const PROJECT_ENVIRONMENT_ONLY = "Graphify exists only in the project environment (.mgd/graphify-venv). This CLI does not run programs from inside the project; run .mgd/graphify-venv/bin/graphify yourself.";

function releaseDetail(state, companionId) {
  const source = state.sources.get(companionId);
  return source && source.ref ? describeResolved(source) : null;
}

async function execute(action, context, state) {
  if (action.state === "keep") return { status: "kept", detail: action.detail };
  if (action.state === "skipped") return { status: "skipped", detail: action.detail };

  if (SOURCE_ACTIONS.has(action.type) && state.errors.has(action.companionId)) throw state.errors.get(action.companionId);

  if (action.type === "skill") {
    const guardRoot = action.scope === "project" ? context.root : null;
    const status = await writeSkill(action.dest, state.files.get(action.companionId + "/" + action.skill), guardRoot);
    return { status, detail: status === "installed" ? releaseDetail(state, action.companionId) : null };
  }

  if (action.type === "scaffold-docs") {
    const template = await fs.readFile(path.join(foundationRoot, action.template), "utf8");
    await writeNew(action.dest, renderScaffold(template, { PROJECT_NAME: projectName(context), DATE: today() }), context.root);
    return { status: "installed" };
  }

  if (action.type === "scaffold-todo") return scaffoldTodo(action, context, state);
  if (action.type === "graphify-cli") return { status: await installGraphifyCli(action, context) };
  if (action.type === "graphify-config") return configureGraphify(action, context.root);

  const tool = await resolveGraphify(context);
  if (tool.projectVenv) return { status: "skipped", detail: PROJECT_ENVIRONMENT_ONLY };
  if (!tool.installed || !tool.versionOk) return { status: "skipped", detail: "Graphify CLI is not available" };

  if (action.type === "graphify-refresh") {
    await runChecked(context.run, tool.executable, ["update", "."], { cwd: context.root });
    return { status: "installed", detail: "graph built" };
  }
  if (action.type === "graphify-hook") {
    await runChecked(context.run, tool.executable, ["hook", "install"], { cwd: context.root });
    return { status: "installed" };
  }
  throw new Error("Unknown plan action: " + action.type);
}

/** Fetches every needed source once. A failure is remembered per companion so only its actions fail. */
async function prepareSources(plan, context, state) {
  const needed = new Set(plan.actions.filter((a) => a.state === "install" && SOURCE_ACTIONS.has(a.type)).map((a) => a.companionId));
  for (const companion of context.registry.companions.filter((entry) => needed.has(entry.id))) {
    try {
      const source = await context.fetch(companion.source, { run: context.run, owner: context.registry.owner, pinned: context.pinned });
      state.sources.set(companion.id, source);
      for (const skill of companion.skills || []) {
        const wanted = plan.actions.some((a) => a.type === "skill" && a.skill === skill.name && a.state === "install");
        if (wanted) state.files.set(companion.id + "/" + skill.name, await collectSkillFiles(source.dir, skill));
      }
    } catch (error) {
      state.errors.set(companion.id, error);
    }
  }
}

/** Runs the plan. Every action reports its own result; one failure does not stop the others. */
export async function executePlan(plan, { registry, root, home, profile = {}, run = runProcess, fetch = fetchSource, env = process.env, pinned = false }) {
  const context = { registry, root, home, profile: profile || {}, run, fetch, env, created: null, pinned };
  const state = { sources: new Map(), files: new Map(), errors: new Map() };
  const results = [];

  try {
    await prepareSources(plan, context, state);
    for (const action of plan.actions) {
      try {
        results.push({ action, ...await execute(action, context, state) });
      } catch (error) {
        results.push({ action, status: "failed", detail: error.message });
      }
    }
  } finally {
    for (const source of state.sources.values()) await source.cleanup();
  }
  return results;
}
