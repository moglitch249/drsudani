<?php
/**
 * تحديد كل الإشعارات كمقروءة (API)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
if (empty($_SESSION['agent_id'])) {
    jsonError('Unauthorized', 401);
}

// 1. التحقق من CSRF
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonError('Invalid security token.', 403);
}

$db = db();
$agentId = (int)$_SESSION['agent_id'];
$role = $_SESSION['role'];

// تحديث الإشعارات
$stmt = $db->prepare(
    'UPDATE notifications SET is_read = 1 
     WHERE is_read = 0 
       AND (target_role = "all" OR target_role = ?)
       AND (target_agent_id IS NULL OR target_agent_id = ?)'
);
$stmt->execute([$role, $agentId]);

jsonSuccess(['message' => 'تم تحديد الكل كمقروء']);
