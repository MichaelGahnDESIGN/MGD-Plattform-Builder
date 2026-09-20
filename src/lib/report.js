export function printValidation(result, profilePath) {
  console.log("\nMGD Platform validation: " + profilePath);
  console.log(result.valid ? "✓ Profile is valid" : "✗ Profile is not valid");

  for (const message of result.schemaErrors) {
    console.log("  ✗ schema: " + message);
  }
  for (const message of result.ruleErrors) {
    console.log("  ✗ rule: " + message);
  }
  for (const message of result.warnings) {
    console.log("  ! warning: " + message);
  }
}
