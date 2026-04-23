-- ============================================================
-- MULTI-BOT ORCHESTRATION SYSTEM — DATABASE MIGRATION
-- قم بتشغيل هذا الملف مرة واحدة فقط على قاعدة البيانات الحالية
-- ============================================================

-- 1) تعديل جدول orders بإضافة الحقول الجديدة
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS locked_by        VARCHAR(50)   NULL,
    ADD COLUMN IF NOT EXISTS locked_at        TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS bot_assigned     VARCHAR(50)   NULL,
    ADD COLUMN IF NOT EXISTS dispatched_at    TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS completed_at     TIMESTAMP     NULL,
    ADD COLUMN IF NOT EXISTS duration_seconds INT           NULL,
    ADD COLUMN IF NOT EXISTS checkout_clicked TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS requires_human   TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS financial_risk   ENUM('none','low','high','critical') DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS woo_order_id     VARCHAR(100)  NULL,
    ADD COLUMN IF NOT EXISTS idempotency_key  VARCHAR(64)   NULL,
    ADD COLUMN IF NOT EXISTS fail_reason      TEXT          NULL;

-- 2) جدول حسابات ريزر (كل بوت له حساب منفصل)
CREATE TABLE IF NOT EXISTS razer_accounts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    bot_id       VARCHAR(50)  NULL UNIQUE COMMENT 'البوت المرتبط بهذا الحساب',
    label        VARCHAR(100) NOT NULL COMMENT 'اسم وصفي للحساب',
    email        VARCHAR(255) NOT NULL,
    password     VARCHAR(255) NOT NULL,
    otp_secret   VARCHAR(255) NOT NULL,
    balance_status ENUM('sufficient','low','insufficient','unknown') DEFAULT 'unknown',
    is_active    TINYINT(1)   DEFAULT 1,
    notes        TEXT         NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3) جدول البوتات وحالتها (يتم تحديثه كل 60 ثانية عبر heartbeat)
CREATE TABLE IF NOT EXISTS bot_workers (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    bot_id               VARCHAR(50)  NOT NULL UNIQUE,
    port                 INT          NOT NULL DEFAULT 5000,
    ip                   VARCHAR(50)  DEFAULT 'localhost',
    status               ENUM('online','offline','paused','busy','review_needed','draining') DEFAULT 'offline',
    capacity             INT          DEFAULT 1 COMMENT 'حد أقصى للطلبات المتزامنة',
    priority             INT          DEFAULT 1 COMMENT 'أولوية التوجيه (1=عادي, 2=عالي)',
    active_orders        INT          DEFAULT 0,
    orders_today         INT          DEFAULT 0,
    success_count        INT          DEFAULT 0,
    fail_count           INT          DEFAULT 0,
    success_rate         FLOAT        DEFAULT 100.0,
    consecutive_failures INT          DEFAULT 0,
    last_heartbeat       TIMESTAMP    NULL,
    session_start        TIMESTAMP    NULL,
    current_order_id     INT          NULL,
    pending_command      VARCHAR(50)  NULL COMMENT 'أمر معلق: pause, resume, drain, restart',
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4) جدول سجل أحداث البوتات
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

-- 5) جدول مفاتيح الـ Idempotency (Layer 2 - منع التكرار)
CREATE TABLE IF NOT EXISTS processed_keys (
    idem_key   VARCHAR(64)  PRIMARY KEY,
    order_id   INT          NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id)
);

-- 6) جدول إعدادات النظام
CREATE TABLE IF NOT EXISTS system_config (
    config_key   VARCHAR(100) PRIMARY KEY,
    config_value TEXT         NOT NULL,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إدراج الإعدادات الافتراضية
INSERT IGNORE INTO system_config (config_key, config_value) VALUES
('routing_strategy',   'least_active'),
('order_timeout_min',  '10'),
('heartbeat_interval', '60'),
('session_max_hours',  '6'),
('low_balance_alert',  '5'),
('system_paused',      '0'),
('webhook_dedup_min',  '60');

-- 7) Indexes للأداء
CREATE INDEX IF NOT EXISTS idx_orders_status_locked  ON orders (status, locked_by);
CREATE INDEX IF NOT EXISTS idx_orders_bot_assigned   ON orders (bot_assigned);
CREATE INDEX IF NOT EXISTS idx_orders_woo_order      ON orders (woo_order_id);
CREATE INDEX IF NOT EXISTS idx_orders_idem           ON orders (idempotency_key);
