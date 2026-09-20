import { loadProjectProfile } from "../lib/profile.js";
import { validateProfile } from "../lib/validation.js";
import { printValidation } from "../lib/report.js";

export async function validateCommand(target = ".") {
  try {
    const loaded = await loadProjectProfile(target);
    const result = await validateProfile(loaded.profile);
    printValidation(result, loaded.profilePath);
    return result.valid ? 0 : 1;
  } catch (error) {
    console.error("✗ Could not validate profile: " + error.message);
    return 1;
  }
}
