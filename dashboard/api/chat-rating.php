<?php
/**
 * API: تقديم تقييم للمحادثة
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

// CORS: السماح لطلب التقييم من ووردبريس
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

// يمكن للعميل التقييم دون تسجيل دخول (باستخدام التوكن)
$token = $_POST['token'] ?? null;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
$comment = $_POST['comment'] ?? '';

if (!$token || !$rating) {
    jsonError("بيانات ناقصة");
}

$db = db();

try {
    // التحقق من صحة التوكن والمحادثة (تعديل اسم العمود من token إلى session_token)
    $stmt = $db->prepare("SELECT id, status, rating FROM chat_sessions WHERE session_token = ?");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        jsonError("المحادثة غير موجودة أو انتهى الرمز الخاص بها");
    }

    if ($session['rating'] !== null) {
        jsonError("لقد قمت بتقييم هذه المحادثة مسبقاً");
    }

    // تحديث التقييم
    $update = $db->prepare("UPDATE chat_sessions SET rating = ?, rating_comment = ? WHERE id = ?");
    $update->execute([$rating, $comment, $session['id']]);

    jsonSuccess([], "نشكرك على تقييمك!");

} catch (PDOException $e) {
    jsonError("خطأ في السيرفر: " . $e->getMessage(), 500);
}
