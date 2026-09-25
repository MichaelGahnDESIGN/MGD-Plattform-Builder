import { loadProjectProfile } from "../lib/profile.js";
import { loadRecommendationRegistry, recommend } from "../lib/recommendations.js";

export async function recommendCommand(target = ".") {
  let loaded;
  try {
    loaded = await loadProjectProfile(target);
  } catch (error) {
    console.error("✗ recommend requires MGD_PLATFORM.yml: " + error.message);
    return 1;
  }

  const results = recommend(await loadRecommendationRegistry(), loaded.profile);
  console.log("\nEmpfohlene MGD Skills, Tools und Plugins\n========================================");

  if (results.length === 0) {
    console.log("Keine passenden Empfehlungen für dieses Projektprofil.");
    return 0;
  }

  for (const entry of results) {
    const mark = entry.accepted ? "✓" : "•";
    console.log("\n" + mark + " " + entry.name + " (" + entry.kind + ")");
    console.log("  " + entry.summary);
    console.log("  Warum: " + entry.reason);
    console.log("  Treffer: " + entry.reasons.join(", "));
    console.log("  " + entry.url);
  }

  console.log("\nAngenommene Empfehlungen in MGD_PLATFORM.yml unter briefing.accepted_recommendations eintragen.");
  return 0;
}
