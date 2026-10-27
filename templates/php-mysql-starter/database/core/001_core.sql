-- MGD PHP/MySQL Starter – Kerndatenbank (Logins, Einstellungen, CMS)
-- schema_migrations wird vom MigrationRunner angelegt; hier nur zur Dokumentation idempotent.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(190) NOT NULL PRIMARY KEY,
    checksum CHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT chk_users_role CHECK (role IN ('admin', 'editor', 'moderator', 'user')),
    CONSTRAINT chk_users_status CHECK (status IN ('active', 'disabled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL,
    KEY idx_login_attempts_email (email_hash, attempted_at),
    KEY idx_login_attempts_ip (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key VARCHAR(190) NOT NULL PRIMARY KEY,
    value_json TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(200) NOT NULL,
    page_type VARCHAR(20) NOT NULL DEFAULT 'page',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    content_format VARCHAR(20) NOT NULL DEFAULT 'html',
    content_source MEDIUMTEXT NULL,
    content_html MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(300) NOT NULL DEFAULT '',
    current_revision INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_pages_slug (slug),
    KEY idx_pages_type (page_type, status, deleted_at),
    CONSTRAINT chk_pages_type CHECK (page_type IN ('page', 'legal', 'snippet')),
    CONSTRAINT chk_pages_status CHECK (status IN ('draft', 'published')),
    CONSTRAINT chk_pages_format CHECK (content_format IN ('html', 'markdown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id INT UNSIGNED NOT NULL,
    revision_no INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    status VARCHAR(20) NOT NULL,
    content_format VARCHAR(20) NOT NULL,
    content_source MEDIUMTEXT NULL,
    content_html MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(300) NOT NULL DEFAULT '',
    author_id INT UNSIGNED NULL,
    author_label VARCHAR(120) NOT NULL DEFAULT '',
    note VARCHAR(300) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_page_revisions_no (page_id, revision_no),
    CONSTRAINT fk_page_revisions_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE release_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(32) NOT NULL,
    status VARCHAR(20) NOT NULL,
    released_on DATE NOT NULL,
    note_type VARCHAR(20) NOT NULL,
    title VARCHAR(190) NOT NULL,
    items_json TEXT NOT NULL,
    audience_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_release_notes_version_title (version, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE credit_people (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    role VARCHAR(150) NOT NULL,
    link_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE credit_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'other',
    logo_path VARCHAR(300) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    provider_name VARCHAR(150) NOT NULL DEFAULT '',
    provider_info TEXT NOT NULL,
    links_json TEXT NOT NULL,
    license VARCHAR(100) NOT NULL DEFAULT '',
    tags_json TEXT NOT NULL,
    commercial_use VARCHAR(20) NOT NULL DEFAULT 'unknown',
    attribution_required TINYINT(1) NOT NULL DEFAULT 0,
    locally_embedded TINYINT(1) NOT NULL DEFAULT 0,
    version VARCHAR(50) NOT NULL DEFAULT '',
    notes TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_credit_components_category (category, sort_order),
    CONSTRAINT chk_credit_components_commercial CHECK (commercial_use IN ('yes', 'no', 'restricted', 'unknown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_label VARCHAR(120) NOT NULL,
    action_name VARCHAR(120) NOT NULL,
    resource_type VARCHAR(60) NOT NULL,
    resource_id VARCHAR(120) NULL,
    metadata_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_audit_log_created (created_at),
    KEY idx_audit_log_resource (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
