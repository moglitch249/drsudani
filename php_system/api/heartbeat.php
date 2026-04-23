<?php
// api/heartbeat.php — يستقبل نبض من كل بوت كل 60 ثانية
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

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);
$was_encrypted = false;

if (isset($data['payload'])) {
    $decrypted_data = decrypt_payload($data['payload']);
    if (!$decrypted_data) {
        http_response_code(403); die(json_encode(['error' => 'Decryption Failed']));
    }
    $data = $decrypted_data;
    $was_encrypted = true;
}

$token           = isset($data['token']) ? $data['token'] : '';
if (!$data || $token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}

$bot_id          = isset($data['bot_id']) ? trim($data['bot_id']) : '';
$port            = isset($data['port']) ? (int)$data['port'] : 5000;
$status          = isset($data['status']) ? $data['status'] : 'online';
$balance_status  = isset($data['balance_status']) ? $data['balance_status'] : 'unknown';
$active_orders   = isset($data['active_orders']) ? (int)$data['active_orders'] : 0;
$orders_today    = isset($data['orders_today']) ? (int)$data['orders_today'] : 0;
$success_count   = isset($data['success_count']) ? (int)$data['success_count'] : 0;
$fail_count      = isset($data['fail_count']) ? (int)$data['fail_count'] : 0;
$success_rate    = isset($data['success_rate']) ? (float)$data['success_rate'] : 100.0;
$consec_failures = isset($data['consecutive_failures']) ? (int)$data['consecutive_failures'] : 0;
$session_start   = isset($data['session_start']) ? $data['session_start'] : null;
$current_order   = isset($data['current_order_id']) ? $data['current_order_id'] : null;

if (!$bot_id) {
    http_response_code(400); die(json_encode(['error' => 'bot_id required']));
}

try {
    // إنشاء أو تحديث سجل البوت
    $stmt = $pdo->prepare("
        INSERT INTO bot_workers 
            (bot_id, port, status, balance_status, active_orders, orders_today,
             success_count, fail_count, success_rate, consecutive_failures,
             last_heartbeat, session_start, current_order_id, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            port                 = VALUES(port),
            status               = VALUES(status),
            balance_status       = VALUES(balance_status),
            active_orders        = VALUES(active_orders),
            orders_today         = VALUES(orders_today),
            success_count        = VALUES(success_count),
            fail_count           = VALUES(fail_count),
            success_rate         = VALUES(success_rate),
            consecutive_failures = VALUES(consecutive_failures),
            last_heartbeat       = NOW(),
            session_start        = IF(session_start IS NULL, VALUES(session_start), session_start),
            current_order_id     = VALUES(current_order_id),
            updated_at           = NOW()
    ");
    $stmt->execute(array(
        $bot_id, $port, $status, $balance_status, $active_orders, $orders_today,
        $success_count, $fail_count, $success_rate, $consec_failures,
        $session_start, $current_order
    ));

    // تنبيه: رصيد منخفض
    if ($balance_status === 'insufficient') {
        error_log("[ALERT] Bot {$bot_id} — INSUFFICIENT BALANCE! Action required.");
    }
    if ($consec_failures >= 3) {
        error_log("[ALERT] Bot {$bot_id} — {$consec_failures} consecutive failures!");
    }

    // اقرأ أي أمر معلق لهذا البوت (pause, drain, restart, resume)
    $cmdStmt = $pdo->prepare("SELECT pending_command FROM bot_workers WHERE bot_id = ?");
    $cmdStmt->execute(array($bot_id));
    $row = $cmdStmt->fetch(PDO::FETCH_ASSOC);
    $pending_command = isset($row['pending_command']) ? $row['pending_command'] : null;

    // امسح الأمر بعد إرساله
    if ($pending_command) {
        $pdo->prepare("UPDATE bot_workers SET pending_command = NULL WHERE bot_id = ?")
            ->execute(array($bot_id));
    }

    // الفحص إذا كان الطلب الجاري تم إلغاؤه أو تغييره من لوحة التحكم
    $abort_order = false;
    if ($current_order) {
        $chk = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $chk->execute(array($current_order));
        $st = $chk->fetchColumn();
        if ($st && $st !== 'processing') {
            $abort_order = true;
        }
    }

    $response_data = array(
        'success'         => true,
        'pending_command' => $pending_command,
        'abort_order'     => $abort_order,
        'timestamp'       => date('c')
    );
    if ($was_encrypted) {
        echo json_encode(['payload' => encrypt_payload($response_data)]);
    } else {
        echo json_encode($response_data);
    }

} catch (PDOException $e) {
    http_response_code(500);
    $err = ['error' => 'DB error', 'details' => 'Database Error'];
    if ($was_encrypted) {
        echo json_encode(['payload' => encrypt_payload($err)]);
    } else {
        echo json_encode($err);
    }
}
