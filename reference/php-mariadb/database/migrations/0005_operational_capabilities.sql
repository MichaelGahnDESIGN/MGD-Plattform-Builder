INSERT INTO capabilities (capability_key, risk_level, audit_required)
VALUES
    ('jobs.read', 'medium', 0),
    ('jobs.manage', 'high', 1)
ON DUPLICATE KEY UPDATE capability_key = VALUES(capability_key);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c ON c.capability_key IN ('jobs.read', 'jobs.manage')
WHERE r.role_key = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);
