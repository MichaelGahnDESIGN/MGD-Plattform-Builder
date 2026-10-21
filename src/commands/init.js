import fs from "node:fs/promises";
import path from "node:path";
import { foundationRoot, resolveProjectPath } from "../lib/paths.js";
import { assertNoSymlink } from "../lib/companions.js";
import { companionsCommand } from "./companions.js";

const allowedPresets = new Set(["general", "game", "community", "creator", "ecommerce"]);

async function copyUnlessExists(source, destination, force, root) {
  // lstat, not access: a dangling symbolic link must count as "there", and no copy may follow a link out of the project.
  await assertNoSymlink(root, destination);
  const exists = await fs.lstat(destination).then(() => true).catch(() => false);
  if (exists && !force) {
    return { path: destination, status: "kept" };
  }
  await fs.copyFile(source, destination);
  return { path: destination, status: exists ? "replaced" : "created" };
}

function optionValue(args, name) {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : undefined;
}

export async function initCommand(args = []) {
  const presetIndex = args.indexOf("--preset");
  const targetIndex = args.indexOf("--target");
  const force = args.includes("--force");
  const preset = presetIndex >= 0 ? args[presetIndex + 1] : "general";
  const target = resolveProjectPath(targetIndex >= 0 ? args[targetIndex + 1] : ".");

  if (args.includes("--companions") && !optionValue(args, "--scope")) {
    console.error("✗ --companions needs an explicit --scope project|user (ask the user where the skills should live).");
    console.error("  Example: mgd-platform init --target " + (targetIndex >= 0 ? args[targetIndex + 1] : ".") + " --companions --scope project --graphify user");
    return 1;
  }

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
    await copyUnlessExists(sourceProfile, path.join(target, "MGD_PLATFORM.yml"), force, target),
    await copyUnlessExists(path.join(foundationRoot, "templates", "AGENTS.md"), path.join(target, "AGENTS.md"), force, target),
    await copyUnlessExists(path.join(foundationRoot, "templates", "CLAUDE.md"), path.join(target, "CLAUDE.md"), force, target),
    await copyUnlessExists(path.join(foundationRoot, "templates", "FEATURE-GOVERNANCE.md"), path.join(target, "FEATURE-GOVERNANCE.md"), force, target),
    await copyUnlessExists(path.join(foundationRoot, "templates", "version.example.json"), path.join(target, "version.json"), false, target),
    await copyUnlessExists(path.join(foundationRoot, "templates", "release-notes.example.json"), path.join(target, "release-notes.json"), false, target)
  ];

  await assertNoSymlink(target, path.join(target, ".mgd", "evidence"));
  await fs.mkdir(path.join(target, ".mgd", "evidence"), { recursive: true });

  console.log("\nMGD Platform initialized with preset: " + preset);
  for (const operation of operations) {
    console.log("  " + operation.status.padEnd(8) + " " + path.relative(target, operation.path));
  }
  console.log("  created  .mgd/evidence/");
  console.log("\nLiving documentation, Graphify and Todo are mandatory companions of every project.");
  if (args.includes("--companions")) {
    const forwarded = ["--scope", "--graphify", "--agent"].flatMap((name) => (optionValue(args, name) ? [name, optionValue(args, name)] : []));
    return companionsCommand(["install", target, ...forwarded, ...["--hooks", "--pinned"].filter((flag) => args.includes(flag)), "--yes"]);
  }
  console.log("Install them next (ask the user for the scope first):");
  console.log("  mgd-platform companions install " + target + " --scope project --graphify user --yes");
  console.log("\nThen run mgd-platform briefing --write, answer the questions in MGD_PLATFORM.yml,");
  console.log("then run mgd-platform recommend and mgd-platform doctor.");
  return 0;
}
