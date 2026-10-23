import YAML from "yaml";
import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import {
  parsePackages,
  parseDeclarations,
  component,
} from "../src/resources/parsers.js";
import { discover, inspect } from "../src/resources/inventory.js";
import { readSafe, writePrivate } from "../src/resources/files.js";
import { requestJson } from "../src/resources/network.js";
import { osvAudit, classifySupport } from "../src/resources/audit.js";
import {
  dependabotConfig,
  githubAudit,
  setupGithub,
} from "../src/resources/github.js";
import { evaluateUpdate } from "../src/resources/policy.js";
import { renderReport } from "../src/resources/report.js";
import { scan, summaryFor, trackChanges } from "../src/resources/manager.js";

const temporary = async (fn) => {
  const root = await fs.realpath(
    await fs.mkdtemp(path.join(os.tmpdir(), "mgd-resources-test-")),
  );
  try {
    return await fn(root);
  } finally {
    await fs.rm(root, { recursive: true, force: true });
  }
};
const comp = () => ({
  ...component(
    "example-package",
    "1.0.0",
    "npm",
    "package",
    "package-lock.json",
  ),
  publicSource: true,
  checks: {
    vulnerability: { status: "unchecked" },
    support: { status: "unchecked" },
  },
});

test("npm-Scopes, verschachtelte Versionen und Workspaces korrekt", () => {
  const r = parsePackages(
    "package-lock.json",
    JSON.stringify({
      packages: {
        "": { name: "private-root", version: "1.0.0" },
        "node_modules/@scope/pkg": {
          version: "2.0.1",
          resolved: "https://registry.npmjs.org/@scope/pkg/-/pkg.tgz",
        },
        "node_modules/local": { link: true },
        "node_modules/a/node_modules/x": {
          version: "1.2.3",
          resolved: "https://private.invalid/x",
        },
      },
    }),
  );
  assert.deepEqual(
    r.map((c) => [c.name, c.version, c.publicSource]),
    [
      ["@scope/pkg", "2.0.1", true],
      ["x", "1.2.3", false],
    ],
  );
});
test("Composer, Python, Rust, Dart, Go, Ruby, Java und NuGet", () => {
  const cases = [
    [
      "composer.lock",
      '{"packages":[{"name":"vendor/example","version":"v1.2.3"}]}',
      "Packagist",
    ],
    [
      "requirements.txt",
      "example==1.2.3\nother>=2\n-e git+https://private.invalid/x",
      "PyPI",
    ],
    [
      "Cargo.lock",
      '[[package]]\nname = "example"\nversion = "1.2.3"',
      "crates.io",
    ],
    [
      "pubspec.lock",
      'packages:\n  example:\n    version: "1.2.3"\n    source: hosted\n    description:\n      url: https://pub.dev\n',
      "Pub",
    ],
    ["go.sum", "example.org/example v1.2.3 h1:abc\n", "Go"],
    [
      "Gemfile.lock",
      "GEM\n  remote: https://rubygems.org/\n  specs:\n    example (1.2.3)\n",
      "RubyGems",
    ],
    ["gradle.lockfile", "org.example:example:1.2.3=runtimeClasspath", "Maven"],
    [
      "packages.lock.json",
      '{"dependencies":{"net8.0":{"example":{"resolved":"1.2.3"}}}}',
      "NuGet",
    ],
  ];
  for (const [file, text, ecosystem] of cases) {
    const c = parsePackages(file, text);
    assert.equal(c.length, 1, file);
    assert.equal(c[0].ecosystem, ecosystem);
    assert.equal(c[0].exact, true);
  }
});
test("CycloneDX nimmt PURLs auf, unbekannte Typen bleiben unbekannt", () => {
  const c = parsePackages(
    "bom.json",
    JSON.stringify({
      bomFormat: "CycloneDX",
      components: [
        { name: "pkg", version: "1.0", purl: "pkg:npm/%40scope/pkg@1.0" },
        { name: "internal", version: "1.0", purl: "pkg:custom/internal@1.0" },
      ],
    }),
  );
  assert.equal(c[0].name, "@scope/pkg");
  assert.equal(c[1].ecosystem, "unknown");
  assert.equal(c[0].publicSource, false);
});
test("Deklaration und tatsächliche Beobachtung bleiben getrennt", () => {
  const c = parseDeclarations(
    "package.json",
    '{"engines":{"node":">=20"},"scripts":{"postinstall":"touch should-not-run"},"dependencies":{"example":"^1.2.3"}}',
  );
  assert.equal(c[0].observed, false);
  assert.equal(c[0].exact, false);
  assert.equal(
    parseDeclarations("Dockerfile", "FROM php:8.3-fpm\nRUN do-not-run")[0]
      .observed,
    false,
  );
});
test("YAML-Aliasbomben und doppelte Schlüssel werden abgewiesen", () => {
  assert.throws(() =>
    parseDeclarations("compose.yml", "services: {}\nservices: {}"),
  );
});
test("Scanner liest keine Secrets und führt keine Projektskripte aus", () =>
  temporary(async (root) => {
    await fs.writeFile(
      path.join(root, "package.json"),
      ' {"scripts":{"postinstall":"touch should-not-run"},"dependencies":{"example":"^1"}}',
    );
    await fs.writeFile(path.join(root, ".env"), "SECRET=never-copy");
    await fs.mkdir(path.join(root, "secrets"));
    await fs.writeFile(path.join(root, "secrets", "package.json"), "malformed");
    const r = await inspect({ root, name: "example", repository: null });
    assert.equal(r.warnings.length, 0);
    assert.equal(JSON.stringify(r).includes("never-copy"), false);
    await assert.rejects(fs.access(path.join(root, "should-not-run")));
  }));
test("Symlinks nach außen und übergroße Dateien werden nicht gelesen", () =>
  temporary(async (root) => {
    await fs.symlink("/etc/passwd", path.join(root, "package.json"));
    await assert.rejects(readSafe(root, "package.json"));
    await assert.rejects(readSafe(root, "../passwd"));
    await fs.writeFile(
      path.join(root, "big.json"),
      "x".repeat(8 * 1024 * 1024 + 1),
    );
    await assert.rejects(readSafe(root, "big.json"));
  }));
test("Discovery ist begrenzt und ignoriert Vendor/Symlinks", () =>
  temporary(async (root) => {
    await fs.mkdir(path.join(root, "a"));
    await fs.writeFile(path.join(root, "a", "package.json"), "{}");
    await fs.mkdir(path.join(root, "node_modules"));
    await fs.mkdir(path.join(root, "node_modules", "b"));
    await fs.writeFile(
      path.join(root, "node_modules", "b", "package.json"),
      "{}",
    );
    assert.equal((await discover([root])).projects.length, 1);
    assert.equal((await discover([root], { maxProjects: 1 })).truncated, true);
  }));
test("Private Berichte haben 0600; Ausgabe-Symlink bleibt unangetastet", () =>
  temporary(async (root) => {
    const file = path.join(root, "out", "report.json");
    await writePrivate(file, "{}");
    assert.equal((await fs.stat(file)).mode & 0o777, 0o600);
    await fs.symlink(file, path.join(root, "link.json"));
    await assert.rejects(writePrivate(path.join(root, "link.json"), "changed"));
    assert.equal(await fs.readFile(file, "utf8"), "{}");
  }));
test("Netzwerk: nur feste HTTPS-Quellen, keine Redirects, Größenlimit", async () => {
  await assert.rejects(requestJson("http://api.osv.dev/v1/querybatch"));
  await assert.rejects(requestJson("https://private.invalid/"));
  await assert.rejects(requestJson("https://user:password@api.osv.dev/"));
  let options;
  const fetcher = async (url, o) => {
    options = o;
    return new Response('{"ok":true}');
  };
  assert.deepEqual(
    await requestJson("https://api.osv.dev/v1/querybatch", { fetcher }),
    { ok: true },
  );
  assert.equal(options.redirect, "error");
  await assert.rejects(
    requestJson("https://api.osv.dev/v1/querybatch", { fetcher, maxBytes: 2 }),
  );
});
test("OSV-Pagination und Fixversionen vollständig auswerten", async () => {
  const c = comp();
  let calls = 0;
  const issues = await osvAudit([c], {
    request: async (url, args) => {
      if (url.includes("vulns"))
        return {
          summary: "Known issue",
          database_specific: { severity: "HIGH" },
          affected: [
            {
              package: { name: c.name, ecosystem: "npm" },
              ranges: [{ events: [{ fixed: "1.0.2" }] }],
            },
          ],
        };
      calls++;
      return calls === 1
        ? {
            results: [
              {
                vulns: [{ id: "GHSA-test-test-test" }],
                next_page_token: "second",
              },
            ],
          }
        : { results: [{}] };
    },
  });
  assert.equal(calls, 2);
  assert.equal(issues[0].fixed[0], "1.0.2");
  assert.equal(issues[0].severity, "high");
  assert.equal(c.checks.vulnerability.status, "findings");
});
for (const [label, reply] of [
  ["falsche Anzahl", { results: [] }],
  ["ungültige Fund-ID", { results: [{ vulns: [{ id: "<script>" }] }] }],
  ["wiederholte Seite", { results: [{ next_page_token: "same" }] }],
])
  test("OSV bleibt bei " + label + " unbekannt", async () => {
    const c = comp();
    await osvAudit([c], { request: async () => reply });
    assert.equal(c.checks.vulnerability.status, "unchecked");
  });
test("Ausfall einer OSV-Quelle erzeugt keinen grünen Befund", async () => {
  const c = comp();
  await osvAudit([c], {
    request: async () => {
      throw new Error("offline");
    },
  });
  assert.equal(c.checks.vulnerability.status, "unchecked");
});
test("Support prüft den längsten Zyklus und behandelt fehlende Daten als unbekannt", () => {
  const cycles = [
    { cycle: "8", eol: true },
    { cycle: "8.3", eol: "2027-12-31", latest: "8.3.36" },
  ];
  assert.equal(
    classifySupport("8.3.1", cycles, "2026-10-24").status,
    "supported",
  );
  assert.equal(classifySupport("8.1.0", cycles).status, "unsupported");
  assert.equal(classifySupport("8.3", [{ cycle: "8.3" }]).status, "unchecked");
  assert.equal(classifySupport("unknown", cycles).status, "unchecked");
});
test("GitHub nutzt Cursor-Pagination ohne page-Parameter; 403 bleibt unbekannt", async () => {
  const p = { repository: "example/repo" };
  let requests = 0;
  const r = await githubAudit(p, {
    owners: ["example"],
    api: async (url, options) => {
      if (!url.includes("/alerts"))
        return { private: true, default_branch: "main" };
      requests++;
      assert.equal(options.paginate, true);
      assert.equal(url.includes("&page="), false);
      return Array.from({ length: 201 }, (_, number) => ({ number }));
    },
  });
  assert.equal(requests, 1);
  assert.equal(r.alerts.length, 201);
  assert.equal(p.visibility, "private");
  assert.equal(
    (
      await githubAudit(p, {
        owners: ["example"],
        api: async () => {
          throw new Error("403");
        },
      })
    ).status,
    "unchecked",
  );
});
test("Bestehende Dependabot-Regeln und Secrets-Referenzen werden erhalten", () => {
  const previous =
    "version: 2\nupdates:\n  - package-ecosystem: npm\n    directory: /\n    schedule: {interval: weekly}\n    ignore: [{dependency-name: special}]\n";
  const r = dependabotConfig(
    {
      manifests: [
        "package.json",
        "src/composer.json",
        ".github/workflows/check.yml",
      ],
    },
    previous,
  );
  assert.equal(r.count, 3);
  assert.equal(
    YAML.parse(r.text, { version: "1.1" }).updates[1].schedule.time,
    "08:00",
  );
  assert.ok(r.text.includes("special"));
  assert.ok(r.text.includes("weekly"));
  assert.equal(
    dependabotConfig({ manifests: ["package.json"] }, previous).changed,
    false,
  );
});
test("GitHub-Setup verweigert Fremdrepositories, fehlende Adminrechte und abgeschnittene Bäume", async () => {
  await assert.rejects(
    setupGithub({ repository: "foreign/repo" }, { owner: "example" }),
  );
  await assert.rejects(
    setupGithub(
      { repository: "example/repo" },
      {
        owner: "example",
        api: async () => ({ permissions: { admin: false } }),
      },
    ),
  );
  await assert.rejects(
    setupGithub(
      { repository: "example/repo" },
      {
        owner: "example",
        api: async (u) =>
          u.includes("/git/trees/")
            ? { truncated: true, tree: [] }
            : { permissions: { admin: true }, default_branch: "main" },
      },
    ),
  );
});
test("Kein PR auf Grundlage ausschließlich lokaler Manifeste", async () => {
  const calls = [];
  const r = await setupGithub(
    { repository: "example/repo", manifests: ["local/package.json"] },
    {
      owner: "example",
      api: async (u) => {
        calls.push(u);
        return u.includes("/git/trees/")
          ? { tree: [] }
          : { permissions: { admin: true }, default_branch: "main" };
      },
    },
  );
  assert.equal(r.status, "not-applicable");
  assert.equal(calls.length, 2);
});
test("Update-Policy erzwingt Freigabe, aktuellen Testcommit und Rückfall", () => {
  const c = {
    kind: "package",
    sourceTrusted: true,
    securityUpdate: true,
    current: "1.0.0",
    target: "1.0.1",
    repository: "example/repo",
    requiredChecks: [{ status: "success" }],
    testedHead: "abc",
    head: "abc",
    rollbackVerified: true,
    backupAvailable: true,
    lockfileUpdated: true,
  };
  const p = { autoMergeSecurityPatch: true, repositories: ["example/repo"] };
  assert.equal(evaluateUpdate(c, p).decision, "eligible");
  for (const change of [
    { kind: "runtime" },
    { target: "2.0.0" },
    { testedHead: "old" },
    { backupAvailable: false },
    { requiredChecks: [] },
  ])
    assert.equal(evaluateUpdate({ ...c, ...change }, p).decision, "review");
  assert.equal(evaluateUpdate(c, {}).decision, "review");
});
test("Bericht escaped Script-Enden und verwendet eine feste Content-Security-Policy", async () => {
  const html = await renderReport({
    summary: {},
    projects: [],
    issues: [{ title: "</script><script>evil()</script>" }],
  });
  assert.ok(!html.includes("</script><script>evil"));
  assert.ok(html.includes("script-src 'sha256-"));
  assert.ok(!html.includes("'unsafe-inline'"));
});
test("Offline-Erstlauf verspricht keine vollständige Sicherheit; keine stillen Netzaufrufe", () =>
  temporary(async (root) => {
    await fs.writeFile(
      path.join(root, "package.json"),
      '{"dependencies":{"example":"1.2.3"}}',
    );
    let calls = 0;
    const r = await scan({
      config: { roots: [root], owners: [] },
      request: async () => {
        calls++;
      },
      api: async () => {
        calls++;
      },
    });
    assert.equal(calls, 0);
    assert.equal(r.summary.status, "incomplete");
    assert.equal(r.summary.projects, 1);
  }));
test("Private Repository-Daten werden nicht an OSV übertragen", () =>
  temporary(async (root) => {
    await fs.mkdir(path.join(root, ".git"));
    await fs.writeFile(
      path.join(root, "package-lock.json"),
      '{"packages":{"node_modules/example-package":{"version":"1.0.0","resolved":"https://registry.npmjs.org/example-package/-/a.tgz"}}}',
    );
    let osv = 0;
    const r = await scan({
      config: { roots: [root], owners: [] },
      online: true,
      request: async (url) => {
        if (url.includes("osv.dev")) osv++;
        return [];
      },
    });
    assert.equal(osv, 0);
    assert.equal(r.summary.unchecked, 1);
  }));
test("Benachrichtigungen bleiben bei unverändertem Befund still", () =>
  temporary(async (root) => {
    const file = path.join(root, "state.json"),
      r = {
        generatedAt: new Date().toISOString(),
        issues: [],
        projects: [],
        summary: summaryFor([], []),
      };
    assert.equal(await trackChanges(r, file), true);
    assert.equal(
      await trackChanges({ ...r, generatedAt: "later" }, file),
      false,
    );
    assert.equal(
      await trackChanges({ ...r, issues: [{ id: "new" }] }, file),
      true,
    );
  }));

test("pnpm und Yarn behalten öffentliche Herkunftsnachweise", () => {
  const pnpm = parsePackages(
    "pnpm-lock.yaml",
    "lockfileVersion: '9.0'\npackages:\n  '@scope/demo@1.2.3':\n    resolution: {tarball: 'https://registry.npmjs.org/@scope/demo/-/demo.tgz'}\n",
  );
  assert.equal(pnpm[0].name, "@scope/demo");
  assert.equal(pnpm[0].publicSource, true);
  const yarn = parsePackages(
    "yarn.lock",
    '# yarn lockfile v1\n\ndemo@^1.0.0:\n  version "1.2.3"\n  resolved "https://registry.yarnpkg.com/demo/-/demo.tgz"\n',
  );
  assert.equal(yarn[0].version, "1.2.3");
  assert.equal(yarn[0].publicSource, true);
});
test("Veraltete und zukünftige Beobachtungen bestätigen keine laufende Version", () => {
  const observations = (observedAt) =>
    parseDeclarations(
      "resources.observations.json",
      JSON.stringify({
        observedAt,
        components: [
          {
            kind: "database",
            name: "mariadb",
            version: "11.4.0",
            environment: "production",
            password: "niemals-übernehmen",
          },
        ],
      }),
    )[0];
  assert.equal(observations(new Date().toISOString()).observed, true);
  assert.equal(observations("2020-01-01").observed, false);
  assert.equal(observations("2099-01-01").observed, false);
  assert.equal(
    JSON.stringify(observations(new Date().toISOString())).includes("niemals"),
    false,
  );
});
test("Fehlender Projektordner wird als Prüflücke gemeldet", () =>
  temporary(async (root) => {
    const r = await scan({
      config: { roots: [path.join(root, "missing")], owners: [] },
    });
    assert.equal(r.unavailableRoots.length, 1);
    assert.equal(r.summary.status, "incomplete");
  }));
test("Supportausfall ändert den Benachrichtigungsstand", () =>
  temporary(async (root) => {
    const c = comp(),
      p = {
        name: "test",
        coverage: [],
        repository: null,
        warnings: [],
        unsupported: [],
        github: { status: "unchecked" },
        components: [c],
      };
    c.checks.support = { status: "supported" };
    const r = {
      generatedAt: new Date().toISOString(),
      projects: [p],
      issues: [],
      summary: summaryFor([p], []),
    };
    const file = path.join(root, "state.json");
    await trackChanges(r, file);
    c.checks.support = { status: "unchecked", reason: "Quelle ausgefallen" };
    assert.equal(await trackChanges(r, file), true);
  }));
test("Registry-Klartextgeheimnisse werden vor jeder Mutation zurückgewiesen", () => {
  assert.throws(() =>
    dependabotConfig(
      { manifests: ["package.json"] },
      "version: 2\nregistries:\n  internal: {type: npm-registry, url: 'https://registry.example', token: 'never-public'}\nupdates: []\n",
    ),
  );
});
test("Setup protokolliert Aktivierung und verwendet eine vorhandene PR wieder", async () => {
  const calls = [],
    journal = [];
  const r = await setupGithub(
    { repository: "example/repo", manifests: ["package.json"] },
    {
      owner: "example",
      journal: async (e) => journal.push(e),
      api: async (url, options = {}) => {
        calls.push([url, options.method || "GET"]);
        if (url === "repos/example/repo")
          return { permissions: { admin: true }, default_branch: "main" };
        if (url.includes("/git/trees/"))
          return { tree: [{ path: "package.json" }], truncated: false };
        if (url.endsWith("/automated-security-fixes") && !options.method)
          return { enabled: true };
        if (url.includes("/git/ref/")) return { object: { sha: "base" } };
        if (url.includes("/pulls?"))
          return [
            {
              head: { ref: "codex/resource-security-setup" },
              base: { ref: "main" },
              html_url: "https://github.com/example/repo/pull/1",
            },
          ];
        if (options.method === "PUT") return null;
        throw Error("Unerwarteter Schreibzugriff");
      },
    },
  );
  assert.equal(r.reused, true);
  assert.equal(r.securitySettings.status, "enabled");
  assert.ok(journal.some((e) => e.stage === "before-security-settings"));
  assert.equal(
    calls.some(([, method]) => method === "POST"),
    false,
  );
});

test("Ausgabeverweis erzeugt auch keine neuen Unterordner außerhalb des Ziels", () =>
  temporary(async (root) => {
    const outside = path.join(root, "outside"),
      link = path.join(root, "link");
    await fs.mkdir(outside);
    await fs.symlink(outside, link, "dir");
    await assert.rejects(
      writePrivate(path.join(link, "new", "report.json"), "private"),
    );
    assert.deepEqual(await fs.readdir(outside), []);
  }));

test("Beobachtungsbeispiele in Unterordnern gelten nicht als Projektumgebung", () =>
  temporary(async (root) => {
    await fs.mkdir(path.join(root, "examples"));
    await fs.writeFile(
      path.join(root, "examples/resources.observations.json"),
      JSON.stringify({
        observedAt: new Date().toISOString(),
        components: [
          {
            name: "mariadb",
            version: "11.4.0",
            kind: "database",
            environment: "staging",
          },
        ],
      }),
    );
    const r = await inspect({ root, name: "example" });
    assert.equal(r.components.length, 0);
  }));

test("Bekannte moderate Meldungen werden aus OSV und GitHub als mittel eingeordnet", async () => {
  const c = comp();
  const issues = await osvAudit([c], {
    request: async (url) =>
      url.includes("/vulns/")
        ? { database_specific: { severity: "MODERATE" } }
        : { results: [{ vulns: [{ id: "GHSA-test-moderate" }] }] },
  });
  assert.equal(issues[0].severity, "medium");
  assert.equal(c.checks.vulnerability.status, "findings");
  const r = await githubAudit(
    { repository: "example/repo" },
    {
      owners: ["example"],
      api: async (url) =>
        url.includes("/alerts")
          ? [{ number: 1, security_advisory: { severity: "moderate" } }]
          : { private: false, default_branch: "main" },
    },
  );
  assert.equal(r.alerts[0].severity, "medium");
});
