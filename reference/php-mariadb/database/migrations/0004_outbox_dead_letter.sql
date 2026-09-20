ALTER TABLE jobs_outbox
    ADD COLUMN max_attempts INT UNSIGNED NOT NULL DEFAULT 5 AFTER attempts,
    ADD COLUMN locked_at DATETIME NULL AFTER max_attempts,
    ADD COLUMN locked_by VARCHAR(100) NULL AFTER locked_at,
    ADD COLUMN failed_at DATETIME NULL AFTER last_error;

CREATE INDEX idx_jobs_claim ON jobs_outbox (status, available_at, locked_at, id);
