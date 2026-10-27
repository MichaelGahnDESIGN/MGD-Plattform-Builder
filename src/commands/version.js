import path from "node:path";
import {
  formatVersionLabel,
  findVersionDrift,
  nextVersionInfo,
  readVersionFile,
  syncVersionTargets,
  writeJson
} from "../lib/versioning.js";
import { addEntry, buildEntry, readReleaseNotes, writeReleaseNotes } from "../lib/release-notes.js";

function option(args, name) {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : undefined;
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function positional(args) {
  const valued = new Set(["--bump", "--status", "--note", "--audience", "--type"]);
  return args.find((arg, index) => !arg.startsWith("--") && !valued.has(args[index - 1]));
}

async function appendNote(root, info, args) {
  const title = option(args, "--note");
  if (!title) return;
  const { file, notes } = await readReleaseNotes(root);
  const entry = buildEntry({
    version: info.version,
    status: info.status,
    date: info.released_at || today(),
    title,
    audience: option(args, "--audience"),
    type: option(args, "--type") || (option(args, "--bump") === "patch" ? "patch" : "feature")
  });
  await writeReleaseNotes(file, addEntry(notes, entry));
  console.log("  added    release note: " + entry.title + " [" + entry.audience.join(", ") + "]");
}

export async function versionCommand(args = []) {
  const root = path.resolve(positional(args) || ".");
  const bump = option(args, "--bump");
  const status = option(args, "--status");
  const check = args.includes("--check");

  let loaded;
  try {
    loaded = await readVersionFile(root);
  } catch (error) {
    console.error("✗ Could not read version.json: " + error.message);
    console.error("  Create one with: mgd-platform init (or copy templates/version.example.json)");
    return 1;
  }

  if (check) {
    const drift = await findVersionDrift(root, loaded.info);
    console.log("Version: " + formatVersionLabel(loaded.info));
    for (const item of drift) console.log("  ✗ " + item.target + " has " + (item.found || "no version"));
    if (drift.length === 0) console.log("  ✓ all sync targets match");
    return drift.length === 0 ? 0 : 1;
  }

  try {
    const info = nextVersionInfo(loaded.info, { bump, status, today: today() });
    if (bump || status) await writeJson(loaded.file, info);

    console.log("\nVersion: " + formatVersionLabel(info));
    if (bump || status || args.includes("--sync")) {
      for (const result of await syncVersionTargets(root, info)) {
        console.log("  " + result.status.padEnd(8) + " " + path.relative(root, result.file));
      }
    }
    await appendNote(root, info, args);
    return 0;
  } catch (error) {
    console.error("✗ " + error.message);
    return 1;
  }
}
