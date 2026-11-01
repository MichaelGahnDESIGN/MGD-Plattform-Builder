import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import Ajv2020 from "ajv/dist/2020.js";
import { foundationRoot } from "../src/lib/paths.js";

const moduleDir = path.join(foundationRoot, "templates", "php-mysql-starter", "modules", "backup");

/** Läuft PHP mit den Erweiterungen, die die Modultests brauchen? Sonst werden die PHP-Tests übersprungen. */
function phpSupport() {
  const result = spawnSync("php", ["-m"], { encoding: "utf8" });
  if (result.error || result.status !== 0) return { ok: false, reason: "php not available" };
  const modules = new Set(result.stdout.split(/\r?\n/).map((line) => line.trim().toLowerCase()));
  const missing = ["sodium", "zlib", "curl", "pdo_sqlite"].filter((name) => !modules.has(name));
  return missing.length === 0 ? { ok: true } : { ok: false, reason: "missing PHP extensions: " + missing.join(", ") };
}

test("backup module manifest matches the module package schema", async () => {
  const schema = JSON.parse(await fs.readFile(path.join(foundationRoot, "schema", "module-package.schema.json"), "utf8"));
  const manifest = JSON.parse(await fs.readFile(path.join(moduleDir, "module.json"), "utf8"));
  const validate = new Ajv2020({ allErrors: true, strict: false }).compile(schema);
  assert.equal(validate(manifest), true, JSON.stringify(validate.errors));
  assert.equal(manifest.id, "backup");
  assert.equal(manifest.license, "free");
  assert.ok(manifest.menu.every((item) => item.role === "admin"), "menu entries are admin-only");
  await fs.access(path.join(moduleDir, manifest.entry));
  await fs.access(path.join(moduleDir, manifest.migrations));
});

test("backup module ships German and English documentation", async () => {
  for (const file of ["wiki/30-Backup-Modul.md", "WIKI/18-CMS/BACKUP-MODULE.md"]) {
    const text = await fs.readFile(path.join(foundationRoot, file), "utf8");
    assert.match(text, /MGDB1/, file);
    assert.match(text, /(DSGVO|GDPR)/, file);
    assert.match(text, /(AVV|DPA)/, file);
  }
});

test("backup module contains no private keys or access tokens", async () => {
  const forbidden = [
    /-----BEGIN [A-Z ]*PRIVATE KEY-----/,
    /\bAKIA[0-9A-Z]{16}\b/,
    /ghp_[A-Za-z0-9]{20,}/,
  ];
  const files = [];
  const walk = async (dir) => {
    for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) await walk(full);
      else files.push(full);
    }
  };
  await walk(moduleDir);
  for (const file of files) {
    const text = await fs.readFile(file, "utf8");
    for (const pattern of forbidden) {
      assert.doesNotMatch(text, pattern, path.relative(foundationRoot, file));
    }
  }
});

const support = phpSupport();

test("backup module PHP tests (format, runs, FTPS, security, restore)", { skip: support.ok ? false : support.reason, timeout: 300000 }, async () => {
  const tmp = await fs.mkdtemp(path.join(os.tmpdir(), "mgd-backup-php-"));
  try {
    const result = spawnSync("php", [path.join(moduleDir, "tests", "run.php")], {
      encoding: "utf8",
      env: { ...process.env, TMPDIR: tmp, MGD_HUB_TOOL: "" },
      maxBuffer: 32 * 1024 * 1024,
    });
    assert.equal(result.status, 0, (result.stdout || "").split("\n").slice(-15).join("\n") + (result.stderr || ""));
    assert.match(result.stdout, /\d+ Pruefungen bestanden/);
    assert.doesNotMatch(result.stdout + result.stderr, /(Warning|Deprecated|Fatal error)/);
  } finally {
    await fs.rm(tmp, { recursive: true, force: true });
  }
});

test("backup module PHP files are syntactically valid", { skip: support.ok ? false : support.reason }, async () => {
  const files = [];
  const walk = async (dir) => {
    for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) await walk(full);
      else if (entry.name.endsWith(".php")) files.push(full);
    }
  };
  await walk(moduleDir);
  assert.ok(files.length > 30);
  for (const file of files) {
    const result = spawnSync("php", ["-l", file], { encoding: "utf8" });
    assert.equal(result.status, 0, file + "\n" + result.stdout + result.stderr);
  }
});
