import os from "node:os";
import {
  findCommands,
  isInsideProject,
  trustedPathEntries,
} from "../lib/companions.js";

export async function trustedProgram(name, root = process.cwd()) {
  const candidates = await findCommands(name, { root, home: os.homedir() });
  for (const candidate of candidates)
    if (!(await isInsideProject(root, candidate))) return candidate;
  throw new Error(
    "Kein vertrauenswürdiges " + name + " außerhalb des Projekts gefunden",
  );
}
export async function trustedEnvironment(root = process.cwd()) {
  return {
    ...process.env,
    PATH: (await trustedPathEntries(root)).join(
      process.platform === "win32" ? ";" : ":",
    ),
    NoDefaultCurrentDirectoryInExePath: "1",
    GH_DEBUG: "",
    GIT_TERMINAL_PROMPT: "0",
  };
}
