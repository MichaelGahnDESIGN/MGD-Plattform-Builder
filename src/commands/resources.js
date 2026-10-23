import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { loadConfig, scan, trackChanges } from "../resources/manager.js";
import { setupGithub, dependabotConfig } from "../resources/github.js";
import { writePrivate } from "../resources/files.js";
import { isInside } from "../resources/files.js";

function option(args, name) {
  const i = args.indexOf(name);
  if (i >= 0 && (!args[i + 1] || args[i + 1].startsWith("--")))
    throw new Error("Wert fehlt: " + name);
  return i < 0 ? undefined : args[i + 1];
}
function target(args) {
  const valued = new Set(["--config", "--output", "--owner"]);
  return (
    args.find((s, i) => !s.startsWith("--") && !valued.has(args[i - 1])) || "."
  );
}

export async function resourcesCommand(args = []) {
  const command = args[0] || "help",
    rest = args.slice(1);
  if (command === "help" || command === "--help") {
    console.log(
      "MGD System- und Abhängigkeitsmanager\n\n  resources scan [PATH] [--config FILE] [--output DIR] [--online --github] [--host] [--track]\n  resources init [PATH]\n  resources register [PATH] [--config FILE] [--owner OWNER]\n  resources github [--config FILE] [--output DIR] [--owner OWNER] [--apply]\n\nScan liest nur Metadaten. --online fragt öffentliche Quellen ab; GitHub-Abfragen brauchen vorhandene Anmeldung. Private Paketdaten werden nicht an OSV übertragen. github zeigt zunächst einen Plan; --apply erstellt Konfigurations-PRs und aktiviert GitHub-Sicherheits-PRs. Keine Serverupdates oder automatischen Merges.",
    );
    return 0;
  }
  if (command === "init") {
    const root = await fs.realpath(path.resolve(target(rest))),
      file = path.join(root, ".mgd", "resources.json");
    try {
      await fs.lstat(file);
      console.log("Vorhandene Ressourcen-Konfiguration bleibt erhalten.");
      return 0;
    } catch (e) {
      if (e.code !== "ENOENT") throw e;
    }
    await writePrivate(
      file,
      JSON.stringify({ schemaVersion: 1, roots: [".."], owners: [] }, null, 2) +
        "\n",
    );
    console.log("Lokale Ressourcen-Konfiguration angelegt.");
    return 0;
  }
  if (command === "register") {
    const root = await fs.realpath(path.resolve(target(rest)));
    const registry = path.resolve(
      option(rest, "--config") ||
        path.join(os.homedir(), ".config", "mgd", "resources.json"),
    );
    let data;
    try {
      data = await loadConfig(registry);
    } catch (e) {
      try {
        await fs.lstat(registry);
      } catch (missing) {
        if (missing.code === "ENOENT")
          data = { schemaVersion: 1, roots: [], owners: [] };
        else throw missing;
      }
      if (!data) throw e;
    }
    if (!data.roots.some((parent) => isInside(parent, root)))
      data.roots.push(root);
    const owner = option(rest, "--owner");
    if (owner) {
      if (!/^[A-Za-z0-9_.-]{1,100}$/.test(owner))
        throw new Error("Ungültiger Eigentümer");
      if (!data.owners.includes(owner)) data.owners.push(owner);
    }
    if (data.roots.length > 30)
      throw new Error(
        "Zu viele Einzelwurzeln; übergeordneten Projektordner registrieren",
      );
    await writePrivate(registry, JSON.stringify(data, null, 2) + "\n");
    console.log("Projekt für die vorhandene Ressourcenprüfung registriert.");
    return 0;
  }
  const configFile = option(rest, "--config");
  const config = configFile
    ? await loadConfig(configFile)
    : {
        schemaVersion: 1,
        roots: [path.resolve(target(rest))],
        owners: option(rest, "--owner") ? [option(rest, "--owner")] : [],
      };
  const output = path.resolve(
    option(rest, "--output") ||
      path.join(os.homedir(), ".local", "share", "mgd-resources", "reports"),
  );
  if (command === "scan") {
    const report = await scan({
      config,
      output,
      online: rest.includes("--online"),
      github: rest.includes("--github"),
      host: rest.includes("--host"),
    });
    const changed = rest.includes("--track")
      ? await trackChanges(report, path.join(output, "notification-state.json"))
      : null;
    console.log(
      JSON.stringify(
        {
          summary: report.summary,
          changed,
          report: path.join(output, "index.html"),
        },
        null,
        2,
      ),
    );
    return report.issues.some((i) => ["critical", "high"].includes(i.severity))
      ? 2
      : 0;
  }
  if (command === "github") {
    const report = JSON.parse(
      await fs.readFile(path.join(output, "report.json"), "utf8"),
    );
    const owner =
      option(rest, "--owner") ||
      (config.owners.length === 1 ? config.owners[0] : null);
    if (!owner)
      throw new Error(
        "Ein GitHub-Eigentümer muss ausdrücklich festgelegt sein",
      );
    const results = [];
    const seen = new Set();
    for (const project of report.projects) {
      if (!project.repository || project.repository.split("/")[0] !== owner)
        continue;
      if (seen.has(project.repository)) continue;
      seen.add(project.repository);
      if (!rest.includes("--apply")) {
        results.push({
          repository: project.repository,
          mode: "plan",
          ecosystems: dependabotConfig(project).count,
        });
        continue;
      }
      try {
        const result = await setupGithub(project, {
          owner,
          journal: async (event) => {
            await writePrivate(
              path.join(output, "github-journal.json"),
              JSON.stringify([...results, event], null, 2) + "\n",
            );
          },
        });
        results.push(result);
      } catch {
        results.push({
          repository: project.repository,
          status: "unchecked",
          reason:
            "Konfiguration nicht abgeschlossen; Berechtigungen oder bestehenden Setup-Branch prüfen",
        });
      }
      await writePrivate(
        path.join(output, "github-setup.json"),
        JSON.stringify(results, null, 2) + "\n",
      );
    }
    console.log(
      JSON.stringify(
        results.map(({ previousConfig, ...r }) => r),
        null,
        2,
      ),
    );
    return results.some((r) => r.status === "unchecked") ? 2 : 0;
  }
  throw new Error("Unbekannter Ressourcen-Befehl");
}
