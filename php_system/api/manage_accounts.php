<?php
// api/manage_accounts.php — إدارة حسابات ريزر (CRUD)
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

if (isset($data['payload'])) {
    $decrypted_data = decrypt_payload($data['payload']);
    if (!$decrypted_data) {
        http_response_code(403); die(json_encode(['error' => 'Decryption Failed']));
    }
    $data = $decrypted_data;
}

$token  = $data['token'] ?? ($_GET['token'] ?? '');

if ($token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}

try {
    // GET — قائمة الحسابات
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT a.*, b.status AS bot_status, b.last_heartbeat
            FROM razer_accounts a
            LEFT JOIN bot_workers b ON b.bot_id = a.bot_id
            ORDER BY a.id ASC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    $action = $data['action'] ?? '';

    // إضافة حساب جديد
    if ($action === 'add') {
        $label      = trim($data['label']      ?? '');
        $email      = trim($data['email']      ?? '');
        $password   = trim($data['password']   ?? '');
        $otp_secret = trim($data['otp_secret'] ?? '');
        $bot_id     = trim($data['bot_id']     ?? '') ?: null;
        $notes      = trim($data['notes']      ?? '');

        if (!$email || !$password || !$otp_secret) {
            http_response_code(400); die(json_encode(['error' => 'email, password, otp_secret required']));
        }

        $stmt = $pdo->prepare("
            INSERT INTO razer_accounts (label, email, password, otp_secret, bot_id, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$label ?: $email, $email, $password, $otp_secret, $bot_id, $notes]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // تعديل حساب
    if ($action === 'edit') {
        $id         = (int)($data['id'] ?? 0);
        $label      = trim($data['label']      ?? '');
        $email      = trim($data['email']      ?? '');
        $password   = trim($data['password']   ?? '');
        $otp_secret = trim($data['otp_secret'] ?? '');
        $bot_id     = trim($data['bot_id']     ?? '') ?: null;
        $notes      = trim($data['notes']      ?? '');
        $is_active  = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        $stmt = $pdo->prepare("
            UPDATE razer_accounts
            SET label=?, email=?, password=?, otp_secret=?, bot_id=?, notes=?, is_active=?
            WHERE id=?
        ");
        $stmt->execute([$label, $email, $password, $otp_secret, $bot_id, $notes, $is_active, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // حذف حساب
    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        $pdo->prepare("DELETE FROM razer_accounts WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // تحديث حالة الرصيد (يستدعيها البوت)
    if ($action === 'update_balance') {
        $bot_id        = trim($data['bot_id']        ?? '');
        $balance_status = $data['balance_status']   ?? 'unknown';
        $stmt = $pdo->prepare("UPDATE razer_accounts SET balance_status=? WHERE bot_id=?");
        $stmt->execute([$balance_status, $bot_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database Error']);
}
