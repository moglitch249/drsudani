<?php
/**
 * عرض الإيصال
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireLogin();

$id = intval_safe($_GET['id'] ?? 0);
if (!$id) {
    die('Invalid ID');
}

$db = db();

if ($_SESSION['role'] === 'agent' && hasPermission('view_own_transactions_only')) {
    $stmt = $db->prepare('SELECT receipt_image FROM transactions WHERE id = ? AND agent_id = ?');
    $stmt->execute([$id, $_SESSION['agent_id']]);
} else {
    $stmt = $db->prepare('SELECT receipt_image FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
}

$blob = $stmt->fetchColumn();

if (!$blob) {
    die('Receipt not found or no permission.');
}

// تحسس نوع الملف من البايتات الأولى (magic bytes)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($blob);

// Fallback if mime not identified securely
if (!in_array($mime, UPLOAD_ALLOWED_MIME)) {
    $mime = 'image/jpeg';
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400'); // كاش ليوم واحد للمتصفح
echo $blob;
exit;
