<?php
/**
 * إضافة معاملة جديدة (API)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// 1. التحقق من CSRF (من الهيدر أو البوست)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonError('Invalid security token. Please refresh.', 403);
}

// 2. التحقق من الصلاحيات
if ($_SESSION['role'] === 'agent' && !hasPermission('add_transaction')) {
    jsonError('ليس لديك صلاحية لإضافة معاملات.', 403);
}

// 3. مسح المدخلات
$db       = db();
$agentId  = (int)$_SESSION['agent_id'];
$wpUserId = intval_safe($_POST['wp_user_id'] ?? 0);
$amount   = (float)($_POST['amount'] ?? 0);
$type     = $_POST['type'] ?? '';
$method   = $_POST['payment_method'] ?? '';
$notes    = trim($_POST['notes'] ?? '');

// 4. التحقق من البيانات
if ($wpUserId <= 0 || $amount <= 0 || !in_array($type, ['deposit', 'withdraw', 'adjustment'])) {
    jsonError('بيانات غير صالحة. تأكد من إدخال مبلغ صحيح والنوع.');
}
if (!in_array($method, ['bank_transfer', 'wallet', 'cash'])) {
    jsonError('طريقة الدفع غير صالحة.');
}

// 5. التحقق من وجود المستخدم
$userStmt = $db->prepare('SELECT id, full_name, email, wallet_balance FROM wp_users_cache WHERE wp_user_id = ?');
$userStmt->execute([$wpUserId]);
$user = $userStmt->fetch();

if (!$user) {
    jsonError('المستخدم غير موجود في النظام.');
}

// 6. التحقق من الصورة ومعالجتها (BLOB)
$receiptImage = null;
if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['receipt_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError('حدث خطأ أثناء رفع الصورة.');
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        jsonError('حجم الصورة كبير جداً. الحد الأقصى 5 ميجابايت.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, UPLOAD_ALLOWED_MIME)) {
        jsonError('نوع الملف غير مسموح. يرجى رفع صورة (JPG, PNG, WebP).');
    }

    // قراءة محتوى الصورة للتخزين كـ BLOB
    $receiptImage = file_get_contents($file['tmp_name']);
}

// 7. حفظ المعاملة وتحديث الرصيد (Transaction)
try {
    $db->beginTransaction();

    // إدراج المعاملة
    $stmt = $db->prepare(
        'INSERT INTO transactions 
           (agent_id, wp_user_id, amount, type, payment_method, notes, receipt_image, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    
    // جميع المعاملات من المدراء مؤكدة تلقائياً، ومن الوكلاء قد تكون معلقة أو مؤكدة حسب سياسة العمل
    // سنفترض هنا أنها "مؤكدة" مباشرة إذا كان الدفع نقدي، ومكتملة، ويمكن تخصيص ذلك لاحقاً
    $status = 'confirmed';

    $stmt->execute([
        $agentId,
        $wpUserId,
        $amount,
        $type,
        $method,
        $notes,
        $receiptImage,
        $status
    ]);
    
    $txId = $db->lastInsertId();

    // تحديث رصيد الكاشير/المحفظة (افتراضي)
    // حساب الرصيد الجديد: إيداع (+) سحب (-) تعديل (+/- حسب الإشارة)
    $balanceChange = ($type === 'withdraw') ? -$amount : $amount;
    
    $updateStmt = $db->prepare('UPDATE wp_users_cache SET wallet_balance = wallet_balance + ? WHERE wp_user_id = ?');
    $updateStmt->execute([$balanceChange, $wpUserId]);

    // تسجيل الإجراء
    logAction('add_transaction', "معاملة جديدة #{$txId} للمستخدم #{$wpUserId} بمبلغ {$amount}");

    // إرسال إشعار للمدراء
    $agentName = clean($_SESSION['agent_name'] ?? 'وكيل');
    $typeName  = $type === 'deposit' ? 'إيداع' : 'سحب';
    createNotification(
        'new_transaction',
        'معاملة جديدة',
        "قام {$agentName} بإضافة {$typeName} بمبلغ {$amount} للمستخدم " . clean($user['full_name']),
        $txId,
        'admin'
    );

    // مزامنة الرصيد مع مركز إضافة TeraWallet في ووردبريس (API)
    if (defined('TERAWALLET_TOKEN') && TERAWALLET_TOKEN !== '') {
        $ch = curl_init();
        $payload = json_encode([
            'user_id' => $wpUserId,
            'amount'  => $amount,
            'type'    => $type,
            'note'    => "لوحة التحكم: " . $notes
        ]);
        
        $syncUrl = rtrim(WC_API_URL, '/') . '/wp-json/dashboard/v1/wallet';
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $syncUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Dashboard-Token: ' . TERAWALLET_TOKEN,
                'Authorization: Bearer ' . TERAWALLET_TOKEN
            ],
            CURLOPT_SSL_VERIFYPEER => true,  // لا تعطل SSL أبداً حتى في التطوير
            CURLOPT_SSL_VERIFYHOST => 2,       // تحقق من اسم الهوست
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            throw new Exception("TeraWallet Sync Error: فشل cURL — $curlError");
        }
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorData = json_decode($response, true);
            $errorMsg  = $errorData['message'] ?? "فشل الاتصال بمحفظة TeraWallet (HTTP $httpCode). تحقق من الرابط وتفعيل الإضافة.";
            throw new Exception("TeraWallet Sync Error: " . $errorMsg);
        }
    } else {
        throw new Exception("TeraWallet Sync Error: مفتاح TERAWALLET_TOKEN غير موجود في ملف config.php على السيرفر.");
    }

    $db->commit();

    jsonSuccess(['tx_id' => $txId]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Transaction Error: " . $e->getMessage());
    $userFriendlyMsg = strpos($e->getMessage(), 'TeraWallet Sync Error:') !== false 
        ? str_replace('TeraWallet Sync Error: ', '', $e->getMessage()) 
        : 'حدث خطأ في قاعدة البيانات. برجاء المحاولة لاحقاً.';
    
    jsonError($userFriendlyMsg, 500);
}
