import fs from "node:fs/promises";
import path from "node:path";
import { loadProjectProfile } from "../lib/profile.js";
import { validateProfile } from "../lib/validation.js";

function isExpired(record) {
  if (!record.expires_at) return false;
  const time = Date.parse(record.expires_at);
  return Number.isFinite(time) && time < Date.now();
}

export async function releaseCheckCommand(target = ".") {
  const root = path.resolve(target);
  let loaded;

  try {
    loaded = await loadProjectProfile(root);
  } catch (error) {
    console.error("✗ Release check requires MGD_PLATFORM.yml: " + error.message);
    return 1;
  }

  const validation = await validateProfile(loaded.profile);
  const gates = loaded.profile.release_gates || [];
  let blocked = !validation.valid;

  console.log("\nMGD PLATFORM RELEASE CHECK");
  console.log("==========================");
  console.log((validation.valid ? "✓" : "✗") + " Project profile valid");

  for (const gate of gates) {
    const evidencePath = path.join(root, ".mgd", "evidence", gate + ".json");
    let record = null;

    try {
      record = JSON.parse(await fs.readFile(evidencePath, "utf8"));
    } catch {
      console.log("✗ " + gate + " — evidence missing");
      blocked = true;
      continue;
    }

    const passed = record.status === "pass" && !isExpired(record);
    console.log((passed ? "✓" : "✗") + " " + gate + (isExpired(record) ? " — evidence expired" : record.status !== "pass" ? " — status is " + String(record.status) : ""));
    if (!passed) blocked = true;
  }

  if (gates.length === 0) {
    console.log("! No release gates declared");
  }

  console.log("\nRelease readiness: " + (blocked ? "BLOCKED" : "READY"));
  return blocked ? 1 : 0;
}
