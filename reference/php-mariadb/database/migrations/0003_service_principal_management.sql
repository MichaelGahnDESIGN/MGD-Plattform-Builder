ALTER TABLE service_principals
    ADD COLUMN description VARCHAR(500) NULL AFTER name,
    ADD COLUMN created_by_actor_id VARCHAR(64) NULL AFTER scopes_json,
    ADD COLUMN last_rotated_at DATETIME NULL AFTER created_at;

INSERT INTO capabilities (capability_key, risk_level, audit_required)
VALUES
    ('service-principals.read', 'high', 1),
    ('service-principals.manage', 'critical', 1)
ON DUPLICATE KEY UPDATE capability_key = VALUES(capability_key);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c ON c.capability_key IN ('service-principals.read', 'service-principals.manage')
WHERE r.role_key = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);
