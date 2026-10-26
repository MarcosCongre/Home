-- Soft delete: rows are kept and stamped instead of removed; reads filter deleted_at IS NULL.
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

ALTER TABLE members
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
