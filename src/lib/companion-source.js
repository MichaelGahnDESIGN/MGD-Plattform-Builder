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
const SAFE_GIT_ENV = (env) => {
  const clean = Object.fromEntries(Object.entries(env).filter(([key]) => !/^(GIT_DIR|GIT_WORK_TREE|GIT_INDEX_FILE|GIT_OBJECT_DIRECTORY|GIT_ALTERNATE_OBJECT_DIRECTORIES|GIT_COMMON_DIR|GIT_NAMESPACE|GIT_CONFIG.*)$/.test(key)));
  return { ...clean, GIT_TERMINAL_PROMPT: "0" };
};

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
  const listed = await run("git", ["ls-remote", "--tags", "--", source.repo], { cwd: os.tmpdir(), env: SAFE_GIT_ENV(process.env), timeout: LIST_TIMEOUT_MS });
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
