import fs from "node:fs/promises";
import path from "node:path";
import Ajv2020 from "ajv/dist/2020.js";
import { foundationRoot } from "./paths.js";

export async function loadProfileSchema() {
  const schemaPath = path.join(foundationRoot, "schema", "mgd-platform.schema.json");
  return JSON.parse(await fs.readFile(schemaPath, "utf8"));
}

function formatAjvError(error) {
  const location = error.instancePath || "/";
  return location + " " + (error.message || "is invalid");
}

export async function validateProfileSchema(profile) {
  const schema = await loadProfileSchema();
  const ajv = new Ajv2020({ allErrors: true, strict: false });
  const validate = ajv.compile(schema);
  const valid = validate(profile);

  return {
    valid: Boolean(valid),
    errors: valid ? [] : (validate.errors || []).map(formatAjvError)
  };
}

export function validateProfileRules(profile) {
  const errors = [];
  const warnings = [];
  const enabledLanguages = profile.languages && profile.languages.enabled ? profile.languages.enabled : [];
  const releaseGates = new Set(profile.release_gates || []);

  if (profile.languages && profile.languages.default && !enabledLanguages.includes(profile.languages.default)) {
    errors.push("languages.default must also be listed in languages.enabled");
  }

  if (profile.features && profile.features.ai_agents && (!profile.agents || profile.agents.enabled !== true)) {
    errors.push("features.ai_agents is true, but agents.enabled is not true");
  }

  if (profile.privacy && profile.privacy.sensitive_data) {
    if (!profile.security || !profile.security.audit_log) {
      errors.push("Sensitive data requires security.audit_log=true");
    }
    if (!profile.security || !profile.security.mfa_privileged) {
      warnings.push("Sensitive data should normally use MFA for privileged roles");
    }
    if (!profile.security || !profile.security.incident_response) {
      warnings.push("Sensitive data should have an incident-response process");
    }
  }

  if (profile.privacy && profile.privacy.personal_data && !profile.privacy.retention_policy) {
    warnings.push("Personal data is enabled but no retention policy is declared");
  }

  if (profile.features && profile.features.billing && (!profile.security || !profile.security.audit_log)) {
    errors.push("Billing requires security.audit_log=true");
  }

  if (profile.features && profile.features.billing && !releaseGates.has("payment-flow-tested")) {
    warnings.push("Billing is enabled; consider the payment-flow-tested release gate");
  }

  if (profile.privacy && profile.privacy.personal_data && !releaseGates.has("privacy-reviewed")) {
    warnings.push("Personal data is enabled; consider the privacy-reviewed release gate");
  }

  if (profile.infrastructure && profile.infrastructure.backup === "none") {
    warnings.push("No backup strategy is declared");
  }

  if (profile.infrastructure && profile.infrastructure.staging === "none") {
    warnings.push("No staging environment is declared");
  }

  if (profile.features && profile.features.uploads && (!profile.security || !profile.security.security_events)) {
    warnings.push("Uploads are enabled without security event tracking");
  }

  const backoffice = profile.backoffice || {};
  const roleViews = Array.isArray(backoffice.role_views) ? backoffice.role_views : [];

  if (backoffice.enabled === true && roleViews.length === 0) {
    warnings.push("Backoffice is enabled but no role-specific views are declared");
  }

  const seenViewIds = new Set();
  for (const view of roleViews) {
    const id = view && view.id ? String(view.id) : "";
    if (id && seenViewIds.has(id)) {
      errors.push("Duplicate backoffice role view id: " + id);
    }
    if (id) seenViewIds.add(id);

    const actorTypes = Array.isArray(view && view.actor_types) ? view.actor_types : [];
    if (actorTypes.includes("ai_agent") || actorTypes.includes("service_principal")) {
      warnings.push(
        "Backoffice view " + (id || "<unnamed>") +
        " targets a non-human actor; prefer scoped APIs and a separate human supervision view"
      );
    }
  }

  if (backoffice.shared_shell === false && roleViews.length > 1) {
    warnings.push("Multiple role views use separate backoffice shells; consider a shared shell to reduce duplication");
  }

  if (profile.features && profile.features.moderation) {
    const hasModerationView = roleViews.some((view) =>
      (Array.isArray(view.roles) && view.roles.includes("moderator")) ||
      (Array.isArray(view.navigation_groups) && view.navigation_groups.includes("moderation"))
    );
    if (!hasModerationView) {
      warnings.push("Moderation is enabled but no moderation-focused backoffice view is declared");
    }
  }

  if (profile.features && profile.features.support) {
    const hasSupportView = roleViews.some((view) =>
      (Array.isArray(view.roles) && view.roles.includes("support")) ||
      (Array.isArray(view.navigation_groups) && view.navigation_groups.includes("support"))
    );
    if (!hasSupportView) {
      warnings.push("Support is enabled but no support-focused backoffice view is declared");
    }
  }

  return { errors, warnings };
}

export async function validateProfile(profile) {
  const schema = await validateProfileSchema(profile);
  const rules = validateProfileRules(profile);

  return {
    valid: schema.valid && rules.errors.length === 0,
    schemaErrors: schema.errors,
    ruleErrors: rules.errors,
    warnings: rules.warnings
  };
}
