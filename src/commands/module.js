import fs from "node:fs/promises";
import path from "node:path";

function slugify(value) {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

export async function moduleCommand(args = []) {
  if (args[0] !== "create" || !args[1]) {
    console.error("Usage: mgd-platform module create <name> [--target <dir>]");
    return 1;
  }

  const name = args[1];
  const slug = slugify(name);
  const targetIndex = args.indexOf("--target");
  const root = path.resolve(targetIndex >= 0 ? args[targetIndex + 1] : ".");
  const moduleDir = path.join(root, "modules", slug);
  const manifestPath = path.join(moduleDir, "module.json");

  const exists = await fs.access(manifestPath).then(() => true).catch(() => false);
  if (exists) {
    console.error("✗ Module already exists: " + manifestPath);
    return 1;
  }

  await fs.mkdir(moduleDir, { recursive: true });

  const manifest = {
    id: slug,
    name,
    version: "0.1.0",
    type: "feature",
    description: "",
    requires: [],
    permissions: [],
    events_subscribed: [],
    events_emitted: [],
    translations: [],
    backoffice: false,
    entitlement: null,
    data_classification: ["internal"]
  };

  await fs.writeFile(manifestPath, JSON.stringify(manifest, null, 2) + "\n", "utf8");

  const readme = [
    "# " + name,
    "",
    "## Purpose",
    "",
    "Describe the responsibility of this module.",
    "",
    "## Data ownership",
    "",
    "Document which entities and files this module owns.",
    "",
    "## Permissions",
    "",
    "Document capabilities and policies.",
    "",
    "## Events",
    "",
    "Document emitted and subscribed domain events.",
    "",
    "## Privacy and retention",
    "",
    "Document personal data, classification and deletion rules.",
    "",
    "## Security",
    "",
    "Document abuse cases and security invariants.",
    "",
    "## Tests and release gates",
    "",
    "Document required validation before release.",
    ""
  ].join("\n");

  await fs.writeFile(path.join(moduleDir, "README.md"), readme, "utf8");
  console.log("✓ Created module: " + slug);
  console.log("  " + manifestPath);
  return 0;
}
