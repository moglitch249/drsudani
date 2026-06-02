<?php
// api/get_pending.php — v2: Atomic Lock + Idempotency (Layer 1 + 2)
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_GET;
$was_encrypted = false;

if (isset($data['payload'])) {
    $decrypted_data = decrypt_payload($data['payload']);
    if (!$decrypted_data) {
        http_response_code(403); die(json_encode(['error' => 'Decryption Failed']));
    }
    $data = $decrypted_data;
    $was_encrypted = true;
}

$token  = isset($data['token']) ? $data['token'] : '';
$bot_id = isset($data['bot_id']) ? $data['bot_id'] : 'unknown_bot';

if ($token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}

try {
    // التحقق من الإيقاف تم استبداله عن طريق تحديث أوامر البوتات مباشرة في النظام الجديد.

    // استعادة الطلبات العالقة التي لم تصل لصفحة الدفع (مات البوت قبل الـ Checkout)...
    $resetStmt = $pdo->prepare("
        UPDATE orders
        SET status = 'pending', locked_by = NULL, locked_at = NULL
        WHERE status = 'processing'
          AND requires_human = 0
          AND checkout_clicked = 0
          AND locked_at < NOW() - INTERVAL 10 MINUTE
    ");
    $resetStmt->execute();
    $resetCount = $resetStmt->rowCount();
    if ($resetCount > 0) {
        error_log("[Bot:{$bot_id}] استعادة {$resetCount} طلب عالق → pending");
    }

    // تحويل الطلبات العالقة التي تم الضغط فيها على الدفع إلى مراجعة يدوية (منع الشحن المزدوج)...
    $stuckStmt = $pdo->prepare("
        UPDATE orders
        SET status = 'stuck', requires_human = 1
        WHERE status = 'processing'
          AND checkout_clicked = 1
          AND locked_at < NOW() - INTERVAL 10 MINUTE
    ");
    $stuckStmt->execute();
    $stuckCount = $stuckStmt->rowCount();
    if ($stuckCount > 0) {
        error_log("[Bot:{$bot_id}] تحويل {$stuckCount} طلب عالق (تم الدفع فيه) → stuck");
    }

    // ────── Layer 1: High-Concurrency Atomic Lock ──────
    // استخدام UPDATE المباشر بدلاً من SELECT FOR UPDATE لمنع أي تضارب (Deadlocks) أثناء الضغط العالي
    $lockStmt = $pdo->prepare("
        UPDATE orders
        SET status = 'processing',
            locked_by = :bot_id,
            locked_at = NOW(),
            bot_assigned = :bot_id,
            dispatched_at = NOW()
        WHERE status = 'pending'
          AND (locked_by IS NULL OR locked_by = '')
        ORDER BY created_at ASC
        LIMIT 1
    ");
    $lockStmt->execute([':bot_id' => $bot_id]);

    if ($lockStmt->rowCount() === 0) {
        echo json_encode(array('success' => true, 'data' => null, 'message' => 'No pending orders'));
        exit;
    }

    // استرجاع الطلب الذي تم قفله للتو لهذا البوت
    $stmt = $pdo->prepare("
        SELECT * FROM orders
        WHERE bot_assigned = :bot_id
          AND status = 'processing'
        ORDER BY dispatched_at DESC
        LIMIT 1
    ");
    $stmt->execute([':bot_id' => $bot_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(array('success' => false, 'error' => 'Lock acquired but order not found'));
        exit;
    }

    $diamonds = isset($order['diamonds']) ? $order['diamonds'] : (isset($order['amount']) ? $order['amount'] : '100');
    $order['diamonds'] = $diamonds;
    
    $response_data = array('success' => true, 'data' => $order);
    if ($was_encrypted) {
        echo json_encode(['payload' => encrypt_payload($response_data)]);
    } else {
        echo json_encode($response_data);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    $err = ['error' => 'Database error', 'details' => 'Database Error'];
    if ($was_encrypted) {
        echo json_encode(['payload' => encrypt_payload($err)]);
    } else {
        echo json_encode($err);
    }
}
