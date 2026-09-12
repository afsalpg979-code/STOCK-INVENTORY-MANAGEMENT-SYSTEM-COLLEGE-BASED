-- College Stock Inventory Upgrade
-- Inward -> Principal BOH -> Movement -> HOD FOH -> Replenishment
-- Outward is a permanent exit from college inventory.

CREATE TABLE IF NOT EXISTS inventory_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_code VARCHAR(100) NOT NULL,
  barcode VARCHAR(100) DEFAULT NULL,
  serial_number VARCHAR(150) DEFAULT NULL,
  qr_value VARCHAR(255) DEFAULT NULL,
  rfid_uid VARCHAR(32) DEFAULT NULL,
  principal_stock_id INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_code (product_code),
  UNIQUE KEY uq_barcode (barcode),
  UNIQUE KEY uq_serial (serial_number),
  UNIQUE KEY uq_qr (qr_value),
  UNIQUE KEY uq_rfid (rfid_uid),
  KEY idx_principal_stock (principal_stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  product_code VARCHAR(100) DEFAULT NULL,
  item_name VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  from_location VARCHAR(100) NOT NULL DEFAULT 'PRINCIPAL_BOH',
  to_location VARCHAR(100) NOT NULL,
  from_stock_id INT DEFAULT NULL,
  to_stock_id INT DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  movement_type ENUM('MOVEMENT','REPLENISHMENT','RETURN') NOT NULL DEFAULT 'MOVEMENT',
  status ENUM('PENDING','APPROVED','RECEIVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'RECEIVED',
  requested_by VARCHAR(255) DEFAULT NULL,
  approved_by VARCHAR(255) DEFAULT NULL,
  received_by VARCHAR(255) DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_movement_product (product_id),
  KEY idx_movement_code (product_code),
  KEY idx_movement_status (status),
  KEY idx_movement_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_replenishments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  product_code VARCHAR(100) DEFAULT NULL,
  item_name VARCHAR(100) NOT NULL,
  department VARCHAR(100) NOT NULL,
  current_quantity INT NOT NULL DEFAULT 0,
  requested_quantity INT UNSIGNED NOT NULL,
  status ENUM('PENDING','APPROVED','FULFILLED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  requested_by VARCHAR(255) DEFAULT NULL,
  approved_by VARCHAR(255) DEFAULT NULL,
  fulfilled_by VARCHAR(255) DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_replenishment_product (product_id),
  KEY idx_replenishment_code (product_code),
  KEY idx_replenishment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_inward (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  product_code VARCHAR(100) DEFAULT NULL,
  item_name VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  brand VARCHAR(100) NOT NULL DEFAULT '',
  branch VARCHAR(50) NOT NULL DEFAULT '',
  supplier VARCHAR(255) DEFAULT NULL,
  invoice_no VARCHAR(100) DEFAULT NULL,
  received_by VARCHAR(255) DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inward_code (product_code),
  KEY idx_inward_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_outward (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  product_code VARCHAR(100) DEFAULT NULL,
  item_name VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  source_location VARCHAR(100) NOT NULL,
  destination VARCHAR(255) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  approved_by VARCHAR(255) DEFAULT NULL,
  handed_over_by VARCHAR(255) DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_outward_code (product_code),
  KEY idx_outward_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing RFID table in this project already has uid and item_code.
-- Add BOH/FOH state only if it has not already been added.
ALTER TABLE rfid_tags
  ADD COLUMN IF NOT EXISTS stock_zone ENUM('BOH','FOH') NOT NULL DEFAULT 'BOH' AFTER department;

-- Recommended indexes for fast universal lookup.
ALTER TABLE rfid_tags ADD INDEX idx_rfid_item_code (item_code);

-- Existing BOH -> FOH RFID movement table remains compatible with this upgrade.
CREATE INDEX IF NOT EXISTS idx_rfid_movements_code ON rfid_boh_foh_movements(item_code);
