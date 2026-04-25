-- ============================================================
-- BOT ORCHESTRATOR — UNIFIED DATABASE SCHEMA
-- ============================================================

-- 1) Create the database
CREATE DATABASE IF NOT EXISTS freefire_bot_db;
USE freefire_bot_db;

-- 2) Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    woo_order_id     VARCHAR(100)  NULL UNIQUE,
    player_id        VARCHAR(50)   NOT NULL,
    product_name     VARCHAR(255)  NULL,
    diamonds         VARCHAR(20)   NOT NULL DEFAULT '100',
    amount           VARCHAR(50)   NULL,
    status           ENUM('pending', 'processing', 'completed', 'failed', 'manual_review', 'stuck') DEFAULT 'pending',
    locked_by        VARCHAR(50)   NULL,
    locked_at        TIMESTAMP     NULL,
    bot_assigned     VARCHAR(50)   NULL,
    dispatched_at    TIMESTAMP     NULL,
    completed_at     TIMESTAMP     NULL,
    duration_seconds INT           NULL,
    checkout_clicked TINYINT(1)    DEFAULT 0,
    requires_human   TINYINT(1)    DEFAULT 0,
    financial_risk   ENUM('none','low','high','critical','balance_changed') DEFAULT 'none',
    idempotency_key  VARCHAR(64)   NULL,
    fail_reason      TEXT          NULL,
    evidence_path    VARCHAR(255)  NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_bot (bot_assigned),
    INDEX idx_locked (locked_by)
);

-- 3) Razer Accounts Table
CREATE TABLE IF NOT EXISTS razer_accounts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    bot_id         VARCHAR(50)  NULL UNIQUE,
    label          VARCHAR(100) NOT NULL,
    email          VARCHAR(255) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    otp_secret     VARCHAR(255) NOT NULL,
    balance_status ENUM('sufficient','low','insufficient','unknown') DEFAULT 'unknown',
    is_active      TINYINT(1)   DEFAULT 1,
    notes          TEXT         NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4) Bot Workers Table
CREATE TABLE IF NOT EXISTS bot_workers (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    bot_id               VARCHAR(50)  NOT NULL UNIQUE,
    port                 INT          NOT NULL DEFAULT 5000,
    ip                   VARCHAR(50)  DEFAULT 'localhost',
    status               ENUM('online','offline','paused','busy','review_needed','draining','error_paused') DEFAULT 'offline',
    balance_status       ENUM('sufficient','low','insufficient','unknown') DEFAULT 'unknown',
    capacity             INT          DEFAULT 1,
    priority             INT          DEFAULT 1,
    active_orders        INT          DEFAULT 0,
    orders_today         INT          DEFAULT 0,
    success_count        INT          DEFAULT 0,
    fail_count           INT          DEFAULT 0,
    success_rate         FLOAT        DEFAULT 100.0,
    consecutive_failures INT          DEFAULT 0,
    last_heartbeat       TIMESTAMP    NULL,
    session_start        TIMESTAMP    NULL,
    current_order_id     INT          NULL,
    pending_command      VARCHAR(50)  NULL COMMENT 'pause, resume, drain, restart',
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5) Bot Action Logs Table
CREATE TABLE IF NOT EXISTS bot_action_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    bot_id     VARCHAR(50)  NOT NULL,
    order_id   INT          NULL,
    action     VARCHAR(255) NOT NULL,
    result     VARCHAR(255) NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bot_order (bot_id, order_id),
    INDEX idx_created  (created_at)
);

-- 6) Idempotency Keys Table
CREATE TABLE IF NOT EXISTS processed_keys (
    idem_key   VARCHAR(64)  PRIMARY KEY,
    order_id   INT          NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id)
);

-- 7) System Configuration Table
CREATE TABLE IF NOT EXISTS system_config (
    config_key   VARCHAR(100) PRIMARY KEY,
    config_value TEXT         NOT NULL,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default Settings
INSERT IGNORE INTO system_config (config_key, config_value) VALUES
('routing_strategy',   'least_active'),
('order_timeout_min',  '10'),
('heartbeat_interval', '60'),
('session_max_hours',  '6'),
('low_balance_alert',  '5'),
('system_paused',      '0'),
('webhook_dedup_min',  '60');
