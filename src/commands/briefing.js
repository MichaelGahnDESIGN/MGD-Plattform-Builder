import fs from "node:fs/promises";
import path from "node:path";
import { loadProjectProfile } from "../lib/profile.js";
import { evaluateBriefing, loadBriefing, renderBriefingMarkdown, summarizeBriefing } from "../lib/briefing.js";

export async function briefingCommand(args = []) {
  const target = args.find((arg) => !arg.startsWith("--")) || ".";
  const write = args.includes("--write");
  const briefing = await loadBriefing();

  let profile = {};
  let root = path.resolve(target);
  try {
    const loaded = await loadProjectProfile(target);
    profile = loaded.profile;
    root = path.dirname(loaded.profilePath);
  } catch {
    console.log("! No MGD_PLATFORM.yml found – showing the full questionnaire.");
  }

  const sections = evaluateBriefing(briefing, profile);
  const summary = summarizeBriefing(sections);

  console.log("\nMGD Briefing\n============");
  for (const section of sections) {
    console.log("\n" + section.title);
    for (const question of section.questions) {
      const mark = question.answered ? "✓" : question.mandatory ? "✗" : "·";
      console.log("  " + mark + " " + question.question);
    }
  }

  console.log("\nBeantwortet: " + summary.answered + "/" + summary.total +
    " · offene Pflichtfragen: " + summary.openMandatory.length);

  if (write) {
    const projectName = (profile.project && profile.project.name) || path.basename(root);
    const outputPath = path.join(root, "BRIEFING.md");
    await fs.writeFile(outputPath, renderBriefingMarkdown(sections, projectName), "utf8");
    console.log("✓ Wrote " + outputPath);
  }

  return summary.openMandatory.length === 0 ? 0 : 1;
}
