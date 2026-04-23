-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS freefire_bot_db;
USE freefire_bot_db;

-- Table to store orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    woo_order_id VARCHAR(50) NOT NULL UNIQUE,
    player_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NULL,
    diamonds VARCHAR(20) NOT NULL DEFAULT '100',
    amount VARCHAR(50) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    fail_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Migration: Add new columns if table already exists
ALTER TABLE orders ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) NULL AFTER player_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS diamonds VARCHAR(20) NOT NULL DEFAULT '100' AFTER product_name;
