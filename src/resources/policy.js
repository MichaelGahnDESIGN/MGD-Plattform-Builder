export function evaluateUpdate(change, policy = {}) {
  const deny = (reason) => ({ decision: "review", reason });
  if (!["package", "ci"].includes(change.kind))
    return deny(
      "Laufzeit-, Infrastruktur- und Dienständerungen brauchen eine eigene Freigabe",
    );
  if (!change.sourceTrusted || !change.securityUpdate)
    return deny("Quelle oder Sicherheitsbezug nicht bestätigt");
  if (
    !/^\d+\.\d+\.\d+$/.test(change.current || "") ||
    !/^\d+\.\d+\.\d+$/.test(change.target || "")
  )
    return deny("Versionssprung nicht eindeutig");
  const a = change.current.split(".").map(Number),
    b = change.target.split(".").map(Number);
  if (a[0] !== b[0] || a[1] !== b[1] || b[2] <= a[2])
    return deny("Kein reines Patch-Update");
  if (
    !policy.autoMergeSecurityPatch ||
    !policy.repositories?.includes(change.repository)
  )
    return deny("Keine projektspezifische Patch-Freigabe");
  if (
    !change.requiredChecks?.length ||
    change.requiredChecks.some((c) => c.status !== "success") ||
    !change.testedHead ||
    change.testedHead !== change.head
  )
    return deny("Verbindliche Prüfungen am aktuellen Commit fehlen");
  if (
    !change.rollbackVerified ||
    !change.backupAvailable ||
    !change.lockfileUpdated
  )
    return deny("Lockdatei, Sicherung oder Rückfall nicht bestätigt");
  return {
    decision: "eligible",
    reason:
      "Patch-Freigabe und Nachweise vorhanden; Veröffentlichung bleibt ein eigener Schritt",
  };
}
