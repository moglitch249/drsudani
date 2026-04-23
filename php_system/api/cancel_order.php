<?php
require_once '../config.php';

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
// We can use the same BOT_API_TOKEN as a simple admin authorization if needed, 
// but since this is currently an open dashboard, we just perform the action.
// You might add a password protection to your index.php later.

if (!$order_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid Order ID']));
}

try {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'failed', fail_reason = 'تم الإلغاء يدوياً من لوحة التحكم' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$order_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'تم إلغاء الطلب بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'لم يتم الإلغاء، ربما الطلب لم يعد قيد الانتظار']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . 'Database Error']);
}
