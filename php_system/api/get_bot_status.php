<?php
// api/get_bot_status.php — يُعيد بيانات كل البوتات + الطلبات الحالية للداشبورد
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
if ($token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}

try {
    // ── البوتات ──
    $botsStmt = $pdo->query("
        SELECT b.*,
               TIMESTAMPDIFF(MINUTE, b.last_heartbeat, NOW())  AS mins_since_heartbeat,
               TIMESTAMPDIFF(MINUTE, b.session_start, NOW())   AS session_age_minutes,
               a.email AS razer_email,
               a.balance_status AS account_balance_status
        FROM bot_workers b
        LEFT JOIN razer_accounts a ON a.bot_id = b.bot_id
        ORDER BY b.bot_id ASC
    ");
    $bots = $botsStmt->fetchAll(PDO::FETCH_ASSOC);

    // تحديث حالة البوتات التي لم تُرسل heartbeat منذ أكثر من 2 دقيقة
    $pdo->exec("
        UPDATE bot_workers
        SET status = 'offline'
        WHERE last_heartbeat < NOW() - INTERVAL 2 MINUTE
          AND status NOT IN ('offline','paused')
    ");

    // ── الطلبات الحية ──
    $ordersStmt = $pdo->query("
        SELECT id, player_id, diamonds, status, bot_assigned, locked_by,
               checkout_clicked, requires_human, financial_risk,
               created_at, dispatched_at, updated_at,
               TIMESTAMPDIFF(SECOND, dispatched_at, NOW()) AS elapsed_seconds,
               fail_reason
        FROM orders
        WHERE status IN ('pending','processing','manual_review','stuck')
           OR (status IN ('completed','failed') AND updated_at > NOW() - INTERVAL 1 HOUR)
        ORDER BY
            CASE status
                WHEN 'manual_review' THEN 1
                WHEN 'stuck'         THEN 2
                WHEN 'processing'    THEN 3
                WHEN 'pending'       THEN 4
                ELSE 5
            END,
            created_at ASC
        LIMIT 100
    ");
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Risk Panel ──
    $riskStmt = $pdo->query("
        SELECT
            SUM(CASE WHEN status = 'manual_review' THEN 1 ELSE 0 END) AS manual_review_count,
            SUM(CASE WHEN status = 'manual_review' THEN CAST(diamonds AS UNSIGNED) ELSE 0 END) AS manual_review_diamonds,
            SUM(CASE WHEN status = 'processing' AND dispatched_at < NOW() - INTERVAL 10 MINUTE THEN 1 ELSE 0 END) AS stuck_count,
            SUM(CASE WHEN checkout_clicked = 1 AND status NOT IN ('completed','failed') THEN 1 ELSE 0 END) AS checkout_uncertain_count
        FROM orders
    ");
    $risk = $riskStmt->fetch(PDO::FETCH_ASSOC);

    $insufficientBots = array_filter($bots, fn($b) => $b['account_balance_status'] === 'insufficient');

    // ── إحصائيات اليوم ──
    $todayStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_today,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_today,
            SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed_today,
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS queue_depth,
            AVG(CASE WHEN status = 'completed' THEN duration_seconds END) AS avg_duration
        FROM orders
        WHERE DATE(created_at) = CURDATE()
    ");
    $today = $todayStmt->fetch(PDO::FETCH_ASSOC);

    $totalToday    = (int)($today['total_today'] ?? 0);
    $completedToday = (int)($today['completed_today'] ?? 0);
    $successRate   = $totalToday > 0 ? round($completedToday / $totalToday * 100, 1) : 100;

    // ── آخر سجلات الأحداث ──
    $logsStmt = $pdo->query("
        SELECT bot_id, order_id, action, result, created_at
        FROM bot_action_logs
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'bots'    => array_values($bots),
        'orders'  => $orders,
        'risk'    => [
            'manual_review_count'     => (int)$risk['manual_review_count'],
            'manual_review_diamonds'  => (int)$risk['manual_review_diamonds'],
            'stuck_count'             => (int)$risk['stuck_count'],
            'checkout_uncertain'      => (int)$risk['checkout_uncertain_count'],
            'insufficient_balance_bots' => array_values(array_map(fn($b) => $b['bot_id'], $insufficientBots)),
        ],
        'system'  => [
            'total_today'    => $totalToday,
            'completed_today'=> $completedToday,
            'failed_today'   => (int)($today['failed_today'] ?? 0),
            'success_rate'   => $successRate,
            'queue_depth'    => (int)($today['queue_depth'] ?? 0),
            'avg_duration'   => round($today['avg_duration'] ?? 0),
            'active_workers' => count(array_filter($bots, fn($b) => in_array($b['status'], ['online','busy']))),
        ],
        'logs'    => $logs,
        'server_time' => date('c'),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database Error']);
}
