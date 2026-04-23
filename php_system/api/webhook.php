<?php
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/../config.php';
}

// التأكد من أن الطلب الوارد هو POST (لأن الووكومرس يرسل POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method Not Allowed']));
}

// قراءة بيانات JSON الواردة
$inputData = file_get_contents('php://input');

// --- الحماية (Security) ---
// ندعم طريقتين: التوكن في الرابط أو توقيع ووكومرس (WooCommerce Signature)
$provided_token = isset($_GET['token']) ? $_GET['token'] : '';
$wc_signature   = isset($_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] : '';

$is_authorized = false;

// 1. فحص التوكن في الرابط (للتوافق مع القديم)
if ($provided_token === API_SECRET_TOKEN) {
    $is_authorized = true;
} 
// 2. فحص توقيع ووكومرس الرسمي (أكثر أماناً)
elseif (!empty($wc_signature)) {
    $calculated_hmac = base64_encode(hash_hmac('sha256', $inputData, API_SECRET_TOKEN, true));
    if (hash_equals($wc_signature, $calculated_hmac)) {
        $is_authorized = true;
    }
}

if (!$is_authorized) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Rejected: Invalid Signature/Token. Signature: $wc_signature\n\n", FILE_APPEND);
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized: Invalid Token or Signature']));
}

// الرد بنجاح (200) على رسائل الفحص (Ping) التي يرسلها ووكومرس عند حفظ الإعدادات لكي لا يعطي خطأ
if (strpos($inputData, 'webhook_id=') !== false) {
    http_response_code(200);
    die('Ping received');
}

// --- تسجيل الديباج لمعرفة هل يصل الطلب من الووكومرس ---
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Incoming Request:\n" . $inputData . "\n\n", FILE_APPEND);

$orderData = json_decode($inputData, true);

if (!$orderData || !isset($orderData['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid or empty JSON']));
}

$woo_order_id = $orderData['id'];
$order_status = isset($orderData['status']) ? strtolower($orderData['status']) : '';

// حماية خطيرة: منع الشحن للطلبات غير المدفوعة (مثل pending, on-hold, cancelled)
if ($order_status !== 'processing' && $order_status !== 'completed') {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Ignored Order #{$woo_order_id}: Status is not processing (Status: {$order_status}). Unpaid order rejected.\n\n", FILE_APPEND);
    die(json_encode(['success' => true, 'message' => "Ignoring order: Status is {$order_status}. Only processing orders are accepted."]));
}

// ==========================================
// فلترة الطلبات واستخراج البيانات الذكية
// نبحث عن المنتج الذي يحتوي على Player ID (= منتج البوت)
// ونستخرج منه: اسم المنتج، عدد جواهر ريزر، أيدي اللاعب
// ==========================================
$has_bot_product = false;
$player_id = 'UNKNOWN';
$product_name = '';
$diamonds = '100'; // القيمة الافتراضية
$store_amount = ''; // الكمية كما تظهر في المتجر

if (isset($orderData['line_items'])) {
    foreach ($orderData['line_items'] as $item) {
        $has_player_id_meta = false;
        $item_player_id = '';
        $item_razer_diamonds = '';
        
        if (isset($item['meta_data'])) {
            foreach ($item['meta_data'] as $meta) {
                $m_key = strtolower(trim($meta['key']));
                
                // استخراج Player ID
                if ($m_key === 'player id' || $m_key === 'player_id' || strpos($m_key, "\xD8\xA3\xD9\x8A\xD8\xAF\xD9\x8A") !== false) {
                    $has_player_id_meta = true;
                    $item_player_id = trim($meta['value']);
                }
                
                // استخراج عدد جواهر ريزر (من الإضافة الجديدة v3)
                if ($m_key === 'razer diamonds' || $m_key === 'razer_diamonds') {
                    $item_razer_diamonds = trim($meta['value']);
                }
            }
        }
        
        // استخراج razer_diamonds من بيانات الـ REST API (الإضافة v3.2 وما فوق)
        if (empty($item_razer_diamonds) && isset($item['razer_diamonds_fixed'])) {
            $item_razer_diamonds = trim($item['razer_diamonds_fixed']);
        }
        
        // دعم المسمى القديم أيضاً كخطة بديلة
        if (empty($item_razer_diamonds) && isset($item['razer_diamonds'])) {
            $item_razer_diamonds = trim($item['razer_diamonds']);
        }
        
        if ($has_player_id_meta && !empty($item_player_id)) {
            $has_bot_product = true;
            $player_id = $item_player_id;
            $product_name = isset($item['name']) ? $item['name'] : '';
            
            // عدد الجواهر: أولوية لحقل ريزر المخصص، ثم استخراج من الاسم
            if (!empty($item_razer_diamonds)) {
                $diamonds = $item_razer_diamonds;
            } else {
                // خطة بديلة: استخراج الرقم من اسم المنتج ومحاولة المطابقة
                $amount_from_name = preg_replace('/[^0-9]/', '', $item['name']);
                if ($amount_from_name) {
                    $diamonds = $amount_from_name;
                }
            }
            
            $store_amount = $product_name; // حفظ الاسم الكامل
            break;
        }
    }
}

// إذا لم يكن الطلب يحتوي على المنتج المحدد بصندوق الاختيار، يتجاهله السيرفر
if (!$has_bot_product) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Ignored Order #{$woo_order_id}: No Player ID metadata found.\n\n", FILE_APPEND);
    die(json_encode(['success' => true, 'message' => 'Ignoring order: No Bot products found in order']));
}

// كخيار بديل في الأسوأ، البحث في الميتا داتا العامة للطلب
if ($player_id === 'UNKNOWN' && isset($orderData['meta_data'])) {
    foreach ($orderData['meta_data'] as $meta) {
        $key = strtolower(trim($meta['key']));
        if ($key === 'player id' || $key === 'player_id' || strpos($key, "\xD8\xA3\xD9\x8A\xD8\xAF\xD9\x8A") !== false) {
            $player_id = trim($meta['value']);
            break;
        }
    }
}

// رفض تام إذا كان الأيدي غير موجود
if ($player_id === 'UNKNOWN' || empty($player_id)) {
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Ignored Order #{$woo_order_id}: Missing Player ID.\n\n", FILE_APPEND);
    die(json_encode(['success' => true, 'message' => 'Ignoring order: No valid Player ID found.']));
}

// تسجيل البيانات المستخرجة
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Processing Order #{$woo_order_id}: Player={$player_id}, Diamonds={$diamonds}, Product={$product_name}\n\n", FILE_APPEND);

// إدراج الطلب في قاعدة البيانات
try {
    // ── حماية ذرية ضد Race Condition ──
    // بدون Transaction، يمكن لطلبين متزامنين أن يمررا فحص SELECT معاً ثم يُدرج كلاهما!
    $pdo->beginTransaction();

    $stmtExist = $pdo->prepare("SELECT id FROM orders WHERE woo_order_id = ? LIMIT 1 FOR UPDATE");
    $stmtExist->execute([$woo_order_id]);
    $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $pdo->commit();
        file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Ignored Order #{$woo_order_id}: Already exists in database (Local ID: {$existing['id']}).\n\n", FILE_APPEND);
        die(json_encode(['success' => true, 'message' => 'Ignoring order: Already exists in database']));
    }

    $stmt = $pdo->prepare("INSERT INTO orders (woo_order_id, player_id, product_name, diamonds, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$woo_order_id, $player_id, $product_name, $diamonds, $store_amount]);
    $local_order_id = $pdo->lastInsertId();

    $pdo->commit();


    // Orders are now completely handled via the secure polling mechanism in get_pending.php
    // Direct bot pushing has been removed to prevent race conditions and double-topup scenarios.
    
    echo json_encode(['success' => true, 'message' => "Order received: {$diamonds} diamonds for player {$player_id}"]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Database Error: " . 'Database Error' . "\n\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => 'Database Error']);
}
