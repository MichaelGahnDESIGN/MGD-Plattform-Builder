import fs from "node:fs/promises";
import path from "node:path";
import Ajv2020 from "ajv/dist/2020.js";\nimport YAML from "yaml";

const root = process.cwd();
const ajv = new Ajv2020({ allErrors: true, strict: false });

const schemaFiles = [
  "schema/mgd-platform.schema.json",
  "schema/module-manifest.schema.json",
  "schema/evidence.schema.json"
];

const compiled = new Map();

for (const relative of schemaFiles) {
  const schema = JSON.parse(await fs.readFile(path.join(root, relative), "utf8"));
  const validate = ajv.compile(schema);
  compiled.set(relative, validate);
  console.log("✓ Schema compiles: " + relative);
}

const moduleExample = JSON.parse(await fs.readFile(path.join(root, "templates/MODULE.example.json"), "utf8"));
const moduleValid = compiled.get("schema/module-manifest.schema.json");
if (!moduleValid(moduleExample)) {
  console.error("✗ MODULE.example.json is invalid");
  console.error(moduleValid.errors);
  process.exitCode = 1;
} else {
  console.log("✓ Module example matches schema");
}

const evidenceExample = JSON.parse(await fs.readFile(path.join(root, "templates/evidence.example.json"), "utf8"));
const evidenceValid = compiled.get("schema/evidence.schema.json");
if (!evidenceValid(evidenceExample)) {
  console.error("✗ evidence.example.json is invalid");
  console.error(evidenceValid.errors);
  process.exitCode = 1;
} else {
  console.log("✓ Evidence example matches schema");
}


const capabilityRegistry = YAML.parse(await fs.readFile(path.join(root, "registry/capabilities.yml"), "utf8"));
const capabilityValid = compiled.get("schema/capability-registry.schema.json");
if (!capabilityValid(capabilityRegistry)) {
  console.error("✗ registry/capabilities.yml is invalid");
  console.error(capabilityValid.errors);
  process.exitCode = 1;
} else {
  console.log("✓ Capability registry matches schema");
}
