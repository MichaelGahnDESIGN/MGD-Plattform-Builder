CREATE TABLE translation_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    translation_key VARCHAR(190) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    translation_key_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(20) NOT NULL,
    value_text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    updated_by_actor_id VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_translation_locale (translation_key_id, locale),
    CONSTRAINT fk_translations_key FOREIGN KEY (translation_key_id) REFERENCES translation_keys(id) ON DELETE CASCADE,
    INDEX idx_translations_locale_status (locale, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO capabilities (capability_key, risk_level, audit_required)
VALUES
    ('translations.read', 'low', 0),
    ('translations.manage', 'medium', 1)
ON DUPLICATE KEY UPDATE capability_key = VALUES(capability_key);

INSERT INTO role_capabilities (role_id, capability_id)
SELECT r.id, c.id
FROM roles r
JOIN capabilities c ON c.capability_key IN ('translations.read', 'translations.manage')
WHERE r.role_key = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);
