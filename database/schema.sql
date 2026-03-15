-- Purex Chemicals Database Schema
-- Run this in phpMyAdmin SQL tab

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+05:00";

-- ==================== USERS ====================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin','editor') DEFAULT 'editor',
  `active` TINYINT(1) DEFAULT 1,
  `permissions` JSON,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== PRODUCTS ====================
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `volume` VARCHAR(50),
  `category` VARCHAR(50) NOT NULL,
  `price` INT NOT NULL DEFAULT 0,
  `buy_price` INT NOT NULL DEFAULT 0,
  `stock` INT NOT NULL DEFAULT 0,
  `capacity` INT NOT NULL DEFAULT 100,
  `status` VARCHAR(20) DEFAULT 'active',
  `on_sale` TINYINT(1) DEFAULT 0,
  `sale_price` INT DEFAULT 0,
  `description` TEXT,
  `image` VARCHAR(255),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ORDERS ====================
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30),
  `email` VARCHAR(150),
  `city` VARCHAR(100),
  `address` TEXT,
  `notes` TEXT,
  `item_count` INT DEFAULT 0,
  `total` INT DEFAULT 0,
  `status` VARCHAR(20) DEFAULT 'pending',
  `channel` VARCHAR(30) DEFAULT 'WhatsApp',
  `order_date` DATE,
  `corrected` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ORDER ITEMS ====================
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT,
  `product_name` VARCHAR(200),
  `sku` VARCHAR(50),
  `volume` VARCHAR(50),
  `price` INT NOT NULL DEFAULT 0,
  `buy_price` INT NOT NULL DEFAULT 0,
  `qty` INT NOT NULL DEFAULT 1,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== ORDER COUNTER ====================
CREATE TABLE IF NOT EXISTS `order_counter` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `counter` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `order_counter` (`id`, `counter`) VALUES (1, 0);

-- ==================== DAILY SALES ====================
CREATE TABLE IF NOT EXISTS `daily_sales` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT DEFAULT NULL,
  `product_id` INT,
  `product_name` VARCHAR(200),
  `sku` VARCHAR(50),
  `volume` VARCHAR(50),
  `category` VARCHAR(50),
  `qty` INT NOT NULL DEFAULT 1,
  `unit_price` INT NOT NULL DEFAULT 0,
  `buy_price` INT NOT NULL DEFAULT 0,
  `total` INT NOT NULL DEFAULT 0,
  `profit` INT NOT NULL DEFAULT 0,
  `sale_date` DATE NOT NULL,
  `channel` VARCHAR(30) DEFAULT 'WhatsApp',
  `customer_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(150) DEFAULT NULL,
  `transaction_id` INT DEFAULT NULL,
  `recorded_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== CUSTOMERS ====================
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30),
  `email` VARCHAR(150),
  `city` VARCHAR(100),
  `area` VARCHAR(100) DEFAULT NULL,
  `address` TEXT,
  `company` VARCHAR(150),
  `type` VARCHAR(30) DEFAULT 'Walk-in',
  `notes` TEXT,
  `order_count` INT DEFAULT 0,
  `total_spent` INT DEFAULT 0,
  `first_order` DATE NULL,
  `last_order` DATE NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== SUPPLIERS ====================
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(150),
  `products` TEXT,
  `address` TEXT,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== SUPPLIER INVOICES ====================
CREATE TABLE IF NOT EXISTS `supplier_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `supplier_id` INT NOT NULL,
  `supplier_name` VARCHAR(150),
  `amount` INT NOT NULL DEFAULT 0,
  `description` TEXT,
  `photo_path` VARCHAR(255),
  `status` VARCHAR(20) DEFAULT 'draft',
  `added_by` VARCHAR(100),
  `last_edited_by` VARCHAR(100),
  `last_edited_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== SUPPLIER INVOICE ITEMS ====================
CREATE TABLE IF NOT EXISTS `supplier_invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `product_id` INT DEFAULT NULL,
  `description` VARCHAR(255),
  `qty` INT DEFAULT 1,
  `unit_price` INT DEFAULT 0,
  `total` INT DEFAULT 0,
  FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== SALE TRANSACTIONS (POS multi-item) ====================
CREATE TABLE IF NOT EXISTS `sale_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(150),
  `customer_phone` VARCHAR(30),
  `item_count` INT DEFAULT 0,
  `subtotal` INT DEFAULT 0,
  `discount` INT DEFAULT 0,
  `total` INT DEFAULT 0,
  `profit` INT DEFAULT 0,
  `channel` VARCHAR(30) DEFAULT 'Walk-in',
  `payment_method` VARCHAR(30) DEFAULT 'Cash',
  `notes` TEXT,
  `sale_date` DATE NOT NULL,
  `recorded_by` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== TRANSACTION COUNTER ====================
CREATE TABLE IF NOT EXISTS `transaction_counter` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `counter` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `transaction_counter` (`id`, `counter`) VALUES (1, 0);

-- ==================== ACTIVITY LOG ====================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_name` VARCHAR(100),
  `user_role` VARCHAR(20),
  `action` VARCHAR(200) NOT NULL,
  `detail` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== CONTACTS ====================
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30),
  `email` VARCHAR(150),
  `category` VARCHAR(50),
  `order_number` VARCHAR(30),
  `message` TEXT,
  `photo_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
