-- Tasks reference the per-household catalogs (006) by id instead of free strings.
-- Written for MariaDB 11.8. Run it once, before deploying the backend that reads the ids.
-- Columns, indexes and keys use IF NOT EXISTS and households that already have catalog
-- rows are never reseeded, so an interrupted run can be repeated. Do NOT re-run it after
-- the new backend is live: the legacy strings stay frozen, so a re-run would point tasks
-- whose reference was cleared back at their old string and recreate deleted entries.

-- a. Close the schema drift: these columns exist in production (added by hand) but no
--    earlier migration creates them. Definitions match production exactly.
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS day VARCHAR(3) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS time VARCHAR(8) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS category VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS recurrence VARCHAR(32) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS priority VARCHAR(8) NOT NULL DEFAULT 'med';

-- b. Catalog references. NULL means no category / a one-off task.
--    The keys only guarantee the row exists; the application also checks that the entry
--    belongs to the task's household and is not soft-deleted. Catalog rows are soft-deleted,
--    so ON DELETE SET NULL only matters for a manual hard delete.
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS category_id INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS recurrence_id INT NULL DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_tasks_category_id (category_id),
    ADD INDEX IF NOT EXISTS idx_tasks_recurrence_id (recurrence_id);

ALTER TABLE tasks
    ADD CONSTRAINT fk_tasks_category FOREIGN KEY IF NOT EXISTS (category_id)
        REFERENCES categories (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tasks_recurrence_type FOREIGN KEY IF NOT EXISTS (recurrence_id)
        REFERENCES recurrence_types (id) ON DELETE SET NULL;

-- d. DEPRECATED: tasks.category and tasks.recurrence are no longer read or written by the
--    backend (it uses category_id / recurrence_id). They are kept, frozen at their
--    pre-migration values, only so a rollback to the previous backend still works.
--    Drop them in a later migration once the catalogs are settled.

-- c. Backfill.
START TRANSACTION;

-- c1. Seed the defaults (same names, colors, intervals and order as DefaultCatalogs.php)
--     for every household seen in tasks or members that has no rows in that catalog yet.
--     Soft-deleted rows count as "has rows", like the application's seeding.
INSERT INTO categories (household_id, name, color, created_at, updated_at)
SELECT h.household_id, d.name, d.color, NOW(), NOW()
FROM (
    SELECT household_id FROM tasks WHERE TRIM(household_id) <> ''
    UNION
    SELECT household_id FROM members WHERE TRIM(household_id) <> ''
) h
CROSS JOIN (
    SELECT 1 AS seq, 'Cleaning' AS name, '#C4623A' AS color
    UNION ALL SELECT 2, 'Laundry', '#9B7DB5'
    UNION ALL SELECT 3, 'Pets', '#C4963A'
    UNION ALL SELECT 4, 'Garden', '#6B7C4E'
    UNION ALL SELECT 5, 'Shopping', '#3A8FB5'
    UNION ALL SELECT 6, 'Waste', '#7C5E3A'
    UNION ALL SELECT 7, 'Bills', '#B53A3A'
    UNION ALL SELECT 8, 'Kitchen', '#3A7C8F'
) d
WHERE NOT EXISTS (SELECT 1 FROM categories c WHERE c.household_id = h.household_id)
ORDER BY h.household_id, d.seq;

INSERT INTO recurrence_types (household_id, name, interval_days, created_at, updated_at)
SELECT h.household_id, d.name, d.interval_days, NOW(), NOW()
FROM (
    SELECT household_id FROM tasks WHERE TRIM(household_id) <> ''
    UNION
    SELECT household_id FROM members WHERE TRIM(household_id) <> ''
) h
CROSS JOIN (
    SELECT 1 AS seq, 'Daily' AS name, 1 AS interval_days
    UNION ALL SELECT 2, 'Mon & Thu', 3
    UNION ALL SELECT 3, 'Tue & Fri', 3
    UNION ALL SELECT 4, 'Weekly', 7
    UNION ALL SELECT 5, 'Biweekly', 14
    UNION ALL SELECT 6, 'Monthly', 30
) d
WHERE NOT EXISTS (SELECT 1 FROM recurrence_types rt WHERE rt.household_id = h.household_id)
ORDER BY h.household_id, d.seq;

-- c2. Every distinct non-empty category string of a live task that is not yet an active
--     category of its household (case-insensitive) becomes one, without a color.
INSERT INTO categories (household_id, name, color, created_at, updated_at)
SELECT s.household_id, s.name, NULL, NOW(), NOW()
FROM (
    SELECT t.household_id, MIN(TRIM(t.category)) AS name
    FROM tasks t
    WHERE t.deleted_at IS NULL
      AND TRIM(t.household_id) <> ''
      AND t.category IS NOT NULL
      AND TRIM(t.category) <> ''
    GROUP BY t.household_id, LOWER(TRIM(t.category))
) s
WHERE NOT EXISTS (
    SELECT 1 FROM categories c
    WHERE c.household_id = s.household_id
      AND c.deleted_at IS NULL
      AND LOWER(c.name) = LOWER(s.name)
)
ORDER BY s.household_id, s.name;

-- c3. Recurrence strings: '', 'once', 'none' and NULL mean one-off (no type). A string
--     that is not an active type of its household (case-insensitive) becomes one: a
--     default name keeps its default interval (the household may have renamed or deleted
--     it), and any other unknown string gets 7 days, which is how it was treated before.
INSERT INTO recurrence_types (household_id, name, interval_days, created_at, updated_at)
SELECT s.household_id, s.name, COALESCE(d.interval_days, 7), NOW(), NOW()
FROM (
    SELECT t.household_id, MIN(TRIM(t.recurrence)) AS name
    FROM tasks t
    WHERE t.deleted_at IS NULL
      AND TRIM(t.household_id) <> ''
      AND t.recurrence IS NOT NULL
      AND LOWER(TRIM(t.recurrence)) NOT IN ('', 'once', 'none')
    GROUP BY t.household_id, LOWER(TRIM(t.recurrence))
) s
LEFT JOIN (
    SELECT 'daily' AS name, 1 AS interval_days
    UNION ALL SELECT 'mon & thu', 3
    UNION ALL SELECT 'tue & fri', 3
    UNION ALL SELECT 'weekly', 7
    UNION ALL SELECT 'biweekly', 14
    UNION ALL SELECT 'monthly', 30
) d ON d.name = LOWER(s.name)
WHERE NOT EXISTS (
    SELECT 1 FROM recurrence_types rt
    WHERE rt.household_id = s.household_id
      AND rt.deleted_at IS NULL
      AND LOWER(rt.name) = LOWER(s.name)
)
ORDER BY s.household_id, s.name;

-- c4. Point tasks at their entries. Soft-deleted tasks are mapped too when a match exists.
--     updated_at is set to itself so ON UPDATE CURRENT_TIMESTAMP does not touch it.
UPDATE tasks t
SET t.category_id = (
        SELECT MIN(c.id) FROM categories c
        WHERE c.household_id = t.household_id
          AND c.deleted_at IS NULL
          AND LOWER(c.name) = LOWER(TRIM(t.category))
    ),
    t.updated_at = t.updated_at
WHERE t.category_id IS NULL
  AND t.category IS NOT NULL
  AND TRIM(t.category) <> '';

UPDATE tasks t
SET t.recurrence_id = (
        SELECT MIN(rt.id) FROM recurrence_types rt
        WHERE rt.household_id = t.household_id
          AND rt.deleted_at IS NULL
          AND LOWER(rt.name) = LOWER(TRIM(t.recurrence))
    ),
    t.updated_at = t.updated_at
WHERE t.recurrence_id IS NULL
  AND t.recurrence IS NOT NULL
  AND LOWER(TRIM(t.recurrence)) NOT IN ('', 'once', 'none');

COMMIT;
