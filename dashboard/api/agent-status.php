<?php
/**
 * API: حالة الوكلاء في الوقت الفعلي (للمدير فقط)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
if (empty($_SESSION['agent_id'])) {
    jsonError('Unauthorized', 401);
}

$db = db();

// Auto-add last_seen column if not exists
try {
    $db->exec("ALTER TABLE agents ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL COMMENT 'آخر نشاط للوكيل'");
} catch (PDOException $e) {}

$isAdmin = ($_SESSION['role'] === 'admin');
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

// Update heartbeat for the current logged-in agent
if ($action === 'heartbeat') {
    $db->prepare("UPDATE agents SET last_seen=NOW() WHERE id=?")->execute([$_SESSION['agent_id']]);
    jsonSuccess(['ok' => true]);
}

// Only admin can view all agents' status
if (!$isAdmin) {
    jsonError('Unauthorized', 403);
}

// Fetch all agents with online status and active chat count
$stmt = $db->query(
    "SELECT a.id, a.full_name, a.username, a.role, a.is_active, a.last_seen,
            CASE WHEN a.last_seen >= NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END AS is_online,
            COUNT(cs.id) AS active_chats
     FROM agents a
     LEFT JOIN chat_sessions cs ON cs.agent_id = a.id AND cs.status = 'active'
     GROUP BY a.id
     ORDER BY is_online DESC, a.role DESC, a.full_name ASC"
);

$agents = $stmt->fetchAll();

jsonSuccess(['agents' => $agents]);
