ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS last_assigned_at TIMESTAMP NULL DEFAULT NULL AFTER assigned_member_id;
