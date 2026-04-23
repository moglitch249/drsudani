<?php
// api/get_bot_credentials.php — يُعيد بيانات حساب ريزر المرتبط بالبوت
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once __DIR__ . '/config.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); die(json_encode(['error' => 'Method Not Allowed']));
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);
$was_encrypted = false;

// Support both Legacy JSON and New Encrypted Payload
if (isset($data['payload'])) {
    $decrypted_data = decrypt_payload($data['payload']);
    if (!$decrypted_data) {
        http_response_code(403); die(json_encode(['error' => 'Decryption Failed or Invalid Token']));
    }
    $data = $decrypted_data;
    $was_encrypted = true;
}

$token  = isset($data['token']) ? $data['token'] : '';
$bot_id = isset($data['bot_id']) ? trim($data['bot_id']) : '';

if ($token !== API_SECRET_TOKEN) {
    http_response_code(403); die(json_encode(['error' => 'Unauthorized']));
}
if (!$bot_id) {
    http_response_code(400); die(json_encode(['error' => 'bot_id required']));
}

try {
    $stmt = $pdo->prepare("
        SELECT email, password, otp_secret, balance_status
        FROM razer_accounts
        WHERE bot_id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$bot_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(404);
        echo json_encode(['error' => "No active Razer account found for bot '{$bot_id}'"]);
        exit;
    }

    $response_data = ['success' => true, 'data' => $account];
    if ($was_encrypted) {
         echo json_encode(['payload' => encrypt_payload($response_data)]);
    } else {
         echo json_encode($response_data);
    }
} catch (PDOException $e) {
    http_response_code(500);
    $err = ['error' => 'Database Error'];
    if ($was_encrypted) {
         echo json_encode(['payload' => encrypt_payload($err)]);
    } else {
         echo json_encode($err);
    }
}
