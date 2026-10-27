-- Benutzerverwaltung: Pflicht-Passwortwechsel, Zeitpunkt der letzten Passwortänderung,
-- Passwort-Reset per E-Mail (nur Hash des Tokens) und Drosselung der Reset-Anfragen.

ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN password_changed_at DATETIME NULL;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id, used_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    requested_at DATETIME NOT NULL,
    KEY idx_password_reset_requests_email (email_hash, requested_at),
    KEY idx_password_reset_requests_ip (ip_hash, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
