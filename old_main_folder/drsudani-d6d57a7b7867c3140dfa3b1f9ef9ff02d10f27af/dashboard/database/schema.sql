-- =============================================
-- لوحة التحكم - قاعدة البيانات
-- Dashboard System Database Schema
-- =============================================



-- ---------------------------------------------
-- الوكلاء والمديرين (مستخدمو النظام)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `agents` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(80)      NOT NULL UNIQUE,
  `password`     VARCHAR(255)     NOT NULL,
  `full_name`    VARCHAR(150)     NOT NULL,
  `email`        VARCHAR(150)     NOT NULL UNIQUE,
  `role`         ENUM('admin','agent') NOT NULL DEFAULT 'agent',
  `permissions`  JSON             NULL COMMENT 'e.g. {"view_orders":true,"add_transaction":true,"view_own_transactions_only":true}',
  `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
  `last_login`   DATETIME         NULL,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- مدير النظام الافتراضي — كلمة المرور: Admin@123
INSERT INTO `agents` (`username`, `password`, `full_name`, `email`, `role`, `permissions`, `is_active`)
VALUES (
  'admin',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@123
  'مدير النظام',
  'admin@dashboard.local',
  'admin',
  '{"view_orders":true,"add_transaction":true,"view_own_transactions_only":false}',
  1
);

-- وكيل تجريبي — كلمة المرور: Agent@123
INSERT INTO `agents` (`username`, `password`, `full_name`, `email`, `role`, `permissions`, `is_active`)
VALUES (
  'agent1',
  '$2y$12$TKh8H1.LeofqeuZ3IWjnOeB7qDxG/P7z7Fn.wHtDCb4QXdoFiCz6', -- Agent@123
  'أحمد الوكيل',
  'agent1@dashboard.local',
  'agent',
  '{"view_orders":true,"add_transaction":true,"view_own_transactions_only":true}',
  1
);

-- ---------------------------------------------
-- مستخدمو ووردبريس (مخزن مؤقت من API)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `wp_users_cache` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `wp_user_id`   INT UNSIGNED     NOT NULL UNIQUE COMMENT 'WordPress user ID',
  `username`     VARCHAR(100)     NOT NULL,
  `email`        VARCHAR(150)     NOT NULL,
  `full_name`    VARCHAR(200)     NULL,
  `phone`        VARCHAR(50)      NULL,
  `wallet_balance` DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
  `registered_at` DATETIME        NULL,
  `synced_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wp_user_id` (`wp_user_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- المعاملات المالية
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `wp_user_id`     INT UNSIGNED     NOT NULL COMMENT 'WordPress user ID',
  `agent_id`       INT UNSIGNED     NOT NULL COMMENT 'المشغل الذي أضاف المعاملة',
  `amount`         DECIMAL(15,2)    NOT NULL,
  `type`           ENUM('deposit','withdraw','adjustment') NOT NULL,
  `payment_method` ENUM('bank_transfer','wallet','cash')   NOT NULL,
  `notes`          TEXT             NULL,
  `receipt_image`  MEDIUMBLOB       NULL COMMENT 'صورة الإيصال - BLOB',
  `receipt_mime`   VARCHAR(30)      NULL COMMENT 'e.g. image/jpeg',
  `status`         ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'confirmed',
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wp_user_id` (`wp_user_id`),
  KEY `idx_agent_id`   (`agent_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_trans_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- طلبات ووكوميرس
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `wc_order_id`   INT UNSIGNED     NOT NULL UNIQUE,
  `wp_user_id`    INT UNSIGNED     NULL,
  `customer_name` VARCHAR(200)     NULL,
  `customer_email`VARCHAR(150)     NULL,
  `total`         DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `status`        VARCHAR(50)      NOT NULL DEFAULT 'pending',
  `payment_method`VARCHAR(100)     NULL,
  `items_count`   SMALLINT         NOT NULL DEFAULT 0,
  `raw_data`      JSON             NULL COMMENT 'Raw WooCommerce order JSON',
  `wc_created_at` DATETIME         NULL,
  `synced_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wc_order_id` (`wc_order_id`),
  KEY `idx_wp_user_id`  (`wp_user_id`),
  KEY `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- الإشعارات (طابور الوقت الفعلي)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `type`       ENUM('new_order','new_transaction','system') NOT NULL,
  `title`      VARCHAR(200)  NOT NULL,
  `body`       TEXT          NULL,
  `reference_id` INT UNSIGNED NULL COMMENT 'order_id or transaction_id',
  `target_role` ENUM('admin','agent','all') NOT NULL DEFAULT 'all',
  `target_agent_id` INT UNSIGNED NULL COMMENT 'NULL = broadcast',
  `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_read`    (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_target`     (`target_role`, `target_agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- سجلات النظام
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `agent_id`   INT UNSIGNED  NULL COMMENT 'NULL = system/guest action',
  `action`     VARCHAR(100)  NOT NULL,
  `details`    TEXT          NULL,
  `ip_address` VARCHAR(45)   NOT NULL,
  `user_agent` VARCHAR(500)  NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agent_id`   (`agent_id`),
  KEY `idx_action`     (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- الإعدادات العامة
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key_name`   VARCHAR(100) NOT NULL,
  `value`      TEXT         NULL,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key_name`, `value`) VALUES
  ('wc_last_sync', NULL),
  ('site_name', 'لوحة التحكم'),
  ('notification_sound', '1');
