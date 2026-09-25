// Maintainer tool: after changing the license text or the "powered by" label files,
// copy MGD-Lizenz.md into every MGD-licensed template and refresh LicenseIntegrity::FILES.
import crypto from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { listTemplates, parseIntegrityHashes } from "../src/lib/templates.js";
import { foundationRoot } from "../src/lib/paths.js";

const sha256 = async (file) => crypto.createHash("sha256").update(await fs.readFile(file)).digest("hex");

await fs.copyFile(path.join(foundationRoot, "MGD-Lizenz.md"), path.join(foundationRoot, "reference", "php-mariadb", "MGD-Lizenz.md"));

for (const template of await listTemplates()) {
  if (template.manifest.license !== "LicenseRef-MGD") continue;

  await fs.copyFile(path.join(foundationRoot, "MGD-Lizenz.md"), path.join(template.dir, "MGD-Lizenz.md"));
  const integrityFile = path.join(template.dir, "src", "Core", "License", "LicenseIntegrity.php");
  let source = await fs.readFile(integrityFile, "utf8");

  for (const relative of Object.keys(parseIntegrityHashes(source))) {
    const hash = await sha256(path.join(template.dir, relative));
    const pattern = new RegExp("('" + relative.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "'\\s*=>\\s*')[0-9a-f]{64}(')");
    source = source.replace(pattern, "$1" + hash + "$2");
  }

  await fs.writeFile(integrityFile, source, "utf8");
  console.log("✓ Updated license hashes for " + template.manifest.id);
}
