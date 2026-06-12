<?php
/**
 * API: System Data Reset (Admin Only)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

// 1. Security Check: Admin session
startSecureSession();
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالقيام بهذا الإجراء.']);
    exit;
}

// 2. Security Check: CSRF
verifyCsrf();

// 3. Security Check: Double Confirmation Phrase
$phrase = trim($_POST['confirm_phrase'] ?? '');
if ($phrase !== 'مسح الكل' && $phrase !== 'CLEAR ALL') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'يرجى كتابة عبارة التأكيد بشكل صحيح (مسح الكل).']);
    exit;
}

ob_start(); // Start buffering to catch any accidental output

$db = db();

try {
    // Disable foreign key checks to allow TRUNCATE on all tables
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // List of tables to clear
    $tables = [
        'transactions',
        'orders',
        'notifications',
        'system_logs',
        'wp_users_cache',
        'chat_sessions',
        'chat_messages'
    ];

    foreach ($tables as $table) {
        // Use a nested try-catch to ignore tables that don't exist yet
        try {
            $db->exec("TRUNCATE TABLE `$table` ;");
        } catch (PDOException $e) {
            // Table might not exist, ignore
        }
    }

    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Log the reset action in the NOW empty system_logs (it will be the first entry)
    logAction('SYSTEM_RESET', 'تم تصفير بيانات النظام بالكامل بواسطة المدير (ID: ' . ($_SESSION['agent_id'] ?? 'Unknown') . ')');

    ob_end_clean(); // Discard any accidental output
    echo json_encode([
        'success' => true,
        'message' => 'تم تصفير بيانات النظام بنجاح. تم مسح كافة المعاملات والطلبات والمحادثات.'
    ]);

} catch (PDOException $e) {
    // Re-enable in case of error
    @$db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'فشل التصفير: ' . $e->getMessage()]);
}
