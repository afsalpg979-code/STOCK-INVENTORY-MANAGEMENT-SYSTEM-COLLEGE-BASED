-- RFID BOH -> FOH stock movement upgrade
-- BOH = Principal/central stock
-- FOH = department/other operational stocks
-- IMPORTANT: back up the database before applying this migration.

ALTER TABLE rfid_tags
    ADD COLUMN IF NOT EXISTS stock_zone ENUM('BOH','FOH') NOT NULL DEFAULT 'BOH' AFTER department;

CREATE TABLE IF NOT EXISTS rfid_boh_foh_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rfid_tag_id BIGINT UNSIGNED DEFAULT NULL,
    uid VARCHAR(32) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_code VARCHAR(100) DEFAULT NULL,
    quantity INT UNSIGNED NOT NULL,
    from_zone ENUM('BOH','FOH') NOT NULL,
    to_zone ENUM('BOH','FOH') NOT NULL,
    from_department VARCHAR(150) DEFAULT NULL,
    to_department VARCHAR(150) DEFAULT NULL,
    moved_by VARCHAR(255) DEFAULT NULL,
    moved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    device_uid VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_boh_foh_uid (uid),
    KEY idx_boh_foh_time (moved_at),
    KEY idx_boh_foh_from_to (from_zone, to_zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The application rule is:
-- BOH -> FOH: BOH quantity decreases by moved quantity and FOH quantity increases.
-- A movement is recorded with the exact moved_at timestamp.
-- Never allow the BOH balance to become negative.
