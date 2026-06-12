<?php
/**
 * API Endpoint: تحديث حالة الطلب
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/woocommerce.php';

startSecureSession();

// يمكن للمدير أو الوكيل (الذي لديه صلاحية) التعديل
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin' && $role !== 'agent') {
    jsonError('غير مصرح لك بإجراء هذا التعديل', 403);
}

if ($role === 'agent' && !hasPermission('view_orders')) {
    jsonError('ليس لديك صلاحية لإدارة الطلبات', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

verifyCsrf();

$orderId = (int)($_POST['order_id'] ?? 0);
$status  = trim($_POST['status'] ?? '');

$allowedStatuses = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded'];

if (!$orderId || !in_array($status, $allowedStatuses)) {
    jsonError('بيانات غير صالحة', 400);
}

$db = db();

// التحقق من وجود الطلب في النظام المحلي
$stmt = $db->prepare("SELECT wc_order_id, status FROM orders WHERE wc_order_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    jsonError('الطلب غير موجود', 404);
}

if ($order['status'] === $status) {
    jsonError('حالة الطلب هي نفسها الحالية, لم يتم تغيير شيء', 400);
}

// الاتصال بـ WooCommerce لتحديث الطلب
$wc = new WooCommerceAPI();
$response = $wc->updateOrder($orderId, [
    'status' => $status
]);

if ($response === false) {
    jsonError('تعذر الاتصال بواجهة ووكوميرس لتحديث الطلب', 500);
}

// تحديث القاعدة المحلية
try {
    $db->beginTransaction();

    $updateStmt = $db->prepare("UPDATE orders SET status = ? WHERE wc_order_id = ?");
    $updateStmt->execute([$status, $orderId]);

    // تسجيل العملية
    $agentId = $_SESSION['user_id'] ?? $_SESSION['agent_id'] ?? null;
    $details = "قام بتغيير حالة الطلب #{$orderId} إلى '{$status}'";
    logAction('update_order', $details, (int)$agentId);

    $db->commit();
    jsonSuccess([], "تم تحديث حالة الطلب بنجاح إلى: $status");

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Update Order DB Error: " . $e->getMessage());
    jsonError('حدث خطأ في قاعدة البيانات أثناء حفظ التحديث', 500);
}
