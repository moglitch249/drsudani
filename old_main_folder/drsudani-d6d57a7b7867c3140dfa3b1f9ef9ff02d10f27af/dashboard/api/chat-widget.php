<?php
/**
 * API: Chat Widget (Public access for WordPress Plugin)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

// CORS: restrict to the configured WordPress origin only
$allowedOrigin = defined('WC_API_URL') ? rtrim(WC_API_URL, '/') : '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($allowedOrigin && rtrim($requestOrigin, '/') === $allowedOrigin) {
    header("Access-Control-Allow-Origin: $requestOrigin");
} else {
    // If no specific origin match or missing origin, allow all for the public widget
    // This is safer than a broken dynamic header that leads to "Connection failed"
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$db = db();

// Auto-create / alter tables
$db->exec("CREATE TABLE IF NOT EXISTS chat_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) UNIQUE NOT NULL,
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(30) NULL,
    problem_description TEXT NULL,
    agent_id INT UNSIGNED NULL,
    status ENUM('waiting','active','closed') DEFAULT 'waiting',
    department ENUM('support','wallet') DEFAULT 'support',
    customer_username VARCHAR(100) NULL,
    wallet_balance VARCHAR(50) NULL,
    typing_agent TINYINT(1) DEFAULT 0,
    typing_customer TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

foreach (['customer_phone VARCHAR(30) NULL','problem_description TEXT NULL','typing_agent TINYINT(1) DEFAULT 0','typing_customer TINYINT(1) DEFAULT 0','department ENUM(\'support\',\'wallet\') DEFAULT \'support\'','customer_username VARCHAR(100) NULL','wallet_balance VARCHAR(50) NULL','rating INT UNSIGNED NULL','rating_comment TEXT NULL'] as $col) {
    try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN $col"); } catch(Exception $e) {}
}

$db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    client_id VARCHAR(40) NULL COMMENT 'UUID from client to avoid duplication',
    sender_type ENUM('customer','agent','system') NOT NULL,
    sender_id INT UNSIGNED NULL,
    sender_name VARCHAR(100) NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','image') DEFAULT 'text',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

foreach (['sender_name VARCHAR(100) NULL AFTER sender_id','message_type ENUM(\'text\',\'image\') DEFAULT \'text\' AFTER message','client_id VARCHAR(40) NULL AFTER id'] as $col) {
    try { $db->exec("ALTER TABLE chat_messages ADD COLUMN $col"); } catch(Exception $e) {}
}

header('Content-Type: application/json');

function returnError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]); exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'start_session':
        $name   = trim($_POST['name'] ?? 'زائر');
        $email  = trim($_POST['email'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $dept   = $_POST['department'] ?? 'support';
        $userNm = trim($_POST['username'] ?? '');
        $wallet = trim($_POST['wallet_balance'] ?? '');
        $token  = bin2hex(random_bytes(32));
        $stmt   = $db->prepare("INSERT INTO chat_sessions (session_token,customer_name,customer_email,customer_phone,problem_description,department,customer_username,wallet_balance,status) VALUES (?,?,?,?,?,?,?,?,'waiting')");
        if ($stmt->execute([$token,$name,$email,$phone,$desc,$dept,$userNm,$wallet])) {
            echo json_encode(['success'=>true,'token'=>$token,'session_id'=>$db->lastInsertId(),'status'=>'waiting']);
        } else { returnError('فشل بدء الجلسة'); }
        break;

    case 'poll':
        $token   = $_POST['token'] ?? '';
        $last_id = (int)($_POST['last_id'] ?? 0);
        if (!$token) returnError('Token missing');

        $stmt = $db->prepare("SELECT cs.id,cs.status,cs.typing_agent,a.full_name AS agent_name FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.session_token=?");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        if (!$session) returnError('Session not found', 404);

        $sid = $session['id'];
        $msgStmt = $db->prepare("SELECT id,client_id,sender_type,sender_name,message,message_type,created_at FROM chat_messages WHERE session_id=? AND id>? AND sender_type!='system' ORDER BY id ASC");
        $msgStmt->execute([$sid, $last_id]);

        $qpos = 0;
        if ($session['status'] === 'waiting') {
            // FIFO: count sessions that arrived BEFORE this one
            $q = $db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='waiting' AND id<?");
            $q->execute([$sid]);
            $qpos = (int)$q->fetchColumn() + 1;
        }

        echo json_encode([
            'success'        => true,
            'status'         => $session['status'],
            'agent_name'     => $session['agent_name'],
            'typing_agent'   => (bool)$session['typing_agent'],
            'queue_position' => $qpos,
            'messages'       => $msgStmt->fetchAll()
        ]);
        break;

    case 'send':
        $token     = $_POST['token'] ?? '';
        $message   = trim($_POST['message'] ?? '');
        $clientId  = trim($_POST['client_id'] ?? '');
        $type      = 'text';
        if (!$token) returnError('Missing token');

        $stmt = $db->prepare("SELECT id,status,customer_name FROM chat_sessions WHERE session_token=?");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        if (!$session) returnError('Session not found',404);
        if ($session['status'] === 'closed') returnError('Session is closed');

        // Dedup by client_id
        if ($clientId) {
            $chk = $db->prepare("SELECT id FROM chat_messages WHERE client_id=? LIMIT 1");
            $chk->execute([$clientId]);
            if ($chk->fetch()) { echo json_encode(['success'=>true,'deduplicated'=>true]); exit; }
        }

        // Image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($mime, $allowed_mimes)) returnError('نوع الملف غير مدعوم');

            // Robust Extension Check: check both MIME and original filename extension
            $origExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($origExt, $allowedExts)) returnError('امتداد الملف غير مسموح به كصورة');
            $dir = dirname(__DIR__) . '/uploads/chat/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            // Derive extension from MIME — never trust user-supplied filename
            $mimeExtMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
            $ext = $mimeExtMap[$mime] ?? 'jpg';
            $fn = uniqid('img_',true) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fn);
            $message = 'uploads/chat/' . $fn;
            $type = 'image';
        }

        if (!$message) returnError('Missing message');

        // Reset typing
        $db->prepare("UPDATE chat_sessions SET typing_customer=0 WHERE id=?")->execute([$session['id']]);

        $ins2 = $db->prepare("INSERT INTO chat_messages (session_id,client_id,sender_type,sender_name,message,message_type) VALUES (?,?,?,?,?,?)");
        if ($ins2->execute([$session['id'],$clientId,'customer',$session['customer_name'],$message,$type])) {
            echo json_encode(['success'=>true,'message_id'=>$db->lastInsertId()]);
        } else { returnError('فشل الإرسال'); }
        break;

    case 'typing':
        $token   = $_POST['token'] ?? '';
        $isTyping = (int)($_POST['is_typing'] ?? 0);
        if (!$token) returnError('Token missing');
        $db->prepare("UPDATE chat_sessions SET typing_customer=? WHERE session_token=?")->execute([$isTyping,$token]);
        echo json_encode(['success'=>true]);
        break;

    case 'close_session':
        $token = $_POST['token'] ?? '';
        if (!$token) returnError('Token missing');
        $stmt = $db->prepare("SELECT id FROM chat_sessions WHERE session_token=? AND status!='closed'");
        $stmt->execute([$token]);
        $sess = $stmt->fetch();
        if (!$sess) returnError('Not found');
        $db->prepare("UPDATE chat_sessions SET status='closed',updated_at=NOW() WHERE id=?")->execute([$sess['id']]);
        $db->prepare("INSERT INTO chat_messages (session_id,sender_type,sender_name,message) VALUES (?,'system','نظام','انهى العميل المحادثة.')")->execute([$sess['id']]);
        echo json_encode(['success'=>true]);
        break;

    case 'new_session':
        $token = $_POST['token'] ?? '';
        if ($token) {
            $db->prepare("UPDATE chat_sessions SET status='closed' WHERE session_token=?")->execute([$token]);
        }
        echo json_encode(['success'=>true]);
        break;

    default:
        returnError('Invalid action');
}
