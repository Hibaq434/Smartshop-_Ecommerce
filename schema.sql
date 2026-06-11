-- Smart eCommerce database schema
-- Import this file in phpMyAdmin (SQL tab → Import or paste in SQL tab)

CREATE DATABASE IF NOT EXISTS smart_ecommerce
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE smart_ecommerce;

-- ─────────────────────────────────────────
-- Products table
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  quantity INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_products_name (product_name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Sample products
INSERT INTO products (product_name, price, quantity) VALUES
('Smart Watch', 79.99, 12),
('Wireless Headphones', 49.50, 20),
('Portable Speaker', 29.99, 15)
ON DUPLICATE KEY UPDATE
  product_name = VALUES(product_name),
  price        = VALUES(price),
  quantity     = VALUES(quantity);


-- ─────────────────────────────────────────
-- Users table  (role-based authentication)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username     VARCHAR(100)  NOT NULL,
  email        VARCHAR(255)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role         ENUM('user','admin') NOT NULL DEFAULT 'user',
  full_name    VARCHAR(255)  DEFAULT NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY   uq_username (username),
  UNIQUE KEY   uq_email    (email),
  KEY          idx_role     (role)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- NOTE: Passwords are bcrypt-hashed.
-- After importing this schema, visit:
--   http://localhost/smartecomerce/setup_users.php
-- That script will create the default accounts:
--
--   Admin  → username: admin     password: Admin@123
--   User   → username: john      password: User@123
-- ─────────────────────────────────────────
