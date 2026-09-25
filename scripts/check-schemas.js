import fs from "node:fs/promises";
import path from "node:path";
import Ajv2020 from "ajv/dist/2020.js";
import YAML from "yaml";

const root = process.cwd();
const ajv = new Ajv2020({ allErrors: true, strict: false });

const schemaFiles = [
  "schema/mgd-platform.schema.json",
  "schema/module-manifest.schema.json",
  "schema/evidence.schema.json",
  "schema/capability-registry.schema.json",
  "schema/version.schema.json",
  "schema/release-notes.schema.json",
  "schema/template-manifest.schema.json",
  "schema/module-package.schema.json"
];

const compiled = new Map();

for (const relative of schemaFiles) {
  const schema = JSON.parse(await fs.readFile(path.join(root, relative), "utf8"));
  const validate = ajv.compile(schema);
  compiled.set(relative, validate);
  console.log("✓ Schema compiles: " + relative);
}

const moduleExample = JSON.parse(
  await fs.readFile(path.join(root, "templates/MODULE.example.json"), "utf8")
);
const moduleValid = compiled.get("schema/module-manifest.schema.json");

if (!moduleValid(moduleExample)) {
  console.error("✗ MODULE.example.json is invalid");
  console.error(moduleValid.errors);
  process.exitCode = 1;
} else {
  console.log("✓ Module example matches schema");
}

const evidenceExample = JSON.parse(
  await fs.readFile(path.join(root, "templates/evidence.example.json"), "utf8")
);
const evidenceValid = compiled.get("schema/evidence.schema.json");

if (!evidenceValid(evidenceExample)) {
  console.error("✗ evidence.example.json is invalid");
  console.error(evidenceValid.errors);
  process.exitCode = 1;
} else {
  console.log("✓ Evidence example matches schema");
}

const capabilityRegistry = YAML.parse(
  await fs.readFile(path.join(root, "registry/capabilities.yml"), "utf8")
);
const capabilityValid = compiled.get("schema/capability-registry.schema.json");

if (!capabilityValid(capabilityRegistry)) {
  console.error("✗ registry/capabilities.yml is invalid");
  console.error(capabilityValid.errors);
  process.exitCode = 1;
} else {
  console.log("✓ Capability registry matches schema");
}

async function checkJson(schemaFile, relative) {
  const validate = compiled.get(schemaFile);
  const data = JSON.parse(await fs.readFile(path.join(root, relative), "utf8"));
  if (!validate(data)) {
    console.error("✗ " + relative + " does not match " + schemaFile);
    console.error(validate.errors);
    process.exitCode = 1;
    return;
  }
  console.log("✓ " + relative + " matches " + path.basename(schemaFile));
}

await checkJson("schema/version.schema.json", "version.json");
await checkJson("schema/version.schema.json", "templates/version.example.json");
await checkJson("schema/release-notes.schema.json", "release-notes.json");
await checkJson("schema/release-notes.schema.json", "templates/release-notes.example.json");

for (const entry of await fs.readdir(path.join(root, "templates"), { withFileTypes: true })) {
  if (!entry.isDirectory()) continue;
  const base = path.join("templates", entry.name);
  const hasManifest = await fs.access(path.join(root, base, "template.json")).then(() => true).catch(() => false);
  if (!hasManifest) continue;
  await checkJson("schema/template-manifest.schema.json", path.join(base, "template.json"));
  await checkJson("schema/version.schema.json", path.join(base, "version.json"));
  await checkJson("schema/release-notes.schema.json", path.join(base, "release-notes.json"));
}

const recommendations = YAML.parse(await fs.readFile(path.join(root, "registry/recommendations.yml"), "utf8"));
const recommendationIds = new Set();
for (const entry of recommendations.recommendations || []) {
  if (!entry.id || !entry.name || !entry.url || !entry.when || recommendationIds.has(entry.id)) {
    console.error("✗ registry/recommendations.yml has an invalid or duplicate entry: " + (entry.id || "<no id>"));
    process.exitCode = 1;
  }
  recommendationIds.add(entry.id);
}
console.log("✓ Recommendation registry has " + recommendationIds.size + " entries");

const briefing = YAML.parse(await fs.readFile(path.join(root, "registry/briefing.yml"), "utf8"));
const questionIds = (briefing.sections || []).flatMap((section) => section.questions.map((question) => question.id));
if (new Set(questionIds).size !== questionIds.length) {
  console.error("✗ registry/briefing.yml contains duplicate question ids");
  process.exitCode = 1;
} else {
  console.log("✓ Briefing registry has " + questionIds.length + " questions");
}
