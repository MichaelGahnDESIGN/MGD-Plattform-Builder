import fs from "node:fs/promises";
import path from "node:path";
import YAML from "yaml";
import { foundationRoot } from "./paths.js";

export async function loadRecommendationRegistry() {
  const file = path.join(foundationRoot, "registry", "recommendations.yml");
  return YAML.parse(await fs.readFile(file, "utf8"));
}

function projectText(profile) {
  const project = profile.project || {};
  const stack = Object.values(profile.stack || {}).join(" ");
  return [project.name, project.description, stack].filter(Boolean).join(" ").toLowerCase();
}

function enabledKeys(section) {
  return Object.entries(section || {}).filter(([, value]) => value === true).map(([key]) => key);
}

/** Returns the reasons why an entry matches the profile; an empty list means no match. */
export function matchReasons(entry, profile) {
  const when = entry.when || {};
  const reasons = [];
  const text = projectText(profile);
  const features = enabledKeys(profile.features);
  const experience = enabledKeys(profile.experience);
  const infrastructure = profile.infrastructure || {};

  if (when.always) reasons.push("Basisempfehlung");
  if ((when.project_types || []).includes(profile.project && profile.project.type)) reasons.push("Projekttyp");
  for (const feature of when.features || []) if (features.includes(feature)) reasons.push("Feature " + feature);
  for (const topic of when.experience || []) if (experience.includes(topic)) reasons.push("Briefing " + topic);
  for (const keyword of when.keywords || []) if (text.includes(keyword.toLowerCase())) reasons.push("Stichwort \"" + keyword + "\"");
  if ((when.staging || []).includes(infrastructure.staging)) reasons.push("Staging " + infrastructure.staging);
  if (when.docker === true && infrastructure.docker === true) reasons.push("Docker");

  return reasons;
}

export function recommend(registry, profile) {
  const accepted = new Set((profile.briefing && profile.briefing.accepted_recommendations) || []);
  return (registry.recommendations || [])
    .map((entry) => ({ ...entry, reasons: matchReasons(entry, profile), accepted: accepted.has(entry.id) }))
    .filter((entry) => entry.reasons.length > 0)
    .sort((a, b) => (b.reasons.length - a.reasons.length) || a.name.localeCompare(b.name));
}
