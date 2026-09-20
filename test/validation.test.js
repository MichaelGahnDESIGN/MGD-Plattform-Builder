import test from "node:test";
import assert from "node:assert/strict";
import { validateProfileRules } from "../src/lib/validation.js";

function baseProfile() {
  return {
    languages: { default: "de", enabled: ["de"] },
    features: { ai_agents: false, billing: false, uploads: false },
    privacy: { personal_data: false, sensitive_data: false },
    security: { audit_log: true, mfa_privileged: true, incident_response: true, security_events: true },
    infrastructure: { backup: "local", staging: "local" },
    release_gates: []
  };
}

test("default language must be enabled", () => {
  const profile = baseProfile();
  profile.languages.default = "en";
  const result = validateProfileRules(profile);
  assert.equal(result.errors.length, 1);
  assert.match(result.errors[0], /languages\.default/);
});

test("AI agents require an enabled agent section", () => {
  const profile = baseProfile();
  profile.features.ai_agents = true;
  const result = validateProfileRules(profile);
  assert.ok(result.errors.some((item) => item.includes("agents.enabled")));
});

test("billing requires audit logging", () => {
  const profile = baseProfile();
  profile.features.billing = true;
  profile.security.audit_log = false;
  const result = validateProfileRules(profile);
  assert.ok(result.errors.some((item) => item.includes("Billing")));
});

test("sensitive data warns when privileged MFA is disabled", () => {
  const profile = baseProfile();
  profile.privacy.sensitive_data = true;
  profile.security.mfa_privileged = false;
  const result = validateProfileRules(profile);
  assert.ok(result.warnings.some((item) => item.includes("MFA")));
});
