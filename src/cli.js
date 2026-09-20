import { initCommand } from "./commands/init.js";
import { validateCommand } from "./commands/validate.js";
import { doctorCommand } from "./commands/doctor.js";
import { auditCommand } from "./commands/audit.js";
import { releaseCheckCommand } from "./commands/release-check.js";
import { moduleCommand } from "./commands/module.js";
import { updateCommand } from "./commands/update.js";

function help() {
  console.log([
    "",
    "MGD Project Platform CLI",
    "",
    "Usage:",
    "  mgd-platform init [--preset general|game|community|creator|ecommerce] [--target DIR]",
    "  mgd-platform validate [PATH]",
    "  mgd-platform doctor [PATH]",
    "  mgd-platform audit [PATH] [--write]",
    "  mgd-platform release-check [PATH]",
    "  mgd-platform module create <name> [--target DIR]",
    "  mgd-platform update [PATH]",
    "",
    "Recommended first run:",
    "  mgd-platform init --preset general",
    "  mgd-platform doctor",
    "  mgd-platform audit --write",
    ""
  ].join("\n"));
}

export async function runCli(args) {
  const command = args[0];
  const rest = args.slice(1);

  if (!command || command === "help" || command === "--help" || command === "-h") {
    help();
    return 0;
  }

  if (command === "init") return initCommand(rest);
  if (command === "validate") return validateCommand(rest[0] || ".");
  if (command === "doctor") return doctorCommand(rest[0] || ".");
  if (command === "audit") return auditCommand(rest);
  if (command === "release-check") return releaseCheckCommand(rest[0] || ".");
  if (command === "module") return moduleCommand(rest);
  if (command === "update") return updateCommand(rest[0] || ".");

  console.error("Unknown command: " + command);
  help();
  return 1;
}
