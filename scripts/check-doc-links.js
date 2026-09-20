import fs from "node:fs/promises";
import path from "node:path";

const root = process.cwd();
const ignored = new Set(["node_modules", ".git"]);
const failures = [];

async function collectMarkdown(dir) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    if (ignored.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) files.push(...await collectMarkdown(full));
    if (entry.isFile() && entry.name.endsWith(".md")) files.push(full);
  }

  return files;
}

async function exists(target) {
  return fs.access(target).then(() => true).catch(() => false);
}

for (const file of await collectMarkdown(root)) {
  const content = await fs.readFile(file, "utf8");
  const markdownLinks = content.matchAll(/\[[^\]]*\]\(([^)]+)\)/g);

  for (const match of markdownLinks) {
    let href = match[1].trim();
    if (!href || href.startsWith("#") || href.startsWith("http://") || href.startsWith("https://") || href.startsWith("mailto:")) continue;
    href = href.split("#")[0];
    if (!href) continue;

    const target = path.resolve(path.dirname(file), decodeURIComponent(href));
    if (!await exists(target)) {
      failures.push(path.relative(root, file) + " -> " + href);
    }
  }

  if (path.relative(root, file).startsWith("wiki" + path.sep)) {
    const wikiLinks = content.matchAll(/\[\[([^\]|#]+)(?:#[^\]|]+)?(?:\|[^\]]+)?\]\]/g);
    for (const match of wikiLinks) {
      const page = match[1].trim();
      const target = path.join(root, "wiki", page + ".md");
      if (!await exists(target)) {
        failures.push(path.relative(root, file) + " -> [[" + page + "]]");
      }
    }
  }
}

if (failures.length) {
  console.error("Broken local documentation links:");
  for (const failure of failures) console.error("  ✗ " + failure);
  process.exitCode = 1;
} else {
  console.log("✓ Local Markdown and Wiki links are valid");
}
