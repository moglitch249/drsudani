<?php
/**
 * API: Chat Agent API (For dashboard users)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAgent();

$db        = db();
// Ensure schema is updated
try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN transferred_from VARCHAR(100) NULL"); } catch(Exception $e) {}
try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN rating INT DEFAULT NULL"); } catch(Exception $e) {}
try { @$db->exec("ALTER TABLE chat_sessions ADD COLUMN rating_comment TEXT NULL"); } catch(Exception $e) {}

$agentId   = (int)$_SESSION['agent_id'];
$agentName = $_SESSION['full_name'] ?? 'وكيل';
$isAdmin   = ($_SESSION['role'] === 'admin');
$action    = $_GET['action'] ?? $_POST['action'] ?? '';

// CSRF verification for all state-changing POST actions
$readOnlyActions = ['get_waiting','get_active','get_transferred','get_agents','get_stats','poll_messages','get_history'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $readOnlyActions)) {
    verifyCsrf();
}

// Update last_seen heartbeat for every authenticated request
try { $db->prepare("UPDATE agents SET last_seen=NOW() WHERE id=?")->execute([$agentId]); } catch(Exception $e) {}

switch ($action) {

    case 'get_waiting':
        if ($isAdmin) {
            $stmt = $db->query("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.created_at,cs.transferred_from,cs.department,cs.customer_username,cs.wallet_balance,a.full_name AS agent_name, cs.agent_id FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='waiting' AND (cs.agent_id IS NULL OR cs.agent_id=0) ORDER BY cs.id ASC");
        } else {
            $perms = $_SESSION['permissions'] ?? [];
            $allowedDepts = [];
            if (!empty($perms['chat_support'])) $allowedDepts[] = "'support'";
            if (!empty($perms['chat_wallet']))  $allowedDepts[] = "'wallet'";

            // Default: if no explicit dept permission set, show support (backward compat)
            if (empty($allowedDepts)) $allowedDepts[] = "'support'";

            $deptClause = "((cs.department IN (" . implode(',', $allowedDepts) . "))" . (in_array("'support'", $allowedDepts) ? " OR cs.department IS NULL" : "") . ")";
            $stmt = $db->prepare("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.created_at,cs.transferred_from,cs.department,cs.customer_username,cs.wallet_balance,cs.rating,cs.rating_comment,a.full_name AS agent_name, cs.agent_id FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='waiting' AND ($deptClause) AND (cs.agent_id IS NULL OR cs.agent_id=0 OR cs.agent_id=?) ORDER BY cs.id ASC");
            $stmt->execute([$agentId]);
        }
        jsonSuccess(['sessions' => $stmt->fetchAll()]);
        break;


    case 'get_active':
        if ($isAdmin) {
            $stmt = $db->query("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.status,cs.created_at,cs.rating,cs.rating_comment,a.full_name AS agent_name,a.id AS agent_id FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='active' ORDER BY cs.updated_at DESC");
        } else {
            $stmt = $db->prepare("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.status,cs.created_at,cs.rating,cs.rating_comment,a.full_name AS agent_name FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.agent_id=? AND cs.status='active' ORDER BY cs.updated_at DESC");
            $stmt->execute([$agentId]);
        }
        jsonSuccess(['sessions' => $stmt->fetchAll()]);
        break;

    case 'get_history':
        // Fetch last 50 closed sessions
        if ($isAdmin) {
            $stmt = $db->query("SELECT cs.id,cs.customer_name,cs.customer_email,cs.problem_description,cs.status,cs.created_at,cs.rating,cs.rating_comment,a.full_name AS agent_name FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='closed' ORDER BY cs.updated_at DESC LIMIT 50");
        } else {
            $stmt = $db->prepare("SELECT cs.id,cs.customer_name,cs.customer_email,cs.problem_description,cs.status,cs.created_at,cs.rating,cs.rating_comment,a.full_name AS agent_name FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.agent_id=? AND cs.status='closed' ORDER BY cs.updated_at DESC LIMIT 50");
            $stmt->execute([$agentId]);
        }
        jsonSuccess(['sessions' => $stmt->fetchAll()]);
        break;

    case 'accept':
        $sessionId = (int)$_POST['session_id'];
        $stmt = $db->prepare("UPDATE chat_sessions SET agent_id=?, status='active' WHERE id=? AND status='waiting'");
        if ($stmt->execute([$agentId, $sessionId]) && $stmt->rowCount() > 0) {
            $db->prepare("INSERT INTO chat_messages (session_id, sender_type, message, message_type) VALUES (?, 'system', ?, 'text')")
               ->execute([$sessionId, "انضم $agentName إلى المحادثة"]);
            logAction('chat_accept', "Accepted session #$sessionId", $agentId);
            jsonSuccess(['session_id' => $sessionId]);
        } else {
            jsonError('المحادثة تم قبولها من قبل شخص آخر أو غير موجودة');
        }
        break;

    case 'transfer':
        // Transfer chat from current agent to another agent (admin or self-transfer)
        $sessionId   = (int)($_POST['session_id'] ?? 0);
        $targetAgent = (int)($_POST['target_agent_id'] ?? 0);
        if (!$sessionId || !$targetAgent) jsonError('Missing data');

        // Only the owning agent or admin can transfer
        $chk = $db->prepare("SELECT agent_id FROM chat_sessions WHERE id=?");
        $chk->execute([$sessionId]);
        $sess = $chk->fetch();
        if (!$sess) jsonError('Session not found');
        // Allow Admin or current agent to transfer
        if (!$isAdmin && $sess['agent_id'] != $agentId) jsonError('Unauthorized', 403);

        // Get target agent name
        $tgt = $db->prepare("SELECT full_name FROM agents WHERE id=? AND is_active=1");
        $tgt->execute([$targetAgent]);
        $tgtAgent = $tgt->fetch();
        if (!$tgtAgent) jsonError('وكيل غير موجود');

        $db->prepare("UPDATE chat_sessions SET agent_id=?, transferred_from=?, status='waiting', typing_agent=0, typing_customer=0 WHERE id=?")->execute([$targetAgent, $agentName, $sessionId]);

        $tgtStmt = $db->prepare("SELECT full_name FROM agents WHERE id=?");
        $tgtStmt->execute([$targetAgent]);
        $tgtName = $tgtStmt->fetchColumn() ?: 'وكيل';

        $db->prepare("INSERT INTO chat_messages (session_id, sender_type, message, message_type) VALUES (?, 'system', ?, 'text')")
           ->execute([$sessionId, "قام $agentName بتحويل المحادثة إلى $tgtName"]);

        logAction('chat_transfer', "Transferred session #$sessionId to $tgtName", $agentId);
        jsonSuccess(['message' => 'Transferred', 'new_agent' => $tgtAgent['full_name']]);
        break;

    case 'takeover':
        // Admin takes over a chat from an agent
        if (!$isAdmin) jsonError('Unauthorized');
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if (!$sessionId) jsonError('Missing session ID');

        $db->prepare("UPDATE chat_sessions SET agent_id=?,updated_at=NOW() WHERE id=?")->execute([$agentId,$sessionId]);
        $db->prepare("INSERT INTO chat_messages (session_id,sender_type,sender_name,message) VALUES (?,'system','نظام',?)")
           ->execute([$sessionId, "تدخّل مدير النظام ({$agentName}) في المحادثة"]);
        jsonSuccess(['message' => 'Taken over']);
        break;

    case 'poll_messages':
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $lastId    = (int)($_POST['last_id'] ?? 0);

        // Admin can read any session, agents only their own
        if (!$isAdmin) {
            $authChk = $db->prepare("SELECT id FROM chat_sessions WHERE id=? AND agent_id=?");
            $authChk->execute([$sessionId,$agentId]);
            if (!$authChk->fetch()) jsonError('Unauthorized');
        }

        $msgStmt = $db->prepare("SELECT id,client_id,sender_type,sender_name,message,message_type,created_at FROM chat_messages WHERE session_id=? AND id>? ORDER BY id ASC");
        $msgStmt->execute([$sessionId,$lastId]);

        $sessStmt = $db->prepare("SELECT cs.status,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.agent_id,cs.typing_customer,cs.rating,cs.rating_comment,a.full_name AS agent_name FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.id=?");
        $sessStmt->execute([$sessionId]);
        $sess = $sessStmt->fetch();

        jsonSuccess(['messages' => $msgStmt->fetchAll(), 'status' => $sess['status'], 'session' => $sess]);
        break;

    case 'send':
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $message   = trim($_POST['message'] ?? '');
        $clientId  = trim($_POST['client_id'] ?? '');
        $type      = 'text';
        if (!$sessionId) jsonError('Missing session ID');

        $sessStmt = $db->prepare("SELECT status,agent_id FROM chat_sessions WHERE id=?");
        $sessStmt->execute([$sessionId]);
        $session = $sessStmt->fetch();

        if (!$session || $session['status'] === 'closed') jsonError('Session closed');
        if (!$isAdmin && $session['agent_id'] != $agentId) jsonError('Not your chat');

        // Dedup
        if ($clientId) {
            $chk = $db->prepare("SELECT id FROM chat_messages WHERE client_id=? LIMIT 1");
            $chk->execute([$clientId]);
            if ($chk->fetch()) { jsonSuccess(['deduplicated'=>true]); }
        }

        // Image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($mime,$allowed)) jsonError('نوع الملف غير مدعوم');

            // Robust Extension Check: check both MIME and original filename extension
            $origExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($origExt, $allowedExts)) jsonError('امتداد الملف غير مسموح به كصورة');
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

        if (!$message) jsonError('Missing message');

        // Reset typing
        $db->prepare("UPDATE chat_sessions SET typing_agent=0 WHERE id=?")->execute([$sessionId]);

        $ins = $db->prepare("INSERT INTO chat_messages (session_id,client_id,sender_type,sender_id,sender_name,message,message_type) VALUES (?,?,'agent',?,?,?,?)");
        if ($ins->execute([$sessionId,$clientId,$agentId,$agentName,$message,$type])) {
            $db->prepare("UPDATE chat_sessions SET updated_at=NOW() WHERE id=?")->execute([$sessionId]);
            jsonSuccess(['message_id'=>$db->lastInsertId()]);
        } else { jsonError('Failed to send'); }
        break;

    case 'typing':
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $isTyping  = (int)($_POST['is_typing'] ?? 0);
        // Ownership check: agents can only update their own sessions
        if (!$isAdmin) {
            $ownerChk = $db->prepare("SELECT id FROM chat_sessions WHERE id=? AND agent_id=? AND status='active'");
            $ownerChk->execute([$sessionId, $agentId]);
            if (!$ownerChk->fetch()) { echo json_encode(['success'=>false]); exit; }
        }
        $db->prepare("UPDATE chat_sessions SET typing_agent=? WHERE id=?")->execute([$isTyping,$sessionId]);
        echo json_encode(['success'=>true]);
        exit;

    case 'close':
        $sessionId = (int)$_POST['session_id'];
        if (!$sessionId) jsonError('ID required');
        if ($isAdmin) {
            $stmt = $db->prepare("UPDATE chat_sessions SET status='closed',updated_at=NOW() WHERE id=?");
            $stmt->execute([$sessionId]);
        } else {
            $stmt = $db->prepare("UPDATE chat_sessions SET status='closed',updated_at=NOW() WHERE id=? AND agent_id=?");
            $stmt->execute([$sessionId,$agentId]);
        }
        $db->prepare("INSERT INTO chat_messages (session_id, sender_type, message, message_type) VALUES (?, 'system', ?, 'text')")
           ->execute([$sessionId, "قام $agentName بإنهاء المحادثة"]);
        logAction('chat_close', "Closed session #$sessionId", $agentId);
        jsonSuccess(['message' => 'Session closed']);
        break;



    case 'get_agents':
        // For transfer dropdown - list active agents
        $stmt = $db->query("SELECT id,full_name,role FROM agents WHERE is_active=1 ORDER BY role DESC, full_name ASC");
        jsonSuccess(['agents' => $stmt->fetchAll()]);
        break;

    case 'get_transferred':
        // Sessions in waiting state that are assigned to a specific agent (transferred/assigned)
        if ($isAdmin) {
            $stmt = $db->query("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.created_at,cs.transferred_from,a.full_name AS agent_name,a.id AS agent_id FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='waiting' AND cs.agent_id IS NOT NULL ORDER BY cs.id ASC");
        } else {
            $stmt = $db->prepare("SELECT cs.id,cs.customer_name,cs.customer_email,cs.customer_phone,cs.problem_description,cs.created_at,cs.transferred_from,a.full_name AS agent_name FROM chat_sessions cs LEFT JOIN agents a ON a.id=cs.agent_id WHERE cs.status='waiting' AND cs.agent_id=? ORDER BY cs.id ASC");
            $stmt->execute([$agentId]);
        }
        jsonSuccess(['sessions' => $stmt->fetchAll()]);
        break;

    case 'get_stats':
        if ($isAdmin) {
            $waitStmt   = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE status='waiting' AND (agent_id IS NULL OR agent_id=0)");
            $transStmt  = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE status='waiting' AND (agent_id IS NOT NULL AND agent_id!=0)");
            $activeStmt = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE status='active'");
            $newestStmt = $db->query("SELECT id,customer_name,created_at FROM chat_sessions WHERE status='waiting' AND (agent_id IS NULL OR agent_id=0) ORDER BY id DESC LIMIT 1");
        } else {
            $perms = $_SESSION['permissions'] ?? [];
            $allowedDepts = [];
            if (!empty($perms['chat_support'])) $allowedDepts[] = "'support'";
            if (!empty($perms['chat_wallet'])) $allowedDepts[] = "'wallet'";
            
            // Default: if no explicit dept permission set, show support (backward compat)
            if (empty($allowedDepts)) $allowedDepts[] = "'support'";

            $deptClause = !empty($allowedDepts) ? "AND (department IN (" . implode(',', $allowedDepts) . ")" . (in_array("'support'", $allowedDepts) ? " OR department IS NULL" : "") . ")" : "AND 1=0";

            $waitStmt   = $db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='waiting' AND (agent_id IS NULL OR agent_id=0 OR agent_id=?) $deptClause");
            $waitStmt->execute([$agentId]);
            $transStmt  = $db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='waiting' AND (agent_id IS NOT NULL AND agent_id!=0 AND agent_id=?) $deptClause");
            $transStmt->execute([$agentId]);
            $activeStmt = $db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='active' AND agent_id=?");
            $activeStmt->execute([$agentId]);
            $newestStmt = $db->prepare("SELECT id,customer_name,created_at FROM chat_sessions WHERE status='waiting' AND (agent_id IS NULL OR agent_id=0 OR agent_id=?) $deptClause ORDER BY id DESC LIMIT 1");
            $newestStmt->execute([$agentId]);
        }
        $newest = $newestStmt->fetch() ?: null;
        jsonSuccess([
            'waiting_count'     => (int)$waitStmt->fetchColumn(),
            'transferred_count' => (int)$transStmt->fetchColumn(),
            'active_count'      => (int)$activeStmt->fetchColumn(),
            'newest_waiting'    => $newest
        ]);
        break;

    default:
        jsonError('Invalid action');
}
