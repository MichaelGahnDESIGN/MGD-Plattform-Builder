/**
 * Rules for features every MGD project must provide: versioning, release notes,
 * credits, editable legal pages, searchable settings and light/dark mode.
 * Missing sections produce warnings (older profiles stay valid); sections that
 * explicitly disable a mandatory feature produce errors.
 */

const BRIEFING_HINT = " (run `mgd-platform briefing` to clarify it)";

const BASE_LEGAL_PAGES = ["contact", "imprint", "privacy", "cookies", "cookie_banner", "accessibility", "ai_philosophy", "credits"];
const COMMERCE_LEGAL_PAGES = ["terms", "payment", "shipping", "withdrawal", "withdrawal_button"];
const MINOR_PROTECTION_TYPES = new Set(["game", "community", "creator"]);

export function requiredLegalPages(profile) {
  const features = profile.features || {};
  const pages = [...BASE_LEGAL_PAGES];
  if (features.billing || features.store) pages.push(...COMMERCE_LEGAL_PAGES);
  if (profile.project && MINOR_PROTECTION_TYPES.has(profile.project.type)) pages.push("youth_protection");
  return pages;
}

function checkVersioning(profile, result) {
  const versioning = profile.versioning;
  if (!versioning) {
    result.warnings.push("versioning is mandatory but not declared" + BRIEFING_HINT);
    return;
  }

  const display = versioning.display || {};
  const locations = display.locations || [];
  if (display.enabled === false) {
    result.warnings.push("Version display is disabled; the version should at least be visible on login and in settings");
  } else if (!locations.includes("login") || !(locations.includes("settings") || locations.includes("game_settings"))) {
    result.warnings.push("versioning.display.locations should include login and settings/game_settings");
  }

  const releaseNotes = versioning.release_notes;
  if (!releaseNotes) {
    result.warnings.push("versioning.release_notes is not declared; a release-notes timeline is mandatory");
  } else if (releaseNotes.enabled === false) {
    result.errors.push("Release notes are mandatory and must not be disabled");
  }
}

function checkCredits(profile, result) {
  const credits = profile.credits;
  if (!credits) {
    result.warnings.push("credits page is mandatory but not declared" + BRIEFING_HINT);
    return;
  }
  if (credits.enabled === false) result.errors.push("The credits page is mandatory and must not be disabled");
  if (credits.people === false) result.errors.push("Credits must list the people involved and their roles");
  if (credits.components === false) result.errors.push("Credits must list used AI systems, tools, libraries, fonts and icons");
  if (credits.local_assets_only === false) {
    result.warnings.push("Fonts, icons and libraries should be embedded locally instead of hotlinked (privacy)");
  }
}

function checkCms(profile, result) {
  const cms = profile.cms;
  if (!cms) {
    result.warnings.push("cms section is missing; editable legal pages are mandatory" + BRIEFING_HINT);
    return;
  }
  if (cms.revisions === false) result.errors.push("CMS/legal pages require revisions");
  if (cms.import_export === false) result.errors.push("CMS/legal pages require export and import");
  if (!cms.editor) result.warnings.push("cms.editor is not chosen (tinymce, grapesjs, quill, markdown, plain, ...)" + BRIEFING_HINT);
  if (!cms.editor_delivery) {
    result.warnings.push("cms.editor_delivery is not chosen: embed the editor locally or load it from a CDN" + BRIEFING_HINT);
  } else if (cms.editor_delivery === "cdn") {
    result.warnings.push("The CMS editor is loaded from a CDN; document the provider in privacy policy and credits or embed it locally");
  }

  const declared = new Set(cms.legal_pages || []);
  const missing = requiredLegalPages(profile).filter((page) => !declared.has(page));
  if (missing.length) result.warnings.push("Missing legal/CMS pages: " + missing.join(", "));
}

function checkSettings(profile, result) {
  const settings = profile.settings;
  if (!settings) {
    result.warnings.push("settings section is missing; backoffice settings must be searchable and filterable");
    return;
  }
  if (settings.searchable === false) result.errors.push("Backoffice settings must be searchable");
  if (settings.filterable === false) result.errors.push("Backoffice settings must be filterable");
}

function checkAppearance(profile, result) {
  const appearance = profile.appearance;
  if (!appearance) {
    result.warnings.push("appearance section is missing; light and dark mode are mandatory" + BRIEFING_HINT);
    return;
  }
  const modes = appearance.modes || [];
  if (!modes.includes("light") || !modes.includes("dark")) {
    result.errors.push("appearance.modes must contain both light and dark");
  }
  const toggle = appearance.toggle || {};
  if (toggle.enabled && (!toggle.locations || toggle.locations.length === 0)) {
    result.warnings.push("The light/dark toggle is enabled but no display location is configured");
  }
}

function checkCodeEditors(profile, result) {
  const editors = profile.code_editors;
  if (!editors || !editors.enabled) return;
  if ((editors.languages || []).includes("php") && editors.php_requires_config_flag !== true) {
    result.errors.push("PHP code editing in the backoffice requires code_editors.php_requires_config_flag=true");
  }
  if (editors.backup_before_save === false) {
    result.warnings.push("Backoffice code editors should create a backup before saving");
  }
}

function checkBriefing(profile, result) {
  const briefing = profile.briefing;
  if (!briefing || briefing.completed !== true) {
    result.warnings.push("The agent briefing is not completed (see platform/BRIEFING.md)");
  }
  if (briefing && Array.isArray(briefing.open_questions) && briefing.open_questions.length) {
    result.warnings.push("Briefing has " + briefing.open_questions.length + " open question(s)");
  }
}

export function validateMandatoryFeatures(profile) {
  const result = { errors: [], warnings: [] };
  checkVersioning(profile, result);
  checkCredits(profile, result);
  checkCms(profile, result);
  checkSettings(profile, result);
  checkAppearance(profile, result);
  checkCodeEditors(profile, result);
  checkBriefing(profile, result);
  return result;
}
