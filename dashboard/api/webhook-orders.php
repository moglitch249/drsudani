<?php
/**
 * ووكوميرس ويبهوك لاستقبال الطلبات الجديدة
 * يرسل له ووكوميرس POST payload عندما يتغير طلب (مثلا: order.created أو order.updated)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

// نحن لا نستخدم الجلسات هنا لأن المتصل هو خادم، ولا نستخدم CSRF!
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// 1. قراءة البيّانات من ووكوميرس (JSON Payload)
$payload = file_get_contents('php://input');
$order = json_decode($payload, true);

if (!$order || !isset($order['id'])) {
    http_response_code(400);
    exit('Invalid payload');
}

// 2. التحقق من توقيع WooCommerce Webhook
// الويبهوك بدون توقيع مصدر خطر كبير (أي شخص يستطيع حقن طلبات مزيفة!)
$webhookSecret = defined('WC_WEBHOOK_SECRET') ? WC_WEBHOOK_SECRET : '';
if (!empty($webhookSecret)) {
    $incomingSignature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '';
    if (empty($incomingSignature)) {
        http_response_code(401);
        exit('Unsigned webhook request rejected.');
    }
    $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookSecret, true));
    if (!hash_equals($expectedSignature, $incomingSignature)) {
        http_response_code(401);
        exit('Invalid webhook signature.');
    }
}

$db = db();

try {
    $orderId    = (int)$order['id'];
    $customerId = $order['customer_id'] ?? null;
    $name       = trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? ''));
    $email      = $order['billing']['email'] ?? '';
    $total      = (float)($order['total'] ?? 0);
    $itemsCount = count($order['line_items'] ?? []);
    $method     = $order['payment_method_title'] ?? '';
    $status     = $order['status'] ?? 'pending';
    $createdAt  = isset($order['date_created']) ? date('Y-m-d H:i:s', strtotime($order['date_created'])) : null;

    // 3. إدراج أو تحديث في قاعدة البيانات
    $stmt = $db->prepare(
        'INSERT INTO orders 
           (wc_order_id, wp_user_id, customer_name, customer_email, total, items_count, payment_method, status, wc_created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
           status = VALUES(status), 
           total = VALUES(total), 
           payment_method = VALUES(payment_method)'
    );

    $stmt->execute([
        $orderId, $customerId, $name, $email, $total, $itemsCount, $method, $status, $createdAt
    ]);

    $isNew = $stmt->rowCount() == 1;

    // 4. إنشاء إشعار إذا كان الطلب جديداً
    if ($isNew && in_array($status, ['pending', 'processing', 'on-hold'])) {
        createNotification(
            'new_order',
            "طلب جديد #{$orderId}",
            "مبلغ {$total} من العميل {$name}",
            $orderId,
            'admin' // يرى الإشعار جميع المدراء
        );
        // إنشاء إشعار لجميع الوكلاء المسموح لهم بـ view_orders
        createNotification(
            'new_order',
            "طلب متاح #{$orderId}",
            "متاح للمعالجة من {$name}",
            $orderId,
            'agent'
        );
    }

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Webhook DB Error: " . $e->getMessage());
    http_response_code(500);
    exit('Database error');
}
