(() => {
  "use strict";
  const report = JSON.parse(document.querySelector("#data").textContent);
  const $ = (selector) => document.querySelector(selector);
  const names = {
    package: "Paket",
    declaration: "Bereichsangabe",
    runtime: "Laufzeit",
    cms: "CMS",
    plugin: "Plugin",
    theme: "Theme",
    container: "Container",
    ci: "Build/CI",
    os: "Betriebssystem",
    database: "Datenbank",
    webserver: "Webserver",
    external: "Externer Dienst",
  };
  const text = (tag, value, className) => {
    const e = document.createElement(tag);
    e.textContent = String(value ?? "");
    if (className) e.className = className;
    return e;
  };
  const summary = report.summary;
  $("#date").textContent = new Date(report.generatedAt).toLocaleString("de-DE");
  $("#overall").textContent =
    summary.status === "findings"
      ? "Handlungsbedarf"
      : summary.status === "incomplete"
        ? "Prüfung unvollständig"
        : "Keine bekannten Funde";
  $("#overall").classList.add(summary.status === "findings" ? "error" : "warn");
  for (const [value, label] of [
    [summary.projects, "Projekte"],
    [summary.components, "Komponenten"],
    [summary.findings, "Sicherheits-/Supportfunde"],
    [summary.unsupported, "Ohne Support"],
    [summary.checked || 0, "Komponenten geprüft"],
    [summary.unchecked, "Echte Prüflücken"],
    [summary.declared || 0, "Versionsanforderungen"],
    [summary.codeReview || 0, "Codeprüfung offen"],
  ]) {
    const e = text("div", "", "stat");
    e.append(text("strong", value), text("span", label));
    $("#stats").append(e);
  }
  for (const p of report.projects) {
    const option = text("option", p.name);
    option.value = p.name;
    $("#project").append(option);
  }
  function render() {
    const mode = $("#view").value,
      project = $("#project").value,
      query = $("#search").value.toLocaleLowerCase("de");
    const all =
      mode === "issues"
        ? report.issues
        : mode === "components"
          ? report.projects.flatMap((p) =>
              p.components.map((c) => ({ ...c, project: p.name })),
            )
          : [
              ...(report.unavailableRoots || []).map((root) => ({
                name: "Projektordner nicht erreichbar",
                title: root,
                status: "Nicht geprüft",
              })),
              ...report.projects.flatMap((p) => [
                ...p.coverage.map((c) => ({
                  ...c,
                  project: p.name,
                  title:
                    (names[c.kind] || c.kind) + ": " + c.count + " erfasst",
                  name: p.name,
                  status:
                    c.status === "not-observed"
                      ? "Nicht beobachtet"
                      : "Inventar vorhanden",
                })),
                ...p.warnings.map((w) => ({
                  name: p.name,
                  title: w,
                  project: p.name,
                  status: "Nicht geprüft",
                })),
                ...p.unsupported.map((w) => ({
                  name: p.name,
                  title: "Zusätzlicher Scanner nötig: " + w,
                  project: p.name,
                  status: "Nicht geprüft",
                })),
                {
                  name: p.name,
                  project: p.name,
                  title: "GitHub-Sicherheitsmeldungen",
                  status: p.github.status,
                  reason: p.github.reason,
                },
              ]),
            ];
    const filtered = all.filter(
      (row) =>
        (!project || row.project === project) &&
        (!query ||
          JSON.stringify([
            row.name,
            row.title,
            row.project,
            row.id,
            row.version,
          ])
            .toLocaleLowerCase("de")
            .includes(query)),
    );
    $("#rows").replaceChildren();
    $("#count").textContent =
      filtered.length +
      " Ergebnisse" +
      (filtered.length > 200
        ? " · erste 200 angezeigt, bitte Suche oder Projektfilter verwenden"
        : "");
    for (const row of filtered.slice(0, 200)) {
      const item = text("article", "", "row"),
        copy = text("div", "");
      copy.append(
        text("h3", row.name || row.title || row.id),
        text(
          "p",
          row.title && row.title !== row.name
            ? row.title
            : [
                names[row.kind] || row.ecosystem,
                row.version || "Version nicht bestätigt",
              ]
                .filter(Boolean)
                .join(" · "),
        ),
        text(
          "p",
          [
            row.project,
            row.file,
            row.observed ? "Beobachtung" : "Quellcode / Quelle",
          ]
            .filter(Boolean)
            .join(" · "),
          "meta",
        ),
      );
      const state =
        mode === "issues"
          ? (row.severity || "unknown").toUpperCase()
          : mode === "components"
            ? row.checks.vulnerability.status === "no-known-findings"
              ? "Keine bekannten OSV-Funde"
              : row.checks.vulnerability.status === "findings"
                ? "Sicherheitsfunde"
                : ({declared:"Versionsanforderung", "not-applicable":"Andere Prüfart nötig"}[row.checks.vulnerability.status] || "Sicherheit nicht geprüft")
            : row.status;
      item.append(
        copy,
        text("span", state, "badge " + (mode === "issues" ? "error" : "warn")),
      );
      if (mode === "components") {
        if (row.checks.vulnerability.reason) item.append(text("p",row.checks.vulnerability.reason,"detail"));
        if (row.dependencyGroup === "development") item.append(text("p","Entwicklungs-/Testabhängigkeit","detail"));
        item.append(
          text(
            "p",
            "Support: " +
              ({
                supported: "Supportzyklus geprüft",
                unsupported: "Unterstützung beendet",
                unchecked: "Nicht geprüft",
                declared: "Versionsanforderung",
                "not-applicable": "Kein allgemeiner Supportzyklus",
              }[row.checks.support.status] || "Nicht geprüft") +
              (row.checks.support.latest
                ? " · letzter Stand des Zyklus: " + row.checks.support.latest
                : ""),
            "detail",
          ),
        );
      }
      if (row.fixed?.length)
        item.append(
          text(
            "p",
            "Laut Quelle: " +
              row.fixed.join(", ") +
              " · vor Installation kompatible Zielversion und betroffenen Versionsbereich prüfen.",
            "detail",
          ),
        );
      if (row.reason) item.append(text("p", row.reason, "detail"));
      if (row.url) {
        try {
          const u = new URL(row.url);
          if (
            u.protocol === "https:" &&
            ["github.com", "osv.dev", "endoflife.date"].includes(u.hostname) &&
            !u.username &&
            !u.password
          ) {
            const a = text("a", "Quelle öffnen ↗", "detail");
            a.href = u.href;
            a.target = "_blank";
            a.rel = "noopener noreferrer";
            a.setAttribute(
              "aria-label",
              "Quelle zu " + (row.name || row.id) + " öffnen (neuer Tab)",
            );
            item.append(a);
          }
        } catch {}
      }
      $("#rows").append(item);
    }
    if (!filtered.length)
      $("#rows").append(
        text(
          "p",
          mode === "issues"
            ? "Keine passenden Meldungen. Prüfabdeckung beachten: fehlende Funde sind kein Nachweis vollständiger Sicherheit."
            : "Keine passenden Einträge. Suche zurücknehmen oder anderes Projekt auswählen.",
          "empty",
        ),
      );
  }
  for (const id of ["#search", "#project", "#view"])
    $(id).addEventListener("input", render);
  $("#theme").addEventListener("change", () => {
    const value = $("#theme").value;
    if (value === "system") delete document.documentElement.dataset.theme;
    else document.documentElement.dataset.theme = value;
  });
  render();
})();
