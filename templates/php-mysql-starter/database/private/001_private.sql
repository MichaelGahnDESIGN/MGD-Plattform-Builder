-- MGD PHP/MySQL Starter – private Datenbank (personenbezogene/sensible Daten)
-- Liegt in der optionalen zweiten Datenbank oder – falls nicht konfiguriert – in der Kerndatenbank.
-- Bewusst KEIN Fremdschlüssel auf users: die Tabellen können in unterschiedlichen Datenbanken liegen.

CREATE TABLE user_profiles (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    display_name VARCHAR(120) NOT NULL DEFAULT '',
    real_name VARCHAR(200) NULL,
    email_private VARCHAR(254) NULL,
    address_json TEXT NULL,
    birthdate DATE NULL,
    consent_newsletter TINYINT(1) NOT NULL DEFAULT 0,
    consent_profile_public TINYINT(1) NOT NULL DEFAULT 0,
    consent_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
