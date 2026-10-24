-- Per-household task catalogs. There is no households table, so household_id is a plain indexed column.
-- Rows are soft-deleted (deleted_at); names are unique per household among non-deleted rows,
-- case-insensitively, which the application enforces (a partial unique index is not portable).
CREATE TABLE IF NOT EXISTS categories (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    household_id VARCHAR(128) NOT NULL,
    name VARCHAR(64) NOT NULL,
    color CHAR(7) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    INDEX idx_categories_household_id (household_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurrence_types (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    household_id VARCHAR(128) NOT NULL,
    name VARCHAR(64) NOT NULL,
    interval_days SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    INDEX idx_recurrence_types_household_id (household_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
