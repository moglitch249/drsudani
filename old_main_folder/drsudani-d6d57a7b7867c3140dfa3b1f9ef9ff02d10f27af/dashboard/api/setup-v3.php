<?php
/**
 * API: إعداد الجداول - المرحلة 17 (تطوير إدارة المنتجات)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();

try {
    // أضف أعمدة جديدة لجدول المنتجات (IF NOT EXISTS باستخدام IGNORE)
    $columns = [
        "ALTER TABLE products ADD COLUMN image_url VARCHAR(500) NULL AFTER stock_status",
        "ALTER TABLE products ADD COLUMN category VARCHAR(200) NULL AFTER image_url",
        "ALTER TABLE products ADD COLUMN product_url VARCHAR(500) NULL AFTER category",
        "ALTER TABLE products ADD COLUMN internal_notes TEXT NULL AFTER product_url",
        "ALTER TABLE products ADD COLUMN low_stock_override INT NULL COMMENT 'Custom threshold per product' AFTER internal_notes",
    ];

    $added = 0;
    $skipped = 0;
    foreach ($columns as $sql) {
        try {
            $db->exec($sql);
            $added++;
        } catch (PDOException $e) {
            // Column already exists (duplicate column name error = 1060)
            if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
                $skipped++;
            } else {
                throw $e;
            }
        }
    }

    jsonSuccess(['added' => $added, 'skipped' => $skipped], "تم تحديث جدول المنتجات. $added عمود جديد، $skipped موجود مسبقاً.");

} catch (PDOException $e) {
    error_log("DB Setup v3 Error: " . $e->getMessage());
    jsonError('حدث خطأ: ' . $e->getMessage(), 500);
}
