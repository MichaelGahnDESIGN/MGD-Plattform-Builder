import crypto from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { foundationRoot } from "./paths.js";

export const templatesRoot = path.join(foundationRoot, "templates");
const EXCLUDED = new Set([".git", "node_modules", "vendor", "config.php", ".DS_Store"]);

export async function listTemplates(root = templatesRoot) {
  const entries = await fs.readdir(root, { withFileTypes: true });
  const templates = [];

  for (const entry of entries.filter((item) => item.isDirectory())) {
    const manifestPath = path.join(root, entry.name, "template.json");
    const raw = await fs.readFile(manifestPath, "utf8").catch(() => null);
    if (raw) templates.push({ dir: path.join(root, entry.name), manifest: JSON.parse(raw) });
  }

  return templates;
}

/** Checks the mandatory template contract: light + dark variant, version and release-notes files. */
export async function checkTemplate(template) {
  const problems = [];
  const exists = (relative) => fs.access(path.join(template.dir, relative)).then(() => true).catch(() => false);
  const variants = template.manifest.variants || {};

  for (const variant of ["light", "dark"]) {
    if (!variants[variant]) problems.push("missing " + variant + " variant");
    else if (!await exists(variants[variant])) problems.push(variant + " variant file not found: " + variants[variant]);
  }

  for (const file of ["version.json", "release-notes.json", "README.md", template.manifest.entry || "entry"]) {
    if (!await exists(file)) problems.push("missing " + file);
  }

  if (template.manifest.license === "LicenseRef-MGD") {
    problems.push(...await checkMgdLicense(template.dir));
  }

  return problems;
}

async function sha256(file) {
  return crypto.createHash("sha256").update(await fs.readFile(file)).digest("hex");
}

/** Parses `'relative/path' => 'sha256'` pairs from the starter's LicenseIntegrity::FILES constant. */
export function parseIntegrityHashes(phpSource) {
  const block = /FILES\s*=\s*\[([\s\S]*?)\];/.exec(phpSource);
  if (!block) return {};
  return Object.fromEntries(
    Array.from(block[1].matchAll(/'([^']+)'\s*=>\s*'([0-9a-f]{64})'/g), (match) => [match[1], match[2]])
  );
}

/**
 * MGD-licensed templates must ship the unchanged root MGD-Lizenz.md, and the integrity
 * hashes of the "powered by" label must match the files.
 */
export async function checkMgdLicense(dir) {
  const problems = [];
  const licenseCopy = path.join(dir, "MGD-Lizenz.md");
  const rootLicense = path.join(foundationRoot, "MGD-Lizenz.md");
  const copyExists = await fs.access(licenseCopy).then(() => true).catch(() => false);

  if (!copyExists) return ["missing MGD-Lizenz.md"];
  const agentRules = await fs.readFile(path.join(dir, "AGENTS.md"), "utf8").catch(() => "");
  if (!agentRules.includes("Lizenzschutz")) problems.push("AGENTS.md with the license-protection section is missing");
  if (await sha256(licenseCopy) !== await sha256(rootLicense)) problems.push("MGD-Lizenz.md differs from the foundation license");

  const integrityFile = path.join(dir, "src", "Core", "License", "LicenseIntegrity.php");
  const source = await fs.readFile(integrityFile, "utf8").catch(() => null);
  if (source === null) return [...problems, "missing LicenseIntegrity.php"];

  for (const [relative, expected] of Object.entries(parseIntegrityHashes(source))) {
    const file = path.join(dir, relative);
    const actual = await sha256(file).catch(() => null);
    if (actual !== expected) problems.push("license integrity hash mismatch: " + relative);
  }

  return problems;
}

export async function copyTemplate(template, target) {
  await fs.cp(template.dir, target, {
    recursive: true,
    errorOnExist: false,
    filter: (source) => !EXCLUDED.has(path.basename(source))
  });
}
