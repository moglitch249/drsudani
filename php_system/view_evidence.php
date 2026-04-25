<?php
// view_evidence.php — عرض صور الإثبات للمدير فقط
// الصور محفوظة خارج public_html — لا يمكن لأحد الوصول إليها مباشرة
session_start();

if (empty($_SESSION['bot_ok'])) {
    http_response_code(403);
    die('403 Forbidden');
}

// قراءة config.php لمعرفة مسار EVIDENCE_DIR
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    http_response_code(500);
    die('Config not found');
}

// basename() يمنع هجمات Directory Traversal مثل: ../../config.php
$file = basename(isset($_GET['file']) ? $_GET['file'] : '');

if (!$file) {
    http_response_code(400);
    die('Bad Request');
}

// منع تنفيذ أي ملف ليس صورة
$allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    http_response_code(403);
    die('Forbidden: Only images allowed.');
}

// الملف في مجلد خارج public_html — غير قابل للوصول المباشر من الإنترنت
$path = EVIDENCE_DIR . '/' . $file;

if (!file_exists($path)) {
    http_response_code(404);
    die('Not Found');
}

// إرسال الصورة
$mime_map = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

header('Content-Type: ' . ($mime_map[$ext] ?? 'image/png'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
