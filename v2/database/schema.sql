-- Ekhaya Scents v2 core schema
-- Target: MySQL 8.0+ / MariaDB 10.6+ (verify exact hosting version before deployment).
-- Currency: ZAR. Store timezone should be configured as Africa/Johannesburg.
-- Import policy: source records are preserved and mapped; never overwrite live data blindly.
-- Sensitive credentials, payment secrets and customer exports do not belong in Git.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS migration_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_name VARCHAR(120) NOT NULL,
  source_snapshot_at DATETIME NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  status ENUM('planned','running','validated','committed','rolled_back','failed') NOT NULL DEFAULT 'planned',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legacy_id_map (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_system VARCHAR(40) NOT NULL,
  source_entity VARCHAR(60) NOT NULL,
  source_id VARCHAR(100) NOT NULL,
  target_entity VARCHAR(60) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  source_checksum CHAR(64) NULL,
  mapped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_legacy_map (source_system, source_entity, source_id, target_entity),
  KEY idx_legacy_target (target_entity, target_id),
  CONSTRAINT fk_legacy_map_batch FOREIGN KEY (batch_id) REFERENCES migration_batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  phone VARCHAR(40) NULL,
  account_status ENUM('pending','active','locked','disabled') NOT NULL DEFAULT 'pending',
  email_verified_at DATETIME NULL,
  source_created_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_email (email),
  KEY idx_customer_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  address_type ENUM('billing','shipping','other') NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  company VARCHAR(150) NULL,
  phone VARCHAR(40) NULL,
  address_1 VARCHAR(190) NULL,
  address_2 VARCHAR(190) NULL,
  city VARCHAR(100) NULL,
  province VARCHAR(100) NULL,
  postcode VARCHAR(20) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'ZA',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_address_customer (customer_id),
  CONSTRAINT fk_address_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_category_slug (slug),
  CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  product_type ENUM('simple','variable','bundle','kit') NOT NULL DEFAULT 'simple',
  description LONGTEXT NULL,
  short_description TEXT NULL,
  brand_line VARCHAR(100) NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  regular_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(12,2) NULL,
  cost_price DECIMAL(12,2) NULL,
  manage_stock TINYINT(1) NOT NULL DEFAULT 0,
  stock_quantity DECIMAL(12,3) NULL,
  stock_status ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
  weight_kg DECIMAL(10,3) NULL,
  requires_shipping TINYINT(1) NOT NULL DEFAULT 1,
  tax_class VARCHAR(80) NULL,
  source_created_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_slug (slug),
  UNIQUE KEY uq_product_sku (sku),
  KEY idx_product_status (status),
  KEY idx_product_type (product_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  source_path VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_image_product FOREIGN KEY (product_id) REFERENCES products(id),
  KEY idx_image_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(100) NULL,
  option_values JSON NULL,
  regular_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  sale_price DECIMAL(12,2) NULL,
  stock_quantity DECIMAL(12,3) NULL,
  stock_status ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
  UNIQUE KEY uq_variant_sku (sku),
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL,
  discount_type ENUM('percent','fixed_cart','fixed_product','free_shipping') NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  usage_limit INT NULL,
  usage_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  minimum_spend DECIMAL(12,2) NULL,
  maximum_spend DECIMAL(12,2) NULL,
  restrictions JSON NULL,
  status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_coupon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(50) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  status VARCHAR(40) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(80) NULL,
  payment_reference VARCHAR(190) NULL,
  paid_at DATETIME NULL,
  completed_at DATETIME NULL,
  billing_address_snapshot JSON NULL,
  shipping_address_snapshot JSON NULL,
  customer_note TEXT NULL,
  source_created_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_number (order_number),
  KEY idx_order_customer (customer_id),
  KEY idx_order_status_date (status, created_at),
  CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name_snapshot VARCHAR(190) NOT NULL,
  sku_snapshot VARCHAR(100) NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  metadata JSON NULL,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id),
  KEY idx_item_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_coupon_links (
  order_id BIGINT UNSIGNED NOT NULL,
  coupon_id BIGINT UNSIGNED NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (order_id, coupon_id),
  CONSTRAINT fk_order_coupon_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_order_coupon_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  gateway VARCHAR(40) NOT NULL,
  gateway_reference VARCHAR(190) NULL,
  event_type VARCHAR(80) NOT NULL,
  amount DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'ZAR',
  verification_status ENUM('unverified','verified','rejected') NOT NULL DEFAULT 'unverified',
  idempotency_key VARCHAR(190) NULL,
  safe_payload JSON NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_idempotency (gateway, idempotency_key),
  KEY idx_payment_order (order_id),
  CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reseller_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  reseller_type ENUM('referral_partner','stock_buyer') NOT NULL,
  status ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  referral_code VARCHAR(80) NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reseller_customer (customer_id),
  UNIQUE KEY uq_reseller_referral (referral_code),
  CONSTRAINT fk_reseller_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referrals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_reseller_id BIGINT UNSIGNED NOT NULL,
  referred_customer_id BIGINT UNSIGNED NULL,
  referred_reseller_id BIGINT UNSIGNED NULL,
  referral_code VARCHAR(80) NOT NULL,
  first_qualifying_order_id BIGINT UNSIGNED NULL,
  attribution_status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_referral_code (referral_code),
  CONSTRAINT fk_referrer_reseller FOREIGN KEY (referrer_reseller_id) REFERENCES reseller_accounts(id),
  CONSTRAINT fk_referred_customer FOREIGN KEY (referred_customer_id) REFERENCES customers(id),
  CONSTRAINT fk_referred_reseller FOREIGN KEY (referred_reseller_id) REFERENCES reseller_accounts(id),
  CONSTRAINT fk_referral_order FOREIGN KEY (first_qualifying_order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reseller_commission_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reseller_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  entry_type ENUM('commission','first_purchase_bonus','recruitment_reward','refund_adjustment','payout','manual_adjustment') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','approved','payable','paid','reversed','held') NOT NULL DEFAULT 'pending',
  description VARCHAR(255) NULL,
  source_ledger_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_commission_reseller (reseller_id, created_at),
  KEY idx_commission_order (order_id),
  CONSTRAINT fk_commission_reseller FOREIGN KEY (reseller_id) REFERENCES reseller_accounts(id),
  CONSTRAINT fk_commission_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_commission_source FOREIGN KEY (source_ledger_id) REFERENCES reseller_commission_ledger(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_rewards_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  entry_type ENUM('earned','redeemed','refund_reversal','expiry','restoration','manual_adjustment') NOT NULL,
  points INT NOT NULL,
  points_balance_after INT NULL,
  expires_at DATETIME NULL,
  description VARCHAR(255) NULL,
  source_ledger_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rewards_customer (customer_id, created_at),
  KEY idx_rewards_expiry (expires_at),
  CONSTRAINT fk_rewards_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_rewards_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_rewards_source FOREIGN KEY (source_ledger_id) REFERENCES customer_rewards_ledger(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  source VARCHAR(80) NOT NULL DEFAULT 'storefront_popup',
  consented_at DATETIME NOT NULL,
  unsubscribed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_newsletter_email (email),
  KEY idx_newsletter_status (status, consented_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('email','sms','whatsapp') NOT NULL DEFAULT 'email',
  consent_status ENUM('subscribed','unsubscribed','unknown') NOT NULL DEFAULT 'unknown',
  consent_source VARCHAR(120) NULL,
  consent_text_version VARCHAR(80) NULL,
  consented_at DATETIME NULL,
  withdrawn_at DATETIME NULL,
  evidence_reference VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_channel_consent (customer_id, channel),
  CONSTRAINT fk_consent_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_type ENUM('transactional','marketing','automation','password_setup') NOT NULL,
  name VARCHAR(190) NOT NULL,
  status ENUM('paused','queued','processing','completed','failed','cancelled','audit_only') NOT NULL DEFAULT 'paused',
  scheduled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_job_status (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_job_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  recipient_hash CHAR(64) NULL,
  item_status ENUM('paused','queued','sent','failed','skipped','audit_only') NOT NULL DEFAULT 'paused',
  attempts INT NOT NULL DEFAULT 0,
  last_error_code VARCHAR(80) NULL,
  source_item_id VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_item_job (job_id, item_status),
  CONSTRAINT fk_email_item_job FOREIGN KEY (job_id) REFERENCES email_jobs(id),
  CONSTRAINT fk_email_item_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_customer_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  request_id CHAR(36) NULL,
  ip_hash CHAR(64) NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_date (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Business rules are application-level and must be covered by automated tests:
-- * Rewards: 5% of eligible merchandise spend excluding shipping/tax; 100 points = R10;
--   minimum redemption 1,000 points; eligible perfume products only; 12-month expiry.
-- * Free delivery: qualifying purchases 5, 11, then 14, 17, 20... (verify against
--   source plugin logic and test before enabling).
-- * Referral commission: R10 per complete R50 eligible merchandise block after discounts;
--   separate once-off R50 first-paid-purchase bonus and qualifying recruitment reward.
-- * Do NOT implement the inactive reseller v1.6.0 R600 monthly threshold / R49 invoice rule.
-- * Imported marketing consent unknown remains non-marketable.
-- * Historical email jobs/items are audit-only and must not be replayed automatically.
