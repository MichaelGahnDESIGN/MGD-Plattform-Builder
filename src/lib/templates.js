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

  return problems;
}

export async function copyTemplate(template, target) {
  await fs.cp(template.dir, target, {
    recursive: true,
    errorOnExist: false,
    filter: (source) => !EXCLUDED.has(path.basename(source))
  });
}
