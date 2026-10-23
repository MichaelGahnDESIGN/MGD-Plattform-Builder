import path from "node:path";
import YAML from "yaml";

const exact = (v) =>
  typeof v === "string" &&
  /^v?\d[^\s<>=~^*|{}$]*$/.test(v) &&
  !/https?:|@|\//.test(v);
const validName = (n) =>
  typeof n === "string" && n.length < 250 && /^[A-Za-z0-9@_.+:/-]+$/.test(n);
export const component = (
  name,
  version,
  ecosystem,
  kind,
  file,
  extra = {},
) => ({
  name,
  version: String(version ?? ""),
  ecosystem,
  kind,
  file,
  exact: exact(version),
  ...extra,
});
export const yaml = (text) =>
  YAML.parse(text, { maxAliasCount: 20, uniqueKeys: true });

export function parsePackages(file, text) {
  const base = path.basename(file),
    out = [];
  const add = (n, v, e, extra = {}) => {
    if (validName(n)) out.push(component(n, v, e, "package", file, extra));
  };
  if (base === "package-lock.json" || base === "npm-shrinkwrap.json") {
    const data = JSON.parse(text);
    if (data.packages)
      for (const [location, p] of Object.entries(data.packages)) {
        if (!location || p.link || !p.version) continue;
        const name = p.name || location.split("node_modules/").at(-1);
        add(name, p.version, "npm", {
          publicSource:
            typeof p.resolved === "string" &&
            /^https:\/\/registry\.npmjs\.org\//.test(p.resolved),
        });
      }
    else {
      const visit = (deps) => {
        for (const [n, p] of Object.entries(deps || {})) {
          add(n, p.version, "npm", {
            publicSource: /^https:\/\/registry\.npmjs\.org\//.test(
              p.resolved || "",
            ),
          });
          visit(p.dependencies);
        }
      };
      visit(data.dependencies);
    }
  } else if (base === "pnpm-lock.yaml") {
    const data = yaml(text);
    for (const [key, p] of Object.entries(data?.packages || {})) {
      const m = key
        .replace(/^\//, "")
        .match(/^((?:@[^/]+\/)?[^@()]+)@([^()]+)(?:\(.*)?$/);
      if (m)
        add(m[1], m[2], "npm", {
          publicSource: /^https:\/\/registry\.npmjs\.org\//.test(
            p.resolution?.tarball || "",
          ),
        });
    }
  } else if (base === "yarn.lock") {
    if (text.startsWith("# yarn lockfile v1")) {
      for (const block of text.split(/\n(?=\S)/)) {
        const head = block.split("\n")[0],
          name = head.match(/^"?((?:@[^/\s]+\/)?[^@\s"]+)@/),
          v = block.match(/^  version "([^"\s]+)"/m),
          resolved = block.match(/^  resolved "([^"\s]+)"/m);
        if (name && v)
          add(name[1], v[1], "npm", {
            publicSource:
              /^https:\/\/(?:registry.npmjs.org|registry.yarnpkg.com)\//.test(
                resolved?.[1] || "",
              ),
          });
      }
    } else {
      const d = yaml(text);
      for (const p of Object.values(d || {})) {
        const m = p?.resolution?.match(/^((?:@[^/]+\/)?[^@]+)@npm:(.+)$/);
        if (m) add(m[1], p.version || m[2], "npm", { publicSource: false });
      }
    }
  } else if (base === "composer.lock") {
    const data = JSON.parse(text);
    for (const p of [...(data.packages || []), ...(data["packages-dev"] || [])])
      add(p.name, p.version, "Packagist", { publicSource: false });
  } else if (base === "Pipfile.lock") {
    const d = JSON.parse(text);
    for (const group of ["default", "develop"])
      for (const [n, p] of Object.entries(d[group] || {}))
        add(n, p.version?.replace(/^==/, ""), "PyPI", { publicSource: false });
  } else if (
    base === "requirements.txt" ||
    /^requirements[-.].*\.txt$/.test(base)
  ) {
    for (const line of text.split("\n")) {
      const m = line.match(
        /^\s*([A-Za-z0-9_.-]+)(?:\[[\w,.-]+\])?==([^\s;#]+)(?:\s|;|#|$)/,
      );
      if (m) add(m[1], m[2], "PyPI", { publicSource: false });
    }
  } else if (
    base === "Cargo.lock" ||
    base === "uv.lock" ||
    base === "poetry.lock"
  ) {
    for (const table of text.split(/^\[\[package\]\]\s*$/m).slice(1)) {
      const n = table.match(/^name\s*=\s*"([A-Za-z0-9_.-]+)"\s*$/m),
        v = table.match(/^version\s*=\s*"([^"\s]+)"\s*$/m);
      if (n && v)
        add(n[1], v[1], base === "Cargo.lock" ? "crates.io" : "PyPI", {
          publicSource:
            base === "Cargo.lock"
              ? /registry\+https:\/\/github.com\/rust-lang\/crates.io-index/.test(
                  table,
                )
              : /https:\/\/(?:pypi.org|files.pythonhosted.org)/.test(table),
        });
    }
  } else if (base === "go.sum") {
    for (const line of text.split("\n")) {
      const m = line.match(/^([\w./-]+) (v\S+?)(?:\/go.mod)? h1:/);
      if (m)
        add(m[1], m[2], "Go", { publicSource: false, scope: "lock-history" });
    }
  } else if (base === "pubspec.lock") {
    const data = yaml(text);
    for (const [n, p] of Object.entries(data?.packages || {}))
      add(n, p.version, "Pub", {
        publicSource:
          p.source === "hosted" && p.description?.url === "https://pub.dev",
      });
  } else if (base === "packages.lock.json") {
    const data = JSON.parse(text);
    for (const group of Object.values(data.dependencies || {}))
      for (const [n, p] of Object.entries(group))
        if (p.resolved) add(n, p.resolved, "NuGet", { publicSource: false });
  } else if (base === "Gemfile.lock") {
    for (const line of text.split("\n")) {
      const m = line.match(/^    ([\w.-]+) \((\d[^\s)]*)\)$/);
      if (m)
        add(m[1], m[2], "RubyGems", {
          publicSource: /remote: https:\/\/rubygems.org\//.test(text),
        });
    }
  } else if (base === "gradle.lockfile") {
    for (const line of text.split("\n")) {
      const m = line.match(/^([\w.-]+):([\w.-]+):([^=\s]+)=/);
      if (m) add(m[1] + ":" + m[2], m[3], "Maven", { publicSource: false });
    }
  } else if (base === "trivy.json") {
    const d = JSON.parse(text);
    if (d.SchemaVersion !== 2 || !Array.isArray(d.Results))
      throw new Error("Kein unterstützter Trivy-Report");
    for (const r of d.Results)
      for (const p of r.Packages || []) {
        const findings = (r.Vulnerabilities || [])
          .filter(
            (v) => v.PkgName === p.Name && v.InstalledVersion === p.Version,
          )
          .map((v) => ({
            id: String(v.VulnerabilityID || "Trivy-Fund").slice(0, 120),
            title: String(
              v.Title || v.VulnerabilityID || "Importierter Fund",
            ).slice(0, 500),
            severity: ["CRITICAL", "HIGH", "MEDIUM", "LOW"].includes(v.Severity)
              ? v.Severity.toLowerCase()
              : "unknown",
            fixed:
              typeof v.FixedVersion === "string"
                ? v.FixedVersion.split(",")
                    .map((s) => s.trim())
                    .slice(0, 20)
                : [],
            source: "Trivy-Import",
          }));
        add(p.Name, p.Version, "unknown", {
          publicSource: false,
          scope: "scanner-import",
          importedFindings: findings,
          scannerTimestamp:
            typeof d.CreatedAt === "string" ? d.CreatedAt : null,
          scannerResult: Array.isArray(r.Vulnerabilities)
            ? "provided"
            : "unchecked",
        });
      }
  } else if (base.endsWith(".cdx.json") || base === "bom.json") {
    const d = JSON.parse(text);
    if (d.bomFormat !== "CycloneDX") throw new Error("Kein CycloneDX-SBOM");
    const types = {
      npm: "npm",
      composer: "Packagist",
      pypi: "PyPI",
      cargo: "crates.io",
      golang: "Go",
      maven: "Maven",
      nuget: "NuGet",
      gem: "RubyGems",
      pub: "Pub",
      deb: "Debian",
      apk: "Alpine",
    };
    for (const p of d.components || []) {
      const m = p.purl?.match(
        /^pkg:([a-z]+)\/([^?#]+?)(?:@([^?#]+))?(?:[?#]|$)/,
      );
      const e = m && types[m[1]],
        n = m ? decodeURIComponent(m[2]) : p.name;
      add(n, p.version || m?.[3], e || "unknown", {
        publicSource: false,
        kind: p.type === "operating-system" ? "os" : "package",
        scope: "sbom",
      });
    }
  }
  return out;
}

export function parseDeclarations(file, text) {
  const base = path.basename(file),
    out = [];
  if (base === "package.json") {
    const d = JSON.parse(text);
    for (const n of ["node", "npm"])
      if (d.engines?.[n])
        out.push(
          component(n, d.engines[n], "", "runtime", file, { observed: false }),
        );
    for (const group of [
      "dependencies",
      "devDependencies",
      "optionalDependencies",
    ])
      for (const [n, v] of Object.entries(d[group] || {}))
        if (validName(n))
          out.push(
            component(n, v, "npm", "declaration", file, {
              exact: false,
              observed: false,
            }),
          );
  } else if (base === "composer.json") {
    const d = JSON.parse(text);
    for (const [n, v] of Object.entries(d.require || {}))
      out.push(
        component(
          n,
          v,
          n === "php" ? "" : "Packagist",
          n === "php" ? "runtime" : "declaration",
          file,
          { exact: false, observed: false },
        ),
      );
    if (d.type === "shopware-platform-plugin" && validName(d.name))
      out.push(
        component(d.name, d.version || "", "", "plugin", file, {
          observed: false,
        }),
      );
  } else if (base.startsWith("Dockerfile")) {
    for (const line of text.split("\n")) {
      const m = line.match(
        /^\s*FROM\s+(?:--platform=\S+\s+)?([^\s]+)(?:\s+AS\s+\w+)?\s*$/i,
      );
      if (m && !m[1].includes("$") && !m[1].includes("://"))
        out.push(
          component(m[1], "", "", "container", file, {
            observed: false,
            exact: false,
          }),
        );
    }
  } else if (/(?:docker-)?compose.*\.ya?ml$/.test(base)) {
    const d = yaml(text);
    for (const s of Object.values(d?.services || {}))
      if (typeof s.image === "string" && /^[A-Za-z0-9_./:@-]+$/.test(s.image))
        out.push(
          component(s.image, "", "", "container", file, {
            observed: false,
            exact: false,
          }),
        );
  } else if (
    file.split(path.sep).includes(".github") &&
    /\.ya?ml$/.test(base)
  ) {
    const d = yaml(text);
    for (const job of Object.values(d?.jobs || {}))
      for (const step of job.steps || [])
        if (
          typeof step.uses === "string" &&
          /^[A-Za-z0-9_./@-]+$/.test(step.uses)
        )
          out.push(
            component(
              step.uses.split("@")[0],
              step.uses.split("@")[1] || "",
              "",
              "ci",
              file,
              { observed: false, exact: false },
            ),
          );
  } else if (base === "version.php") {
    const m = text.match(/\$wp_version\s*=\s*['"]([\d.]+)['"]/);
    if (m)
      out.push(
        component("wordpress", m[1], "", "cms", file, { observed: false }),
      );
  } else if (base === "info.xml") {
    const n = text.match(/<Name>\s*([^<]{1,200})\s*<\/Name>/),
      v = text.match(
        /<(?:Version|PluginVersion)>\s*([\d.]+)\s*<\/(?:Version|PluginVersion)>/,
      );
    if (n && v)
      out.push(
        component(n[1].trim(), v[1], "", "plugin", file, { observed: false }),
      );
  } else if (/^shopify.*\.toml$/.test(base)) {
    for (const m of text.matchAll(/^\s*api_version\s*=\s*"(\d{4}-\d{2})"/gm))
      out.push(
        component("shopify-api", m[1], "", "external", file, {
          observed: false,
        }),
      );
  } else if (base === "resources.observations.json") {
    const d = JSON.parse(text);
    if (!Array.isArray(d.components) || d.components.length > 10000)
      throw new Error("Ungültige Beobachtungen");
    for (const c of d.components)
      if (
        validName(c.name) &&
        [
          "runtime",
          "cms",
          "plugin",
          "theme",
          "os",
          "database",
          "webserver",
          "external",
        ].includes(c.kind) &&
        typeof c.version === "string" &&
        c.version.length < 100
      ) {
        const time = Date.parse(c.observedAt || d.observedAt || ""),
          fresh =
            Number.isFinite(time) &&
            time <= Date.now() + 60000 &&
            Date.now() - time < 7 * 86400000;
        out.push(
          component(c.name, c.version, "", c.kind, file, {
            observed: fresh,
            observedAt: fresh ? new Date(time).toISOString() : null,
            environment: ["local", "staging", "production"].includes(
              c.environment,
            )
              ? c.environment
              : "unspecified",
            supportProduct: /^[a-z0-9-]{1,60}$/.test(c.supportProduct || "")
              ? c.supportProduct
              : undefined,
          }),
        );
      }
  }
  return out;
}
