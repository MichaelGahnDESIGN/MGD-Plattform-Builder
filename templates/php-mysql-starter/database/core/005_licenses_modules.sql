-- Lizenzschlüssel (Whitelabel, kostenpflichtige Module) und Modulstatus.
CREATE TABLE licenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(20) NOT NULL,
    subject VARCHAR(120) NOT NULL,
    license_key TEXT NOT NULL,
    licensee VARCHAR(200) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_license_subject (kind, subject),
    CONSTRAINT chk_license_kind CHECK (kind IN ('whitelabel', 'module'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modules (
    module_id VARCHAR(64) NOT NULL PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
