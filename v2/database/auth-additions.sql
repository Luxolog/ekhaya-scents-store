-- Ekhaya v2 authentication additions; apply after schema.sql.
CREATE TABLE IF NOT EXISTS auth_rate_limits (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bucket_hash CHAR(64) NOT NULL, action VARCHAR(40) NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0, window_started_at DATETIME NOT NULL, blocked_until DATETIME NULL,
 UNIQUE KEY uq_auth_bucket_action (bucket_hash,action), KEY idx_auth_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS email_verification_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_email_verification_hash (token_hash), KEY idx_email_verification_customer (customer_id),
 KEY idx_email_verification_expiry (expires_at),
 CONSTRAINT fk_email_verification_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS password_reset_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_password_token_hash (token_hash), KEY idx_password_reset_customer (customer_id),
 KEY idx_password_reset_expiry (expires_at),
 CONSTRAINT fk_password_reset_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
