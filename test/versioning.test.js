import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import {
  bumpVersion,
  findVersionDrift,
  formatVersionLabel,
  nextVersionInfo,
  syncVersionTargets
} from "../src/lib/versioning.js";
import { addEntry, buildEntry, filterForAudience } from "../src/lib/release-notes.js";

test("formats version labels with status", () => {
  assert.equal(formatVersionLabel({ version: "0.5.1", status: "pre-alpha" }), "0.5.1 Pre-Alpha");
  assert.equal(formatVersionLabel({ version: "1.0.0", status: "pre-release" }), "1.0.0 Pre-Release");
  assert.throws(() => formatVersionLabel({ version: "1.0", status: "beta" }), /Invalid version/);
  assert.throws(() => formatVersionLabel({ version: "1.0.0", status: "gold" }), /Unknown version status/);
});

test("bumps major, minor and patch according to the scheme", () => {
  assert.equal(bumpVersion("0.5.1", "patch"), "0.5.2");
  assert.equal(bumpVersion("0.5.1", "minor"), "0.6.0");
  assert.equal(bumpVersion("0.5.1", "major"), "1.0.0");
  assert.throws(() => bumpVersion("0.5.1", "build"), /Unknown bump part/);
});

test("next version info does not mutate the input", () => {
  const info = { version: "0.0.1", status: "pre-alpha", released_at: null };
  const next = nextVersionInfo(info, { bump: "minor", status: "alpha", today: "2026-09-25" });
  assert.deepEqual(next, { version: "0.1.0", status: "alpha", released_at: "2026-09-25" });
  assert.equal(info.version, "0.0.1");
});

test("syncs VERSION and package.json and detects drift", async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-version-"));
  await fs.writeFile(path.join(dir, "package.json"), JSON.stringify({ name: "x", version: "0.0.0" }));
  const info = { version: "0.2.0", status: "beta", sync_targets: ["VERSION", "package.json"] };

  assert.equal((await findVersionDrift(dir, info)).length, 2);
  await syncVersionTargets(dir, info);
  assert.equal((await findVersionDrift(dir, info)).length, 0);
  assert.equal(JSON.parse(await fs.readFile(path.join(dir, "package.json"), "utf8")).name, "x");
});

test("frontend release notes only contain frontend entries", () => {
  const frontend = buildEntry({ version: "0.1.0", date: "2026-09-25", title: "Neues Menü", audience: "frontend,backoffice", type: "feature" });
  const editor = buildEntry({ version: "0.1.0", date: "2026-09-25", title: "Editor-Fix", audience: "editor", type: "fix" });
  const entries = addEntry(addEntry({ entries: [] }, editor), frontend).entries;

  assert.deepEqual(filterForAudience(entries, "frontend").map((entry) => entry.title), ["Neues Menü"]);
  assert.equal(filterForAudience(entries, "backoffice").length, 2);
  assert.throws(() => buildEntry({ version: "0.1.0", date: "x", title: "t", audience: "players" }), /audience/);
});
