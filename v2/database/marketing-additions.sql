-- Ekhaya Customer Engagement: marketing and lifecycle automation foundation
-- Apply after schema.sql and auth-additions.sql. No messages are sent by applying this file.
-- Historical imported email queues must remain audit_only/paused.

CREATE TABLE IF NOT EXISTS email_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(100) NOT NULL,
  name VARCHAR(190) NOT NULL,
  message_type ENUM('transactional','marketing','automation','password_setup') NOT NULL,
  subject_template VARCHAR(255) NOT NULL,
  html_body MEDIUMTEXT NOT NULL,
  text_body MEDIUMTEXT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_template_version (template_key, version),
  KEY idx_email_template_active (template_key,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULL,
  event_key VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  event_dedupe_key VARCHAR(190) NOT NULL,
  event_data JSON NULL,
  occurred_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  processing_status ENUM('pending','processing','processed','ignored','failed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_event_dedupe (event_dedupe_key),
  KEY idx_customer_event_pending (processing_status,occurred_at),
  KEY idx_customer_event_customer (customer_id,event_key,occurred_at),
  CONSTRAINT fk_customer_event_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_key VARCHAR(100) NOT NULL,
  name VARCHAR(190) NOT NULL,
  trigger_event VARCHAR(100) NOT NULL,
  delay_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  template_key VARCHAR(100) NOT NULL,
  audience_type ENUM('transactional','marketing','service') NOT NULL DEFAULT 'marketing',
  conditions JSON NULL,
  stop_conditions JSON NULL,
  max_sends_per_customer INT UNSIGNED NOT NULL DEFAULT 1,
  cooldown_days INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_automation_rule_key (rule_key),
  KEY idx_automation_trigger_active (trigger_event,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_enrollments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  trigger_event_id BIGINT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  status ENUM('scheduled','queued','sent','completed','suppressed','cancelled','failed') NOT NULL DEFAULT 'scheduled',
  email_job_item_id BIGINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  suppression_reason VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_automation_enrollment (rule_id,trigger_event_id,customer_id),
  KEY idx_automation_due (status,scheduled_at),
  KEY idx_automation_customer (customer_id,status),
  CONSTRAINT fk_enrollment_rule FOREIGN KEY (rule_id) REFERENCES automation_rules(id),
  CONSTRAINT fk_enrollment_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_enrollment_event FOREIGN KEY (trigger_event_id) REFERENCES customer_events(id),
  CONSTRAINT fk_enrollment_job_item FOREIGN KEY (email_job_item_id) REFERENCES email_job_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  template_key VARCHAR(100) NOT NULL,
  audience_rules JSON NOT NULL,
  status ENUM('draft','scheduled','queued','sending','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
  scheduled_at DATETIME NULL,
  created_by_customer_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_campaign_status_schedule (status,scheduled_at),
  CONSTRAINT fk_campaign_creator FOREIGN KEY (created_by_customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_delivery_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_job_item_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(80) NULL,
  provider_message_id VARCHAR(190) NULL,
  event_type ENUM('accepted','delivered','deferred','bounced','rejected','complained','opened','clicked','unsubscribed','failed') NOT NULL,
  event_data JSON NULL,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_delivery_item (email_job_item_id,occurred_at),
  KEY idx_delivery_provider_id (provider,provider_message_id),
  CONSTRAINT fk_delivery_job_item FOREIGN KEY (email_job_item_id) REFERENCES email_job_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_suppressions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('email','sms','whatsapp') NOT NULL DEFAULT 'email',
  recipient_hash CHAR(64) NOT NULL,
  reason ENUM('unsubscribe','hard_bounce','complaint','manual','invalid_address') NOT NULL,
  source VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_channel_recipient_suppression (channel,recipient_hash),
  KEY idx_suppression_reason (reason,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inactive starter rules. Review copy, consent handling, and product eligibility before activation.
INSERT INTO automation_rules
(rule_key,name,trigger_event,delay_minutes,template_key,audience_type,conditions,stop_conditions,max_sends_per_customer,cooldown_days,is_active)
VALUES
('first_purchase_day_7','First purchase follow-up - day 7','order_completed',10080,'first-purchase-day-7','marketing',
 JSON_OBJECT('first_purchase_only',TRUE,'requires_email_consent',TRUE),
 JSON_OBJECT('newer_purchase',TRUE,'unresolved_support_case',TRUE,'unsubscribed',TRUE),1,30,0),
('first_purchase_day_15','First purchase follow-up - day 15','order_completed',21600,'first-purchase-day-15','marketing',
 JSON_OBJECT('first_purchase_only',TRUE,'requires_email_consent',TRUE),
 JSON_OBJECT('newer_purchase',TRUE,'unresolved_support_case',TRUE,'unsubscribed',TRUE),1,30,0),
('post_delivery_day_1','After-sales check-in - day 1','order_delivered',1440,'post-delivery-day-1','service',
 JSON_OBJECT('order_delivered',TRUE),
 JSON_OBJECT('unresolved_support_case',TRUE),1,0,0),
('post_delivery_day_7','After-sales check-in - day 7','order_delivered',10080,'post-delivery-day-7','service',
 JSON_OBJECT('order_delivered',TRUE),
 JSON_OBJECT('unresolved_support_case',TRUE),1,0,0)
ON DUPLICATE KEY UPDATE rule_key=VALUES(rule_key);

