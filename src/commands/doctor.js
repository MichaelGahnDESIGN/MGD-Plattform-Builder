import fs from "node:fs/promises";
import path from "node:path";
import { loadProjectProfile } from "../lib/profile.js";
import { validateProfile } from "../lib/validation.js";

const checks = [
  ["MGD_PLATFORM.yml", true, "project profile"],
  ["AGENTS.md", true, "agent rules"],
  ["CLAUDE.md", false, "Claude-specific rules"],
  ["SECURITY.md", false, "security policy"],
  ["FEATURE-GOVERNANCE.md", false, "feature governance"]
];

export async function doctorCommand(target = ".") {
  const root = path.resolve(target);
  let failures = 0;

  console.log("\nMGD Platform Doctor: " + root + "\n");

  for (const check of checks) {
    const file = check[0];
    const required = check[1];
    const label = check[2];
    const exists = await fs.access(path.join(root, file)).then(() => true).catch(() => false);
    const symbol = exists ? "✓" : required ? "✗" : "!";
    const state = exists ? label : required ? "required file missing" : "recommended file missing";
    console.log(symbol + " " + file.padEnd(24) + " " + state);
    if (!exists && required) failures += 1;
  }

  try {
    const loaded = await loadProjectProfile(root);
    const validation = await validateProfile(loaded.profile);
    console.log((validation.valid ? "✓" : "✗") + " Project profile validation");
    if (!validation.valid) failures += 1;
    for (const warning of validation.warnings) console.log("  ! " + warning);

    const evidenceDir = path.join(root, ".mgd", "evidence");
    const evidenceExists = await fs.access(evidenceDir).then(() => true).catch(() => false);
    console.log((evidenceExists ? "✓" : "!") + " .mgd/evidence            " + (evidenceExists ? "release evidence directory" : "create this for release evidence"));
  } catch (error) {
    console.log("✗ Profile could not be read: " + error.message);
    failures += 1;
  }

  console.log(failures === 0 ? "\nDoctor result: READY FOR FURTHER AUDIT" : "\nDoctor result: " + failures + " blocking issue(s)");
  return failures === 0 ? 0 : 1;
}
