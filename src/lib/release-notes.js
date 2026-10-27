import fs from "node:fs/promises";
import path from "node:path";
import { writeJson } from "./versioning.js";

export const RELEASE_NOTES_FILE = "release-notes.json";
export const AUDIENCES = ["frontend", "backoffice", "editor", "platform", "game", "api"];
export const ENTRY_TYPES = ["feature", "patch", "fix", "security", "breaking", "deploy"];

export async function readReleaseNotes(root) {
  const file = path.join(root, RELEASE_NOTES_FILE);
  const raw = await fs.readFile(file, "utf8").catch(() => null);
  return { file, notes: raw ? JSON.parse(raw) : { entries: [] } };
}

export function buildEntry({ version, status, date, title, audience, type, items = [] }) {
  const audiences = (audience || "backoffice").split(",").map((item) => item.trim()).filter(Boolean);
  const unknown = audiences.filter((item) => !AUDIENCES.includes(item));
  if (unknown.length) throw new Error("Unknown release-note audience: " + unknown.join(", "));
  const entryType = type || "patch";
  if (!ENTRY_TYPES.includes(entryType)) throw new Error("Unknown release-note type: " + entryType);
  if (!title || !title.trim()) throw new Error("Release note title must not be empty");

  return { version, status, date, audience: audiences, type: entryType, title: title.trim(), items };
}

export function addEntry(notes, entry) {
  return { ...notes, entries: [entry, ...(notes.entries || [])] };
}

/** Frontends only show entries addressed to the frontend; backoffices show everything. */
export function filterForAudience(entries, view) {
  if (view === "backoffice") return entries;
  return entries.filter((entry) => Array.isArray(entry.audience) && entry.audience.includes("frontend"));
}

export async function writeReleaseNotes(file, notes) {
  await writeJson(file, notes);
}
