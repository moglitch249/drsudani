<?php
// api/log_action.php — يستقبل سجل الأحداث من البوت
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); die(json_encode(['error' => 'Method Not Allowed']));
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['payload'])) {
    $decrypted_data = decrypt_payload($data['payload']);
    if (!$decrypted_data) {
        http_response_code(403); die(json_encode(['error' => 'Decryption Failed']));
    }
    $data = $decrypted_data;
}

if (!$data || ($data['token'] ?? '') !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}

$bot_id   = trim($data['bot_id']   ?? '');
$order_id = $data['order_id'] ? (int)$data['order_id'] : null;
$action   = trim($data['action']   ?? '');
$result   = trim($data['result']   ?? '');

if (!$bot_id || !$action) {
    http_response_code(400); die(json_encode(['error' => 'bot_id and action required']));
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO bot_action_logs (bot_id, order_id, action, result)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$bot_id, $order_id, $action, $result]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database Error']);
}
