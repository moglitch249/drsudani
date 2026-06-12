<?php
/**
 * API Endpoint: حفظ إعدادات النظام (شعار، عملة، إلخ)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

verifyCsrf();

$logoUrl        = clean($_POST['logo_url'] ?? '');
$currencySymbol = clean($_POST['currency_symbol'] ?? 'ر.س');
$commission     = floatval($_POST['agent_commission_percent'] ?? 0);
$threshold      = intval_safe($_POST['low_stock_threshold'] ?? 5);

if (empty($currencySymbol)) {
    jsonError('يرجى تحديد رمز العملة', 400);
}

$db = db();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    
    // حفظ الشعار
    $stmt->execute(['logo_url', $logoUrl]);
    
    // حفظ رمز العملة
    $stmt->execute(['currency_symbol', $currencySymbol]);

    // حفظ نسبة عمولة الوكيل وحد المخزون
    $stmt->execute(['agent_commission_percent', (string)$commission]);
    $stmt->execute(['low_stock_threshold', (string)$threshold]);

    logAction('update_settings', "تحديث إعدادات النظام (الشعار: $logoUrl, العملة: $currencySymbol, النسبة: $commission, المخزون: $threshold)");

    $db->commit();
    jsonSuccess([], "تم حفظ الإعدادات بنجاح");

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Settings Update Error: " . $e->getMessage());
    jsonError('حدث خطأ أثناء حفظ الإعدادات', 500);
}
