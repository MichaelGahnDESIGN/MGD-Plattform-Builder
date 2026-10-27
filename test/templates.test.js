import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { checkMgdLicense, checkTemplate, listTemplates, parseIntegrityHashes } from "../src/lib/templates.js";
import { foundationRoot } from "../src/lib/paths.js";

test("parses integrity hashes from the PHP constant", () => {
  const hash = "a".repeat(64);
  const source = "public const FILES = [\n  'MGD-Lizenz.md' => '" + hash + "',\n];";
  assert.deepEqual(parseIntegrityHashes(source), { "MGD-Lizenz.md": hash });
});

test("every bundled template passes the contract incl. light/dark and license label", async () => {
  const templates = await listTemplates();
  assert.ok(templates.length > 0);
  for (const template of templates) {
    assert.deepEqual(await checkTemplate(template), [], template.manifest.id);
  }
});

test("a modified license copy or label file is detected", async () => {
  const source = path.join(foundationRoot, "templates", "php-mysql-starter");
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-license-"));
  await fs.cp(source, dir, { recursive: true, filter: (item) => !item.includes("node_modules") });

  await fs.appendFile(path.join(dir, "public", "assets", "css", "powered-by.css"), "\n.mgd-powered-by { display: none; }\n");
  await fs.appendFile(path.join(dir, "MGD-Lizenz.md"), "\nGeändert.\n");

  const problems = await checkMgdLicense(dir);
  assert.ok(problems.some((item) => item.includes("differs from the foundation license")));
  assert.ok(problems.some((item) => item.includes("powered-by.css")));
});
