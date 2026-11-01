-- Backup-Modul: Einstellungen, Ziele, Läufe, Teile, Ziel-Zuordnung und Uploads.
-- Zeiten sind UTC. Keine Klartext-Geheimnisse: Passwörter und der Signaturschlüssel stehen nur verschlüsselt (secret_enc).
CREATE TABLE backup_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    schedule_interval VARCHAR(10) NOT NULL DEFAULT 'off',
    schedule_kind VARCHAR(4) NOT NULL DEFAULT 'full',
    schedule_time CHAR(5) NOT NULL DEFAULT '03:00',
    schedule_weekday TINYINT UNSIGNED NOT NULL DEFAULT 7,
    schedule_monthday TINYINT UNSIGNED NOT NULL DEFAULT 1,
    next_run_at DATETIME NULL,
    max_age_days SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    part_size_mb SMALLINT UNSIGNED NOT NULL DEFAULT 64,
    areas_json TEXT NULL,
    exclude_patterns TEXT NULL,
    primary_key_text VARCHAR(120) NULL,
    primary_key_fp CHAR(16) NULL,
    emergency_key_text VARCHAR(120) NULL,
    emergency_key_fp CHAR(16) NULL,
    signing_secret_enc TEXT NULL,
    signing_public CHAR(44) NULL,
    signing_fp CHAR(16) NULL,
    cron_token_hash CHAR(64) NULL,
    cron_fail_count INT UNSIGNED NOT NULL DEFAULT 0,
    cron_fail_since DATETIME NULL,
    stepup_fail_count INT UNSIGNED NOT NULL DEFAULT 0,
    stepup_fail_since DATETIME NULL,
    last_tick_at DATETIME NULL,
    last_success_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO backup_settings (id, updated_at) VALUES (1, '2000-01-01 00:00:00');

CREATE TABLE backup_destinations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dest_type VARCHAR(8) NOT NULL,
    name VARCHAR(100) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    keep_count SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    config_json TEXT NULL,
    secret_enc TEXT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'unconfigured',
    last_test_at DATETIME NULL,
    last_test_ok TINYINT(1) NULL,
    last_error_code VARCHAR(40) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    kind VARCHAR(4) NOT NULL,
    trigger_type VARCHAR(10) NOT NULL,
    status VARCHAR(12) NOT NULL,
    phase VARCHAR(20) NOT NULL DEFAULT 'queued',
    active_slot TINYINT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    folder_name VARCHAR(64) NOT NULL,
    queued_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    heartbeat_at DATETIME NULL,
    finished_at DATETIME NULL,
    cancel_requested_at DATETIME NULL,
    cursor_json LONGTEXT NULL,
    db_consistency VARCHAR(12) NULL,
    key_fps VARCHAR(80) NULL,
    signer_fp CHAR(16) NULL,
    app_version VARCHAR(40) NULL,
    db_tables INT UNSIGNED NOT NULL DEFAULT 0,
    db_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_count INT UNSIGNED NOT NULL DEFAULT 0,
    files_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cipher_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    parts INT UNSIGNED NOT NULL DEFAULT 0,
    manifest_sha256 CHAR(64) NULL,
    error_code VARCHAR(40) NULL,
    warnings_json TEXT NULL,
    local_deleted_at DATETIME NULL,
    UNIQUE (uuid),
    UNIQUE (active_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_backup_runs_status ON backup_runs (status);

CREATE TABLE backup_parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    part_kind VARCHAR(10) NOT NULL,
    file_name VARCHAR(120) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'ready',
    plain_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cipher_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cipher_sha256 CHAR(64) NOT NULL,
    UNIQUE (run_id, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_run_destinations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    destination_id INT UNSIGNED NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    remote_folder_ref VARCHAR(500) NULL,
    error_code VARCHAR(40) NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    completed_at DATETIME NULL,
    delete_after DATETIME NULL,
    UNIQUE (run_id, destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_uploads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id INT UNSIGNED NOT NULL,
    destination_id INT UNSIGNED NOT NULL,
    status VARCHAR(14) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    remote_ref VARCHAR(600) NULL,
    resume_state VARCHAR(500) NULL,
    bytes_done BIGINT UNSIGNED NOT NULL DEFAULT 0,
    error_code VARCHAR(40) NULL,
    uploaded_at DATETIME NULL,
    UNIQUE (part_id, destination_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
