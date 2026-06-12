<?php
/**
 * جلب الإشعارات فورياً (AJAX Polling)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
if (empty($_SESSION['agent_id'])) {
    jsonError('Unauthorized', 401);
}

$db = db();

// Auto-create notifications table if not exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) DEFAULT 'info',
        title VARCHAR(200) NOT NULL,
        body TEXT,
        reference_id INT UNSIGNED NULL,
        target_role ENUM('all','admin','agent') DEFAULT 'all',
        target_agent_id INT UNSIGNED NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Table creation failed - return empty response gracefully
    jsonSuccess(['notifications' => [], 'last_id' => 0, 'unread_count' => 0]);
}

$agentId = (int)$_SESSION['agent_id'];
$role = $_SESSION['role'];
$lastId = intval_safe($_GET['last_id'] ?? 0);

// إذا كانت أول نقرة (last_id = 0)، نجلب المؤشر ونجلب الإشعارات غير المقروءة لتعبئة القائمة
if ($lastId === 0) {
    $notifStmt = $db->prepare(
        'SELECT * FROM notifications
         WHERE is_read = 0
           AND (target_role = "all" OR target_role = ?)
           AND (target_agent_id IS NULL OR target_agent_id = ?)
         ORDER BY id DESC LIMIT 20'
    );
    $notifStmt->execute([$role, $agentId]);
    $notifications = array_reverse($notifStmt->fetchAll());

    $stmt = $db->prepare(
        'SELECT MAX(id) FROM notifications
         WHERE (target_role = "all" OR target_role = ?)
           AND (target_agent_id IS NULL OR target_agent_id = ?)'
    );
    $stmt->execute([$role, $agentId]);
    $maxId = (int)$stmt->fetchColumn();

    $unreadStmt = $db->prepare(
        'SELECT COUNT(*) FROM notifications
         WHERE is_read = 0
           AND (target_role = "all" OR target_role = ?)
           AND (target_agent_id IS NULL OR target_agent_id = ?)'
    );
    $unreadStmt->execute([$role, $agentId]);
    $unreadCount = (int)$unreadStmt->fetchColumn();

    jsonSuccess([
        'notifications' => $notifications,
        'last_id'       => $maxId,
        'unread_count'  => $unreadCount
    ]);
}

// جلب الإشعارات الجديدة
$stmt = $db->prepare(
    'SELECT * FROM notifications
     WHERE id > ?
       AND (target_role = "all" OR target_role = ?)
       AND (target_agent_id IS NULL OR target_agent_id = ?)
     ORDER BY id ASC'
);
$stmt->execute([$lastId, $role, $agentId]);
$notifications = $stmt->fetchAll();

if (!empty($notifications)) {
    $lastId = end($notifications)['id'];
}

$unreadStmt = $db->prepare(
    'SELECT COUNT(*) FROM notifications
     WHERE is_read = 0
       AND (target_role = "all" OR target_role = ?)
       AND (target_agent_id IS NULL OR target_agent_id = ?)'
);
$unreadStmt->execute([$role, $agentId]);
$unreadCount = (int)$unreadStmt->fetchColumn();

jsonSuccess([
    'notifications' => $notifications ?? [],
    'last_id'       => $lastId,
    'unread_count'  => $unreadCount
]);
