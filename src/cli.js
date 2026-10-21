import { initCommand } from "./commands/init.js";
import { validateCommand } from "./commands/validate.js";
import { doctorCommand } from "./commands/doctor.js";
import { auditCommand } from "./commands/audit.js";
import { releaseCheckCommand } from "./commands/release-check.js";
import { moduleCommand } from "./commands/module.js";
import { updateCommand } from "./commands/update.js";
import { versionCommand } from "./commands/version.js";
import { briefingCommand } from "./commands/briefing.js";
import { recommendCommand } from "./commands/recommend.js";
import { templateCommand } from "./commands/template.js";
import { companionsCommand } from "./commands/companions.js";

function help() {
  console.log([
    "",
    "MGD Project Platform CLI",
    "",
    "Usage:",
    "  mgd-platform init [--preset general|game|community|creator|ecommerce] [--target DIR] [--companions]",
    "  mgd-platform validate [PATH]",
    "  mgd-platform doctor [PATH]",
    "  mgd-platform audit [PATH] [--write]",
    "  mgd-platform release-check [PATH]",
    "  mgd-platform module create <name> [--target DIR]",
    "  mgd-platform update [PATH]",
    "  mgd-platform version [PATH] [--bump major|minor|patch] [--status STATUS] [--sync] [--check]",
    "                       [--note TEXT --audience frontend,backoffice --type feature|patch|fix|security]",
    "  mgd-platform briefing [PATH] [--write]",
    "  mgd-platform recommend [PATH]",
    "  mgd-platform template list | check | create <id> [--target DIR]",
    "  mgd-platform companions [status|install] [PATH] [--scope project|user] [--agent claude|codex|both]",
    "                          [--graphify user|project|skip] [--hooks] [--pinned] [--yes]   (living documentation, Graphify, Todo – mandatory)",
    "",
    "Recommended first run:",
    "  mgd-platform template create php-mysql-starter --target ./mein-projekt",
    "  mgd-platform init --preset general --target ./mein-projekt",
    "  mgd-platform companions install ./mein-projekt --scope project --graphify user --yes",
    "  mgd-platform briefing ./mein-projekt --write",
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
  if (command === "version") return versionCommand(rest);
  if (command === "briefing") return briefingCommand(rest);
  if (command === "recommend") return recommendCommand(rest[0] || ".");
  if (command === "template") return templateCommand(rest);
  if (command === "companions") return companionsCommand(rest);

  console.error("Unknown command: " + command);
  help();
  return 1;
}
