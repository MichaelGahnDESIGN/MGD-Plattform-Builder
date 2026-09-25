import fs from "node:fs/promises";
import path from "node:path";
import { resolveProjectPath } from "../lib/paths.js";
import { checkTemplate, copyTemplate, listTemplates } from "../lib/templates.js";

async function isEmptyDir(dir) {
  const entries = await fs.readdir(dir).catch(() => []);
  return entries.length === 0;
}

async function listCommand() {
  const templates = await listTemplates();
  console.log("\nAvailable templates\n===================");
  for (const { manifest } of templates) {
    console.log("• " + manifest.id + " – " + manifest.name + " (" + manifest.version + " " + manifest.status + ")");
    console.log("  variants: " + Object.keys(manifest.variants || {}).join(", ") +
      " · deployment: " + (manifest.requirements.deployment || []).join(", "));
  }
  return 0;
}

async function createCommand(id, args) {
  const targetIndex = args.indexOf("--target");
  const target = resolveProjectPath(targetIndex >= 0 ? args[targetIndex + 1] : id);
  const template = (await listTemplates()).find((item) => item.manifest.id === id);

  if (!template) {
    console.error("Unknown template: " + id + " (see mgd-platform template list)");
    return 1;
  }

  const problems = await checkTemplate(template);
  if (problems.length) {
    console.error("✗ Template " + id + " is incomplete: " + problems.join("; "));
    return 1;
  }

  if (!await isEmptyDir(target) && !args.includes("--force")) {
    console.error("✗ Target is not empty: " + target + " (use --force to merge)");
    return 1;
  }

  await copyTemplate(template, target);
  console.log("\n✓ Created " + template.manifest.name + " in " + target);
  console.log("Next: mgd-platform init --target " + path.relative(process.cwd(), target) +
    " && mgd-platform briefing " + path.relative(process.cwd(), target));
  return 0;
}

export async function templateCommand(args = []) {
  const [subcommand, id, ...rest] = args;
  if (subcommand === "list" || !subcommand) return listCommand();
  if (subcommand === "create" && id) return createCommand(id, rest);
  if (subcommand === "check") {
    let failures = 0;
    for (const template of await listTemplates()) {
      const problems = await checkTemplate(template);
      console.log((problems.length ? "✗ " : "✓ ") + template.manifest.id + (problems.length ? ": " + problems.join("; ") : ""));
      failures += problems.length ? 1 : 0;
    }
    return failures === 0 ? 0 : 1;
  }
  console.error("Usage: mgd-platform template list | check | create <id> [--target DIR] [--force]");
  return 1;
}
