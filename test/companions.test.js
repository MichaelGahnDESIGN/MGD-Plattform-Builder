import test, { after } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import Ajv2020 from "ajv/dist/2020.js";
import {
  assertNoSymlink,
  compareVersions,
  findCommand,
  findPython,
  inspectCompanions,
  inspectTool,
  isInsideProject,
  isOutside,
  printable,
  loadCompanionRegistry,
  recordProjectVenv,
  resolveInside,
  trustedPathEntries,
  withTrustedPath
} from "../src/lib/companions.js";
import { buildPlan, graphifyInstallCommands, plannedCommands } from "../src/lib/companion-plan.js";
import {
  assertSafeSource,
  collectSkillFiles,
  executePlan,
  fetchSource,
  personalizeTodo,
  projectName,
  stripTodoExamples
} from "../src/lib/companion-install.js";
import { companionsCommand } from "../src/commands/companions.js";
import { initCommand } from "../src/commands/init.js";
import { gitEnvironment } from "../src/lib/companion-source.js";
import { releaseCheckCommand } from "../src/commands/release-check.js";
import { validateProfile } from "../src/lib/validation.js";
import { foundationRoot } from "../src/lib/paths.js";

const BOTH = ["claude", "codex"];
const created = [];

async function tempDir(label) {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-companions-" + label + "-"));
  created.push(dir);
  return dir;
}

after(async () => {
  for (const dir of created) await fs.rm(dir, { recursive: true, force: true });
});

async function write(file, content, mode) {
  await fs.mkdir(path.dirname(file), { recursive: true });
  await fs.writeFile(file, content, { mode });
}

const exists = (target) => fs.lstat(target).then(() => true).catch(() => false);

/** Runs `fn` and returns what it printed with console.log / console.error. */
async function captured(fn) {
  const lines = [];
  const original = { log: console.log, error: console.error };
  console.log = (...parts) => lines.push(parts.join(" "));
  console.error = (...parts) => lines.push(parts.join(" "));
  try {
    const result = await fn();
    return { result, output: lines.join("\n") };
  } finally {
    console.log = original.log;
    console.error = original.error;
  }
}

/** A synthetic copy of the two skill companion repositories; nothing is downloaded. */
async function fixtureSources() {
  const base = await tempDir("sources");
  const living = path.join(base, "living");
  const todo = path.join(base, "todo");
  await write(path.join(living, "skills/living-documentation/SKILL.md"), "---\nname: living-documentation\n---\nSynthetic skill\n");
  await write(path.join(living, "skills/living-documentation/references/model.md"), "model");
  await write(path.join(living, "skills/living-documentation/.env"), "SYNTHETIC=not-a-credential");
  await write(path.join(living, "skills/project-start-assistant/SKILL.md"), "---\nname: project-start-assistant\n---\nSynthetic\n");
  await write(path.join(living, "skills/project-start-assistant/assets/project-template/.gitignore"), "/SECRETS/**\n");
  await write(path.join(todo, "SKILL.md"), "---\nname: todo\n---\nSynthetic todo skill\n");
  await write(path.join(todo, "LICENSE"), "MIT");
  await write(path.join(todo, "docs/setup.md"), "setup");
  await write(path.join(todo, "README.md"), "must not be copied");
  await write(path.join(todo, ".git/config"), "must not be copied");
  await write(path.join(todo, "todo/TODO.template.html"), [
    "<title>PROJEKT — TODO</title><div>PROJEKT TODO</div><code>PROJEKT/TODO/TODO.html</code>",
    "<table><tbody id=\"todoBody\">",
    "<!-- format notes -->",
    "<tr data-status=\"offen\" data-kategorie=\"beispiel\"><td>T001</td></tr>",
    "<tr class=\"row-done\" data-status=\"done\" data-kategorie=\"beispiel\"><td>T002</td></tr>",
    "</tbody></table>"
  ].join("\n"));

  const fetch = async (source) => {
    const dir = source.repo.endsWith("MGD_Living-Documentation") ? living : todo;
    return { dir, cleanup: async () => {} };
  };
  return { fetch, base, living, todo };
}

/**
 * A fake process runner. `graphify` is the version a PATH copy reports; `extra` handles other commands.
 * It records every call, including the working directory, and simulates `python -m venv`.
 */
function fakeRun({ graphify = null, venvVersion = "0.9.93", extra = {} } = {}) {
  const calls = [];
  const run = async (command, args, options = {}) => {
    const name = path.basename(command);
    calls.push({ command, name, args, cwd: options.cwd, env: options.env, line: [name, ...args].join(" ") });
    if (name === "graphify" && args[0] === "--version") {
      const inVenv = command.includes(path.join(".mgd", "graphify-venv"));
      const version = inVenv ? venvVersion : graphify;
      return version ? { code: 0, stdout: "graphify " + version + "\n", stderr: "" } : { code: 1, stdout: "", stderr: "not found" };
    }
    if (name === "graphify" && args[0] === "update") await write(path.join(options.cwd, "graphify-out/graph.json"), "{}");
    if (args.includes("venv")) await write(path.join(args.at(-1), "bin/graphify"), "#!/bin/sh\n", 0o755);
    if (extra[name]) return extra[name](args, options);
    return { code: 0, stdout: "", stderr: "" };
  };
  return { run, calls };
}

async function binDirWith(...commands) {
  const dir = await tempDir("bin");
  for (const command of commands) await write(path.join(dir, command), "#!/bin/sh\n", 0o755);
  return dir;
}

async function inspect(root, home, options = {}) {
  return inspectCompanions({ root, home, registry: await loadCompanionRegistry(), env: { PATH: options.bin || "" }, run: options.run || fakeRun().run, profile: options.profile });
}

async function install({ root, home, fetch, run, env, scope = "project", agents = BOTH, graphify = "user", tools = {}, hooks = false, profile = {} }) {
  const registry = await loadCompanionRegistry();
  const inspection = await inspectCompanions({ root, home, registry, run, env, profile });
  const plan = await buildPlan({ inspection, root, home, scope, agents, graphify, tools, hooks });
  const results = await executePlan(plan, { registry, root, home, profile, run, fetch, env });
  return { plan, results, registry };
}

const find = (results, type) => results.find((r) => r.action.type === type);

test("the companion registry matches its schema and points at real files", async () => {
  const schema = JSON.parse(await fs.readFile(path.join(foundationRoot, "schema/companion-registry.schema.json"), "utf8"));
  const registry = await loadCompanionRegistry();
  const validate = new Ajv2020({ allErrors: true, strict: false }).compile(schema);
  assert.ok(validate(registry), JSON.stringify(validate.errors));
  assert.deepEqual(registry.companions.map((entry) => entry.id), ["living-documentation", "todo", "graphify"]);
  assert.ok(registry.companions.every((entry) => entry.mandatory));
  for (const entry of registry.companions) {
    if (entry.project.scaffold) assert.ok(await exists(path.join(foundationRoot, entry.project.scaffold)), entry.project.scaffold);
    if (entry.kind === "skill") assertSafeSource(entry.source);
  }
});

test("versions compare numerically", () => {
  assert.ok(compareVersions("0.9.65", "0.9.65") === 0);
  assert.ok(compareVersions("0.10.0", "0.9.65") > 0);
  assert.ok(compareVersions("0.9.64", "0.9.65") < 0);
  assert.ok(compareVersions("none", "0.9.65") < 0);
});

test("project paths cannot escape the project", () => {
  const root = path.resolve("/tmp/example-project");
  assert.equal(resolveInside(root, "docs/living"), path.join(root, "docs/living"));
  for (const bad of ["../outside", "/etc/passwd", "", "a/../../b", "."]) {
    assert.throws(() => resolveInside(root, bad), undefined, bad);
  }
});

test("the profile schema and the runtime accept only plain project-relative names", async () => {
  const base = { project: { name: "x", type: "general", foundation_version: "0.7.0" }, market: { countries: ["DE"] }, languages: { default: "de", enabled: ["de"] }, features: {}, infrastructure: { docker: false, staging: "none", git_source: "github" } };
  const root = path.resolve("/tmp/example-project");
  const bad = [".", "..", "../shared", "/etc", "a/../../b", ".git/hooks/post-commit", ".claude/settings.json", ".docs", "a//b", "TODO/", "node_modules/x", "docs/<img src=x onerror=alert(1)>", "line\nbreak", "esc\u001b[2J"];
  for (const value of bad) {
    const result = await validateProfile({ ...base, companions: { living_documentation: { path: value } } });
    assert.equal(result.valid, false, JSON.stringify(value) + " must be rejected by the schema");
    assert.throws(() => resolveInside(root, value), undefined, JSON.stringify(value) + " must be rejected at runtime");
  }
  for (const value of ["docs", "docs/living", "DOKUMENTATION", "my-docs_v2", "TODO/TODO.html"]) {
    const result = await validateProfile({ ...base, companions: { todo: { path: value } } });
    assert.deepEqual(result.schemaErrors, [], value + " must be accepted by the schema");
    assert.doesNotThrow(() => resolveInside(root, value), value + " must be accepted at runtime");
  }
  const graph = await validateProfile({ ...base, companions: { graphify: { path: "docs/graph" } } });
  assert.equal(graph.valid, false, "Graphify always writes to graphify-out");
});

test("only pinned GitHub release sources are accepted", () => {
  const good = { repo: "https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL", ref: "v1.3.0", commit: "a".repeat(40) };
  assert.doesNotThrow(() => assertSafeSource(good));
  assert.throws(() => assertSafeSource({ ...good, repo: "https://evil.example/x/y" }));
  assert.throws(() => assertSafeSource({ ...good, repo: "git@github.com:a/b.git" }));
  assert.throws(() => assertSafeSource({ ...good, ref: "--upload-pack=sh" }));
  assert.throws(() => assertSafeSource({ ...good, ref: "main" }));
  assert.throws(() => assertSafeSource({ ...good, commit: "abc" }));
});

test("the clone is verified before checkout, runs with a clean Git environment, and a moved tag cleans up", async () => {
  const source = { repo: "https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL", ref: "v1.3.0", commit: "a".repeat(40) };
  const calls = [];
  let checkoutDir;
  const make = (head) => async (command, args, options) => {
    calls.push({ args, env: options && options.env, cwd: options && options.cwd });
    if (args[0] === "clone") {
      checkoutDir = args.at(-1);
      await fs.mkdir(checkoutDir, { recursive: true });
      return { code: 0, stdout: "", stderr: "" };
    }
    return { code: 0, stdout: head + "\n", stderr: "" };
  };

  process.env.GIT_DIR = "/tmp/attacker";
  try {
    const ok = await fetchSource(source, { run: make(source.commit) });
    await ok.cleanup();
  } finally {
    delete process.env.GIT_DIR;
  }
  const order = calls.map((call) => call.args.find((arg) => ["clone", "rev-parse", "checkout"].includes(arg)));
  assert.deepEqual(order, ["clone", "rev-parse", "checkout"], "the commit is verified before anything is checked out");
  assert.ok(calls[0].args.includes("--no-checkout"));
  assert.ok(calls.every((call) => !("GIT_DIR" in call.env)), "dangerous GIT_* variables are removed");
  assert.equal(calls[0].env.GIT_TERMINAL_PROMPT, "0");
  assert.equal(calls[0].cwd, os.tmpdir(), "git runs in a neutral directory");
  assert.ok(calls[2].args.join(" ").includes("filter.lfs.smudge="), "LFS filters are switched off");

  await assert.rejects(() => fetchSource(source, { run: make("f".repeat(40)) }), /Commit mismatch/);
  assert.equal(await exists(path.dirname(checkoutDir)), false, "the temp clone is removed after a mismatch");
});

test("nothing is installed when the project and home are empty", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const items = await inspect(root, home);
  assert.ok(items.every((item) => !item.installed && !item.project.present && !item.ok));
});

test("an existing install under the old alias name counts as installed", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  await write(path.join(home, ".claude/skills/mgd-todo/SKILL.md"), "---\nname: todo\n---\n");
  const todo = (await inspect(root, home)).find((item) => item.companion.id === "todo");
  assert.equal(todo.installed, true);
  assert.deepEqual(todo.skills.agents, ["claude"]);
});

test("an old Graphify earlier on PATH does not hide a newer one", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const oldBin = await tempDir("old");
  const newBin = await tempDir("new");
  await write(path.join(oldBin, "graphify"), "old", 0o755);
  await write(path.join(newBin, "graphify"), "new", 0o755);
  const run = async (command) => ({ code: 0, stdout: "graphify " + (command.startsWith(oldBin) ? "0.9.10" : "0.9.93") + "\n", stderr: "" });
  const tool = (await loadCompanionRegistry()).companions.find((c) => c.kind === "tool");

  const both = await inspectTool(tool, { root, home, run, env: { PATH: oldBin + path.delimiter + newBin } });
  assert.equal(both.version, "0.9.93");
  assert.equal(both.versionOk, true);

  const onlyOld = await inspectTool(tool, { root, home, run, env: { PATH: oldBin } });
  assert.equal(onlyOld.installed, true);
  assert.equal(onlyOld.versionOk, false);
});

test("the dry-run plan keeps what exists and lists every command", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  await write(path.join(home, ".claude/skills/mgd-todo/SKILL.md"), "---\nname: todo\n---\n");
  await fs.mkdir(path.join(root, ".git"));
  const inspection = await inspect(root, home, { bin: await binDirWith("uv") });

  const plan = await buildPlan({ inspection, root, home, scope: "project", agents: BOTH, graphify: "user", tools: { hasUv: true }, hooks: true });
  const todoClaude = plan.actions.find((a) => a.type === "skill" && a.skill === "todo" && a.agent === "claude");
  const todoCodex = plan.actions.find((a) => a.type === "skill" && a.skill === "todo" && a.agent === "codex");
  assert.equal(todoClaude.state, "keep");
  assert.equal(todoCodex.state, "install");
  assert.equal(todoCodex.dest, path.join(root, ".agents/skills/todo"));
  assert.ok(!plan.actions.some((a) => a.type === "graphify-skill"), "the global /graphify registration is never installed");
  const commands = plannedCommands(plan);
  assert.ok(commands.includes("git ls-remote --tags -- https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation"), "the newest release is looked up");
  assert.ok(commands.some((c) => c.startsWith("git clone --depth 1 --branch <newest release, at least v0.3.0> -- https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation")));
  assert.ok(commands.includes("uv tool install --upgrade graphifyy>=0.9.65"), "Graphify: newest release, never below the floor");
  assert.ok(commands.includes("graphify update ."));
  assert.ok(commands.includes("graphify hook install"));

  const pinnedPlan = await buildPlan({ inspection, root, home, scope: "project", agents: BOTH, graphify: "user", tools: { hasUv: true }, pinned: true });
  const pinnedCommands = plannedCommands(pinnedPlan);
  assert.ok(pinnedCommands.some((c) => c.startsWith("git clone --depth 1 --branch v0.3.0 -- https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation")));
  assert.ok(!pinnedCommands.some((c) => c.includes("ls-remote")), "--pinned never asks which release is newest");
  assert.ok(pinnedCommands.includes("uv tool install --upgrade graphifyy==0.9.93"));

  const userPlan = await buildPlan({ inspection, root, home, scope: "user", agents: ["claude"], graphify: "user", tools: { hasUv: true } });
  assert.equal(userPlan.actions.find((a) => a.type === "skill" && a.skill === "living-documentation").dest, path.join(home, ".claude/skills/living-documentation"));
});

test("git hooks are opt-in and the plan says so", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  await fs.mkdir(path.join(root, ".git"));
  const inspection = await inspect(root, home);
  const off = await buildPlan({ inspection, root, home, scope: "project", agents: ["claude"], graphify: "skip" });
  const hook = off.actions.find((a) => a.type === "graphify-hook");
  assert.equal(hook.state, "skipped");
  assert.match(hook.detail, /--hooks/);
  assert.ok(!plannedCommands(off).includes("graphify hook install"));
  const on = await buildPlan({ inspection, root, home, scope: "project", agents: ["claude"], graphify: "skip", hooks: true });
  assert.equal(on.actions.find((a) => a.type === "graphify-hook").state, "install");
});

test("Graphify install commands take the newest release (floor) or the tested pin, and follow the chosen method", () => {
  const companion = { package: { name: "graphifyy", min_version: "0.9.65", install_version: "0.9.93" } };
  const root = path.resolve("/tmp/example-project");
  assert.deepEqual(graphifyInstallCommands({ method: "user", companion, root, hasUv: true }), [["uv", ["tool", "install", "--upgrade", "graphifyy>=0.9.65"]]]);
  assert.deepEqual(graphifyInstallCommands({ method: "user", companion, root, uv: "/home/me/.local/bin/uv", pipx: "/opt/pipx" }), [["/home/me/.local/bin/uv", ["tool", "install", "--upgrade", "graphifyy>=0.9.65"]]], "the discovered absolute path is used");
  assert.deepEqual(graphifyInstallCommands({ method: "user", companion, root, pipx: "/opt/pipx" }), [["/opt/pipx", ["install", "--force", "graphifyy>=0.9.65"]]]);
  assert.deepEqual(graphifyInstallCommands({ method: "user", companion, root, hasUv: true, pinned: true }), [["uv", ["tool", "install", "--upgrade", "graphifyy==0.9.93"]]]);
  assert.deepEqual(graphifyInstallCommands({ method: "user", companion, root, hasPipx: true }), [["pipx", ["install", "--force", "graphifyy>=0.9.65"]]]);
  assert.deepEqual(graphifyInstallCommands({ method: "user", companion, root }), []);
  const project = graphifyInstallCommands({ method: "project", companion, root, python: { path: "/opt/python3.11" } });
  assert.equal(project.length, 2);
  assert.deepEqual(project[0], ["/opt/python3.11", ["-I", "-m", "venv", path.join(root, ".mgd", "graphify-venv")]]);
  assert.deepEqual(project[1][1], ["-I", "-m", "pip", "install", "--upgrade", "graphifyy>=0.9.65"]);
  assert.deepEqual(graphifyInstallCommands({ method: "project", companion, root, python: null }), [], "no suitable Python, no commands");
  assert.deepEqual(graphifyInstallCommands({ method: "skip", companion, root, hasUv: true }), []);
});

test("installing fills skills, documentation skeleton and TODO.html, and never overwrites", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const bin = await binDirWith("graphify");
  const { run, calls } = fakeRun({ graphify: "0.9.93" });
  const env = { PATH: bin };
  await fs.mkdir(path.join(root, ".git"));
  const profile = { project: { name: "Beispiel" } };

  const { plan, results, registry } = await install({ root, home, fetch, run, env, hooks: true, profile });
  assert.deepEqual(results.filter((r) => r.status === "failed"), []);

  for (const dir of [".claude", ".agents"]) {
    assert.ok(await exists(path.join(root, dir, "skills/living-documentation/SKILL.md")));
    assert.ok(await exists(path.join(root, dir, "skills/living-documentation/references/model.md")));
    assert.ok(await exists(path.join(root, dir, "skills/project-start-assistant/SKILL.md")));
    assert.ok(await exists(path.join(root, dir, "skills/project-start-assistant/assets/project-template/.gitignore")), "nested .gitignore files travel with the skill");
    assert.ok(await exists(path.join(root, dir, "skills/todo/todo/TODO.template.html")));
    assert.ok(await exists(path.join(root, dir, "skills/todo/docs/setup.md")));
    assert.equal(await exists(path.join(root, dir, "skills/todo/README.md")), false, "files outside the allowlist stay out");
    assert.equal(await exists(path.join(root, dir, "skills/todo/.git")), false);
    assert.equal(await exists(path.join(root, dir, "skills/living-documentation/.env")), false);
    const leftovers = (await fs.readdir(path.join(root, dir, "skills"))).filter((name) => name.includes(".partial-"));
    assert.deepEqual(leftovers, [], "no half-written skill folders remain");
  }

  const docs = await fs.readFile(path.join(root, "DOKUMENTATION/README.md"), "utf8");
  assert.ok(docs.includes("# Beispiel – Living Documentation"));
  assert.ok(!docs.includes("{{"), "all placeholders are replaced");
  const todo = await fs.readFile(path.join(root, "TODO/TODO.html"), "utf8");
  assert.ok(todo.includes("todoBody") && !todo.includes("T001"), "example rows are removed");
  assert.ok(todo.includes("Beispiel — TODO") && todo.includes("<code>TODO/TODO.html</code>") && !todo.includes("PROJEKT"), "placeholders are personalised");
  assert.equal((await fs.readFile(path.join(root, "TODO/.todo-config"), "utf8")).trim(), "TODO/TODO.html");
  assert.ok(calls.some((c) => c.line === "graphify update ." && c.cwd === root));
  assert.ok(calls.some((c) => c.line === "graphify hook install"));

  const ignore = (await fs.readFile(path.join(root, ".graphifyignore"), "utf8")).split("\n");
  for (const entry of [".claude/", ".agents/", "TODO/", "graphify-out/"]) assert.ok(ignore.includes(entry), entry + " stays out of the graph");
  const gitignore = (await fs.readFile(path.join(root, ".gitignore"), "utf8")).split("\n");
  for (const entry of ["graphify-out/cache/", "graphify-out/.graphify_python", "graphify-out/.graphify_root"]) assert.ok(gitignore.includes(entry), entry + " stays out of Git");
  assert.ok(plan.actions.findIndex((a) => a.type === "graphify-config") < plan.actions.findIndex((a) => a.type === "graphify-refresh"), "config comes before the first graph build");

  await fs.writeFile(path.join(root, "TODO/TODO.html"), "my own todos");
  await fs.writeFile(path.join(root, ".claude/skills/todo/SKILL.md"), "customised");
  const again = await install({ root, home, fetch, run, env, hooks: true, profile });
  assert.ok(again.plan.actions.filter((a) => ["skill", "scaffold-docs", "scaffold-todo"].includes(a.type)).every((a) => a.state === "keep"));
  assert.equal(await fs.readFile(path.join(root, "TODO/TODO.html"), "utf8"), "my own todos");
  assert.equal(await fs.readFile(path.join(root, ".claude/skills/todo/SKILL.md"), "utf8"), "customised");
  const repeated = (await fs.readFile(path.join(root, ".gitignore"), "utf8")).split("\n");
  assert.equal(repeated.filter((line) => line === "graphify-out/cache/").length, 1, "config lines are not duplicated");

  const finished = await inspectCompanions({ root, home, registry, run, env });
  assert.ok(finished.every((item) => item.ok), "every mandatory companion is installed and in use");
});

test("skipping Graphify is reported as skipped, not silently accepted", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const { results, registry } = await install({ root, home, fetch, run: fakeRun().run, env: { PATH: "" }, agents: ["claude"], graphify: "skip" });
  const cli = find(results, "graphify-cli");
  assert.equal(cli.status, "skipped");
  assert.match(cli.detail, /--graphify skip/);
  const finished = await inspectCompanions({ root, home, registry, run: fakeRun().run, env: { PATH: "" } });
  assert.equal(finished.find((item) => item.companion.id === "graphify").ok, false);
});

test("a failing install command is reported and the other steps still run", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const bin = await binDirWith("uv");
  const { run, calls } = fakeRun({ extra: { uv: () => ({ code: 1, stdout: "", stderr: "network unreachable" }) } });
  const { results } = await install({ root, home, fetch, run, env: { PATH: bin }, agents: ["claude"], tools: { hasUv: true } });
  const cli = find(results, "graphify-cli");
  assert.equal(cli.status, "failed");
  assert.match(cli.detail, /network unreachable/);
  assert.ok(await exists(path.join(root, ".claude/skills/todo/SKILL.md")), "skills were installed anyway");
  assert.equal(calls.find((c) => c.name === "uv").cwd, os.tmpdir(), "installers do not run inside the project");
});

test("a failed fetch of one companion only fails that companion's steps", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch: working } = await fixtureSources();
  const fetch = async (source) => {
    if (source.repo.endsWith("MGD_Living-Documentation")) throw new Error("offline");
    return working(source);
  };
  const bin = await binDirWith("graphify");
  const { results } = await install({ root, home, fetch, run: fakeRun({ graphify: "0.9.93" }).run, env: { PATH: bin }, agents: ["claude"] });
  const failed = results.filter((r) => r.status === "failed").map((r) => r.action.skill || r.action.type);
  assert.deepEqual(failed.sort(), ["living-documentation", "project-start-assistant"]);
  assert.ok(await exists(path.join(root, ".claude/skills/todo/SKILL.md")));
  assert.equal(find(results, "graphify-config").status, "installed");
  assert.equal(find(results, "graphify-refresh").status, "installed");
  assert.equal(find(results, "graphify-cli").status, "kept");
});

test("a bundle with a symbolic link, a wrong skill name or a path that escapes is refused", async () => {
  const dir = await tempDir("bundle");
  await write(path.join(dir, "SKILL.md"), "---\nname: other\n---\n");
  await assert.rejects(() => collectSkillFiles(dir, { name: "todo", path: "." }), /no matching SKILL.md/);

  await write(path.join(dir, "SKILL.md"), "---\nname: todo\n---\n");
  await fs.symlink("/etc/hosts", path.join(dir, "link.md"));
  await assert.rejects(() => collectSkillFiles(dir, { name: "todo", path: "." }), /symbolic link/);
  await fs.rm(path.join(dir, "link.md"));

  const outside = await tempDir("outside");
  await write(path.join(outside, "SKILL.md"), "---\nname: todo\n---\n");
  await fs.symlink(outside, path.join(dir, "escape"));
  await assert.rejects(() => collectSkillFiles(dir, { name: "todo", path: "escape" }), /leaves the companion source/);
});

test("only the repository's root .gitignore is left out of a skill", async () => {
  const dir = await tempDir("bundle");
  await write(path.join(dir, "SKILL.md"), "---\nname: todo\n---\n");
  await write(path.join(dir, ".gitignore"), "root");
  await write(path.join(dir, "assets/.gitignore"), "nested");
  const files = (await collectSkillFiles(dir, { name: "todo", path: "." })).map((file) => file.relative).sort();
  assert.deepEqual(files, ["SKILL.md", path.join("assets", ".gitignore")]);
});

test("a half-written skill folder is reported instead of being treated as installed", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  await write(path.join(root, ".claude/skills/todo/docs/setup.md"), "left over from an interrupted install");
  const { results } = await install({ root, home, fetch, run: fakeRun().run, env: { PATH: "" }, agents: ["claude"], graphify: "skip" });
  const todo = results.find((r) => r.action.type === "skill" && r.action.skill === "todo");
  assert.equal(todo.status, "failed");
  assert.match(todo.detail, /interrupted install/);
});

test("a failed fetch leaves the project untouched", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const bin = await binDirWith("graphify");
  const { results } = await install({ root, home, fetch: async () => { throw new Error("offline"); }, run: fakeRun({ graphify: "0.9.93" }).run, env: { PATH: bin }, agents: ["claude"] });
  assert.ok(results.some((r) => r.status === "failed" && /offline/.test(r.detail)));
  assert.equal(await exists(path.join(root, ".claude")), false);
  assert.equal(await exists(path.join(root, "TODO")), false);
});

test("symbolic links inside the project never redirect a write", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const outside = await tempDir("outside");
  const { fetch } = await fixtureSources();
  await fs.symlink(outside, path.join(root, "docs"));
  await write(path.join(outside, "victim.conf"), "keep me\n");
  await fs.symlink(path.join(outside, "victim.conf"), path.join(root, ".gitignore"));
  await fs.symlink(outside, path.join(root, ".claude"));

  const profile = { companions: { living_documentation: { path: "docs" } } };
  const { results } = await install({ root, home, fetch, run: fakeRun({ graphify: "0.9.93" }).run, env: { PATH: await binDirWith("graphify") }, agents: ["claude"], profile });
  assert.equal(find(results, "scaffold-docs").status, "failed");
  assert.match(find(results, "scaffold-docs").detail, /symbolic link/);
  assert.equal(find(results, "graphify-config").status, "failed");
  assert.ok(results.filter((r) => r.action.type === "skill").every((r) => r.status === "failed" && /symbolic link/.test(r.detail)));
  assert.deepEqual((await fs.readdir(outside)).sort(), ["victim.conf"], "nothing was written outside the project");
  assert.equal(await fs.readFile(path.join(outside, "victim.conf"), "utf8"), "keep me\n");
});

test("assertNoSymlink only guards paths below the project root", async () => {
  const root = await tempDir("root");
  const outside = await tempDir("outside");
  await fs.symlink(outside, path.join(root, "link"));
  await assert.rejects(() => assertNoSymlink(root, path.join(root, "link", "file")), /symbolic link/);
  await assert.doesNotReject(() => assertNoSymlink(root, path.join(root, "fresh", "file")));
  await assert.doesNotReject(() => assertNoSymlink(root, path.join(outside, "anything")), "user-scope targets are not checked");
});

test("the project name from a profile is flattened before it reaches generated files", () => {
  const name = projectName({ root: "/tmp/x", profile: { project: { name: "Evil\n# injected heading\r\nline" } } });
  assert.ok(!/[\r\n]/.test(name));
  assert.ok(name.length <= 100);
  assert.equal(projectName({ root: "/tmp/folder-name", profile: {} }), "folder-name");
});

test("Todo template examples are removed and placeholders are personalised safely", () => {
  const html = "<tr data-kategorie=\"beispiel\"><td>x</td></tr>\n<tr data-kategorie=\"sicherheit\"><td>keep</td></tr>";
  assert.equal(stripTodoExamples(html).trim(), "<tr data-kategorie=\"sicherheit\"><td>keep</td></tr>");
  assert.equal(personalizeTodo("PROJEKT TODO — PROJEKT/TODO/TODO.html", "A<b>&Co", "docs/TODO.html"), "A&lt;b&gt;&amp;Co TODO — docs/TODO.html");
});

test("findPython skips interpreters that are too old and picks the first suitable one", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const bin = await binDirWith("python3", "python3.11");
  const reported = { python3: "Python 3.9.6", "python3.11": "Python 3.11.4" };
  const run = async (command) => ({ code: 0, stdout: reported[path.basename(command)] + "\n", stderr: "" });
  const found = await findPython("3.10", { root, home, env: { PATH: bin }, run });
  assert.equal(path.basename(found.path), "python3.11");
  assert.equal(found.version, "3.11.4");

  const onlyOld = await binDirWith("python3");
  assert.equal(await findPython("3.10", { root, home, env: { PATH: onlyOld }, run }), null);
  assert.equal(await findPython("3.10", { root, home, env: { PATH: "" }, run }), null);
});

test("programs that live inside the project are never executed by read-only commands or by installers", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const planted = [".mgd/graphify-venv/bin/graphify", ".mgd/graphify-venv/bin/python3.13", "bin/uv", "node_modules/.bin/git", "node_modules/.bin/graphify", "python3.13", "graphify"];
  for (const file of planted) await write(path.join(root, file), "#!/bin/sh\n", 0o755);
  const alias = path.join(await tempDir("alias"), "project-link");
  await fs.symlink(root, alias);
  const { run, calls } = fakeRun();
  const env = { PATH: ["", ".", "bin", root, path.join(root, "node_modules/.bin"), path.join(alias, "node_modules/.bin"), "/usr/bin"].join(path.delimiter) };

  const registry = await loadCompanionRegistry();
  await inspectCompanions({ root, home, registry, run, env });
  await inspectCompanions({ root: alias, home, registry, run, env });
  await captured(() => companionsCommand(["status", root, "--home", home], { run, env }));
  await captured(() => companionsCommand(["install", root, "--home", home, "--graphify", "project"], { run, env }));
  const { fetch } = await fixtureSources();
  await install({ root, home, fetch, run, env, agents: ["claude"], graphify: "skip" });

  assert.ok(calls.length > 0);
  for (const call of calls) {
    if (path.isAbsolute(call.command)) {
      assert.ok(!(await isInsideProject(root, path.dirname(call.command))), "ran a program from inside the project: " + call.command);
    } else {
      const entries = String(call.env && call.env.PATH).split(path.delimiter);
      assert.ok(entries.every((entry) => entry && path.isAbsolute(entry) && !entry.startsWith(root)), call.name + " was started with a PATH that reaches into the project: " + call.env.PATH);
    }
  }
  assert.equal(await findCommand("graphify", { root, home, env }), null);
  assert.equal(await findCommand("git", { root: alias, home, env: { PATH: path.join(alias, "node_modules/.bin") } }), null, "a PATH entry that reaches the project through a symlink is ignored too");
});

test("PATH entries are compared by real path, relative entries are dropped, and the wrapper sanitizes every child", async () => {
  const root = await tempDir("root");
  const alias = path.join(await tempDir("alias"), "link");
  await fs.symlink(root, alias);
  await fs.mkdir(path.join(root, "node_modules/.bin"), { recursive: true });
  const outside = await tempDir("outside");
  assert.equal(await isInsideProject(root, path.join(alias, "node_modules/.bin")), true);
  assert.equal(await isInsideProject(root, outside), false);
  const entries = await trustedPathEntries(root, { PATH: ["", ".", "bin", root, path.join(alias, "node_modules/.bin"), outside, outside].join(path.delimiter) });
  assert.deepEqual(entries, [outside]);

  let seen;
  const wrapped = withTrustedPath(async (command, args, options) => { seen = options; return { code: 0, stdout: "", stderr: "" }; }, root, { PATH: root + path.delimiter + outside, KEEP: "1" });
  await wrapped("git", ["--version"], {});
  assert.equal(seen.env.PATH, outside);
  assert.equal(seen.env.KEEP, "1");
});

test("a project-local Graphify environment counts as installed only if this CLI created it", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const registry = await loadCompanionRegistry();
  const { run, calls } = fakeRun();
  const status = async () => (await inspectCompanions({ root, home, registry, run, env: { PATH: "" } })).find((item) => item.companion.kind === "tool");

  // a repository can forge anything inside the project, including package metadata
  await write(path.join(root, ".mgd/graphify-venv/bin/graphify"), "#!/bin/sh\n", 0o755);
  await write(path.join(root, ".mgd/graphify-venv/lib/python3.12/site-packages/graphifyy-99.0.0.dist-info/METADATA"), "Name: graphifyy\n");
  assert.equal((await status()).installed, false, "forged metadata proves nothing");

  await recordProjectVenv({ root, home, version: "0.9.93" });
  const known = await status();
  assert.equal(known.installed, true);
  assert.equal(known.tool.projectVenv, true);
  assert.equal(known.tool.executable, null, "never executed");
  assert.equal(known.tool.version, "0.9.93", "the recorded version, not what the environment claims");
  assert.deepEqual(calls.filter((call) => call.command.startsWith(root)), []);

  // the record lives outside the project
  assert.ok(await exists(path.join(home, ".cache/mgd-platform/venvs.json")));
  assert.equal(await exists(path.join(root, ".cache")), false);

  // a replaced folder is no longer the environment that was created (it cannot hold the secret token, and inode reuse cannot fool this)
  const token = await fs.readFile(path.join(root, ".mgd/graphify-venv/.mgd-venv-token"), "utf8");
  await fs.rm(path.join(root, ".mgd/graphify-venv"), { recursive: true });
  await fs.mkdir(path.join(root, ".mgd/graphify-venv"));
  assert.equal((await status()).installed, false);
  await fs.writeFile(path.join(root, ".mgd/graphify-venv/.mgd-venv-token"), "guessed-token");
  assert.equal((await status()).installed, false, "a wrong token proves nothing");
  await fs.writeFile(path.join(root, ".mgd/graphify-venv/.mgd-venv-token"), token);
  assert.equal((await status()).installed, false, "the right token without the program is still not an installation");
  await write(path.join(root, ".mgd/graphify-venv/bin/graphify"), "#!/bin/sh\n", 0o755);
  assert.equal((await status()).installed, true, "the original token with the program does");
  assert.ok(!(await fs.readFile(path.join(home, ".cache/mgd-platform/venvs.json"), "utf8")).includes(token), "the cache holds only the hash");

  // a symlink is ignored even with a matching record
  await fs.rm(path.join(root, ".mgd/graphify-venv"), { recursive: true });
  await fs.symlink(await tempDir("elsewhere"), path.join(root, ".mgd/graphify-venv"));
  assert.equal((await status()).installed, false);
});

test("a later install run skips the graph steps for a recorded project environment instead of executing it", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const { run, calls } = fakeRun();
  await write(path.join(root, ".mgd/graphify-venv/bin/graphify"), "#!/bin/sh\n", 0o755);
  await recordProjectVenv({ root, home, version: "0.9.93" });
  const { results } = await install({ root, home, fetch, run, env: { PATH: "" }, agents: ["claude"] });
  assert.equal(find(results, "graphify-refresh").status, "skipped");
  assert.match(find(results, "graphify-refresh").detail, /does not run programs from inside the project/);
  assert.deepEqual(calls.filter((call) => call.command.startsWith(root)), []);
});

test("the project-local Graphify environment is created fresh and is the only project program that runs", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const python = { path: "/opt/python3.11", version: "3.11.4" };
  const { run, calls } = fakeRun();
  const { results } = await install({ root, home, fetch, run, env: { PATH: "" }, agents: ["claude"], graphify: "project", tools: { python } });
  assert.deepEqual(results.filter((r) => r.status === "failed"), []);

  const venvCall = calls.find((c) => c.args.includes("venv"));
  assert.equal(venvCall.args[0], "-I", "Python ignores the current directory");
  assert.equal(venvCall.cwd, os.tmpdir(), "Python does not run inside the project");
  const pipCall = calls.find((c) => c.args.includes("pip"));
  assert.deepEqual(pipCall.args.slice(0, 3), ["-I", "-m", "pip"]);
  assert.equal(pipCall.cwd, os.tmpdir());
  const refresh = calls.find((c) => c.line === "graphify update .");
  assert.equal(refresh.command, path.join(root, ".mgd", "graphify-venv", "bin", "graphify"), "the environment this run created is used");
  assert.equal(refresh.cwd, root);

  const known = (await inspectCompanions({ root, home, registry: await loadCompanionRegistry(), run, env: { PATH: "" } })).find((item) => item.companion.kind === "tool");
  assert.equal(known.installed, true, "the environment this CLI created is remembered");
  assert.equal(known.tool.projectVenv, true);
});

test("an existing or symlinked environment location is refused, and a failed pip run cleans up", async () => {
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const python = { path: "/opt/python3.11", version: "3.11.4" };
  const options = { home, fetch, env: { PATH: "" }, agents: ["claude"], graphify: "project", tools: { python } };

  const existing = await tempDir("root");
  await write(path.join(existing, ".mgd/graphify-venv/pip.conf"), "[global]\nindex-url = https://attacker.example/\n");
  const first = await install({ ...options, root: existing, run: fakeRun().run });
  assert.match(find(first.results, "graphify-cli").detail, /never reuses an environment it did not just create/);

  const linked = await tempDir("root");
  const target = await tempDir("outside");
  await fs.symlink(target, path.join(linked, ".mgd"));
  const second = await install({ ...options, root: linked, run: fakeRun().run });
  assert.match(find(second.results, "graphify-cli").detail, /symbolic link/);
  assert.deepEqual(await fs.readdir(target), [], "nothing was created behind the link");

  const broken = await tempDir("root");
  const run = fakeRun({ extra: { python: (args) => (args.includes("pip") ? { code: 1, stdout: "", stderr: "no matching distribution" } : { code: 0, stdout: "", stderr: "" }) } }).run;
  const third = await install({ ...options, root: broken, run, tools: { python: { path: "/opt/python3", version: "3.11.4" } } });
  assert.equal(find(third.results, "graphify-cli").status, "failed");
  assert.equal(await exists(path.join(broken, ".mgd/graphify-venv")), false, "the half-created environment is removed");
});

test("a project-local Graphify install explains a missing or too old Python", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const { results } = await install({ root, home, fetch, run: fakeRun().run, env: { PATH: "" }, agents: ["claude"], graphify: "project", tools: { python: null } });
  const cli = find(results, "graphify-cli");
  assert.equal(cli.status, "failed");
  assert.match(cli.detail, /No Python 3\.10 or newer was found/);
  assert.match(cli.detail, /--graphify user/);
});

test("an install that leaves only an outdated Graphify reports why", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const bin = await binDirWith("uv", "graphify");
  const { run } = fakeRun({ graphify: "0.9.10" });
  const { results } = await install({ root, home, fetch, run, env: { PATH: bin }, agents: ["claude"], tools: { hasUv: true } });
  const cli = find(results, "graphify-cli");
  assert.equal(cli.status, "failed");
  assert.match(cli.detail, /older than 0\.9\.65/);
});

test("findCommand locates executables only in absolute program folders", async () => {
  const home = await tempDir("home");
  const bin = await binDirWith("graphify");
  assert.equal(await findCommand("graphify", { home, env: { PATH: "" } }), null);
  assert.equal(await findCommand("graphify", { home, env: { PATH: bin } }), path.join(bin, "graphify"));
  await write(path.join(home, ".local/bin/tool"), "#!/bin/sh\n", 0o755);
  assert.equal(await findCommand("tool", { home, env: { PATH: "" } }), path.join(home, ".local/bin/tool"));
  const moved = await binDirWith("moved-tool");
  assert.equal(await findCommand("moved-tool", { home, env: { PATH: "", UV_TOOL_BIN_DIR: moved } }), path.join(moved, "moved-tool"), "UV_TOOL_BIN_DIR is honoured");
  assert.equal(await findCommand("moved-tool", { home, env: { PATH: "", PIPX_BIN_DIR: moved } }), path.join(moved, "moved-tool"), "PIPX_BIN_DIR is honoured");
});

test("the install command asks for explicit choices before it changes anything", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const { fetch } = await fixtureSources();
  const { run } = fakeRun();
  const env = { PATH: "" };
  const common = ["install", root, "--home", home];

  assert.equal((await captured(() => companionsCommand([...common, "--yes"], { run, fetch, env }))).result, 1, "--yes without --scope is refused");
  assert.equal((await captured(() => companionsCommand([...common, "--yes", "--scope", "project"], { run, fetch, env }))).result, 1, "--yes without --graphify is refused while Graphify is missing");
  assert.equal(await exists(path.join(root, ".claude")), false);

  const dry = await captured(() => companionsCommand([...common], { run, fetch, env }));
  assert.equal(dry.result, 0, "without --yes it is a dry run");
  assert.match(dry.output, /newest releases/);
  assert.match(dry.output, /git ls-remote --tags -- https:\/\/github\.com\/MichaelGahnDESIGN\/MGD_Living-Documentation/);
  assert.match(dry.output, /git clone --depth 1 --branch <newest release, at least v0\.3\.0>/);
  assert.match(dry.output, /git clone --depth 1 --branch <newest release, at least v1\.3\.0>/);
  assert.equal(await exists(path.join(root, ".claude")), false);

  const pinned = await captured(() => companionsCommand([...common, "--pinned"], { run, fetch, env }));
  assert.match(pinned.output, /pinned releases/);
  assert.match(pinned.output, /git clone --depth 1 --branch v0\.3\.0/);
  assert.match(pinned.output, /git clone --depth 1 --branch v1\.3\.0/);
  assert.doesNotMatch(pinned.output, /ls-remote/);

  assert.equal((await captured(() => companionsCommand([...common, "--scope", "nowhere"], { run, fetch, env }))).result, 1);
  assert.equal((await captured(() => companionsCommand(["status", root, "--home", home], { run, env }))).result, 1, "status fails while companions are missing");
});

test("an unreadable profile or an invalid companion path is an error, not a silent default", async () => {
  const home = await tempDir("home");
  const env = { PATH: "" };
  const { run } = fakeRun();

  const broken = await tempDir("root");
  await write(path.join(broken, "MGD_PLATFORM.yml"), "project: [unclosed\n");
  const unreadable = await captured(() => companionsCommand(["install", broken, "--home", home, "--scope", "project", "--graphify", "skip", "--yes"], { run, env }));
  assert.equal(unreadable.result, 1);
  assert.match(unreadable.output, /could not be read/);
  assert.equal(await exists(path.join(broken, ".claude")), false);

  const empty = await tempDir("root");
  await write(path.join(empty, "MGD_PLATFORM.yml"), "");
  assert.equal((await captured(() => companionsCommand(["status", empty, "--home", home], { run, env }))).result, 1, "an empty profile does not crash");

  const traversal = await tempDir("root");
  await write(path.join(traversal, "MGD_PLATFORM.yml"), "companions:\n  living_documentation:\n    path: \"../shared\"\n");
  const status = await captured(() => companionsCommand(["status", traversal, "--home", home], { run, env }));
  assert.match(status.output, /leaves the project directory/);
  const attempt = await captured(() => companionsCommand(["install", traversal, "--home", home, "--scope", "project", "--graphify", "skip", "--yes"], { run, env }));
  assert.equal(attempt.result, 1);
  assert.equal(await exists(path.join(traversal, ".claude")), false);
});

test("init --companions refuses to run without an explicit scope and creates nothing", async () => {
  const target = path.join(await tempDir("parent"), "new-project");
  const { result, output } = await captured(() => initCommand(["--target", target, "--companions"]));
  assert.equal(result, 1);
  assert.match(output, /--scope/);
  assert.equal(await exists(target), false);
});

test("release-check is blocked while a mandatory companion is missing, and says which", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const profile = await fs.readFile(path.join(foundationRoot, "examples/general-platform.yml"), "utf8");
  await write(path.join(root, "MGD_PLATFORM.yml"), profile.replace(/\nrelease_gates:\n(  - .*\n)+/, "\n"));
  const { result, output } = await captured(() => releaseCheckCommand(root, { home, env: { PATH: "" }, run: fakeRun().run }));
  assert.equal(result, 1);
  assert.match(output, /✗ companion living-documentation — not installed/);
  assert.match(output, /✗ companion graphify — not installed/);
  assert.doesNotMatch(output, /evidence missing/, "the block comes from the companions, not from release gates");
});

test("init never writes through a symbolic link, with or without --force", async () => {
  const outside = await tempDir("outside");
  const victim = path.join(outside, "victim.md");
  await write(victim, "keep me\n");

  const forced = await tempDir("root");
  await fs.symlink(victim, path.join(forced, "CLAUDE.md"));
  await assert.rejects(() => captured(() => initCommand(["--target", forced, "--force"])), /symbolic link/);
  assert.equal(await fs.readFile(victim, "utf8"), "keep me\n");

  const dangling = await tempDir("root");
  await fs.symlink(path.join(outside, "does-not-exist"), path.join(dangling, "version.json"));
  await assert.rejects(() => captured(() => initCommand(["--target", dangling])), /symbolic link/);
  assert.equal(await exists(path.join(outside, "does-not-exist")), false);

  const mgd = await tempDir("root");
  await fs.symlink(outside, path.join(mgd, ".mgd"));
  await assert.rejects(() => captured(() => initCommand(["--target", mgd])), /symbolic link/);
  assert.equal(await exists(path.join(outside, "evidence")), false);

  const clean = await tempDir("root");
  assert.equal((await captured(() => initCommand(["--target", clean]))).result, 0);
  assert.ok(await exists(path.join(clean, "MGD_PLATFORM.yml")));
});

test("git runs without hooks, fsmonitor, submodules, LFS downloads or inherited repository overrides", () => {
  const env = gitEnvironment({ PATH: "/usr/bin", GIT_DIR: "/x", GIT_WORK_TREE: "/y", GIT_CONFIG_GLOBAL: "/z", GIT_CONFIG_COUNT: "1", GIT_CONFIG_KEY_0: "core.sshCommand", GIT_CONFIG_VALUE_0: "evil" });
  assert.equal(env.GIT_DIR, undefined);
  assert.equal(env.GIT_WORK_TREE, undefined);
  assert.equal(env.GIT_CONFIG_GLOBAL, undefined);
  const settings = Object.fromEntries(Array.from({ length: Number(env.GIT_CONFIG_COUNT) }, (_, index) => [env["GIT_CONFIG_KEY_" + index], env["GIT_CONFIG_VALUE_" + index]]));
  assert.deepEqual(settings, { "core.hooksPath": "/dev/null", "core.fsmonitor": "false", "submodule.recurse": "false" });
  assert.equal(env.GIT_TERMINAL_PROMPT, "0");
  assert.equal(env.GIT_LFS_SKIP_SMUDGE, "1");
  assert.equal(env.PATH, "/usr/bin");
});

test("values from untrusted files are made printable and cannot inject terminal escapes", () => {
  assert.equal(printable("a\u001b[2Jb\nc"), "a?[2Jb?c");
  assert.equal(printable("x".repeat(500)).length, 200);
  assert.throws(() => resolveInside("/tmp/p", "a/\u001b[31mred"), (error) => !/\u001b/.test(error.message));
});

test("uv found outside PATH (for example in ~/.local/bin) is started by its absolute path", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  await write(path.join(home, ".local/bin/uv"), "#!/bin/sh\n", 0o755);
  const { fetch } = await fixtureSources();
  const { run, calls } = fakeRun();
  const uv = await findCommand("uv", { root, home, env: { PATH: "" } });
  assert.equal(uv, path.join(home, ".local/bin/uv"));
  await install({ root, home, fetch, run, env: { PATH: "" }, agents: ["claude"], tools: { uv } });
  assert.equal(calls.find((call) => call.name === "uv").command, uv, "not the bare name, which would not resolve on the sanitised PATH");
});

test("an existing skill is kept without checking its release, and the plan says how to reinstall", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  await write(path.join(root, ".claude/skills/todo/SKILL.md"), "---\nname: todo\n---\nolder release\n");
  const plan = await buildPlan({ inspection: await inspect(root, home), root, home, scope: "project", agents: ["claude"], graphify: "skip" });
  const todo = plan.actions.find((a) => a.type === "skill" && a.skill === "todo");
  assert.equal(todo.state, "keep");
  assert.match(todo.detail, /release not checked.*remove the folder to reinstall/);
});

test("a companion artifact that is a symbolic link or the wrong file type does not count as present", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const outside = await tempDir("outside");
  await write(path.join(outside, "README.md"), "external docs");
  await write(path.join(outside, "TODO.html"), "external todo");
  const registry = await loadCompanionRegistry();
  const item = async (id) => (await inspectCompanions({ root, home, registry, run: fakeRun().run, env: { PATH: "" } })).find((entry) => entry.companion.id === id);

  await fs.symlink(outside, path.join(root, "DOKUMENTATION"));
  const docs = await item("living-documentation");
  assert.equal(docs.project.present, false);
  assert.match(docs.project.error, /symbolic link/);
  await fs.rm(path.join(root, "DOKUMENTATION"));

  await fs.mkdir(path.join(root, "DOKUMENTATION"));
  await fs.symlink(path.join(outside, "README.md"), path.join(root, "DOKUMENTATION/README.md"));
  assert.equal((await item("living-documentation")).project.present, false, "a marker file that is a link does not count");
  await fs.rm(path.join(root, "DOKUMENTATION/README.md"));
  await write(path.join(root, "DOKUMENTATION/README.md"), "real docs");
  assert.equal((await item("living-documentation")).project.present, true);

  await fs.mkdir(path.join(root, "TODO"));
  await fs.symlink(path.join(outside, "TODO.html"), path.join(root, "TODO/TODO.html"));
  assert.equal((await item("todo")).project.present, false);
  await fs.rm(path.join(root, "TODO/TODO.html"));
  await fs.mkdir(path.join(root, "TODO/TODO.html"));
  assert.equal((await item("todo")).project.present, false, "a folder is not a file");
  await fs.rmdir(path.join(root, "TODO/TODO.html"));
  await write(path.join(root, "TODO/TODO.html"), "real todo");
  assert.equal((await item("todo")).project.present, true);
});

test("a folder whose name merely starts with two dots is inside the project", async () => {
  assert.equal(isOutside(".."), true);
  assert.equal(isOutside(".." + path.sep + "x"), true);
  assert.equal(isOutside("..bin"), false);
  assert.equal(isOutside("..bin" + path.sep + "x"), false);
  assert.equal(isOutside(path.resolve("/abs")), true);

  const root = await tempDir("root");
  const dotted = path.join(root, "..bin");
  await fs.mkdir(dotted);
  assert.equal(await isInsideProject(root, dotted), true, "PATH entries like <project>/..bin are dropped");
  assert.deepEqual(await trustedPathEntries(root, { PATH: dotted }), []);
  const outside = await tempDir("outside");
  await fs.symlink(outside, path.join(root, "..link"));
  await assert.rejects(() => assertNoSymlink(root, path.join(root, "..link", "file")), /symbolic link/, "the symlink guard applies to ..-prefixed names too");
});

test("--pinned replaces a different installed Graphify instead of keeping it", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const bin = await binDirWith("graphify", "uv");
  const inspection = await inspect(root, home, { bin, run: fakeRun({ graphify: "0.9.70" }).run });
  const normal = await buildPlan({ inspection, root, home, scope: "project", agents: ["claude"], graphify: "user", tools: { uv: "/x/uv" } });
  assert.equal(normal.actions.find((a) => a.type === "graphify-cli").state, "keep", "any copy at or above the floor is accepted by default");
  const pinned = await buildPlan({ inspection, root, home, scope: "project", agents: ["claude"], graphify: "user", tools: { uv: "/x/uv" }, pinned: true });
  const cli = pinned.actions.find((a) => a.type === "graphify-cli");
  assert.equal(cli.state, "install");
  assert.deepEqual(cli.commands, [["/x/uv", ["tool", "install", "--upgrade", "graphifyy==0.9.93"]]]);
  assert.match(cli.detail, /installed 0\.9\.70, --pinned wants 0\.9\.93/);

  const exact = await inspect(root, home, { bin, run: fakeRun({ graphify: "0.9.93" }).run });
  const same = await buildPlan({ inspection: exact, root, home, scope: "project", agents: ["claude"], graphify: "user", pinned: true });
  assert.equal(same.actions.find((a) => a.type === "graphify-cli").state, "keep");
});

test("a recorded project environment whose graphify program is gone no longer counts as installed", async () => {
  const root = await tempDir("root");
  const home = await tempDir("home");
  const registry = await loadCompanionRegistry();
  const status = async () => (await inspectCompanions({ root, home, registry, run: fakeRun().run, env: { PATH: "" } })).find((item) => item.companion.kind === "tool");
  await write(path.join(root, ".mgd/graphify-venv/bin/graphify"), "#!/bin/sh\n", 0o755);
  await recordProjectVenv({ root, home, version: "0.9.93" });
  assert.equal((await status()).installed, true);
  await fs.rm(path.join(root, ".mgd/graphify-venv/bin/graphify"));
  assert.equal((await status()).installed, false, "deleted program");
  await fs.mkdir(path.join(root, ".mgd/graphify-venv/bin/graphify"));
  assert.equal((await status()).installed, false, "a folder is not a program");
});
