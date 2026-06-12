<?php
/**
 * API: إعداد نظام تقييم المحادثات
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();

try {
    // إضافة أعمدة التقييم لجدول المحادثات
    $sqls = [
        "ALTER TABLE chat_sessions ADD COLUMN IF NOT EXISTS department VARCHAR(50) DEFAULT 'support' AFTER agent_id",
        "ALTER TABLE chat_sessions ADD COLUMN IF NOT EXISTS rating INT NULL DEFAULT NULL AFTER status",
        "ALTER TABLE chat_sessions ADD COLUMN IF NOT EXISTS rating_comment TEXT NULL AFTER rating"
    ];

    foreach ($sqls as $sql) {
        $db->exec($sql);
    }

    jsonSuccess([], "تم تحديث جدول المحادثات بنجاح.");

} catch (PDOException $e) {
    jsonError("خطأ في تحديث الجدول: " . $e->getMessage(), 500);
}
