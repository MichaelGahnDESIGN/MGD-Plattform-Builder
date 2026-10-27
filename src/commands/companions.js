import os from "node:os";
import path from "node:path";
import { loadProjectProfile } from "../lib/profile.js";
import { AGENTS, SCOPES, findCommand, findPython, inspectCompanions, loadCompanionRegistry, printable, runProcess, withTrustedPath } from "../lib/companions.js";
import { GRAPHIFY_METHODS, actionLabel, buildPlan, describeAction, plannedCommands } from "../lib/companion-plan.js";
import { executePlan, fetchSource } from "../lib/companion-install.js";
import { describeResolved, resolveSource } from "../lib/companion-source.js";

const VALUE_OPTIONS = new Set(["--scope", "--agent", "--graphify", "--home"]);
const DEFAULT_SCOPE = "project";
const DEFAULT_GRAPHIFY = "user";

function option(args, name) {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : undefined;
}

function positionals(args) {
  return args.filter((arg, index) => !arg.startsWith("--") && !VALUE_OPTIONS.has(args[index - 1]));
}

/** A missing profile is fine (the command also bootstraps projects); an unreadable one is an error. */
async function readProfile(root) {
  try {
    return (await loadProjectProfile(root)).profile || {};
  } catch (error) {
    if (error.code === "ENOENT") return {};
    throw new Error("MGD_PLATFORM.yml could not be read: " + error.message);
  }
}

function parseOptions(args) {
  const scope = option(args, "--scope");
  const agent = option(args, "--agent") || "both";
  const graphify = option(args, "--graphify");
  const errors = [];
  if (scope && !SCOPES.includes(scope)) errors.push("--scope must be one of: " + SCOPES.join(", "));
  if (agent !== "both" && !AGENTS.includes(agent)) errors.push("--agent must be one of: " + [...AGENTS, "both"].join(", "));
  if (graphify && !GRAPHIFY_METHODS.includes(graphify)) errors.push("--graphify must be one of: " + GRAPHIFY_METHODS.join(", "));
  return { scope, graphify, agents: agent === "both" ? [...AGENTS] : [agent], errors, yes: args.includes("--yes"), hooks: args.includes("--hooks"), pinned: args.includes("--pinned") };
}

function skillSummary(item) {
  return AGENTS.map((agent) => {
    const where = item.skills.skills
      .filter((skill) => skill.required)
      .map((skill) => skill.locations.filter((location) => location.agent === agent).map((location) => location.scope));
    const everywhere = where.every((scopes) => scopes.length > 0);
    return agent + ": " + (everywhere ? where[0][0] : "missing");
  }).join(" · ");
}

export function printStatus(items, root) {
  console.log("\nMGD Companions (mandatory): " + root + "\n");
  for (const item of items) {
    const detail = item.tool
      ? (item.tool.installed ? "version " + item.tool.version + (item.tool.versionOk ? "" : " (older than " + item.companion.package.min_version + ")") : "not installed")
      : skillSummary(item);
    console.log((item.installed ? "✓" : "✗") + " " + item.companion.name.padEnd(28) + " " + detail);
    console.log((item.project.present ? "✓" : "✗") + "   in project: " + item.project.path + (item.project.present ? "" : " — " + (printable(item.project.error || "missing"))));
  }
}

export function missingCompanions(items) {
  return items.filter((item) => item.companion.mandatory && !item.ok);
}

async function status(root, options) {
  const registry = await loadCompanionRegistry();
  const profile = await readProfile(root);
  const items = await inspectCompanions({ root, profile, registry, home: options.home, run: options.run, env: options.env });
  printStatus(items, root);
  const missing = missingCompanions(items);
  if (missing.length === 0) {
    console.log("\nCompanions: ALL PRESENT (living documentation, Graphify, Todo)");
    return 0;
  }
  console.log("\nCompanions: " + missing.length + " missing — install with:");
  console.log("  mgd-platform companions install " + root + " --scope project --graphify user --yes");
  return 1;
}

/** Read-only: asks the companion repositories which release a real install would use. */
async function printResolvedReleases(plan, registry, options) {
  const tracked = plan.fetches.filter((source) => source.track === "latest-release" && !plan.pinned);
  if (tracked.length === 0) return;
  console.log("\nReleases that would be installed:");
  for (const source of tracked) {
    const name = source.repo.split("/").pop();
    try {
      const resolved = await resolveSource(source, { run: options.run, owner: registry.owner });
      console.log("  " + name + ": " + describeResolved(resolved) + (resolved.ref !== source.ref ? " – newer than the tested " + source.ref : ""));
    } catch (error) {
      console.log("  " + name + ": could not be looked up (" + printable(error.message) + ")");
    }
  }
}

async function install(root, options) {
  const registry = await loadCompanionRegistry();
  const profile = await readProfile(root);
  const home = options.home;
  const before = await inspectCompanions({ root, profile, registry, home, run: options.run, env: options.env });
  const invalid = before.filter((item) => item.project.error);
  if (invalid.length > 0) {
    for (const item of invalid) console.error("✗ " + item.companion.name + ": " + printable(item.project.error) + " (check companions." + item.companion.project.profile_key + ".path in MGD_PLATFORM.yml)");
    return 1;
  }
  const uv = await findCommand("uv", { root, home, env: options.env });
  const pipx = await findCommand("pipx", { root, home, env: options.env });
  const graphifyCompanion = registry.companions.find((entry) => entry.kind === "tool");
  const python = await findPython(graphifyCompanion.package.requires_python, { root, home, env: options.env, run: options.run });

  if (options.yes && (!options.scope || (!before.find((item) => item.companion.kind === "tool").installed && !options.graphify))) {
    console.error("✗ Installing needs explicit choices (ask the user first):");
    console.error("  --scope project|user    where the skills are installed (project = this repo only, user = all projects)");
    console.error("  --graphify user|project|skip    how the Graphify CLI is installed (needed while it is missing)");
    return 1;
  }

  const plan = await buildPlan({
    inspection: before,
    root,
    home,
    scope: options.scope || DEFAULT_SCOPE,
    agents: options.agents,
    graphify: options.graphify || DEFAULT_GRAPHIFY,
    tools: { uv, pipx, python },
    hooks: options.hooks,
    pinned: options.pinned
  });

  console.log("\nMGD Companions install plan: " + root + " (scope " + plan.scope + ", agents " + plan.agents.join("+") + ", " + (plan.pinned ? "pinned releases" : "newest releases") + ")\n");
  for (const action of plan.actions) console.log("  " + describeAction(action));
  const commands = plannedCommands(plan);
  if (commands.length > 0) {
    console.log("\nCommands that will run:");
    for (const command of commands) console.log("  $ " + command);
  }

  if (!options.yes) {
    await printResolvedReleases(plan, registry, options);
    console.log("\nDry run — nothing was changed. Skills come from the newest release of each companion's GitHub repository" + (plan.pinned ? " (--pinned: the tested release from the registry)" : "") + ".");
    console.log("Run again with --scope <project|user> [--graphify <user|project|skip>] [--hooks] [--pinned] --yes to install.");
    return 0;
  }

  const results = await executePlan(plan, { registry, root, home, profile, run: options.run, fetch: options.fetch, env: options.env, pinned: options.pinned });
  console.log("");
  for (const result of results) {
    const mark = result.status === "failed" ? "✗" : result.status === "skipped" ? "!" : "✓";
    console.log(mark + " " + result.status.padEnd(9) + " " + actionLabel(result.action) + (result.detail && result.status !== "kept" ? " — " + result.detail : ""));
  }

  const after = await inspectCompanions({ root, profile, registry, home, run: options.run, env: options.env });
  printStatus(after, root);
  const failed = results.some((result) => result.status === "failed");
  const missing = missingCompanions(after);
  console.log(failed || missing.length ? "\nCompanions: INCOMPLETE — fix the failed steps above and run the command again." : "\nCompanions: ALL PRESENT");
  return failed || missing.length ? 1 : 0;
}

export async function companionsCommand(args = [], overrides = {}) {
  const [subcommand = "status", target = "."] = positionals(args);
  const parsed = parseOptions(args);
  if (parsed.errors.length > 0) {
    for (const message of parsed.errors) console.error("✗ " + message);
    return 1;
  }
  const root = path.resolve(target);
  const options = {
    ...parsed,
    home: path.resolve(option(args, "--home") || os.homedir()),
    run: withTrustedPath(overrides.run || runProcess, root, overrides.env || process.env),
    fetch: overrides.fetch || fetchSource,
    env: overrides.env || process.env
  };

  try {
    if (subcommand === "status") return await status(root, options);
    if (subcommand === "install") return await install(root, options);
  } catch (error) {
    console.error("✗ " + error.message);
    return 1;
  }
  console.error("Unknown companions subcommand: " + subcommand + " (use status or install)");
  return 1;
}
