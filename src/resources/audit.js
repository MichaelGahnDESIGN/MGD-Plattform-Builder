import { requestJson, pool } from "./network.js";

export async function osvAudit(components, { request = requestJson } = {}) {
  const issues = [],
    pending = components.map((c, i) => ({
      c,
      i,
      token: undefined,
      seen: new Set(),
    }));
  for (const p of pending)
    p.c.checks.vulnerability = { status: "checking", source: "OSV" };
  try {
    let round = 0;
    while (pending.length) {
      if (++round > 20) throw new Error("OSV-Pagination nicht vollständig");
      const current = pending.splice(0);
      for (let start = 0; start < current.length; start += 100) {
        const batch = current.slice(start, start + 100);
        const answer = await request("https://api.osv.dev/v1/querybatch", {
          body: {
            queries: batch.map((p) => ({
              package: { name: p.c.name, ecosystem: p.c.ecosystem },
              version: p.c.version,
              ...(p.token ? { page_token: p.token } : {}),
            })),
          },
        });
        if (
          !Array.isArray(answer.results) ||
          answer.results.length !== batch.length
        )
          throw new Error("Unvollständige OSV-Antwort");
        for (let n = 0; n < batch.length; n++) {
          const p = batch[n],
            result = answer.results[n];
          if (
            !result ||
            typeof result !== "object" ||
            (result.vulns !== undefined && !Array.isArray(result.vulns))
          )
            throw new Error("Ungültige OSV-Antwort");
          for (const v of result.vulns || []) {
            if (
              typeof v.id !== "string" ||
              !/^[A-Za-z0-9_.-]{1,120}$/.test(v.id)
            )
              throw new Error("Ungültiger Fund");
            issues.push({
              component: p.c,
              id: v.id,
              source: "OSV",
              severity: "unknown",
              title: v.id,
              url: "https://osv.dev/vulnerability/" + encodeURIComponent(v.id),
              fixed: [],
            });
          }
          if (result.next_page_token) {
            if (
              typeof result.next_page_token !== "string" ||
              p.seen.has(result.next_page_token)
            )
              throw new Error("OSV-Pagination wiederholt sich");
            p.seen.add(result.next_page_token);
            p.token = result.next_page_token;
            pending.push(p);
          }
        }
      }
    }
    const ids = [...new Set(issues.map((i) => i.id))];
    if (ids.length > 600)
      throw new Error("Zu viele Detailfunde für einen vollständigen Lauf");
    const details = new Map(
      await pool(ids, async (id) => {
        try {
          return [id, await request("https://api.osv.dev/v1/vulns/" + id)];
        } catch {
          return [id, null];
        }
      }),
    );
    for (const issue of issues) {
      const detail = details.get(issue.id);
      if (detail?.withdrawn) {
        issue.withdrawn = true;
        continue;
      }
      issue.title =
        typeof detail?.summary === "string"
          ? detail.summary.slice(0, 500)
          : issue.id;
      const rawSeverity = String(
        detail?.database_specific?.severity || "",
      ).toLowerCase();
      const severity = rawSeverity === "moderate" ? "medium" : rawSeverity;
      if (["critical", "high", "medium", "low"].includes(severity))
        issue.severity = severity;
      for (const affected of detail?.affected || [])
        if (
          affected.package?.name === issue.component.name &&
          affected.package.ecosystem === issue.component.ecosystem
        )
          for (const range of affected.ranges || [])
            for (const event of range.events || [])
              if (typeof event.fixed === "string" && event.fixed.length < 100)
                issue.fixed.push(event.fixed);
      issue.detailComplete = Boolean(detail);
    }
    const active = issues.filter((i) => !i.withdrawn);
    for (const c of components)
      c.checks.vulnerability = {
        status: active.some((i) => i.component === c)
          ? "findings"
          : "no-known-findings",
        source: "OSV",
        checkedAt: new Date().toISOString(),
      };
    return active;
  } catch {
    // Ein Teilabbruch darf aus fehlenden Antworten keinen unauffälligen Befund machen.
    for (const c of components)
      c.checks.vulnerability = {
        status: "unchecked",
        source: "OSV",
        reason: "Abfrage fehlgeschlagen oder unvollständig",
      };
    return issues.filter((i) => !i.withdrawn);
  }
}

export function supportProduct(c) {
  if (c.supportProduct) return c.supportProduct;
  const known = new Set([
    "php",
    "nodejs",
    "python",
    "ruby",
    "java",
    "wordpress",
    "shopware",
    "mysql",
    "mariadb",
    "postgresql",
    "nginx",
    "apache",
    "openssl",
    "ubuntu",
    "debian",
    "alpine-linux",
    "dotnet",
    "go",
    "rust",
    "redis",
    "macos",
  ]);
  const aliases = {
    node: "nodejs",
    postgres: "postgresql",
    alpine: "alpine-linux",
    httpd: "apache",
    mcr: "dotnet",
    "shopware/core": "shopware",
    "symfony/symfony": "symfony",
  };
  let name = c.name.toLowerCase().replace(/@\d+(?:\.\d+)*$/, "");
  if (c.kind === "container") {
    const image = name.split("@")[0];
    const [n, v] = image.split(":");
    name = n.split("/").at(-1);
    return known.has(aliases[name] || name) && v
      ? {
          product: aliases[name] || name,
          version: v.split("-")[0],
          observed: false,
        }
      : null;
  }
  name = aliases[name] || name;
  const version = c.version.replace(/_\d+$/, "");
  return known.has(name) && /^v?\d+(?:\.\d+)*$/.test(version)
    ? {
        product: name,
        version: version.replace(/^v/, ""),
        observed: c.observed === true,
      }
    : null;
}
export function classifySupport(
  version,
  cycles,
  today = new Date().toISOString().slice(0, 10),
) {
  const cycle = cycles
    .filter(
      (c) =>
        typeof c.cycle === "string" &&
        (version === c.cycle || version.startsWith(c.cycle + ".")),
    )
    .sort((a, b) => b.cycle.length - a.cycle.length)[0];
  if (!cycle)
    return { status: "unchecked", reason: "Supportzyklus nicht zugeordnet" };
  if (
    typeof cycle.eol !== "boolean" &&
    !(typeof cycle.eol === "string" && /^\d{4}-\d{2}-\d{2}$/.test(cycle.eol))
  )
    return { status: "unchecked", reason: "Supportende nicht bestätigt" };
  const ended =
    cycle.eol === true ||
    (typeof cycle.eol === "string" &&
      /^\d{4}-\d{2}-\d{2}$/.test(cycle.eol) &&
      cycle.eol < today);
  return {
    status: ended ? "unsupported" : "supported",
    cycle: cycle.cycle,
    eol: cycle.eol,
    latest: typeof cycle.latest === "string" ? cycle.latest : undefined,
    source: "endoflife.date",
    sourceUrl: "https://endoflife.date/" + (cycle.product || ""),
    checkedAt: new Date().toISOString(),
  };
}
export async function supportAudit(components, { request = requestJson } = {}) {
  const products = new Map();
  for (const c of components) {
    const target = supportProduct(c);
    if (!target) continue;
    const key =
      typeof target === "string"
        ? { product: target, version: c.version, observed: c.observed === true }
        : target;
    if (!products.has(key.product)) products.set(key.product, []);
    products.get(key.product).push({ c, ...key });
  }
  await pool([...products], async ([product, entries]) => {
    try {
      const data = await request(
        "https://endoflife.date/api/" + product + ".json",
      );
      if (!Array.isArray(data) || !data.length)
        throw new Error("Fehlende Supportdaten");
      for (const { c, version, observed } of entries)
        c.checks.support = {
          ...classifySupport(
            version,
            data.map((item) => ({ ...item, product })),
          ),
          observed,
        };
    } catch {
      for (const { c } of entries)
        c.checks.support = {
          status: "unchecked",
          reason: "Supportquelle nicht verfügbar",
          source: "endoflife.date",
        };
    }
  });
}
