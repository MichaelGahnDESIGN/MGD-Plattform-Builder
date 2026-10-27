import os from "node:os";
import { compareVersions } from "./companions.js";

/**
 * Decides which release of a skill companion is installed.
 *
 * Default: the newest release tag (vX.Y.Z) of the companion's own repository, resolved at install time, so a
 * new Todo or Living Documentation release reaches every new project without touching this registry.
 * `registry/companions.yml` keeps `ref`/`commit` only as the tested floor (nothing older is ever installed)
 * and as the known-good pin for `--pinned` (reproducible installs, CI).
 *
 * Trust: a tracked repository must belong to the registry owner. The commit is read from `git ls-remote`
 * and the clone is verified against it afterwards, so the tag cannot move between the two steps.
 */

const TAG_LINE = /^([0-9a-f]{40})\trefs\/tags\/(v\d+\.\d+\.\d+)(\^\{\})?$/;
const LIST_TIMEOUT_MS = 60000;
const UNSAFE_GIT_ENV = /^(GIT_DIR|GIT_WORK_TREE|GIT_INDEX_FILE|GIT_OBJECT_DIRECTORY|GIT_ALTERNATE_OBJECT_DIRECTORIES|GIT_COMMON_DIR|GIT_NAMESPACE|GIT_CONFIG.*)$/;
const GIT_SETTINGS = [["core.hooksPath", "/dev/null"], ["core.fsmonitor", "false"], ["submodule.recurse", "false"]];

/**
 * Inherited git settings that are only about reaching the network. Sandboxes and company networks hand their
 * proxy settings to git through GIT_CONFIG_PARAMETERS or GIT_CONFIG_COUNT/KEY/VALUE; without them git cannot
 * connect ("Proxy CONNECT aborted"). Everything else stays out: a variable that carries any other entry is
 * dropped as a whole (fail closed). Empty credential-helper resets are the one harmless extra (see HELPER_RESET).
 */
const KEPT_GIT_KEYS = new Set(["http.proxy", "http.proxyauthmethod", "http.sslcainfo", "http.sslcapath", "safe.directory"]);
const MAX_INHERITED_SETTINGS = 64;

const unquote = (text) => text.replace(/'\\''/g, "'");

/** Parses GIT_CONFIG_PARAMETERS (`'key=value'` and `'key'='value'` entries) into [key, value] pairs, or null. */
export function parseConfigParameters(text) {
  const quoted = /'((?:[^']|'\\'')*)'/y;
  const source = String(text);
  const entries = [];
  let index = 0;
  while (index < source.length) {
    if (source[index] === " ") {
      index += 1;
      continue;
    }
    quoted.lastIndex = index;
    const first = quoted.exec(source);
    if (!first) return null;
    index = quoted.lastIndex;
    if (source[index] === "=") {
      quoted.lastIndex = index + 1;
      const second = quoted.exec(source);
      if (!second) return null;
      index = quoted.lastIndex;
      entries.push([unquote(first[1]), unquote(second[1])]);
    } else {
      const raw = unquote(first[1]);
      const equals = raw.indexOf("=");
      entries.push(equals < 0 ? [raw, ""] : [raw.slice(0, equals), raw.slice(equals + 1)]);
    }
  }
  return entries;
}

// `credential.helper=` / `credential.<url>.helper=` with an EMPTY value only resets the helper list (no program runs);
// sandboxes use it so git does not ask for credentials at their local proxy. A helper with content would run a program.
const HELPER_RESET = /^credential\.(?:[^=]*\.)?helper$/i;
const isKept = ([key, value]) => KEPT_GIT_KEYS.has(key.toLowerCase()) || (HELPER_RESET.test(key) && value === "");
const onlyKeptKeys = (entries) => entries.every(isKept);

function inheritedParameters(base) {
  const entries = base.GIT_CONFIG_PARAMETERS ? parseConfigParameters(base.GIT_CONFIG_PARAMETERS) : null;
  return entries && entries.length > 0 && onlyKeptKeys(entries) ? base.GIT_CONFIG_PARAMETERS : null;
}

function inheritedSettings(base) {
  const count = Number.parseInt(base.GIT_CONFIG_COUNT, 10);
  if (!Number.isInteger(count) || count < 1 || count > MAX_INHERITED_SETTINGS) return [];
  const entries = Array.from({ length: count }, (_, index) => [base["GIT_CONFIG_KEY_" + index], base["GIT_CONFIG_VALUE_" + index]]);
  return entries.every(([key, value]) => typeof key === "string" && typeof value === "string") && onlyKeptKeys(entries) ? entries : [];
}

/**
 * The environment for every git call: no inherited repository overrides, no prompts, no LFS download, and
 * hooks, fsmonitor and submodules switched off, so neither the user's global config nor a cloned repository
 * can make `git clone` or `git checkout` run anything. Network-only settings (see KEPT_GIT_KEYS) survive.
 */
export function gitEnvironment(base = process.env) {
  const clean = Object.fromEntries(Object.entries(base).filter(([key]) => !UNSAFE_GIT_ENV.test(key)));
  const settings = [...inheritedSettings(base), ...GIT_SETTINGS];
  const config = Object.fromEntries(settings.flatMap(([key, value], index) => [["GIT_CONFIG_KEY_" + index, key], ["GIT_CONFIG_VALUE_" + index, value]]));
  const parameters = inheritedParameters(base);
  return {
    ...clean,
    ...(parameters ? { GIT_CONFIG_PARAMETERS: parameters } : {}),
    ...config,
    GIT_CONFIG_COUNT: String(settings.length),
    GIT_TERMINAL_PROMPT: "0",
    GIT_LFS_SKIP_SMUDGE: "1"
  };
}

export function isTracking(source) {
  return source.track === "latest-release";
}

/** Maps every plain release tag to its commit. For annotated tags the peeled commit (`^{}`) wins. */
export function parseTags(lsRemoteOutput) {
  const tags = new Map();
  const peeled = new Set();
  for (const line of String(lsRemoteOutput).split("\n")) {
    const match = TAG_LINE.exec(line.trim().replace(/\s+/, "\t"));
    if (!match) continue;
    const [, commit, tag, isPeeled] = match;
    if (isPeeled) {
      tags.set(tag, commit);
      peeled.add(tag);
    } else if (!peeled.has(tag)) {
      tags.set(tag, commit);
    }
  }
  return tags;
}

/** The highest release tag that is at least `floor`, or null. Pre-release and non-semver tags never match. */
export function highestRelease(tags, floor) {
  let best = null;
  for (const [ref, commit] of tags) {
    if (compareVersions(ref, floor) < 0) continue;
    if (!best || compareVersions(ref, best.ref) > 0) best = { ref, commit };
  }
  return best;
}

/**
 * Returns `{ repo, ref, commit, mode }`. `mode` is "latest" or "pinned".
 * `run` is injectable for tests.
 */
export async function resolveSource(source, { run, owner, pinned = false }) {
  if (pinned || !isTracking(source)) {
    return { repo: source.repo, ref: source.ref, commit: source.commit, mode: "pinned" };
  }
  if (!owner || !source.repo.startsWith(owner + "/")) {
    throw new Error("Refusing to track the latest release of " + source.repo + ": it does not belong to " + owner);
  }
  const listed = await run("git", ["ls-remote", "--tags", "--", source.repo], { cwd: os.tmpdir(), env: gitEnvironment(), timeout: LIST_TIMEOUT_MS });
  if (listed.code !== 0) {
    throw new Error("Could not list the releases of " + source.repo + ": " + (listed.stderr.trim() || "git is not available") + ". Use --pinned to install the tested release " + source.ref + " instead.");
  }
  const best = highestRelease(parseTags(listed.stdout), source.ref);
  if (!best) {
    throw new Error("No release of " + source.repo + " at or above the tested floor " + source.ref + " was found. Use --pinned to install " + source.ref + ".");
  }
  return { repo: source.repo, ref: best.ref, commit: best.commit, mode: "latest" };
}

/** `v1.4.0 (a1b2c3d)` for result lines. */
export function describeResolved(resolved) {
  return resolved.ref + " (" + resolved.commit.slice(0, 7) + (resolved.mode === "pinned" ? ", pinned" : "") + ")";
}
