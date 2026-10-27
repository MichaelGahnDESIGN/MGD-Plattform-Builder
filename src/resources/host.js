import fs from "node:fs/promises";
import path from "node:path";
import os from "node:os";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
const exec = promisify(execFile);
const make = (name, version, kind, source) => ({
  name,
  version,
  kind,
  file: source,
  ecosystem: "",
  exact: true,
  observed: true,
  environment: "local",
  observedAt: new Date().toISOString(),
  checks: {
    vulnerability: { status: "unchecked" },
    support: { status: "unchecked" },
  },
});

export async function inspectHost() {
  const result = [
    make("nodejs", process.versions.node, "runtime", "Laufender Prüfprozess"),
  ];
  const env = {
    PATH: "/usr/bin:/bin:/usr/sbin:/sbin",
    LANG: "C",
    NoDefaultCurrentDirectoryInExePath: "1",
  };
  if (process.platform === "darwin") {
    try {
      const { stdout } = await exec("/usr/bin/sw_vers", ["-productVersion"], {
        cwd: "/",
        env,
        timeout: 5000,
        maxBuffer: 4096,
      });
      if (/^\d+(?:\.\d+)*$/.test(stdout.trim()))
        result.push(
          make("macos", stdout.trim(), "os", "Lokales Betriebssystem"),
        );
    } catch {}
    // Installationsbelege lesen statt Homebrew-Formeln oder Projekt-Skripte auszuführen.
    const cellar = "/opt/homebrew/Cellar";
    try {
      for (const n of (await fs.readdir(cellar, { withFileTypes: true })).slice(
        0,
        1000,
      )) {
        if (!n.isDirectory() || n.isSymbolicLink()) continue;
        for (const v of await fs.readdir(path.join(cellar, n.name), {
          withFileTypes: true,
        })) {
          if (!v.isDirectory() || v.isSymbolicLink()) continue;
          const receipt = path.join(
            cellar,
            n.name,
            v.name,
            "INSTALL_RECEIPT.json",
          );
          try {
            const s = await fs.lstat(receipt);
            if (!s.isFile() || s.isSymbolicLink() || s.size > 262144) continue;
            JSON.parse(await fs.readFile(receipt, "utf8"));
            result.push(
              make(
                n.name,
                v.name,
                "runtime",
                "Lokaler Homebrew-Installationsbeleg",
              ),
            );
          } catch {}
        }
      }
    } catch {}
  } else if (process.platform === "linux") {
    try {
      const text = await fs.readFile("/etc/os-release", "utf8");
      const id = text.match(/^ID=["']?([\w-]+)/m),
        v = text.match(/^VERSION_ID=["']?([\d.]+)/m);
      if (id && v)
        result.push(make(id[1], v[1], "os", "Lokales Betriebssystem"));
    } catch {}
  } else
    result.push(
      make(
        process.platform,
        os.release(),
        "os",
        "Lokales Betriebssystem; Supportzuordnung offen",
      ),
    );
  return result;
}
