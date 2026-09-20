ALTER TABLE translations
    ADD COLUMN submitted_at DATETIME NULL AFTER status,
    ADD COLUMN reviewed_at DATETIME NULL AFTER submitted_at,
    ADD COLUMN reviewed_by_actor_id VARCHAR(64) NULL AFTER reviewed_at,
    ADD COLUMN review_note VARCHAR(1000) NULL AFTER reviewed_by_actor_id;

INSERT INTO capabilities (capability_key, risk_level, audit_required)
VALUES
    ('translations.review', 'medium', 1),
    ('translations.import', 'medium', 1),
    ('translations.export', 'low', 1)
ON DUPLICATE KEY UPDATE capability_key = VALUES(capability_key);

INSERT INTO roles (role_key, label)
VALUES ('translator', 'Translator')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c ON c.capability_key IN (
    'translations.read',
    'translations.manage',
    'translations.import',
    'translations.export'
)
WHERE r.role_key = 'translator'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c ON c.capability_key IN (
    'translations.review',
    'translations.import',
    'translations.export'
)
WHERE r.role_key = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);
