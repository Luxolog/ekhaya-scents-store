-- Ekhaya Scents reseller approval workflow
-- Import this migration into the existing Ekhaya database.
CREATE TABLE IF NOT EXISTS reseller_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  business_name VARCHAR(160) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  privacy_notice_version VARCHAR(40) NOT NULL,
  privacy_acknowledged_at DATETIME NOT NULL,
  marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
  marketing_consent_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  reviewed_by VARCHAR(120) NULL,
  rejection_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_reseller_application_email (email),
  INDEX idx_reseller_status (status),
  INDEX idx_reseller_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
