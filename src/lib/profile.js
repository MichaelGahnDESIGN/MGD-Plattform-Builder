import fs from "node:fs/promises";
import path from "node:path";
import YAML from "yaml";

export async function loadYamlFile(filePath) {
  const raw = await fs.readFile(filePath, "utf8");
  return YAML.parse(raw);
}

export async function loadProjectProfile(inputPath = ".") {
  const resolved = path.resolve(inputPath);
  const stat = await fs.stat(resolved).catch(() => null);
  const profilePath = stat && stat.isDirectory()
    ? path.join(resolved, "MGD_PLATFORM.yml")
    : resolved;

  const profile = await loadYamlFile(profilePath);
  return { profile, profilePath };
}

export async function writeYamlFile(filePath, value) {
  const yaml = YAML.stringify(value, { lineWidth: 0 });
  await fs.writeFile(filePath, yaml, "utf8");
}
