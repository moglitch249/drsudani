<?php
// api/manage_bot.php — إدارة البوتات (pause, resume, drain, command, add, remove)
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $data['token'] ?? ($_GET['token'] ?? '');

if ($token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}

$action = trim($data['action'] ?? '');
$bot_id = trim($data['bot_id'] ?? '');

try {

    // ── إضافة بوت جديد ──
    if ($action === 'add') {
        $port     = (int)($data['port'] ?? 5000);
        $ip       = $data['ip']       ?? 'localhost';
        $capacity = (int)($data['capacity'] ?? 1);
        $priority = (int)($data['priority'] ?? 1);

        if (!$bot_id) { http_response_code(400); die(json_encode(['error' => 'bot_id required'])); }

        $stmt = $pdo->prepare("
            INSERT INTO bot_workers (bot_id, port, ip, capacity, priority, status)
            VALUES (?, ?, ?, ?, ?, 'offline')
            ON DUPLICATE KEY UPDATE port=VALUES(port), ip=VALUES(ip), capacity=VALUES(capacity), priority=VALUES(priority)
        ");
        $stmt->execute([$bot_id, $port, $ip, $capacity, $priority]);
        echo json_encode(['success' => true, 'message' => "Bot {$bot_id} registered. Run: python freefire_bot.py {$bot_id} {$port}"]);
        exit;
    }

    // ── إزالة بوت ──
    if ($action === 'remove') {
        $pdo->prepare("DELETE FROM bot_workers WHERE bot_id=?")->execute([$bot_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── إرسال أمر للبوت عبر pending_command (يُقرأ في الـ heartbeat) ──
    $allowed_commands = ['pause', 'resume', 'drain', 'restart'];
    if (in_array($action, $allowed_commands)) {
        if (!$bot_id) { http_response_code(400); die(json_encode(['error' => 'bot_id required'])); }

        $stmt = $pdo->prepare("UPDATE bot_workers SET pending_command=? WHERE bot_id=?");
        $stmt->execute([$action, $bot_id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404); die(json_encode(['error' => "Bot '{$bot_id}' not found"]));
        }
        echo json_encode(['success' => true, 'message' => "Command '{$action}' queued for {$bot_id}"]);
        exit;
    }

    // ── Emergency Stop (كل البوتات) ──
    if ($action === 'emergency_stop') {
        $pdo->exec("UPDATE bot_workers SET pending_command='pause' WHERE status NOT IN ('offline')");
        // كل الطلبات قيد المعالجة بدون checkout_clicked تُعاد لـ pending
        $pdo->exec("
            UPDATE orders SET status='manual_review', requires_human=1
            WHERE status='processing' AND checkout_clicked=1
        ");
        $pdo->exec("
            UPDATE orders SET status='pending', locked_by=NULL, locked_at=NULL
            WHERE status='processing' AND checkout_clicked=0
        ");
        echo json_encode(['success' => true, 'message' => 'Emergency stop sent to all bots']);
        exit;
    }

    // ── Global Pause ──
    if ($action === 'global_pause') {
        $pdo->exec("UPDATE system_config SET config_value='1' WHERE config_key='system_paused'");
        $pdo->exec("UPDATE bot_workers SET pending_command='pause' WHERE status NOT IN ('offline')");
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Global Resume ──
    if ($action === 'global_resume') {
        $pdo->exec("UPDATE system_config SET config_value='0' WHERE config_key='system_paused'");
        $pdo->exec("UPDATE bot_workers SET pending_command='resume' WHERE status='paused'");
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Force Complete (يتطلب كلمة سر) ──
    if ($action === 'force_complete') {
        $order_id = (int)($data['order_id'] ?? 0);
        $password = $data['admin_password'] ?? '';
        $reason   = trim($data['reason'] ?? '');

        if ($password !== ADMIN_PASSWORD) {
            http_response_code(403); die(json_encode(['error' => 'Invalid admin password']));
        }
        if (!$reason) {
            http_response_code(400); die(json_encode(['error' => 'Written reason required']));
        }

        $pdo->prepare("
            UPDATE orders
            SET status='completed', requires_human=0, fail_reason=CONCAT('[FORCE] ', ?),
                completed_at=NOW(), duration_seconds=TIMESTAMPDIFF(SECOND, dispatched_at, NOW())
            WHERE id=?
        ")->execute([$reason, $order_id]);

        $pdo->prepare("INSERT INTO bot_action_logs (bot_id, order_id, action, result) VALUES ('admin', ?, 'Force Complete', ?)")
            ->execute([$order_id, $reason]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ── Reset to Pending ──
    if ($action === 'reset_pending') {
        $order_id = (int)($data['order_id'] ?? 0);
        $pdo->prepare("
            UPDATE orders
            SET status='pending', locked_by=NULL, locked_at=NULL, bot_assigned=NULL,
                checkout_clicked=0, requires_human=0, financial_risk='none'
            WHERE id=? AND status NOT IN ('completed')
        ")->execute([$order_id]);

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database Error']);
}
