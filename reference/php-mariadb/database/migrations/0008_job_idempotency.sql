ALTER TABLE jobs_outbox
    ADD COLUMN idempotency_hash CHAR(64) NULL AFTER topic,
    ADD UNIQUE KEY uq_jobs_idempotency (idempotency_hash);
