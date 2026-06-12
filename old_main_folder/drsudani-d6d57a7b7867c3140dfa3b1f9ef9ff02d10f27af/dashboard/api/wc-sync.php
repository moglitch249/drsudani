<?php
/**
 * مزامنة طلبات ووكوميرس يدوياً (API)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/woocommerce.php';

startSecureSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

verifyCsrf();

$wc = new WooCommerceAPI();
$orders = $wc->getOrders(['status' => 'any', 'per_page' => 50]);

if ($orders === false) {
    jsonError('تعذر الاتصال بواجهة ووكوميرس البرمجية. تحقق من المفاتيح والاتصال.', 500);
}

$db = db();
$newCount = 0;
$updateCount = 0;

try {
    // محاولة إضافة العمود في حال لم يكن موجوداً
    $db->query("ALTER TABLE orders ADD COLUMN items_data LONGTEXT NULL");
} catch(PDOException $e) {
    // العمود موجود مسبقاً، لا مشكلة
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO orders 
           (wc_order_id, wp_user_id, customer_name, customer_email, total, items_count, payment_method, status, wc_created_at, items_data)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
           status = VALUES(status), 
           total = VALUES(total), 
           payment_method = VALUES(payment_method),
           items_data = VALUES(items_data)'
    );

    foreach ($orders as $o) {
        $orderId = $o['id'] ?? 0;
        if (!$orderId) continue;

        $customerId = $o['customer_id'] ?? null;
        $name       = trim(($o['billing']['first_name'] ?? '') . ' ' . ($o['billing']['last_name'] ?? ''));
        $email      = $o['billing']['email'] ?? '';
        $total      = (float)($o['total'] ?? 0);
        $itemsCount = count($o['line_items'] ?? []);
        $method     = $o['payment_method_title'] ?? '';
        $status     = $o['status'] ?? 'pending';
        // WC returns dates like: 2023-01-01T12:00:00
        $createdAt  = isset($o['date_created']) ? date('Y-m-d H:i:s', strtotime($o['date_created'])) : null;
        $itemsData  = json_encode($o['line_items'] ?? [], JSON_UNESCAPED_UNICODE);

        $stmt->execute([
            $orderId,
            $customerId,
            $name,
            $email,
            $total,
            $itemsCount,
            $method,
            $status,
            $createdAt,
            $itemsData
        ]);

        if ($stmt->rowCount() == 1) {
            $newCount++;
        } elseif ($stmt->rowCount() == 2) {
            $updateCount++;
        }
    }

    // تحديث كاشير وقت المزامنة
    $db->query("INSERT INTO settings (key_name, value) VALUES ('wc_last_sync', NOW()) ON DUPLICATE KEY UPDATE value = NOW()");

    logAction('sync_orders', "تم جلب/تحديث " . ($newCount + $updateCount) . " طلب من ووكوميرس.");
    
    // تنبيه المدراء إذا تم استيراد طلبات جديدة فقط
    if ($newCount > 0) {
        createNotification('new_order', 'طلبات جديدة', "تمت مزامنة $newCount طلبات جديدة من المتجر.", null, 'admin');
    }

    $db->commit();

    jsonSuccess([
        'new' => $newCount,
        'updated' => $updateCount
    ], "تمت المزامنة بنجاح. $newCount جديد، $updateCount محدّث.");

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Sync Orders DB Error: " . $e->getMessage());
    jsonError('حدث خطأ في قاعدة البيانات أثناء المزامنة.', 500);
}
