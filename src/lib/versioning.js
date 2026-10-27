import fs from "node:fs/promises";
import path from "node:path";

export const VERSION_FILE = "version.json";

export const STATUSES = [
  "pre-alpha",
  "alpha",
  "beta",
  "pre-release",
  "release",
  "stable",
  "staging",
  "hotfix",
  "lts",
  "deprecated"
];

const STATUS_LABELS = {
  "pre-alpha": "Pre-Alpha",
  alpha: "Alpha",
  beta: "Beta",
  "pre-release": "Pre-Release",
  release: "Release",
  stable: "Stable",
  staging: "Staging",
  hotfix: "Hotfix",
  lts: "LTS",
  deprecated: "Deprecated"
};

const SEMVER = /^(\d+)\.(\d+)\.(\d+)$/;

export function parseVersion(value) {
  const match = SEMVER.exec(String(value || "").trim());
  if (!match) throw new Error("Invalid version (expected MAJOR.MINOR.PATCH): " + value);
  return { major: Number(match[1]), minor: Number(match[2]), patch: Number(match[3]) };
}

export function statusLabel(status) {
  if (!STATUSES.includes(status)) throw new Error("Unknown version status: " + status);
  return STATUS_LABELS[status];
}

export function formatVersionLabel(info) {
  parseVersion(info.version);
  return info.version + " " + statusLabel(info.status);
}

/**
 * MAJOR = release line, MINOR = new features, PATCH = patches/updates of existing features.
 */
export function bumpVersion(version, part) {
  const current = parseVersion(version);
  if (part === "major") return (current.major + 1) + ".0.0";
  if (part === "minor") return current.major + "." + (current.minor + 1) + ".0";
  if (part === "patch") return current.major + "." + current.minor + "." + (current.patch + 1);
  throw new Error("Unknown bump part (use major, minor or patch): " + part);
}

export function nextVersionInfo(info, { bump, status, today }) {
  const version = bump ? bumpVersion(info.version, bump) : info.version;
  const nextStatus = status || info.status;
  statusLabel(nextStatus);

  return {
    ...info,
    version,
    status: nextStatus,
    released_at: bump || status ? today : info.released_at
  };
}

export async function readVersionFile(root) {
  const file = path.join(root, VERSION_FILE);
  const info = JSON.parse(await fs.readFile(file, "utf8"));
  parseVersion(info.version);
  statusLabel(info.status);
  return { file, info };
}

export async function writeJson(file, value) {
  await fs.writeFile(file, JSON.stringify(value, null, 2) + "\n", "utf8");
}

async function updateJsonVersion(file, version) {
  const exists = await fs.access(file).then(() => true).catch(() => false);
  if (!exists) return null;
  const data = JSON.parse(await fs.readFile(file, "utf8"));
  if (data.version === version) return { file, status: "unchanged" };
  await writeJson(file, { ...data, version });
  return { file, status: "updated" };
}

async function updatePlainVersion(file, version) {
  const exists = await fs.access(file).then(() => true).catch(() => false);
  const current = exists ? (await fs.readFile(file, "utf8")).trim() : null;
  if (current === version) return { file, status: "unchanged" };
  await fs.writeFile(file, version + "\n", "utf8");
  return { file, status: exists ? "updated" : "created" };
}

export async function syncVersionTargets(root, info) {
  const targets = Array.isArray(info.sync_targets) ? info.sync_targets : ["VERSION"];
  const results = [];

  for (const target of targets) {
    const file = path.join(root, target);
    const result = target === "VERSION"
      ? await updatePlainVersion(file, info.version)
      : await updateJsonVersion(file, info.version);
    results.push(result || { file, status: "missing" });
  }

  return results;
}

export async function findVersionDrift(root, info) {
  const drift = [];
  for (const result of await Promise.all((info.sync_targets || ["VERSION"]).map(async (target) => {
    const file = path.join(root, target);
    const raw = await fs.readFile(file, "utf8").catch(() => null);
    if (raw === null) return { target, found: null };
    const found = target === "VERSION" ? raw.trim() : JSON.parse(raw).version;
    return { target, found };
  }))) {
    if (result.found !== info.version) drift.push(result);
  }
  return drift;
}
