<?php
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$data = json_decode(file_get_contents("php://input"), true);

// ── Authentication ──
$token = isset($data['token']) ? $data['token'] : '';
if (!$data || $token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$order_id = isset($data['id']) ? (int)$data['id'] : 0;
$action = isset($data['action']) ? trim($data['action']) : '';

if (!$order_id || empty($action)) {
    die(json_encode(['success' => false, 'message' => 'بيانات مفقودة.']));
}

// دالة مساعدة لإرسال تحديث الحالة لـ WooCommerce
function sync_to_woocommerce($pdo, $local_order_id, $woo_status, $customer_note = '') {
    try {
        $stmtGet = $pdo->prepare("SELECT woo_order_id FROM orders WHERE id = ?");
        $stmtGet->execute([$local_order_id]);
        $row = $stmtGet->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['woo_order_id'])) return;

        $woo_order_id = $row['woo_order_id'];
        $updateData = ['status' => $woo_status];
        if (!empty($customer_note)) $updateData['customer_note'] = $customer_note;

        $url = WOO_STORE_URL . '/wp-json/wc/v3/orders/' . $woo_order_id;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_USERPWD, WOO_CONSUMER_KEY . ":" . WOO_CONSUMER_SECRET);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {
        // تجاهل أخطاء WooCommerce - تحديث اللوحة أهم
    }
}

try {
    $stmt = null;
    $success_message = '';
    $woo_status = null;
    $woo_note = '';

    switch ($action) {
        case 'cancel':
            $stmt = $pdo->prepare("UPDATE orders SET status = 'failed', fail_reason = 'تم الإلغاء يدوياً من الإدارة' WHERE id = ?");
            $success_message = 'تم إلغاء الطلب وإبلاغ العميل.';
            $woo_status = 'cancelled';
            $woo_note = 'تم إلغاء طلبك يدوياً من قِبل الإدارة. تواصل معنا للمساعدة.';
            break;
            
        case 'retry':
            // تنظيف شامل لكل بيانات المعالجة السابقة لضمان التقاطه من البوت الجديد كطلب جديد تماماً
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'pending', 
                    fail_reason = NULL,
                    locked_by = NULL,
                    locked_at = NULL,
                    bot_assigned = NULL,
                    checkout_clicked = 0,
                    requires_human = 0,
                    financial_risk = 'none'
                WHERE id = ? AND status != 'completed' AND status != 'processing'
            ");
            $success_message = 'تمت إعادته لطابور الانتظار، سيحاول البوت شحنه قريباً.';
            // لا نُحدّث WooCommerce عند إعادة المحاولة - الطلب لا يزال قيد المعالجة
            break;

        case 'complete':
            $stmt = $pdo->prepare("UPDATE orders SET status = 'completed', fail_reason = 'تم الشحن يدوياً عبر الإدارة' WHERE id = ?");
            $success_message = 'تم تعليم الطلب كمكتمل وإبلاغ العميل.';
            $woo_status = 'completed';
            $woo_note = 'تم شحن جواهرك بنجاح يدوياً من قِبل الإدارة.';
            break;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $success_message = 'تم محو الطلب من قاعدة البيانات نهائياً.';
            break;

        default:
            die(json_encode(['success' => false, 'message' => 'إجراء غير معروف.']));
    }

    if ($stmt) {
        $stmt->execute([$order_id]);
        if ($stmt->rowCount() > 0) {
            // مزامنة مع WooCommerce إذا كان الإجراء يستدعي ذلك
            if ($woo_status) {
                sync_to_woocommerce($pdo, $order_id, $woo_status, $woo_note);
            }
            echo json_encode(['success' => true, 'message' => $success_message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم تطبيق الإجراء، ربما تم حذفه مسبقاً.']);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . 'Database Error']);
}
