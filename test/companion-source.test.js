import test from "node:test";
import os from "node:os";
import path from "node:path";
import assert from "node:assert/strict";
import fs from "node:fs/promises";
import { describeResolved, highestRelease, isTracking, parseTags, resolveSource } from "../src/lib/companion-source.js";
import { executePlan, fetchSource } from "../src/lib/companion-install.js";
import { buildPlan } from "../src/lib/companion-plan.js";
import { inspectCompanions, loadCompanionRegistry } from "../src/lib/companions.js";

const A = "a".repeat(40);
const B = "b".repeat(40);
const C = "c".repeat(40);
const D = "d".repeat(40);
const OWNER = "https://github.com/MichaelGahnDESIGN";
const SOURCE = { repo: OWNER + "/MGD_Todo_SKILL", ref: "v1.3.0", commit: A, track: "latest-release" };

const listing = [
  A + "\trefs/tags/v1.3.0",
  B + "\trefs/tags/v1.10.0",
  C + "\trefs/tags/v1.9.2",
  D + "\trefs/tags/v2.0.0-rc1",
  C + "\trefs/tags/nightly",
  "1".repeat(40) + "\trefs/tags/v1.2.0"
].join("\n");

test("parseTags keeps plain release tags and prefers the peeled commit of annotated tags", () => {
  const tags = parseTags([
    "1".repeat(40) + "\trefs/tags/v0.3.0",
    A + "\trefs/tags/v0.3.0^{}",
    B + "\trefs/tags/v0.2.1",
    C + "\trefs/tags/v0.2.1^{}",
    D + "\trefs/tags/v1.0.0-beta",
    "garbage"
  ].join("\n"));
  assert.deepEqual([...tags], [["v0.3.0", A], ["v0.2.1", C]]);
});

test("highestRelease compares numerically, honours the floor and ignores pre-releases", () => {
  const tags = parseTags(listing);
  assert.deepEqual(highestRelease(tags, "v1.3.0"), { ref: "v1.10.0", commit: B });
  assert.deepEqual(highestRelease(tags, "v1.10.0"), { ref: "v1.10.0", commit: B });
  assert.equal(highestRelease(tags, "v1.11.0"), null, "nothing at or above the floor");
  assert.equal(highestRelease(new Map(), "v1.3.0"), null);
});

test("by default the newest release of the companion's own repository is installed", async () => {
  const calls = [];
  const run = async (command, args, options) => {
    calls.push({ command, args, options });
    return { code: 0, stdout: listing, stderr: "" };
  };
  const resolved = await resolveSource(SOURCE, { run, owner: OWNER });
  assert.deepEqual(resolved, { repo: SOURCE.repo, ref: "v1.10.0", commit: B, mode: "latest" });
  assert.deepEqual(calls[0].args, ["ls-remote", "--tags", "--", SOURCE.repo]);
  assert.ok(!("GIT_DIR" in calls[0].options.env));
  assert.equal(calls[0].options.env.GIT_TERMINAL_PROMPT, "0");
  assert.match(describeResolved(resolved), /^v1\.10\.0 \(bbbbbbb\)$/);
});

test("--pinned and sources without tracking use the registry pin and never ask the network", async () => {
  const run = async () => assert.fail("must not call git");
  const pinned = await resolveSource(SOURCE, { run, owner: OWNER, pinned: true });
  assert.deepEqual(pinned, { repo: SOURCE.repo, ref: "v1.3.0", commit: A, mode: "pinned" });
  assert.match(describeResolved(pinned), /pinned/);
  const untracked = await resolveSource({ ...SOURCE, track: undefined }, { run, owner: OWNER });
  assert.equal(untracked.mode, "pinned");
  assert.equal(isTracking(SOURCE), true);
  assert.equal(isTracking({ ...SOURCE, track: undefined }), false);
});

test("only repositories of the registry owner may be tracked", async () => {
  const run = async () => assert.fail("must not call git");
  await assert.rejects(() => resolveSource({ ...SOURCE, repo: "https://github.com/someone-else/MGD_Todo_SKILL" }, { run, owner: OWNER }), /does not belong/);
  await assert.rejects(() => resolveSource({ ...SOURCE, repo: OWNER + "-evil/x" }, { run, owner: OWNER }), /does not belong/);
  await assert.rejects(() => resolveSource(SOURCE, { run, owner: undefined }), /does not belong/);
});

test("an unreachable repository or no release at the floor gives an actionable error", async () => {
  const offline = async () => ({ code: 128, stdout: "", stderr: "could not resolve host" });
  await assert.rejects(() => resolveSource(SOURCE, { run: offline, owner: OWNER }), /could not resolve host.*--pinned/s);
  const old = async () => ({ code: 0, stdout: A + "\trefs/tags/v1.2.0", stderr: "" });
  await assert.rejects(() => resolveSource(SOURCE, { run: old, owner: OWNER }), /tested floor v1\.3\.0.*--pinned/s);
});

function gitFake({ head, cloneArgs = [] }) {
  return async (command, args) => {
    if (args[0] === "ls-remote") return { code: 0, stdout: listing, stderr: "" };
    if (args[0] === "clone") {
      cloneArgs.push(args);
      await fs.mkdir(args.at(-1), { recursive: true });
      return { code: 0, stdout: "", stderr: "" };
    }
    return { code: 0, stdout: head + "\n", stderr: "" };
  };
}

test("fetchSource clones the newest release and verifies it against the commit from ls-remote", async () => {
  const cloneArgs = [];
  const ok = await fetchSource(SOURCE, { run: gitFake({ head: B, cloneArgs }), owner: OWNER });
  assert.equal(cloneArgs[0][cloneArgs[0].indexOf("--branch") + 1], "v1.10.0");
  assert.deepEqual([ok.ref, ok.commit, ok.mode], ["v1.10.0", B, "latest"]);
  await ok.cleanup();

  await assert.rejects(() => fetchSource(SOURCE, { run: gitFake({ head: A }), owner: OWNER }), /Commit mismatch.*tag moved/s);
  const pinned = await fetchSource(SOURCE, { run: gitFake({ head: A, cloneArgs }), owner: OWNER, pinned: true });
  assert.equal(cloneArgs.at(-1)[cloneArgs.at(-1).indexOf("--branch") + 1], "v1.3.0");
  await pinned.cleanup();
});

test("the install asks for the newest release, passes the owner, and reports which release it installed", async () => {
  const seen = [];
  const fetch = async (source, options) => {
    seen.push({ repo: source.repo, owner: options.owner, pinned: options.pinned });
    const dir = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-source-test-"));
    await fs.mkdir(path.join(dir, "skills/living-documentation"), { recursive: true });
    await fs.mkdir(path.join(dir, "skills/project-start-assistant"), { recursive: true });
    await fs.mkdir(path.join(dir, "todo"), { recursive: true });
    await fs.writeFile(path.join(dir, "skills/living-documentation/SKILL.md"), "---\nname: living-documentation\n---\n");
    await fs.writeFile(path.join(dir, "skills/project-start-assistant/SKILL.md"), "---\nname: project-start-assistant\n---\n");
    await fs.writeFile(path.join(dir, "SKILL.md"), "---\nname: todo\n---\n");
    await fs.writeFile(path.join(dir, "LICENSE"), "MIT");
    await fs.mkdir(path.join(dir, "docs"), { recursive: true });
    await fs.writeFile(path.join(dir, "docs/setup.md"), "x");
    await fs.writeFile(path.join(dir, "todo/TODO.template.html"), "<tbody></tbody>");
    return { dir, cleanup: () => fs.rm(dir, { recursive: true, force: true }), ref: "v9.9.9", commit: "9".repeat(40), mode: "latest" };
  };
  const root = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-source-root-"));
  const home = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-source-home-"));
  try {
    const registry = await loadCompanionRegistry();
    const run = async () => ({ code: 1, stdout: "", stderr: "" });
    const inspection = await inspectCompanions({ root, home, registry, run, env: { PATH: "" } });
    const plan = await buildPlan({ inspection, root, home, scope: "project", agents: ["claude"], graphify: "skip" });
    const results = await executePlan(plan, { registry, root, home, run, fetch, env: { PATH: "" } });
    assert.deepEqual(seen.map((entry) => entry.owner), [registry.owner, registry.owner]);
    assert.deepEqual(seen.map((entry) => entry.pinned), [false, false]);
    const todo = results.find((r) => r.action.type === "skill" && r.action.skill === "todo");
    assert.equal(todo.status, "installed");
    assert.equal(todo.detail, "v9.9.9 (9999999)");
  } finally {
    await fs.rm(root, { recursive: true, force: true });
    await fs.rm(home, { recursive: true, force: true });
  }
});

test("parseConfigParameters reads both GIT_CONFIG_PARAMETERS formats, escaped quotes, and rejects garbage", async () => {
  const { parseConfigParameters } = await import("../src/lib/companion-source.js");
  assert.deepEqual(parseConfigParameters("'http.proxyAuthMethod=basic'"), [["http.proxyAuthMethod", "basic"]]);
  assert.deepEqual(parseConfigParameters("'http.proxyauthmethod'='basic' 'safe.directory'='/a b'"), [["http.proxyauthmethod", "basic"], ["safe.directory", "/a b"]]);
  assert.deepEqual(parseConfigParameters("'x.y=it'\\''s' 'a.b'"), [["x.y", "it's"], ["a.b", ""]]);
  assert.equal(parseConfigParameters("http.proxy=x"), null, "unquoted text is not a valid entry");
  assert.equal(parseConfigParameters("'unterminated"), null);
});

test("git keeps the network-only settings a sandbox hands over and drops everything else", async () => {
  const { gitEnvironment } = await import("../src/lib/companion-source.js");
  const settingsOf = (env) => Object.fromEntries(Array.from({ length: Number(env.GIT_CONFIG_COUNT) }, (_, index) => [env["GIT_CONFIG_KEY_" + index], env["GIT_CONFIG_VALUE_" + index]]));

  // what a proxying sandbox sets: without it git says "Proxy CONNECT aborted"
  const sandbox = gitEnvironment({ PATH: "/usr/bin", GIT_CONFIG_PARAMETERS: "'http.proxyAuthMethod=basic'", GIT_CONFIG_COUNT: "2", GIT_CONFIG_KEY_0: "safe.directory", GIT_CONFIG_VALUE_0: "/a", GIT_CONFIG_KEY_1: "safe.directory", GIT_CONFIG_VALUE_1: "/b" });
  assert.equal(sandbox.GIT_CONFIG_PARAMETERS, "'http.proxyAuthMethod=basic'");
  assert.deepEqual(Object.values(settingsOf(sandbox)), ["/b", "/dev/null", "false", "false"], "inherited safe.directory entries stay (same key: last wins), ours are appended");
  assert.equal(sandbox.GIT_CONFIG_COUNT, "5");
  assert.equal(sandbox.GIT_CONFIG_KEY_2, "core.hooksPath");
  assert.equal(sandbox.GIT_CONFIG_VALUE_2, "/dev/null");

  // the real sandbox adds an empty credential-helper reset for its local proxy: harmless, so it must not cost the proxy settings
  const real = gitEnvironment({ GIT_CONFIG_PARAMETERS: "'http.proxyAuthMethod=basic' 'credential.http://localhost:59011.helper='" });
  assert.equal(real.GIT_CONFIG_PARAMETERS, "'http.proxyAuthMethod=basic' 'credential.http://localhost:59011.helper='");
  assert.equal(gitEnvironment({ GIT_CONFIG_PARAMETERS: "'credential.helper='" }).GIT_CONFIG_PARAMETERS, "'credential.helper='");
  // ... but a helper WITH content would run a program
  assert.equal(gitEnvironment({ GIT_CONFIG_PARAMETERS: "'http.proxyAuthMethod=basic' 'credential.helper=!evil'" }).GIT_CONFIG_PARAMETERS, undefined);
  assert.equal(gitEnvironment({ GIT_CONFIG_PARAMETERS: "'credential.http://localhost:1.helper=store'" }).GIT_CONFIG_PARAMETERS, undefined);

  // one risky key makes the whole variable go away
  const hostile = gitEnvironment({ GIT_CONFIG_PARAMETERS: "'http.proxy=http://p' 'core.sshCommand=evil'", GIT_CONFIG_COUNT: "2", GIT_CONFIG_KEY_0: "http.proxy", GIT_CONFIG_VALUE_0: "http://p", GIT_CONFIG_KEY_1: "url.x.insteadOf", GIT_CONFIG_VALUE_1: "y" });
  assert.equal(hostile.GIT_CONFIG_PARAMETERS, undefined);
  assert.deepEqual(settingsOf(hostile), { "core.hooksPath": "/dev/null", "core.fsmonitor": "false", "submodule.recurse": "false" });

  // unparseable, oversized or incomplete input is dropped too
  assert.equal(gitEnvironment({ GIT_CONFIG_PARAMETERS: "garbage" }).GIT_CONFIG_PARAMETERS, undefined);
  assert.equal(gitEnvironment({ GIT_CONFIG_COUNT: "5000", GIT_CONFIG_KEY_0: "safe.directory", GIT_CONFIG_VALUE_0: "x" }).GIT_CONFIG_COUNT, "3");
  assert.equal(gitEnvironment({ GIT_CONFIG_COUNT: "2", GIT_CONFIG_KEY_0: "safe.directory", GIT_CONFIG_VALUE_0: "x" }).GIT_CONFIG_COUNT, "3", "a missing KEY_1/VALUE_1 drops the inherited entries");
  // repository overrides and config files never pass
  const overrides = gitEnvironment({ GIT_DIR: "/x", GIT_WORK_TREE: "/y", GIT_INDEX_FILE: "/z", GIT_CONFIG_GLOBAL: "/g", GIT_CONFIG_SYSTEM: "/s", GIT_CONFIG_NOSYSTEM: "1" });
  for (const name of ["GIT_DIR", "GIT_WORK_TREE", "GIT_INDEX_FILE", "GIT_CONFIG_GLOBAL", "GIT_CONFIG_SYSTEM", "GIT_CONFIG_NOSYSTEM"]) assert.equal(overrides[name], undefined, name);
});

test("a real git accepts the merged settings (inherited, ours, and the parameter format) without network", async () => {
  const { execFile } = await import("node:child_process");
  const { gitEnvironment } = await import("../src/lib/companion-source.js");
  const git = (env, args) => new Promise((resolve) => execFile("git", args, { env, cwd: os.tmpdir() }, (error, stdout) => resolve(error ? "ERROR " + error.message : stdout.trim())));
  const env = gitEnvironment({ PATH: process.env.PATH, HOME: os.tmpdir(), GIT_CONFIG_PARAMETERS: "'http.proxyAuthMethod=basic'", GIT_CONFIG_COUNT: "1", GIT_CONFIG_KEY_0: "safe.directory", GIT_CONFIG_VALUE_0: "/inherited" });
  assert.equal(await git(env, ["config", "--get", "http.proxyauthmethod"]), "basic");
  assert.equal(await git(env, ["config", "--get", "safe.directory"]), "/inherited");
  assert.equal(await git(env, ["config", "--get", "core.hooksPath"]), "/dev/null");
  assert.equal(await git(env, ["config", "--get", "core.fsmonitor"]), "false");
});
