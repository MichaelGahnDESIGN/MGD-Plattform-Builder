import test from "node:test";
import assert from "node:assert/strict";
import { evaluateBriefing, loadBriefing, readPath, summarizeBriefing } from "../src/lib/briefing.js";
import { loadRecommendationRegistry, matchReasons, recommend } from "../src/lib/recommendations.js";

test("reads nested profile paths", () => {
  assert.equal(readPath({ a: { b: { c: 1 } } }, "a.b.c"), 1);
  assert.equal(readPath({ a: null }, "a.b"), undefined);
});

test("briefing asks for editor delivery, updater, loading screen, SEO and code editors", async () => {
  const briefing = await loadBriefing();
  const paths = briefing.sections.flatMap((section) => section.questions.map((question) => question.path));
  for (const expected of [
    "cms.editor",
    "cms.editor_delivery",
    "versioning.display.locations",
    "experience.updater",
    "experience.loading_screen",
    "experience.seo",
    "code_editors.enabled",
    "appearance.toggle.locations",
    "template.databases.private"
  ]) {
    assert.ok(paths.includes(expected), "briefing misses " + expected);
  }
});

test("an empty profile leaves mandatory questions open", async () => {
  const summary = summarizeBriefing(evaluateBriefing(await loadBriefing(), {}));
  assert.equal(summary.answered, 0);
  assert.ok(summary.openMandatory.length > 10);
});

test("answered values and false booleans count as answered", async () => {
  const sections = evaluateBriefing(await loadBriefing(), { experience: { updater: false }, cms: { legal_pages: [] } });
  const questions = sections.flatMap((section) => section.questions);
  assert.equal(questions.find((q) => q.path === "experience.updater").answered, true);
  assert.equal(questions.find((q) => q.path === "cms.legal_pages").answered, false);
});

test("MGD-DevOS is recommended for agent-driven multi-project work", async () => {
  const registry = await loadRecommendationRegistry();
  const devos = registry.recommendations.find((entry) => entry.id === "mgd-devos");
  assert.ok(devos);
  assert.ok(matchReasons(devos, { features: { ai_agents: true } }).length > 0);
  assert.equal(matchReasons(devos, { features: {} }).length, 0);
});

test("recommendations match description keywords and mark accepted entries", async () => {
  const results = recommend(await loadRecommendationRegistry(), {
    project: { type: "general", description: "Landingpage auf WordPress" },
    briefing: { accepted_recommendations: ["mgd-wordpress-mcp"] }
  });
  const wordpress = results.find((entry) => entry.id === "mgd-wordpress-mcp");
  assert.ok(wordpress && wordpress.accepted);
  assert.ok(results.some((entry) => entry.id === "mgd-dev-skill"));
});
