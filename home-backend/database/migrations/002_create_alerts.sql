CREATE TABLE IF NOT EXISTS alerts (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    household_id VARCHAR(128) NOT NULL,
    member_id INT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    icon VARCHAR(64) NOT NULL,
    urgent TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alerts_household_id (household_id),
    INDEX idx_alerts_member_id (member_id),
    CONSTRAINT fk_alerts_member
        FOREIGN KEY (member_id)
        REFERENCES members (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
