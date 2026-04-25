<?php
// api/update_status.php — v2: Orchestration Version
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); die(json_encode(array('error' => 'Method Not Allowed')));
}

$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

if (isset($data['payload'])) {
    $decrypted_data = decrypt_payload($data['payload']);
    if (!$decrypted_data) {
        http_response_code(403); die(json_encode(['error' => 'Decryption Failed']));
    }
    $data = $decrypted_data;
}

$token = isset($data['token']) ? $data['token'] : '';
if (!$data || $token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(array('error' => 'Unauthorized')));
}

$id               = isset($data['id']) ? (int)$data['id'] : 0;
$status           = isset($data['status']) ? trim($data['status']) : '';
$fail_reason      = isset($data['fail_reason']) ? trim($data['fail_reason']) : '';
$checkout_clicked = isset($data['checkout_clicked']) ? (int)$data['checkout_clicked'] : 0;
$financial_risk   = isset($data['financial_risk']) ? trim($data['financial_risk']) : 'none';

if (!$id || !$status) {
    http_response_code(400); die(json_encode(array('error' => 'id and status required')));
}

try {
    $pdo->beginTransaction();

    // 1. جلب الحالة الحالية مع قفل الصف لمنع Race Condition
    $stmt = $pdo->prepare("SELECT status, woo_order_id, bot_assigned, checkout_clicked FROM orders WHERE id = ? FOR UPDATE");
    $stmt->execute(array($id));
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->commit();
        http_response_code(404); die(json_encode(array('error' => 'Order not found')));
    }

    // منع تحديث الطلبات المكتملة
    if ($order['status'] === 'completed') {
        $pdo->commit();
        echo json_encode(array('success' => true, 'message' => 'Order already completed'));
        exit;
    }

    // ── حماية من تعديات البوت المسحوب منه الطلب (Race Condition Vulnerability Fix) ──
    $bot_id = isset($data['bot_id']) ? trim($data['bot_id']) : '';
    if ($order['bot_assigned'] && $order['bot_assigned'] !== $bot_id && $status !== 'completed') {
        $pdo->commit();
        error_log("[SECURITY] Bot $bot_id tried to update order $id assigned to {$order['bot_assigned']}. Ignored.");
        echo json_encode(array('success' => true, 'message' => 'Ignored. Order assigned to another bot or withdrawn.'));
        exit;
    }

    // ── حماية من Race Condition ──
    // إذا كان checkout_clicked=1 في قاعدة البيانات (من Checkpoint سابق)
    // لا نسمح بالتراجع عنه أبداً (لأن الـ Checkout فعلاً ضُغط)
    if ($order['checkout_clicked'] == 1 && $checkout_clicked == 0) {
        $checkout_clicked = 1; // حافظ على القيمة الحقيقية
    }

    // 2. تحديث الطلب محلياً
    $sql = "UPDATE orders SET 
                status = ?, 
                fail_reason = ?, 
                checkout_clicked = ?, 
                financial_risk = ?,
                requires_human = ?,
                updated_at = NOW()";
    
    $params = [$status, $fail_reason, $checkout_clicked, $financial_risk];

    // تحديد ما إذا كان يحتاج مراجعة بشرية (risk panel)
    $requires_human = ($financial_risk !== 'none' || $status === 'manual_review' || $status === 'stuck') ? 1 : 0;
    $params[] = $requires_human;

    // تصفير القفل عند الانتهاء
    if (in_array($status, ['completed', 'failed', 'manual_review'])) {
        $sql .= ", locked_by = NULL, locked_at = NULL";
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;

    $pdo->prepare($sql)->execute($params);


    // 3. معالجة الإثبات (Base64) — يُحفظ خارج public_html لمنع الوصول المباشر
    if (!empty($data['evidence'])) {
        $evidence_dir = EVIDENCE_DIR; // مجلد خارج public_html — محدد في config.php
        if (!file_exists($evidence_dir)) mkdir($evidence_dir, 0750, true);
        $img_data = base64_decode($data['evidence']);
        if ($img_data) {
            $fname = "order_{$id}_" . time() . ".png";
            file_put_contents($evidence_dir . '/' . $fname, $img_data);
            // نحفظ اسم الملف فقط — المسار الكامل سري ولا يُكشف للمتصفح
            try {
                $pdo->prepare("UPDATE orders SET evidence_path=? WHERE id=?")
                    ->execute([$fname, $id]);
            } catch(Exception $e) {}
        }
    }

    // commit قبل استدعاء WooCommerce (لأن curl قد يتأخر)
    $pdo->commit();

    // 4. تحديث WooCommerce — فقط عند النجاح!
    // الفشل لا يُبلغ للعميل تلقائياً — الأدمن يقرر يدوياً من اللوحة
    if ($status === 'completed' && !empty($order['woo_order_id'])) {
        $note = "تم الشحن بنجاح! رقم العملية: #FF-{$id}";

        $url = WOO_STORE_URL . "/wp-json/wc/v3/orders/" . $order['woo_order_id'];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'completed', 'customer_note' => $note]));
        curl_setopt($ch, CURLOPT_USERPWD, WOO_CONSUMER_KEY . ":" . WOO_CONSUMER_SECRET);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database Error']);
}
