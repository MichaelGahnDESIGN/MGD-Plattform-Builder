import fs from "node:fs/promises";
import path from "node:path";
import { foundationRoot, resolveProjectPath } from "../lib/paths.js";

const allowedPresets = new Set(["general", "game", "community", "creator", "ecommerce"]);

async function copyUnlessExists(source, destination, force) {
  const exists = await fs.access(destination).then(() => true).catch(() => false);
  if (exists && !force) {
    return { path: destination, status: "kept" };
  }
  await fs.copyFile(source, destination);
  return { path: destination, status: exists ? "replaced" : "created" };
}

export async function initCommand(args = []) {
  const presetIndex = args.indexOf("--preset");
  const targetIndex = args.indexOf("--target");
  const force = args.includes("--force");
  const preset = presetIndex >= 0 ? args[presetIndex + 1] : "general";
  const target = resolveProjectPath(targetIndex >= 0 ? args[targetIndex + 1] : ".");

  if (!allowedPresets.has(preset)) {
    console.error("Unknown preset: " + preset);
    console.error("Use one of: " + Array.from(allowedPresets).join(", "));
    return 1;
  }

  await fs.mkdir(target, { recursive: true });

  const sourceProfile = path.join(
    foundationRoot,
    preset === "general" ? "examples/general-platform.yml" : "examples/" + preset + ".yml"
  );

  const operations = [
    await copyUnlessExists(sourceProfile, path.join(target, "MGD_PLATFORM.yml"), force),
    await copyUnlessExists(path.join(foundationRoot, "templates", "AGENTS.md"), path.join(target, "AGENTS.md"), force),
    await copyUnlessExists(path.join(foundationRoot, "templates", "CLAUDE.md"), path.join(target, "CLAUDE.md"), force),
    await copyUnlessExists(path.join(foundationRoot, "templates", "FEATURE-GOVERNANCE.md"), path.join(target, "FEATURE-GOVERNANCE.md"), force)
  ];

  await fs.mkdir(path.join(target, ".mgd", "evidence"), { recursive: true });

  console.log("\nMGD Platform initialized with preset: " + preset);
  for (const operation of operations) {
    console.log("  " + operation.status.padEnd(8) + " " + path.relative(target, operation.path));
  }
  console.log("  created  .mgd/evidence/");
  console.log("\nNext: edit MGD_PLATFORM.yml, then run mgd-platform doctor.");
  return 0;
}
