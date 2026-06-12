<?php
/**
 * API Endpoint: إعداد الجداول الجديدة للمرحلة 16
 * (Run this once to create the products and customers tables)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();

try {
    // No transactions here as DDL statements (CREATE TABLE) cause implicit commits in MySQL

    // 1. جدول المنتجات
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wc_product_id INT NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        stock_quantity INT NULL,
        stock_status VARCHAR(50) DEFAULT 'instock',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. جدول العملاء
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wc_customer_id INT NOT NULL UNIQUE,
        email VARCHAR(150) NOT NULL,
        first_name VARCHAR(100) DEFAULT '',
        last_name VARCHAR(100) DEFAULT '',
        total_spent DECIMAL(12,2) DEFAULT 0.00,
        orders_count INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. إضافة إعداد نسبة العمولة إن لم يكن موجوداً
    $stmt = $db->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES ('agent_commission_percent', '0')");
    $stmt->execute();
    
    // 4. إضافة إعداد حد المخزون المنخفض
    $stmt2 = $db->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES ('low_stock_threshold', '5')");
    $stmt2->execute();

    jsonSuccess([], "تم إنشاء وتحديث جداول قاعدة البيانات بنجاح للمرحلة 16.");

} catch (PDOException $e) {
    error_log("DB Setup v2 Error: " . $e->getMessage());
    jsonError('حدث خطأ أثناء إعداد قاعدة البيانات', 500);
}
