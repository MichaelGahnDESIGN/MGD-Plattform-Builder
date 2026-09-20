CREATE TABLE service_principal_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_principal_public_id CHAR(36) NOT NULL,
    actor_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sp_events_principal (service_principal_public_id, created_at),
    INDEX idx_sp_events_actor (actor_id, created_at),
    INDEX idx_sp_events_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
