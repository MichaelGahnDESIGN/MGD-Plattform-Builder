import fs from "node:fs/promises";
import path from "node:path";

export const SKIP = new Set([
  ".git",
  ".svn",
  ".hg",
  "node_modules",
  "vendor",
  ".venv",
  "venv",
  "__pycache__",
  ".cache",
  "dist",
  "build",
  "artifacts",
  "BACKUPS",
  "backups",
  "MICHA",
  "secrets",
  ".ssh",
  ".codex",
  "Pods",
  ".dart_tool",
]);
export const MAX_FILE = 8 * 1024 * 1024;
export const isInside = (root, file) =>
  file === root ||
  (!path.relative(root, file).startsWith(".." + path.sep) &&
    path.relative(root, file) !== ".." &&
    !path.isAbsolute(path.relative(root, file)));

export async function readSafe(root, relative) {
  const file = path.resolve(root, relative);
  if (!isInside(root, file)) throw new Error("Pfad außerhalb des Projekts");
  const stat = await fs.lstat(file);
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size > MAX_FILE)
    throw new Error("Datei nicht sicher lesbar oder zu groß");
  if (!isInside(root, await fs.realpath(file)))
    throw new Error("Verweis außerhalb des Projekts");
  return fs.readFile(file, "utf8");
}

export async function walk(root, { maxDepth = 8, maxFiles = 18000 } = {}) {
  const files = [],
    warnings = [];
  if ((await fs.lstat(root)).isSymbolicLink())
    throw new Error("Projektwurzel darf kein Symlink sein");
  let visited = 0;
  async function visit(dir, depth) {
    if (visited >= maxFiles) return;
    let entries;
    try {
      entries = await fs.readdir(dir, { withFileTypes: true });
    } catch {
      warnings.push("Ordner nicht lesbar: " + path.relative(root, dir));
      return;
    }
    for (const entry of entries) {
      if (++visited > maxFiles) {
        warnings.push("Dateigrenze erreicht");
        return;
      }
      if (
        entry.isSymbolicLink() ||
        (entry.name.startsWith(".") && entry.name !== ".github")
      )
        continue;
      const file = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        if (SKIP.has(entry.name)) continue;
        if (depth >= maxDepth) {
          warnings.push("Tiefengrenze: " + path.relative(root, file));
          continue;
        }
        await visit(file, depth + 1);
      } else if (entry.isFile()) files.push(path.relative(root, file));
    }
  }
  await visit(root, 0);
  return { files, warnings };
}

const canonicalParent = (parent) =>
  process.platform === "darwin"
    ? path
        .resolve(parent)
        .replace(/^\/tmp(?=\/|$)/, "/private/tmp")
        .replace(/^\/var(?=\/|$)/, "/private/var")
    : path.resolve(parent);

export async function writePrivate(file, content) {
  const parent = path.dirname(path.resolve(file));
  // Vor dem Anlegen prüfen, damit auch leere Unterordner nicht durch fremde Links entstehen.
  let ancestor = parent;
  while (true) {
    try {
      await fs.lstat(ancestor);
      break;
    } catch (e) {
      if (e.code !== "ENOENT") throw e;
      ancestor = path.dirname(ancestor);
    }
  }
  if ((await fs.realpath(ancestor)) !== canonicalParent(ancestor))
    throw new Error("Ausgabeordner enthält Symlinks");
  await fs.mkdir(parent, { recursive: true, mode: 0o700 });
  let stat;
  try {
    stat = await fs.lstat(file);
  } catch (e) {
    if (e.code !== "ENOENT") throw e;
  }
  if (stat?.isSymbolicLink()) throw new Error("Ausgabeziel ist ein Symlink");
  if ((await fs.realpath(parent)) !== canonicalParent(parent))
    throw new Error("Ausgabeordner enthält Symlinks");
  const temporary = file + "." + process.pid + ".tmp";
  await fs.writeFile(temporary, content, { mode: 0o600, flag: "wx" });
  await fs.rename(temporary, file);
  await fs.chmod(file, 0o600);
}
