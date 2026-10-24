import fs from "node:fs/promises";
import path from "node:path";
import { INSTALL_DIR, PROJECT_VENV, resolveInside } from "./companions.js";

/**
 * Builds the install plan for the mandatory companions. Pure data: nothing is written here, so
 * `mgd-platform companions install` without `--yes` can show exactly what would happen.
 */

export const GRAPHIFY_METHODS = ["user", "project", "skip"];
const HOOK_MARKER = "graphify-hook-start";

/** Installed agent skills and the TODO table are tooling, not project knowledge: keep them out of the graph. */
const GRAPHIFY_IGNORE_BASE = [".claude/", ".agents/", ".codex/", ".mgd/graphify-venv/", "graphify-out/"];
/**
 * The extraction cache is reproducible; graph.json and GRAPH_REPORT.md are versioned with the project.
 * The two helper files hold absolute local paths (user and folder names) and must not be committed.
 * `graphify update` can also keep a dated backup of the previous graph (graphify-out/YYYY-MM-DD/): local noise, not project knowledge.
 */
export const GRAPHIFY_GIT_IGNORE = [
  "graphify-out/cache/",
  "graphify-out/.graphify_python",
  "graphify-out/.graphify_root",
  "graphify-out/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]/",
  ".mgd/graphify-venv/"
];

async function exists(target) {
  return fs.access(target).then(() => true).catch(() => false);
}

export function venvExecutable(root, command) {
  return venvBin(root, command);
}

export function projectVenvDir(root) {
  return path.join(root, PROJECT_VENV);
}

/** Newest release by default (at or above the tested floor); the exact tested version with --pinned. */
export function graphifySpec(companion, pinned = false) {
  const { name, min_version: floor, install_version: tested } = companion.package;
  return pinned ? name + "==" + tested : name + ">=" + floor;
}

function venvBin(root, command) {
  return path.join(projectVenvDir(root), process.platform === "win32" ? "Scripts" : "bin", command);
}

/** The shell-free command lines that install (or upgrade) the Graphify CLI for the chosen method. */
export function graphifyInstallCommands({ method, companion, root, uv, pipx, hasUv, hasPipx, python, pinned = false }) {
  const spec = graphifySpec(companion, pinned);
  if (method === "project") {
    if (!python) return [];
    // -I: ignore PYTHON* variables and the current directory, so nothing in the project can shadow the standard library.
    return [
      [python.path, ["-I", "-m", "venv", projectVenvDir(root)]],
      [venvBin(root, "python"), ["-I", "-m", "pip", "install", "--upgrade", spec]]
    ];
  }
  // The absolute path that was found (uv/pipx may live in ~/.local/bin, which is not necessarily on PATH).
  const uvCommand = uv || (hasUv ? "uv" : null);
  const pipxCommand = pipx || (hasPipx ? "pipx" : null);
  if (method === "user" && uvCommand) return [[uvCommand, ["tool", "install", "--upgrade", spec]]];
  if (method === "user" && pipxCommand) return [[pipxCommand, ["install", "--force", spec]]];
  return [];
}

/** Ignore entries for `.graphifyignore`; the Todo file's folder follows the profile. */
export function graphifyIgnoreEntries(todoPath) {
  const folder = path.posix.dirname(todoPath);
  return [...GRAPHIFY_IGNORE_BASE, folder === "." ? todoPath : folder + "/"];
}

function skillActions(item, { agents, base, scope }) {
  const actions = [];
  for (const agent of agents) {
    for (const skill of item.skills.skills) {
      const present = skill.locations.filter((location) => location.agent === agent);
      const dest = path.join(base, INSTALL_DIR[agent], "skills", skill.name);
      actions.push({
        type: "skill",
        companionId: item.companion.id,
        skill: skill.name,
        agent,
        scope,
        dest,
        required: skill.required,
        state: present.length > 0 ? "keep" : "install",
        detail: present.length > 0 ? "already installed (" + present[0].scope + "), release not checked – remove the folder to reinstall" : null
      });
    }
  }
  return actions;
}

function scaffoldActions(items, root) {
  const actions = [];
  for (const { companion, project } of items) {
    if (companion.project.scaffold) {
      actions.push({
        type: "scaffold-docs",
        companionId: companion.id,
        template: companion.project.scaffold,
        dest: path.join(resolveInside(root, project.path), "README.md"),
        state: project.present ? "keep" : "install"
      });
    }
    if (companion.project.scaffold_from_source) {
      actions.push({
        type: "scaffold-todo",
        companionId: companion.id,
        source: companion.project.scaffold_from_source,
        dest: resolveInside(root, project.path),
        state: project.present ? "keep" : "install"
      });
    }
  }
  return actions;
}

async function hookInstalled(root) {
  const hook = await fs.readFile(path.join(root, ".git", "hooks", "post-commit"), "utf8").catch(() => "");
  return hook.includes(HOOK_MARKER);
}

async function graphifyActions({ item, todoPath, root, method, tools, hooks, pinned }) {
  // --pinned means "exactly the tested version": a different installed version is replaced, not kept.
  const present = item.installed && !(pinned && item.tool.version !== item.companion.package.install_version);
  const actions = [{
    type: "graphify-cli",
    method: present ? "keep" : method,
    state: present ? "keep" : method === "skip" ? "skipped" : "install",
    commands: present ? [] : graphifyInstallCommands({ method, companion: item.companion, root, pinned, ...tools }),
    detail: present ? "version " + item.tool.version + (item.tool.projectVenv ? " (project environment, not executed by this CLI)" : "") : method === "skip" ? "skipped by --graphify skip" : item.installed ? "installed " + item.tool.version + ", --pinned wants " + item.companion.package.install_version : null
  }];

  actions.push({ type: "graphify-config", state: "run", ignore: graphifyIgnoreEntries(todoPath) });
  actions.push({ type: "graphify-refresh", state: "run", command: item.companion.project.refresh });

  if (await exists(path.join(root, ".git"))) {
    actions.push({
      type: "graphify-hook",
      state: !hooks ? "skipped" : await hookInstalled(root) ? "keep" : "install",
      detail: hooks ? null : "opt-in: pass --hooks (the hooks run Python in the repository root after commits and checkouts)",
      command: item.companion.project.hook
    });
  }
  return actions;
}

export async function buildPlan({ inspection, root, scope, home, agents, graphify, tools = {}, hooks = false, pinned = false }) {
  const base = scope === "user" ? home : root;
  const skillItems = inspection.filter((item) => item.companion.kind === "skill");
  const graphifyItem = inspection.find((item) => item.companion.kind === "tool");
  const todoItem = inspection.find((item) => item.companion.project.scaffold_from_source);

  const actions = [
    ...skillItems.flatMap((item) => skillActions(item, { agents, base, scope })),
    ...scaffoldActions(inspection, root)
  ];
  if (graphifyItem) {
    const todoPath = todoItem ? todoItem.settings.path : "TODO/TODO.html";
    actions.push(...await graphifyActions({ item: graphifyItem, todoPath, root, method: graphify, tools, hooks, pinned }));
  }

  const needsSource = new Set(actions.filter((a) => a.state === "install" && ["skill", "scaffold-todo"].includes(a.type)).map((a) => a.companionId));
  const fetches = skillItems.filter((item) => needsSource.has(item.companion.id)).map((item) => item.companion.source);
  return { scope, agents, graphify, root, actions, fetches, pinned };
}

const LABELS = {
  skill: (action) => "skill " + action.skill + " → " + action.agent + " (" + action.scope + ")",
  "scaffold-docs": () => "documentation skeleton",
  "scaffold-todo": () => "TODO.html from the Todo template",
  "graphify-cli": (action) => "Graphify CLI" + (action.method && action.method !== "keep" ? " (" + action.method + ")" : ""),
  "graphify-config": () => ".graphifyignore and .gitignore entries (keep agent skills and the cache out of the graph)",
  "graphify-refresh": (action) => "build/refresh the project graph (" + action.command + ")",
  "graphify-hook": () => "git post-commit and post-checkout hooks plus a merge driver for graph.json"
};

export function actionLabel(action) {
  return LABELS[action.type](action);
}

export function describeAction(action) {
  const label = actionLabel(action);
  const marker = action.state === "keep" ? "•" : action.state === "skipped" ? "!" : "+";
  const suffix = action.detail ? " — " + action.detail : "";
  return marker + " " + action.state.padEnd(8) + " " + label + suffix;
}

/** Every external command the plan would run, in order, for the dry run. */
export function plannedCommands(plan) {
  const clones = (plan.fetches || []).flatMap((source) => {
    const tracking = source.track === "latest-release" && !plan.pinned;
    const clone = "git clone --depth 1 --branch " + (tracking ? "<newest release, at least " + source.ref + ">" : source.ref) + " -- " + source.repo + " <temp folder>";
    return tracking ? ["git ls-remote --tags -- " + source.repo, clone] : [clone];
  });
  const installs = plan.actions.flatMap((action) => (action.commands || []).map(([command, args]) => [command, ...args].join(" ")));
  const graph = plan.actions.filter((a) => ["graphify-refresh", "graphify-hook"].includes(a.type) && !["keep", "skipped"].includes(a.state)).map((a) => a.command);
  return [...clones, ...installs, ...graph];
}
