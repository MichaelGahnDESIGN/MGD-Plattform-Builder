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
  // On Windows, Node looks for programs in the working directory first unless this is set in our own environment.
  if (process.platform === "win32") process.env.NoDefaultCurrentDirectoryInExePath = "1";

  const command = args[0];
  const rest = args.slice(1);

  if (!command || command === "help" || command === "--help" || command === "-h") {
    help();
    return 0;
  }

  // Befehle werden erst bei Bedarf geladen. Ein optionaler Bereich (z. B. YAML für
  // Begleiter) darf damit nicht die reine Versions- oder Template-Prüfung blockieren.
  if (command === "init") return (await import("./commands/init.js")).initCommand(rest);
  if (command === "validate") return (await import("./commands/validate.js")).validateCommand(rest[0] || ".");
  if (command === "doctor") return (await import("./commands/doctor.js")).doctorCommand(rest[0] || ".");
  if (command === "audit") return (await import("./commands/audit.js")).auditCommand(rest);
  if (command === "release-check") return (await import("./commands/release-check.js")).releaseCheckCommand(rest[0] || ".");
  if (command === "module") return (await import("./commands/module.js")).moduleCommand(rest);
  if (command === "update") return (await import("./commands/update.js")).updateCommand(rest[0] || ".");
  if (command === "version") return (await import("./commands/version.js")).versionCommand(rest);
  if (command === "briefing") return (await import("./commands/briefing.js")).briefingCommand(rest);
  if (command === "recommend") return (await import("./commands/recommend.js")).recommendCommand(rest[0] || ".");
  if (command === "template") return (await import("./commands/template.js")).templateCommand(rest);
  if (command === "companions") return (await import("./commands/companions.js")).companionsCommand(rest);

  console.error("Unknown command: " + command);
  help();
  return 1;
}
