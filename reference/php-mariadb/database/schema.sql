CREATE TABLE accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    email VARCHAR(320) NOT NULL UNIQUE,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE capabilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capability_key VARCHAR(160) NOT NULL UNIQUE,
    risk_level VARCHAR(20) NOT NULL DEFAULT 'low',
    audit_required TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_capabilities (
    role_id BIGINT UNSIGNED NOT NULL,
    capability_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, capability_id),
    CONSTRAINT fk_role_capabilities_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_capabilities_capability FOREIGN KEY (capability_id) REFERENCES capabilities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_principals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    scopes_json JSON NOT NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id VARCHAR(64) NOT NULL,
    actor_type VARCHAR(32) NOT NULL,
    action_name VARCHAR(160) NOT NULL,
    resource_type VARCHAR(100) NOT NULL,
    resource_id VARCHAR(100) NULL,
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_actor (actor_id, created_at),
    INDEX idx_audit_resource (resource_type, resource_id, created_at),
    INDEX idx_audit_action (action_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO capabilities (capability_key, risk_level, audit_required)
VALUES
    ('content.read', 'low', 0),
    ('content.publish', 'medium', 1),
    ('moderation.case.read', 'medium', 0),
    ('moderation.case.decide', 'high', 1),
    ('support.case.read', 'medium', 1),
    ('privacy.request.manage', 'high', 1),
    ('security.audit.read', 'high', 1),
    ('permissions.manage', 'critical', 1),
    ('accounts.suspend', 'high', 1);
