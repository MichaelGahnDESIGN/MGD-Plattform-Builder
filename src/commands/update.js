import fs from "node:fs/promises";
import path from "node:path";
import { foundationRoot } from "../lib/paths.js";
import { loadProjectProfile } from "../lib/profile.js";

export async function updateCommand(target = ".") {
  const root = path.resolve(target);
  const loaded = await loadProjectProfile(root);
  const current = loaded.profile.project && loaded.profile.project.foundation_version
    ? loaded.profile.project.foundation_version
    : "unknown";
  const available = (await fs.readFile(path.join(foundationRoot, "VERSION"), "utf8")).trim();

  console.log("\nMGD Platform Foundation Update");
  console.log("==============================");
  console.log("Project version:    " + current);
  console.log("Available locally:  " + available);

  if (current === available) {
    console.log("\n✓ Project already declares the current foundation version.");
    return 0;
  }

  console.log("\n! Foundation versions differ.");
  console.log("Run an audit before changing the declared version:");
  console.log("  mgd-platform audit --write");
  console.log("\nDo not update the version field until relevant changes and release gates have been reviewed.");
  return 1;
}
