import test from "node:test";
import assert from "node:assert/strict";
import { requiredLegalPages, validateMandatoryFeatures } from "../src/lib/mandatory-rules.js";

function completeProfile() {
  return {
    project: { type: "general" },
    features: {},
    versioning: {
      current: "0.0.1",
      status: "pre-alpha",
      display: { enabled: true, locations: ["login", "settings"] },
      release_notes: { enabled: true }
    },
    cms: {
      editor: "tinymce",
      editor_delivery: "local",
      revisions: true,
      import_export: true,
      legal_pages: ["contact", "imprint", "privacy", "cookies", "cookie_banner", "accessibility", "ai_philosophy", "credits"]
    },
    credits: { enabled: true, people: true, components: true, local_assets_only: true },
    settings: { searchable: true, filterable: true },
    appearance: { modes: ["light", "dark"], toggle: { enabled: true, locations: ["header"] } },
    briefing: { completed: true }
  };
}

test("a complete profile has no mandatory findings", () => {
  const result = validateMandatoryFeatures(completeProfile());
  assert.deepEqual(result, { errors: [], warnings: [] });
});

test("missing mandatory sections only warn so older profiles stay valid", () => {
  const result = validateMandatoryFeatures({ project: { type: "general" } });
  assert.equal(result.errors.length, 0);
  for (const topic of ["versioning", "credits", "cms", "settings", "appearance", "briefing"]) {
    assert.ok(result.warnings.some((item) => item.includes(topic)), "expected warning for " + topic);
  }
});

test("disabling release notes or credits is an error", () => {
  const profile = completeProfile();
  profile.versioning.release_notes.enabled = false;
  profile.credits.enabled = false;
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.errors.some((item) => item.includes("Release notes")));
  assert.ok(result.errors.some((item) => item.includes("credits page")));
});

test("light and dark mode are both required", () => {
  const profile = completeProfile();
  profile.appearance.modes = ["light"];
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.errors.some((item) => item.includes("light and dark")));
});

test("settings must stay searchable and filterable", () => {
  const profile = completeProfile();
  profile.settings = { searchable: false, filterable: false };
  const result = validateMandatoryFeatures(profile);
  assert.equal(result.errors.filter((item) => item.includes("settings")).length, 2);
});

test("CMS pages require revisions and import/export", () => {
  const profile = completeProfile();
  profile.cms.revisions = false;
  profile.cms.import_export = false;
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.errors.some((item) => item.includes("revisions")));
  assert.ok(result.errors.some((item) => item.includes("export and import")));
});

test("CDN-delivered editors produce a privacy warning", () => {
  const profile = completeProfile();
  profile.cms.editor_delivery = "cdn";
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.warnings.some((item) => item.includes("CDN")));
});

test("shops need commerce legal pages and games need youth protection", () => {
  assert.ok(requiredLegalPages({ features: { store: true } }).includes("withdrawal_button"));
  assert.ok(requiredLegalPages({ project: { type: "game" } }).includes("youth_protection"));
  assert.ok(!requiredLegalPages({ project: { type: "internal" } }).includes("terms"));

  const profile = completeProfile();
  profile.features.billing = true;
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.warnings.some((item) => item.includes("Missing legal/CMS pages") && item.includes("terms")));
});

test("PHP code editors need an extra config flag", () => {
  const profile = completeProfile();
  profile.code_editors = { enabled: true, languages: ["css", "php"], php_requires_config_flag: false };
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.errors.some((item) => item.includes("PHP code editing")));
});

test("version display should include login and settings", () => {
  const profile = completeProfile();
  profile.versioning.display.locations = ["public_footer"];
  const result = validateMandatoryFeatures(profile);
  assert.ok(result.warnings.some((item) => item.includes("login and settings")));
});
