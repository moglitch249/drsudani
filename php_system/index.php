<?php
// ── Early AJAX fast-path: skip Bootstrap DDL and release session immediately ──
if (isset($_GET['action'])) {
    session_start(['read_and_close' => true]);
    if (empty($_SESSION['bot_ok'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error'=>'Unauthenticated']); exit;
    }
    if (file_exists('config.php')) require_once 'config.php';
    else require_once __DIR__ . '/config.php';
    // Jump to AJAX handler (search for AJAX_ENTRY_POINT below)
    goto AJAX_ENTRY_POINT;
}

session_start();

// ── Authentication ─────────────────────────────────────────────────────────
if (file_exists('config.php')) require_once 'config.php';
else require_once __DIR__ . '/config.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

// ── Session timeout: 8 ساعات ─────────────────────────────────────────────
if (!empty($_SESSION['bot_ok'])) {
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > 28800) {
        session_destroy();
        header('Location: ?'); exit;
    }
    $_SESSION['last_active'] = time();
}

// ── Login handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_login'])) {
    // Brute-force protection: 5 محاولات ثم قفل 15 دقيقة
    $attempts  = $_SESSION['login_attempts']  ?? 0;
    $locked_at = $_SESSION['login_locked_at'] ?? 0;

    if ($locked_at && time() - $locked_at < 900) {
        $wait = ceil((900 - (time() - $locked_at)) / 60);
        $login_err = "تم قفل الدخول. انتظر {$wait} دقيقة.";
    } else {
        $pw = trim($_POST['password'] ?? '');
        // يدعم كلمة مرور نصية عادية أو مشفرة بـ password_hash
        $ok = ($pw === ADMIN_PASSWORD) || password_verify($pw, ADMIN_PASSWORD);
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['bot_ok']           = true;
            $_SESSION['last_active']      = time();
            $_SESSION['login_attempts']   = 0;
            $_SESSION['login_locked_at']  = 0;
            header('Location: ?'); exit;
        } else {
            $_SESSION['login_attempts'] = $attempts + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_locked_at'] = time();
                $login_err = 'تم قفل الدخول لمدة 15 دقيقة بعد 5 محاولات فاشلة.';
            } else {
                $remaining = 5 - $_SESSION['login_attempts'];
                $login_err = "كلمة المرور غير صحيحة. ({$remaining} محاولات متبقية)";
            }
        }
    }
}


if (empty($_SESSION['bot_ok'])) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error'=>'Unauthenticated']); exit;
    }
    $err = $login_err ?? null; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | Bot Orchestrator</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:     #0E1016;
            --bg2:    #14161F;
            --bg3:    #1C1F2E;
            --bg4:    #242840;
            --border: #252836;
            --border2:#2F3347;
            --text:   #DDE1F0;
            --text2:  #7B82A0;
            --text3:  #4A5070;
            --green:  #14532D; --gl: #4ADE80; --gg: rgba(74,222,128,0.12);
            --amber:  #78350F; --al: #FCD34D; --ag: rgba(252,211,77,0.12);
            --red:    #7F1D1D; --rl: #F87171; --rg: rgba(248,113,113,0.12);
            --purple: #3B0764; --pl: #C084FC; --pg: rgba(192,132,252,0.12);
            --cyan:   #1E3A5F; --cl: #60A5FA;
            --razer:  #34D399;
            --brand-color: var(--razer);
            --mono:   'JetBrains Mono', monospace;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .login-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.4);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-text {
            color: var(--text);
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .logo-accent {
            color: var(--razer);
        }

        .subtitle {
            font-size: 12px;
            color: var(--text2);
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        .input-group {
            margin-bottom: 24px;
        }

        .input-label {
            display: block;
            font-size: 10px;
            font-weight: 500;
            color: var(--text3);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-field {
            width: 100%;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 10px 14px;
            font-size: 13px;
            transition: all 0.15s;
            text-align: left;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--cl);
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.12);
        }

        .btn-submit {
            width: 100%;
            background: #166534;
            border: 1px solid #14532D;
            color: #4ADE80;
            border-radius: 8px;
            padding: 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #15803D;
        }

        .error-msg {
            background: rgba(248, 113, 113, 0.08);
            border: 1px solid rgba(248, 113, 113, 0.25);
            color: #F87171;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Notifications */
        .bell-icon {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text2);
            transition: color 0.2s;
            margin-left: 15px;
            padding: 5px;
        }
        .bell-icon:hover { color: var(--text); }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -6px;
            background: #F87171;
            color: #7F1D1D;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 10px;
            min-width: 16px;
            text-align: center;
            border: 2px solid var(--bg);
            animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .bell-ring { animation: ring 0.5s ease-in-out; }
        @keyframes ring { 0% {transform: rotate(0)} 25% {transform: rotate(15deg)} 50% {transform: rotate(-10deg)} 75% {transform: rotate(5deg)} 100% {transform: rotate(0)} }
        @keyframes popIn { 0% {transform: scale(0.5); opacity: 0;} 100% {transform: scale(1); opacity: 1;} }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <div class="logo-text">Bot <span class="logo-accent">Orchestrator</span></div>
            <div class="subtitle">نظام الإدارة المركزي المتقدم</div>
        </div>

        <?php if ($err): ?>
        <div class="error-msg">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM0 8a8 8 0 1116 0A8 8 0 010 8zm6.5-1.25a.75.75 0 011.5 0v2.5a.75.75 0 01-1.5 0v-2.5zm1.5 5a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
            </svg>
            <?= htmlspecialchars($err) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_login" value="1">
            <div class="input-group">
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="input-field" placeholder="••••••••••••" autofocus required autocomplete="current-password">
                </div>
            </div>
            <button type="submit" class="btn-submit">
                المتابعة إلى النظام
                <svg class="btn-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="transform: rotate(180deg);">
                    <path fill-rule="evenodd" d="M1 8a7 7 0 1 1 14 0A7 7 0 0 1 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM4.5 7.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H4.5z"/>
                </svg>
            </button>
        </form>
    </div>

</body>
</html>
<?php exit; }

// ══════════════════════════════════════════════════════════════════
//  Bot Orchestrator — Multi-System Dashboard
// ══════════════════════════════════════════════════════════════════
error_reporting(E_ALL); ini_set('display_errors', 0);
// config.php already loaded above


// ── Bootstrap ────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS systems (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        url        VARCHAR(255) NOT NULL,
        api_token  VARCHAR(100) NOT NULL,
        color      VARCHAR(20)  DEFAULT '#58a6ff',
        is_active  TINYINT(1)   DEFAULT 1,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");
    if (!(int)$pdo->query("SELECT COUNT(*) FROM systems WHERE url='local'")->fetchColumn())
        $pdo->prepare("INSERT INTO systems(name,url,api_token,color)VALUES(?,?,?,?)")
            ->execute(['النظام المحلي','local',API_SECRET_TOKEN,'#3fb950']);
} catch(Exception $e) {}
// ── Helpers ──────────────────────────────────────────────────────
function sysGet($pdo, $id) {
    $s = $pdo->prepare("SELECT * FROM systems WHERE id=?");
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

function proxy($sys, $action, $post = null) {
    if ($sys['url'] === 'local') return null;
    $url = rtrim($sys['url'], '/') . '/?action=' . rawurlencode($action);
    $ch  = curl_init($url);
    $o   = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false];
    if ($post !== null) {
        $post['token'] = $sys['api_token'];
        $o += [CURLOPT_POST=>true,
               CURLOPT_POSTFIELDS=>json_encode($post),
               CURLOPT_HTTPHEADER=>['Content-Type: application/json']];
    }
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($c === 200 && $r) ? (json_decode($r, true) ?: null) : null;
}

// ── AJAX Router ──────────────────────────────────────────────────

AJAX_ENTRY_POINT:
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $act   = $_GET['action'];
    $d     = json_decode(file_get_contents('php://input'), true) ?: [];
    $sysId = (int)($_GET['sys'] ?? 1);
    $sys   = sysGet($pdo, $sysId);

    // ── Systems ──────────────────────────────────────────────────
    if ($act === 'get_systems') {
        $rows = $pdo->query("SELECT * FROM systems WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['online'] = ($r['url'] === 'local'); // نفترض أنها اوفلاين لو كانت بعيدة حتى يتم تحديثها لاحقاً
        }
        echo json_encode(['success'=>true, 'data'=>$rows]); exit;
    }

    if ($act === 'add_system') {
        $nm  = trim($d['name']   ?? '');
        $url = trim($d['url']    ?? '');
        $tk  = trim($d['token']  ?? '');
        $col = $d['color'] ?? '#58a6ff';
        if (!$nm || !$url || !$tk) { echo json_encode(['success'=>false,'error'=>'missing fields']); exit; }
        $pdo->prepare("INSERT INTO systems(name,url,api_token,color)VALUES(?,?,?,?)")
            ->execute([$nm, rtrim($url, '/'), $tk, $col]);
        echo json_encode(['success'=>true]); exit;
    }

    if ($act === 'remove_system') {
        $pdo->prepare("DELETE FROM systems WHERE id=? AND url!='local'")->execute([(int)($d['id']??0)]);
        echo json_encode(['success'=>true]); exit;
    }

    // ── Dashboard data ────────────────────────────────────────────
    if ($act === 'get_dashboard_data') {
        if ($sys && $sys['url'] !== 'local') {
            $r = proxy($sys, 'get_dashboard_data');
            echo json_encode($r ?? ['error'=>'timeout','bots'=>[],'orders'=>[],'today'=>null,'risk'=>null,'logs'=>[]]);
            exit;
        }
        
        $pdo->exec("UPDATE bot_workers SET status='offline'
                    WHERE last_heartbeat < NOW()-INTERVAL 60 SECOND
                      AND status NOT IN ('offline','paused')");
                      
        // سحب الطلبات العالقة مع بوتات فقدت الاتصال (لأكثر من دقيقتين) - Safe version
        try {
            $offline_bots = $pdo->query("SELECT bot_id FROM bot_workers WHERE last_heartbeat < NOW()-INTERVAL 120 SECOND")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($offline_bots)) {
                $in_clause = implode(',', array_fill(0, count($offline_bots), '?'));
                $pdo->prepare("UPDATE orders SET status='manual_review', requires_human=1, fail_reason='[SYSTEM] البوت فقد الاتصال' WHERE status='processing' AND bot_assigned IN ($in_clause)")->execute($offline_bots);
            }
        } catch(Exception $e) {}
        
        $bots = $pdo->query("
            SELECT b.*, a.email razer_email, a.balance_status acct_balance,
                   TIMESTAMPDIFF(SECOND, b.last_heartbeat, NOW()) secs_since_hb,
                   TIMESTAMPDIFF(MINUTE, b.session_start,  NOW()) session_mins
            FROM bot_workers b LEFT JOIN razer_accounts a ON a.bot_id=b.bot_id
            ORDER BY b.bot_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $orders = $pdo->query("
            SELECT id, woo_order_id, player_id, diamonds, status, bot_assigned, checkout_clicked,
                   requires_human, financial_risk, created_at, dispatched_at,
                   updated_at, fail_reason, evidence_path,
                   TIMESTAMPDIFF(SECOND, IFNULL(dispatched_at,created_at), NOW()) elapsed
            FROM orders
            WHERE status IN ('pending','processing','manual_review','stuck')
               OR (status IN ('completed','failed') AND updated_at > NOW()-INTERVAL 48 HOUR)
            ORDER BY FIELD(status,'stuck','manual_review','processing','pending','failed','completed'),
                     updated_at DESC LIMIT 500
        ")->fetchAll(PDO::FETCH_ASSOC);

        $today = $pdo->query("
            SELECT COUNT(*) total,
                   SUM(status='completed') done,
                   SUM(status='failed')    failed,
                   ROUND(AVG(CASE WHEN status='completed' THEN duration_seconds END)) avg_sec
            FROM orders WHERE created_at >= NOW() - INTERVAL 24 HOUR
        ")->fetch(PDO::FETCH_ASSOC);

        $risk = $pdo->query("
            SELECT SUM(status='manual_review') mr_count,
                   SUM(status='processing' AND dispatched_at < NOW()-INTERVAL 10 MINUTE) stuck_count,
                   SUM(status='pending') queue_depth
            FROM orders
        ")->fetch(PDO::FETCH_ASSOC);

        $logs = $pdo->query("
            SELECT bot_id,order_id,action,result,created_at
            FROM bot_action_logs ORDER BY created_at DESC LIMIT 60
        ")->fetchAll(PDO::FETCH_ASSOC);

        $insuf = array();
        foreach ($bots as $b) {
            if ((isset($b['acct_balance']) && $b['acct_balance'] === 'insufficient') ||
                (isset($b['balance_status']) && $b['balance_status'] === 'insufficient')) {
                $insuf[] = $b['bot_id'];
            }
        }

        $low_bots = array();
        $error_paused = array();
        foreach ($bots as $b) {
            if ((isset($b['acct_balance']) && $b['acct_balance'] === 'low') || 
                (isset($b['balance_status']) && $b['balance_status'] === 'low')) {
                $low_bots[] = $b['bot_id'];
            }
            if (isset($b['status']) && $b['status'] === 'error_paused') {
                $error_paused[] = $b['bot_id'];
            }
        }

        echo json_encode([
            'bots'=>$bots, 'orders'=>$orders, 'today'=>$today, 'risk'=>$risk,
            'insufficient_bots'=>$insuf, 'low_active_bots'=>$low_bots, 'error_paused_bots'=>$error_paused, 'logs'=>$logs,
            'server_time'=>date('Y-m-d H:i:s')
        ]);
        
        dbg_log("total time: " . round((microtime(true)-$t_start)*1000) . "ms");
        dbg_log("-----------------");
        exit;
    }

    // Route remote commands via proxy
    if ($sys && $sys['url'] !== 'local' &&
        in_array($act, ['bot_command','order_action','global_pause','global_resume','emergency_stop'])) {
        echo json_encode(proxy($sys, $act, $d) ?? ['success'=>false,'error'=>'proxy failed']);
        exit;
    }

    // ── Accounts ─────────────────────────────────────────────────
    if ($act === 'get_accounts') {
        echo json_encode(['success'=>true, 'data'=>$pdo->query(
            "SELECT id,bot_id,label,email,balance_status,is_active,notes FROM razer_accounts ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($act === 'save_account') {
        $id    = (int)($d['id'] ?? 0);
        $email = trim($d['email'] ?? '');
        $pass  = trim($d['password'] ?? '');
        $otp   = trim($d['otp_secret'] ?? '');
        $label = trim($d['label'] ?? $email);
        $bot   = (trim($d['bot_id'] ?? '') ?: null);
        $notes = trim($d['notes'] ?? '');
        $act_  = (int)($d['is_active'] ?? 1);
        if (!$email || !$otp) { echo json_encode(['success'=>false,'error'=>'missing fields']); exit; }
        if ($id > 0) {
            $sql = "UPDATE razer_accounts SET label=?,email=?,otp_secret=?,bot_id=?,notes=?,is_active=?";
            $p   = [$label,$email,$otp,$bot,$notes,$act_];
            if ($pass) { $sql .= ',password=?'; $p[] = $pass; }
            $p[] = $id;
            $pdo->prepare($sql.' WHERE id=?')->execute($p);
        } else {
            if (!$pass) { echo json_encode(['success'=>false,'error'=>'password required']); exit; }
            $pdo->prepare("INSERT INTO razer_accounts(label,email,password,otp_secret,bot_id,notes,is_active)VALUES(?,?,?,?,?,?,?)")
                ->execute([$label,$email,$pass,$otp,$bot,$notes,$act_]);
        }
        echo json_encode(['success'=>true]); exit;
    }

    if ($act === 'delete_account') {
        $pdo->prepare("DELETE FROM razer_accounts WHERE id=?")->execute([$d['id']??0]);
        echo json_encode(['success'=>true]); exit;
    }

    // ── Bots ─────────────────────────────────────────────────────
    if ($act === 'bot_command') {
        $cmd = $d['command'] ?? '';
        $bid = $d['bot_id'] ?? '';
        if (!in_array($cmd, ['pause','resume'])) { echo json_encode(['success'=>false,'error'=>'invalid command']); exit; }
        
        $pdo->prepare("UPDATE bot_workers SET pending_command=? WHERE bot_id=?")->execute([$cmd, $bid]);
        
        if ($cmd === 'pause') {
            try {
                // سحب الطلب الجاري فوراً للمراجعة اليدوية لضمان عدم بقائه عالقاً
                $pdo->prepare("UPDATE orders SET status='manual_review', requires_human=1, fail_reason='[SYSTEM] تم إيقاف البوت يدوياً' 
                               WHERE bot_assigned=? AND status='processing'")->execute([$bid]);
            } catch(Exception $e) {}
        }
        
        echo json_encode(['success'=>true]); exit;
    }

    if ($act === 'add_bot') {
        $bid  = trim($d['bot_id'] ?? '');
        $port = (int)($d['port'] ?? 5000);
        if (!$bid) { echo json_encode(['success'=>false,'error'=>'bot_id required']); exit; }
        $pdo->prepare("INSERT INTO bot_workers(bot_id,port,ip,capacity,priority,status)VALUES(?,?,?,?,?,'offline')
                       ON DUPLICATE KEY UPDATE port=VALUES(port)")->execute([$bid,$port,'localhost',1,1]);
        echo json_encode(['success'=>true,'cmd'=>"python freefire_bot.py {$bid} {$port}"]); exit;
    }

    if ($act === 'remove_bot') {
        $pdo->prepare("DELETE FROM bot_workers WHERE bot_id=?")->execute([$d['bot_id']??'']);
        echo json_encode(['success'=>true]); exit;
    }

    // ── Orders ───────────────────────────────────────────────────
    if ($act === 'order_action') {
        $oid = (int)($d['order_id'] ?? 0);
        $cmd = trim($d['command'] ?? '');

        if ($cmd === 'force_complete') {
            $pw     = trim($d['admin_password'] ?? '');
            $reason = trim($d['reason'] ?? '');
            $ok = ($pw === ADMIN_PASSWORD) || password_verify($pw, ADMIN_PASSWORD);
            if (!$ok) { echo json_encode(['success'=>false,'error'=>'Wrong password']); exit; }
            if (!$reason) { echo json_encode(['success'=>false,'error'=>'Reason required']); exit; }
            $row = $pdo->prepare("SELECT woo_order_id,status FROM orders WHERE id=?");
            $row->execute([$oid]);
            $ord = $row->fetch(PDO::FETCH_ASSOC);
            if ($ord && $ord['status'] === 'completed') { echo json_encode(['success'=>false,'error'=>'already completed']); exit; }
            $pdo->prepare("UPDATE orders SET status='completed',requires_human=0,fail_reason=CONCAT('[FORCE] ',?),updated_at=NOW(),locked_by=NULL WHERE id=?")
                ->execute([$reason, $oid]);
            $pdo->prepare("INSERT INTO bot_action_logs(bot_id,order_id,action,result)VALUES('admin',?,?,?)")
                ->execute([$oid,'Force Complete',$reason]);
            $woo = 'no_woo_id';
            if ($ord && !empty($ord['woo_order_id'])) {
                $ch = curl_init(WOO_STORE_URL.'/wp-json/wc/v3/orders/'.$ord['woo_order_id']);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>'PUT',
                    CURLOPT_POSTFIELDS=>json_encode(['status'=>'completed']),
                    CURLOPT_USERPWD=>WOO_CONSUMER_KEY.':'.WOO_CONSUMER_SECRET,
                    CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>15]);
                $wc = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_exec($ch); curl_close($ch);
                $woo = ($wc >= 200 && $wc < 300) ? 'updated' : "http_{$wc}";
                $pdo->prepare("INSERT INTO bot_action_logs(bot_id,order_id,action,result)VALUES('admin',?,?,?)")
                    ->execute([$oid,'WooCommerce Update',"status=completed|result={$woo}"]);
            }
            echo json_encode(['success'=>true,'woo_updated'=>$woo]); exit;
        }

        if ($cmd === 'reset_pending') {
            $pdo->prepare("UPDATE orders SET status='pending',locked_by=NULL,locked_at=NULL,
                           bot_assigned=NULL,checkout_clicked=0,requires_human=0,financial_risk='none'
                           WHERE id=? AND status!='completed'")->execute([$oid]);
            echo json_encode(['success'=>true]); exit;
        }

        if ($cmd === 'withdraw_to_review') {
            $pdo->prepare("UPDATE orders SET status='manual_review',requires_human=1,locked_by=NULL,locked_at=NULL,
                           bot_assigned=NULL,checkout_clicked=0,fail_reason='[ADMIN] تم سحب الطلب يدوياً للمراجعة'
                           WHERE id=? AND status!='completed'")->execute([$oid]);
            echo json_encode(['success'=>true]); exit;
        }

        if ($cmd === 'force_failed') {
            $row = $pdo->prepare("SELECT woo_order_id,status FROM orders WHERE id=?");
            $row->execute([$oid]);
            $ord = $row->fetch(PDO::FETCH_ASSOC);

            if ($ord && $ord['status'] === 'completed') { echo json_encode(['success'=>false,'error'=>'already completed']); exit; }

            $pdo->prepare("UPDATE orders SET status='failed', requires_human=0, locked_by=NULL, fail_reason='[FORCE] تم الإلغاء يدوياً من لوحة التحكم', updated_at=NOW() WHERE id=?")
                ->execute([$oid]);

            $pdo->prepare("INSERT INTO bot_action_logs(bot_id,order_id,action,result)VALUES('admin',?,?,?)")
                ->execute([$oid,'Force Cancel','تم الإلغاء من لوحة التحكم']);

            $woo = 'no_woo_id';
            if ($ord && !empty($ord['woo_order_id'])) {
                $ch = curl_init(WOO_STORE_URL.'/wp-json/wc/v3/orders/'.$ord['woo_order_id']);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>'PUT',
                    CURLOPT_POSTFIELDS=>json_encode(['status'=>'cancelled', 'customer_note'=>'تم إلغاء الطلب من لوحة التحكم']),
                    CURLOPT_USERPWD=>WOO_CONSUMER_KEY.':'.WOO_CONSUMER_SECRET,
                    CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>15]);
                $wc = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_exec($ch); curl_close($ch);
                $woo = ($wc >= 200 && $wc < 300) ? 'updated' : "http_{$wc}";
                $pdo->prepare("INSERT INTO bot_action_logs(bot_id,order_id,action,result)VALUES('admin',?,?,?)")
                    ->execute([$oid,'WooCommerce Cancel Update',"status=cancelled|result={$woo}"]);
            }

            echo json_encode(['success'=>true, 'woo_updated'=>$woo]); exit;
        }

        if ($cmd === 'delete_order') {
            $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$oid]);
            $pdo->prepare("DELETE FROM bot_action_logs WHERE order_id=?")->execute([$oid]);
            echo json_encode(['success'=>true]); exit;
        }

        echo json_encode(['success'=>false,'error'=>'unknown command']); exit;
    }

    if ($act === 'global_pause') {
        $pdo->exec("UPDATE bot_workers SET pending_command='pause' WHERE status NOT IN ('offline')");
        echo json_encode(['success'=>true]); exit;
    }

    if ($act === 'global_resume') {
        $pdo->exec("UPDATE bot_workers SET pending_command='resume' WHERE status='paused'");
        echo json_encode(['success'=>true]); exit;
    }

    if ($act === 'emergency_stop') {
        $pdo->exec("UPDATE bot_workers SET pending_command='pause'");
        $pdo->exec("UPDATE orders SET status='manual_review',requires_human=1 WHERE status='processing' AND checkout_clicked=1");
        $pdo->exec("UPDATE orders SET status='pending',locked_by=NULL,locked_at=NULL WHERE status='processing' AND checkout_clicked=0");
        echo json_encode(['success'=>true]); exit;
    }

    echo json_encode(['error'=>'unknown action']); exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bot Orchestrator</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
  --bg:     #0E1016;
  --bg2:    #14161F;
  --bg3:    #1C1F2E;
  --bg4:    #242840;
  --border: #252836;
  --border2:#2F3347;
  --text:   #DDE1F0;
  --text2:  #7B82A0;
  --text3:  #4A5070;
  --green:  #14532D; --gl: #4ADE80; --gg: rgba(74,222,128,0.12);
  --amber:  #78350F; --al: #FCD34D; --ag: rgba(252,211,77,0.12);
  --red:    #7F1D1D; --rl: #F87171; --rg: rgba(248,113,113,0.12);
  --purple: #3B0764; --pl: #C084FC; --pg: rgba(192,132,252,0.12);
  --cyan:   #1E3A5F; --cl: #60A5FA;
  --razer:  #34D399;
  --mono:   'JetBrains Mono', monospace;
}
html { font-family: 'Cairo', sans-serif; background: var(--bg); color: var(--text); font-size: 13px; line-height: 1.5; font-weight: 400; }
body { min-height: 100vh; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

/* NAV */
nav { display: flex; align-items: center; padding: 0 16px; background: var(--bg2); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; min-height: 48px; gap: 0; }
.brand { font-size: 13px; font-weight: 500; color: var(--razer); letter-spacing: 0.3px; padding-left: 16px; border-left: 1px solid var(--border); margin-left: 8px; white-space: nowrap; }
.sys-tabs { display: flex; flex: 1; overflow-x: auto; padding: 0 6px; }
.sys-tab { padding: 0 14px; height: 48px; background: transparent; border: none; border-bottom: 2px solid transparent; color: var(--text2); font-size: 13px; cursor: pointer; white-space: nowrap; transition: color 0.15s; display: flex; align-items: center; gap: 6px; font-weight: 400; }
.sys-tab:hover { color: var(--text); }
.sys-tab.active { color: var(--text); border-bottom-color: var(--cl); }
.sys-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.nav-right { display: flex; align-items: center; gap: 10px; padding-right: 4px; border-right: 1px solid var(--border); margin-right: 8px; }
.nav-clock { font-family: var(--mono); font-size: 11px; color: var(--text3); }

/* PAGE TABS */
.ptabs { display: flex; background: var(--bg2); border-bottom: 1px solid var(--border); }
.ptab { padding: 10px 18px; background: transparent; border: none; border-bottom: 2px solid transparent; color: var(--text2); font-size: 13px; font-weight: 400; cursor: pointer; transition: all 0.15s; }
.ptab:hover { color: var(--text); background: var(--bg3); }
.ptab.active { color: var(--text); border-bottom-color: var(--cl); }

/* PAGES */
.page { display: none; padding: 14px 16px; max-width: 1700px; margin: 0 auto; }
.page.active { display: block; }

/* SECTION HEADER */
.shdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
.shdr-title { font-size: 10px; font-weight: 500; color: var(--text3); text-transform: uppercase; letter-spacing: 0.7px; display: flex; align-items: center; gap: 8px; }

/* STATS BAR */
.stats-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.stat-cell { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; flex: 1; min-width: 110px; display: flex; flex-direction: column; gap: 3px; }
.stat-lbl { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.7px; font-weight: 500; }
.stat-val { font-size: 24px; font-weight: 500; font-family: var(--mono); }
.stat-val.g { color: var(--gl); } .stat-val.a { color: var(--al); } .stat-val.r { color: var(--rl); }
.stat-actions-cell { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; flex: 1; min-width: 110px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

/* BOTS GRID */
.bots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 14px; }
.bot-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; transition: all 0.15s; }
.bot-card:hover { border-color: var(--border2); transform: translateY(-1px); }
.bot-top { display: flex; align-items: center; gap: 8px; padding: 9px 12px; background: var(--bg3); border-bottom: 1px solid var(--border); }
.bot-id { font-weight: 500; font-size: 13px; font-family: var(--mono); flex: 1; text-transform: uppercase; }
.bot-port { font-size: 10px; color: var(--text3); font-family: var(--mono); }
.bot-body { padding: 8px 12px; display: flex; flex-direction: column; gap: 3px; }
.brow { display: flex; justify-content: space-between; align-items: center; padding: 2px 0; border-bottom: 1px solid var(--border); font-size: 11px; }
.brow:last-child { border: none; }
.brow-label { color: var(--text3); font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; }
.brow-val { font-family: var(--mono); font-weight: 400; }
.bot-pending { padding: 4px 12px; font-size: 10px; color: var(--al); background: var(--ag); border-bottom: 1px solid var(--border); }
.bot-foot { padding: 8px 12px; }

/* TWO-PANEL LAYOUT */
.panels-row { display: grid; grid-template-columns: 1fr 370px 370px; gap: 12px; align-items: start; }
@media(max-width:1400px) { .panels-row { grid-template-columns: 1fr 370px; } }
@media(max-width:1100px) { .panels-row { grid-template-columns: 1fr; } }
.panel { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; overflow: hidden; }

/* TABLE */
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 6px; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead tr { background: transparent; }
th { padding: 8px 12px; text-align: right; font-weight: 500; color: var(--text2); font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; white-space: nowrap; border-bottom: 1px solid var(--border2); }
th.sortable { cursor: pointer; user-select: none; }
th.sortable:hover { color: var(--text); background: var(--bg4); }
.sort-ind { font-size: 9px; color: var(--cl); margin-right: 2px; }
td { padding: 8px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; white-space: nowrap; transition: background 0.15s; }
tr:last-child td { border: none; }
tr:hover td { background: var(--bg3); }
tr.tr-processing td { background: rgba(252,211,77,0.04); }
tr.tr-manual_review td { background: rgba(192,132,252,0.05); }
tr.tr-completed td { background: rgba(74,222,128,0.04); }
tr.tr-failed td { background: rgba(248,113,113,0.04); }
tr.tr-stuck td { background: rgba(248,113,113,0.08); }
tr.tr-processing:hover td, tr.tr-manual_review:hover td, tr.tr-completed:hover td, tr.tr-failed:hover td, tr.tr-stuck:hover td { background: var(--bg3); }
.mono { font-family: var(--mono); }
.tdim { color: var(--text3); }
.tempty { text-align: center; padding: 20px; color: var(--text3); }

/* SEARCH INPUT */
.srch { padding: 8px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: all 0.15s; width: 220px; }
.srch:focus { border-color: var(--cl); outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }

/* REVIEW PANEL */
.review-list { display: flex; flex-direction: column; gap: 8px; max-height: 640px; overflow-y: auto; margin-top: 10px; }
.rcard { background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; padding: 10px; }
.rcard-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.rcard-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: var(--text2); margin-bottom: 6px; }
.rcard-reason { font-size: 10px; color: var(--text3); margin-bottom: 8px; line-height: 1.5; border-right: 2px solid var(--border2); padding-right: 7px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.rcard-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.rcard-btns { display: flex; gap: 4px; flex-wrap: wrap; }
.review-thumb { width: 72px; height: 46px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid var(--border); transition: opacity 0.15s; }
.review-thumb:hover { opacity: 0.8; }

/* BADGES */
.badge { border-radius: 6px; font-size: 10px; font-weight: 500; padding: 2px 8px; letter-spacing: 0.2px; display: inline-block; }
.badge-sm { font-size: 9px; padding: 2px 6px; }
.badge-num { background: var(--bg3); color: var(--cl); padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 500; }
.badge-online { background: var(--gg); color: var(--gl); }
.badge-offline { background: var(--rg); color: var(--rl); }
.badge-red { background: var(--rg); color: var(--rl); }
.badge-amber { background: var(--ag); color: var(--al); }
.badge-purple { background: var(--pg); color: var(--pl); }
.bot-st-online { background: var(--gg); color: var(--gl); }
.bot-st-busy { background: var(--ag); color: var(--al); }
.bot-st-offline { background: var(--rg); color: var(--rl); }
.bot-st-paused { background: rgba(123,130,160,0.12); color: var(--text2); }
.bot-st-draining { background: var(--ag); color: var(--al); }

/* STATUS PILLS */
.pill { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 500; letter-spacing: 0.2px; }
.p-pending { background: rgba(123,130,160,0.12); color: var(--text2); }
.p-processing { background: var(--ag); color: var(--al); }
.p-completed { background: var(--gg); color: var(--gl); }
.p-failed { background: var(--rg); color: var(--rl); }
.p-manual_review { background: var(--pg); color: var(--pl); }
.p-stuck { background: var(--rg); color: var(--rl); }

/* BUTTONS */
.btn { background: transparent; border: 1px solid var(--border2); color: var(--text2); border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.15s; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; font-family: inherit; }
.btn:hover { background: var(--bg3); color: var(--text); border-color: var(--border2); }
.btn-sm { padding: 4px 10px; font-size: 11px; }
.btn-xs { padding: 2px 8px; font-size: 10px; }
.btn-primary { background: var(--cyan); border-color: #1D4ED8; color: #93C5FD; } .btn-primary:hover { background: #2563EB; }
.btn-green { background: #14532D; border-color: #166534; color: #4ADE80; } .btn-green:hover { background: #166534; }
.btn-amber { background: #78350F; border-color: #92400E; color: #FCD34D; } .btn-amber:hover { background: #92400E; }
.btn-red { background: #7F1D1D; border-color: #991B1B; color: #F87171; } .btn-red:hover { background: #991B1B; }
.btn-ghost { }
.btn-red-ghost { color: var(--rl); border-color: var(--border2); } .btn-red-ghost:hover { background: var(--rg); border-color: var(--red); color: var(--rl); }
.acell { display: flex; gap: 4px; flex-wrap: nowrap; }

/* ACCOUNTS / SYSTEMS GRID */
.agrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
.acard { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.acard-top { display: flex; justify-content: space-between; align-items: flex-start; }
.acard-label { font-weight: 500; font-size: 14px; }
.acard-sub { font-size: 11px; color: var(--text2); margin-top: 2px; }
.acard-meta { font-size: 12px; color: var(--text2); }
.acard-foot { display: flex; gap: 6px; margin-top: 4px; }
.add-card { background: var(--bg2); border: 1px dashed var(--border2); border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text2); font-size: 13px; transition: all 0.15s; min-height: 90px; }
.add-card:hover { border-color: var(--text3); color: var(--text); background: var(--bg3); }
.bot-cmd-code { font-size: 10px; background: var(--bg); padding: 5px 8px; border-radius: 4px; color: var(--cl); display: block; word-break: break-all; font-family: var(--mono); margin-top: 4px; }

/* LOG FEED */
.log-feed { display: flex; flex-direction: column; gap: 0; height: 480px; overflow-y: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 8px 0; font-family: var(--mono); font-size: 11px; line-height: 1.8; margin-top: 10px; }
.lrow { display: flex; gap: 8px; align-items: flex-start; padding: 2px 12px; background: transparent; }
.lrow:nth-child(even) { background: rgba(255, 255, 255, 0.015); }
.ltime { color: var(--text3); white-space: nowrap; flex-shrink: 0; }
.lbot { color: var(--cl); flex-shrink: 0; }
.lact { color: var(--text2); flex: 1; overflow: hidden; }
.lres.ok { color: var(--gl); } .lres.fail { color: var(--rl); }

/* MODALS */
.moverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.moverlay.open { display: flex; }
.modal { background: var(--bg2); border: 1px solid var(--border2); border-radius: 14px; padding: 24px; width: 90%; max-width: 480px; max-height: 88vh; overflow-y: auto; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
.modal h3 { font-size: 15px; font-weight: 500; margin-bottom: 16px; color: var(--text); }
.fg { margin-bottom: 12px; }
.fg label { display: block; font-size: 10px; color: var(--text3); margin-bottom: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.7px; }
.fg input, .fg select, .fg textarea { width: 100%; padding: 8px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: all 0.15s; }
.fg input:focus, .fg select:focus, .fg textarea:focus { border-color: var(--cl); outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }
.fg textarea { resize: vertical; height: 60px; }
.frow { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.mfooter { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 16px; }
.warnbox { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.25); border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #F87171; margin-bottom: 16px; line-height: 1.5; font-weight: 500; }

/* TOAST */
.toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(60px); background: var(--bg3); border: 1px solid var(--border2); border-radius: 8px; padding: 12px 24px; font-size: 13px; opacity: 0; transition: all 0.25s; z-index: 9999; pointer-events: none; white-space: nowrap; box-shadow: 0 10px 30px rgba(0,0,0,0.3); font-weight: 500; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* SCREENSHOT OVERLAY */
#ss-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 2000; display: none; align-items: center; justify-content: center; cursor: zoom-out; backdrop-filter: blur(4px); }
#ss-img { max-width: 96vw; max-height: 94vh; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }

/* BALANCE BADGES */
.bal-sufficient { background: var(--gg); color: var(--gl); }
.bal-low { background: var(--ag); color: var(--al); }
.bal-insufficient { background: var(--rg); color: var(--rl); }
.bal-unknown { background: rgba(123,130,160,0.12); color: var(--text3); }

/* ALERTS */
.critical-alerts {
  display: none;
  background: rgba(248,113,113,0.08);
  border-bottom: 1px solid rgba(248,113,113,0.25);
  color: #F87171;
  padding: 0 16px;
  text-align: center;
  font-weight: 500;
  font-size: 12px;
  z-index: 999;
  position: sticky;
  top: 0;
  height: 36px;
  line-height: 36px;
}
.warning-alerts {
  display: none;
  background: rgba(252,211,77,0.08);
  border-bottom: 1px solid rgba(252,211,77,0.25);
  color: #FCD34D;
  padding: 0 16px;
  text-align: center;
  font-weight: 500;
  font-size: 12px;
  z-index: 999;
  position: sticky;
  top: 0;
  height: 36px;
  line-height: 36px;
}
.alert-icon { font-size: 14px; margin: 0 8px; vertical-align: middle; opacity: 0.8; }
</style>
</head>
<body>

<div id="critical-alert-bar" class="critical-alerts">
    <span class="alert-icon">⚠</span> 
    <span id="alert-text">تنبيه: الرصيد منتهٍ في بعض البوتات!</span> 
    <span class="alert-icon">⚠</span>
</div>

<audio id="alert-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
<audio id="new-order-sound" src="https://assets.mixkit.co/active_storage/sfx/2866/2866-preview.mp3" preload="auto"></audio>

<!-- NAV -->
<nav>
  <div class="brand">BOT ORCHESTRATOR</div>
  <div class="sys-tabs" id="sys-tabs"></div>
  <div class="nav-right">
    <div id="notif-bell" class="bell-icon" onclick="resetNotifs()" title="الطلبات الجديدة">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      <span id="notif-badge" class="notif-badge" style="display:none">0</span>
    </div>
    <span class="nav-clock" id="nav-clock">--:--:--</span>
    <span class="badge" id="conn-badge">...</span>
    <a href="?logout=1" class="btn btn-xs btn-ghost" style="text-decoration:none" onclick="return confirm('تسجيل الخروج؟')">خروج</a>
  </div>
</nav>


<!-- PAGE TABS -->
<div class="ptabs">
  <button class="ptab active" onclick="showPage('dash',this)">لوحة التحكم</button>
  <button class="ptab" onclick="showPage('accounts',this)">الحسابات</button>
  <button class="ptab" onclick="showPage('bots-cfg',this)">البوتات</button>
  <button class="ptab" onclick="showPage('logs',this)">السجلات</button>
  <button class="ptab" onclick="showPage('systems',this)">الانظمة</button>
</div>

<!-- ═══ DASHBOARD ═══════════════════════════════════════════════════════════ -->
<div class="page active" id="page-dash">

  <!-- Stats Bar -->
  <div class="stats-bar" id="stats-bar">
    <div class="stat-actions-cell">
      <button class="btn btn-sm btn-ghost" onclick="globalAction('pause')">ايقاف الكل</button>
      <button class="btn btn-sm btn-ghost" onclick="globalAction('resume')">استئناف الكل</button>
      <button class="btn btn-sm btn-red" onclick="doEmergencyStop()">ايقاف طارئ</button>
    </div>
    <div class="stat-cell"><div class="stat-lbl">الطلبات اليوم</div><div class="stat-val" id="s-total">0</div></div>
    <div class="stat-cell"><div class="stat-lbl">مكتملة</div><div class="stat-val g" id="s-done">0</div></div>
    <div class="stat-cell"><div class="stat-lbl">فاشلة</div><div class="stat-val r" id="s-failed">0</div></div>
    <div class="stat-cell"><div class="stat-lbl">معدل النجاح</div><div class="stat-val" id="s-rate">100%</div></div>
    <div class="stat-cell"><div class="stat-lbl">في الطابور</div><div class="stat-val a" id="s-queue">0</div></div>
    <div class="stat-cell"><div class="stat-lbl">بوتات نشطة</div><div class="stat-val g" id="s-workers">0</div></div>
  </div>

  <!-- Bots -->
  <div class="shdr"><span class="shdr-title">البوتات</span></div>
  <div class="bots-grid" id="bots-grid">
    <div class="tdim" style="padding:12px">جاري التحميل...</div>
  </div>

  <!-- Two-panel: Orders + Manual Review -->
  <div class="panels-row">
    <!-- Orders table -->
    <div class="panel">
      <div class="shdr">
        <span class="shdr-title">الطلبات النشطة <span class="badge-num" id="orders-count">0</span></span>
        <input class="srch" id="order-srch" placeholder="بحث بـ ID او Player ID" autocomplete="off" oninput="renderOrders()">
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th class="sortable" onclick="sortBy('id')"># <span class="sort-ind" id="si-id"></span></th>
            <th>Player ID</th>
            <th class="sortable" onclick="sortBy('diamonds')">الكمية <span class="sort-ind" id="si-diamonds"></span></th>
            <th>الحالة</th>
            <th>البوت</th>
            <th class="sortable" onclick="sortBy('elapsed')">الوقت <span class="sort-ind" id="si-elapsed"></span></th>
            <th>اجراءات</th>
          </tr></thead>
          <tbody id="orders-tbody">
            <tr><td colspan="7" class="tempty">جاري التحميل...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Manual Review -->
    <div class="panel">
      <div class="shdr">
        <span class="shdr-title">تحتاج مراجعة <span class="badge-red badge-num" id="review-count">0</span></span>
        <input class="srch" id="review-srch" placeholder="بحث مراجعة..." style="width:140px;padding:4px 8px;font-size:11px" autocomplete="off" oninput="renderManualReview()">
      </div>
      <div class="review-list" id="review-list">
        <div style="color:var(--text3);font-size:12px;padding:12px 0;text-align:center">لا توجد طلبات تحتاج مراجعة</div>
      </div>
    </div>

    <!-- Archive -->
    <div class="panel">
      <div class="shdr">
        <span class="shdr-title">الطلبات المكتملة والفاشلة</span>
      </div>
      <div class="review-list" id="archive-list">
        <div style="color:var(--text3);font-size:12px;padding:12px 0;text-align:center">لا توجد طلبات مؤرشفة</div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ ACCOUNTS ══════════════════════════════════════════════════════════════ -->
<div class="page" id="page-accounts">
  <div class="shdr">
    <span class="shdr-title">حسابات ريزر</span>
    <button class="btn btn-sm btn-primary" onclick="openAddAccount()">اضافة حساب</button>
  </div>
  <div class="agrid" id="accounts-grid"></div>
</div>

<!-- ═══ BOTS CONFIG ═══════════════════════════════════════════════════════════ -->
<div class="page" id="page-bots-cfg">
  <div class="shdr">
    <span class="shdr-title">اعدادات البوتات</span>
    <button class="btn btn-sm btn-primary" onclick="openModal('m-add-bot')">اضافة بوت</button>
  </div>
  <div class="agrid" id="bots-cfg-grid"></div>
</div>

<!-- ═══ LOGS ══════════════════════════════════════════════════════════════════ -->
<div class="page" id="page-logs">
  <div class="shdr">
    <span class="shdr-title">سجل الاحداث (آخر 60)</span>
    <select class="srch" id="log-filter" style="width:150px" onchange="renderLogs()">
      <option value="">كل البوتات</option>
    </select>
  </div>
  <div class="log-feed" id="log-feed"></div>
</div>

<!-- ═══ SYSTEMS ═══════════════════════════════════════════════════════════════ -->
<div class="page" id="page-systems">
  <div class="shdr">
    <span class="shdr-title">الانظمة المتصلة</span>
    <button class="btn btn-sm btn-primary" onclick="openModal('m-add-sys')">اضافة نظام</button>
  </div>
  <div class="agrid" id="systems-grid"></div>
</div>

<!-- ═══ MODALS ════════════════════════════════════════════════════════════════ -->

<!-- Add Account -->
<div class="moverlay" id="m-account">
  <div class="modal">
    <h3 id="m-acc-title">اضافة حساب ريزر</h3>
    <input type="hidden" id="acc-id">
    <div class="frow">
      <div class="fg"><label>اسم وصفي</label><input id="acc-label" placeholder="حساب ريزر 1"></div>
      <div class="fg"><label>البوت المرتبط</label><input id="acc-bot-id" placeholder="bot_1"></div>
    </div>
    <div class="fg"><label>البريد الالكتروني</label><input id="acc-email" type="email"></div>
    <div class="fg"><label>كلمة المرور</label><input id="acc-password" type="password" placeholder="اتركه فارغاً للابقاء على الحالي"></div>
    <div class="fg"><label>OTP Secret</label><input id="acc-otp"></div>
    <div class="fg"><label>ملاحظات</label><textarea id="acc-notes"></textarea></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="closeModal('m-account')">الغاء</button>
      <button class="btn btn-green" onclick="saveAccount()">حفظ</button>
    </div>
  </div>
</div>

<!-- Add Bot -->
<div class="moverlay" id="m-add-bot">
  <div class="modal">
    <h3>اضافة بوت جديد</h3>
    <div class="frow">
      <div class="fg"><label>معرف البوت (BOT_ID)</label><input id="nb-id" placeholder="bot_2"></div>
      <div class="fg"><label>المنفذ (PORT)</label><input id="nb-port" type="number" value="5001"></div>
    </div>
    <div id="nb-result" style="display:none;margin-top:10px"></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="closeModal('m-add-bot')">الغاء</button>
      <button class="btn btn-green" onclick="addBot()">اضافة</button>
    </div>
  </div>
</div>

<!-- Add System -->
<div class="moverlay" id="m-add-sys">
  <div class="modal">
    <h3>اضافة نظام جديد</h3>
    <div class="fg"><label>اسم النظام</label><input id="ns-name" placeholder="النظام الثاني"></div>
    <div class="fg"><label>رابط النظام (URL)</label><input id="ns-url" placeholder="https://bot.site2.com"></div>
    <div class="fg"><label>API Token</label><input id="ns-token" placeholder="super_secret_token_123"></div>
    <div class="fg"><label>اللون</label><input id="ns-color" type="color" value="#58a6ff"></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="closeModal('m-add-sys')">الغاء</button>
      <button class="btn btn-green" onclick="addSystem()">اضافة</button>
    </div>
  </div>
</div>

<!-- Force Complete -->
<div class="moverlay" id="m-fc">
  <div class="modal">
    <h3>اكمال يدوي — طلب #<span id="fc-oid"></span></h3>
    <div class="warnbox">هذا الاجراء لا يمكن التراجع عنه. سيتم تحديث حالة الطلب في قاعدة البيانات والمتجر.</div>
    <div class="fg"><label>كلمة سر المسؤول</label><input id="fc-pw" type="password"></div>
    <div class="fg"><label>السبب (مطلوب)</label><textarea id="fc-reason" placeholder="سبب الاكمال اليدوي..."></textarea></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="closeModal('m-fc')">الغاء</button>
      <button class="btn btn-red" onclick="submitFC()">تأكيد الاكمال</button>
    </div>
  </div>
</div>

<!-- Screenshot Overlay -->
<div id="ss-overlay" onclick="this.style.display='none'">
  <img id="ss-img" src="" alt="screenshot">
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
let dashData = {bots:[],orders:[],logs:[],today:{},risk:{},insufficient_bots:[]};
let systems  = [];
let selSys   = 1;
let pollTimer= null;
let sortCol  = 'id';
let sortDir  = -1;
let currentFCId = null;
let lastAlertedBots = [];
let lastTotalOrders = -1;
let unreadBellCount = 0;

function resetNotifs() {
  unreadBellCount = 0;
  const badge = document.getElementById('notif-badge');
  if (badge) badge.style.display = 'none';
}

// ── Boot ──────────────────────────────────────────────────────────────────────
let appBooted = false;
function bootApp() {
  if (appBooted) return;
  appBooted = true;
  try { loadSystems(); } catch(e) { console.error('loadSystems error:', e); }
  try { startPoll(); } catch(e) { console.error('startPoll error:', e); }
  try {
    setInterval(updateClock, 1000);
    updateClock();
  } catch(e) { console.error('Clock error:', e); }
}

setTimeout(bootApp, 500);
window.addEventListener('DOMContentLoaded', bootApp);
window.addEventListener('load', bootApp);

// ── Systems ───────────────────────────────────────────────────────────────────
async function loadSystems(cb) {
  const r = await api('get_systems', null, 1);
  if (!r.success) return;
  systems = r.data;
  renderSysTabs();
  renderSystemsPage();
  if (!pollTimer) startPoll();
  if (cb) cb();
}

function renderSysTabs() {
  const el = document.getElementById('sys-tabs');
  el.innerHTML = systems.map(s => `
    <button class="sys-tab ${s.id == selSys ? 'active' : ''}" onclick="selectSystem(${s.id})">
      <span class="sys-dot" style="background:${s.online ? s.color : '#6e7681'}"></span>
      ${esc(s.name)}
    </button>
  `).join('') +
  `<button class="sys-tab" onclick="openModal('m-add-sys')" style="color:var(--text3)">+ نظام</button>`;
}

function selectSystem(id) {
  selSys = id;
  renderSysTabs();
  clearInterval(pollTimer);
  pollTimer = null;
  startPoll();
}

function renderSystemsPage() {
  const grid = document.getElementById('systems-grid');
  if (!systems.length) { grid.innerHTML = '<div class="add-card" onclick="openModal(\'m-add-sys\')">+ اضافة نظام</div>'; return; }
  grid.innerHTML = systems.map(s => `
    <div class="acard">
      <div class="acard-top">
        <div>
          <div class="acard-label" style="color:${s.color}">${esc(s.name)}</div>
          <div class="acard-sub">${s.url === 'local' ? 'النظام المحلي' : esc(s.url)}</div>
        </div>
        <span class="badge ${s.online ? 'badge-online' : 'badge-offline'}">${s.online ? 'متصل' : 'منفصل'}</span>
      </div>
      ${s.url !== 'local' ? `<div class="acard-foot"><button class="btn btn-xs btn-red-ghost" onclick="removeSystem(${s.id})">حذف</button></div>` : ''}
    </div>
  `).join('') +
  `<div class="add-card" onclick="openModal('m-add-sys')">+ اضافة نظام جديد</div>`;
}

async function addSystem() {
  const nm  = v('ns-name'), url = v('ns-url'), tk = v('ns-token'), col = v('ns-color');
  if (!nm || !url || !tk) { alert('يرجى ملء جميع الحقول'); return; }
  const r = await api('add_system', {name:nm, url, token:tk, color:col}, 1);
  if (r.success) { closeModal('m-add-sys'); loadSystems(); showToast('تم اضافة النظام'); }
  else alert('خطأ: ' + r.error);
}

async function removeSystem(id) {
  if (!confirm('حذف هذا النظام؟')) return;
  await api('remove_system', {id}, 1);
  loadSystems();
  showToast('تم الحذف');
}

// ── Poll ──────────────────────────────────────────────────────────────────────
function startPoll() {
  poll();
  pollTimer = setInterval(poll, 3000);
}

let isPolling = false;
async function poll() {
  if (isPolling) return;
  isPolling = true;
  const badge = document.getElementById('conn-badge');
  try {
    const r = await api('get_dashboard_data');
    if (r.error && !r.bots) throw new Error(r.error);
    dashData = r;
    renderAll();
    badge.textContent = 'ONLINE';
    badge.className   = 'badge badge-online';
  } catch(e) {
    badge.textContent = 'OFFLINE';
    badge.className   = 'badge badge-offline';
  } finally {
    isPolling = false;
  }
}

function renderAll() {
  try {
    renderStats();
    renderBots();
    renderOrders();
    renderManualReview();
    renderArchive();
    renderLogs();
    checkAlerts();
    checkNewOrdersNotif();
  } catch(e) { console.error('Render error:', e); }
}

function checkNewOrdersNotif() {
  if (dashData.today && typeof dashData.today.total !== 'undefined') {
    const currentTotal = +(dashData.today.total);
    if (lastTotalOrders === -1 || currentTotal < lastTotalOrders) {
      lastTotalOrders = currentTotal;
    } else if (currentTotal > lastTotalOrders) {
      const newOrders = currentTotal - lastTotalOrders;
      lastTotalOrders = currentTotal;
      unreadBellCount += newOrders;
      
      const badge = document.getElementById('notif-badge');
      const bell = document.getElementById('notif-bell');
      if (badge && bell) {
        badge.textContent = unreadBellCount;
        badge.style.display = 'block';
        bell.classList.remove('bell-ring');
        void bell.offsetWidth;
        bell.classList.add('bell-ring');
      }
      
      const sound = document.getElementById('new-order-sound');
      if (sound) {
        sound.currentTime = 0;
        sound.play().catch(e => console.log('Audio blocked:', e));
      }
    }
  }
}

function checkAlerts() {
  const bar = document.getElementById('critical-alert-bar');
  const sound = document.getElementById('alert-sound');
  const insufficient = dashData.insufficient_bots || [];
  const lowBots = dashData.low_active_bots || [];
  const errorBots = dashData.error_paused_bots || [];
  
  let msg = [];
  let isCritical = false;
  let currentAlerts = [];
  
  if (insufficient.length > 0) {
    msg.push('رصيد منتهٍ: ' + insufficient.join(', '));
    isCritical = true;
    insufficient.forEach(id => currentAlerts.push('crit_'+id));
  }
  
  if (errorBots.length > 0) {
    msg.push('توقف إجباري (أخطاء): ' + errorBots.join(', '));
    isCritical = true;
    errorBots.forEach(id => currentAlerts.push('err_'+id));
  }
  
  if (lowBots.length > 0) {
    msg.push('رصيد منخفض: ' + lowBots.join(', '));
    lowBots.forEach(id => currentAlerts.push('warn_'+id));
  }
  
  if (msg.length > 0) {
    if (bar) {
      bar.style.display = 'block';
      bar.className = isCritical ? 'critical-alerts' : 'warning-alerts';
    }
    const textEl = document.getElementById('alert-text');
    if (textEl) textEl.textContent = 'تنبيه: ' + msg.join(' | ');
    
    const newBots = currentAlerts.filter(id => !lastAlertedBots.includes(id));
    if (newBots.length > 0) {
      if (sound) {
        sound.currentTime = 0;
        sound.play().catch(e => console.log("Audio play blocked:", e));
      }
      lastAlertedBots = currentAlerts.slice();
    }
  } else {
    if (bar) bar.style.display = 'none';
    lastAlertedBots = [];
  }
}


// ── Stats ─────────────────────────────────────────────────────────────────────
function renderStats() {
  const t = dashData.today || {};
  const total = +(t.total||0), done = +(t.done||0), failed = +(t.failed||0);
  const rate  = total > 0 ? Math.round(done/total*100) : 100;
  const queue = +( (dashData.risk && dashData.risk.queue_depth) || 0 );
  const workers = (dashData.bots||[]).filter(b=>['online','busy'].includes(b.status)).length;
  set('s-total',  total);
  set('s-done',   done);
  set('s-failed', failed);
  set('s-rate',   rate + '%');
  set('s-queue',  queue);
  set('s-workers', workers);
  const rateEl = document.getElementById('s-rate');
  if (rateEl) rateEl.className = 'stat-val ' + (rate>=90?'g':rate>=70?'a':'r');
}

// ── Bots ──────────────────────────────────────────────────────────────────────
function renderBots() {
  const grid = document.getElementById('bots-grid');
  const bots = dashData.bots || [];
  if (!bots.length) { grid.innerHTML = '<div style="padding:10px;color:var(--text3);font-size:12px">لا بوتات مسجلة</div>'; return; }
  grid.innerHTML = bots.map(botCard).join('');
}

function botCard(b) {
  const stMap = {online:'ONLINE',busy:'BUSY',offline:'OFFLINE',paused:'PAUSED',error_paused:'توقف لأخطاء',draining:'DRAINING'};
  const stLbl = stMap[b.status] || b.status.toUpperCase();
  const stCls = 'bot-st-' + (b.status||'offline');
  const sr    = parseFloat(b.success_rate||100).toFixed(1);
  const srC   = +sr>=90?'green':+sr>=70?'amber':'red';
  const hb    = +(b.secs_since_hb||0);
  const hbTxt = hb + ' ثواني';
  const hbC   = hb > 10 ? 'red' : 'green';
  const sm    = +(b.session_mins||0);
  const smTxt = sm >= 60 ? Math.floor(sm/60)+'h '+sm%60+'m' : sm+'m';
  return `<div class="bot-card">
    <div class="bot-top">
      <span class="bot-id">${esc(b.bot_id)}</span>
      <span class="bot-port">:${b.port}</span>
      <span class="badge ${stCls} badge-sm">${stLbl}</span>
    </div>
    <div class="bot-body">
      <div class="brow"><span class="brow-label">الجلسة</span><span class="brow-val">${smTxt}</span></div>
      <div class="brow"><span class="brow-label">اليوم</span><span class="brow-val">${b.orders_today||0}</span></div>
      <div class="brow"><span class="brow-label">النجاح</span><span class="brow-val ${srC}">${sr}%</span></div>
      <div class="brow"><span class="brow-label">آخر نبض</span><span class="brow-val ${hbC}">${hbTxt}</span></div>
    </div>
    ${b.pending_command?`<div class="bot-pending">جاري مزامنة: ${b.pending_command.toUpperCase()}</div>`:''}
    <div class="bot-foot">
      ${b.status==='paused' || b.status==='error_paused'
        ? `<button class="btn btn-sm btn-green" onclick="botCmd('${b.bot_id}','resume')">استئناف العمل</button>`
        : b.status==='offline' ? `<button class="btn btn-sm btn-ghost" disabled style="opacity:0.5;cursor:not-allowed;">اوفلاين</button>`
        : `<button class="btn btn-sm btn-amber" onclick="botCmd('${b.bot_id}','pause')">ايقاف</button>`}
    </div>
  </div>`;
}

// ── Orders ────────────────────────────────────────────────────────────────────
function renderOrders() {
  const srchEl = document.getElementById('order-srch');
  const srch = srchEl ? srchEl.value.toLowerCase() : '';
  
  // إذا لم يتم تحميل البيانات بعد، لا تضع "لا توجد طلبات"
  const tbody = document.getElementById('orders-tbody');
  if (!dashData.orders || (!dashData.orders.length && !srch)) {
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="tempty">لا توجد طلبات نشطة حالياً</td></tr>';
    set('orders-count', 0);
    return;
  }

  let orders = dashData.orders.filter(o => !['manual_review','stuck','failed'].includes(o.status));
  if (srch) {
    orders = orders.filter(o =>
      String(o.id).includes(srch) || String(o.player_id).toLowerCase().includes(srch)
    );
  }
  
  orders = [...orders].sort((a,b) => {
    let av = a[sortCol]??'', bv = b[sortCol]??'';
    if (typeof av === 'string') return sortDir * av.localeCompare(bv);
    return sortDir * (+av - +bv);
  });
  
  set('orders-count', orders.length);
  if (!orders.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="tempty">${srch ? 'لا توجد نتائج بحث تطابق استعلامك' : 'لا توجد طلبات نشطة'}</td></tr>`;
    return;
  }

  tbody.innerHTML = orders.map(o => {
    const el  = +(o.elapsed||0);
    const elC = el > 600 ? 'red' : el > 300 ? 'amber' : '';
    const ss  = o.evidence_path
      ? `<button class="btn btn-xs btn-ghost" onclick="viewSS('${esc(o.evidence_path.split('/').pop())}')">صورة</button>` : '';
    return `<tr class="tr-${o.status}">
      <td class="mono">#${o.id} ${o.woo_order_id ? `<br><span style="font-size:10px;color:var(--text3)">(Woo: #${o.woo_order_id})</span>` : ''}</td>
      <td class="mono bold">${esc(o.player_id)}</td>
      <td class="mono">${esc(o.diamonds)}</td>
      <td><span class="pill p-${o.status}">${stLbl(o.status)}</span></td>
      <td>${o.bot_assigned ? `<code style="font-size:10px;color:var(--cl)">${esc(o.bot_assigned)}</code>` : '<span class="tdim">-</span>'}</td>
      <td class="mono ${elC}">${fmtEl(el)}</td>
      <td><div class="acell">${ss}${orderBtns(o)}</div></td>
    </tr>`;
  }).join('');
}

function orderBtns(o) {
  const b = [];
  if (o.status !== 'completed')
    b.push(`<button class="btn btn-xs btn-ghost" onclick="openFC(${o.id})">اكمال</button>`);
  if (['failed','stuck','manual_review'].includes(o.status))
    b.push(`<button class="btn btn-xs btn-ghost" onclick="orderAction(${o.id},'reset_pending')">اعادة</button>`);
    if (o.status === 'processing')
    b.push(`<button class="btn btn-xs btn-ghost" style="color:var(--amber)" onclick="orderAction(${o.id},'withdraw_to_review')">سحب من البوت</button>`);
  if (o.status==='pending'||(o.status==='processing'&&!+o.checkout_clicked))
    b.push(`<button class="btn btn-xs btn-red-ghost" onclick="orderAction(${o.id},'force_failed')">الغاء</button>`);
  return b.join('');
}

function sortBy(col) {
  sortDir = (sortCol === col) ? sortDir * -1 : 1;
  sortCol = col;
  ['id','diamonds','elapsed'].forEach(c => {
    const el = document.getElementById('si-'+c);
    if (el) el.textContent = '';
  });
  const ind = document.getElementById('si-' + col);
  if (ind) ind.textContent = sortDir === 1 ? 'v' : '^';
  renderOrders();
}

// ── Manual Review ─────────────────────────────────────────────────────────────
function renderManualReview() {
  const list   = document.getElementById('review-list');
  const srch   = (document.getElementById('review-srch')?.value || '').toLowerCase();
  let orders = (dashData.orders||[]).filter(o => ['manual_review','stuck'].includes(o.status));
  
  // Note: Failed orders are already in Archive, we only show manual_review and stuck here.
  
  if (srch) {
    orders = orders.filter(o => String(o.id).includes(srch) || String(o.player_id).toLowerCase().includes(srch));
  }
  
  set('review-count', orders.length);
  if (!orders.length) {
    list.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px 0;text-align:center">لا توجد طلبات تحتاج مراجعة</div>';
    return;
  }
  list.innerHTML = orders.map(o => {
    const stC  = {manual_review:'badge-purple',stuck:'badge-red',failed:'badge-offline'}[o.status]||'badge-offline';
    const thumb = o.evidence_path
      ? `<img class="review-thumb" src="view_evidence.php?file=${esc(o.evidence_path.split('/').pop())}" alt="ss" onclick="viewSS('${esc(o.evidence_path.split('/').pop())}')" onerror="this.style.display='none'">`
      : '';
    return `<div class="rcard">
      <div class="rcard-top">
        <span class="mono bold">#${o.id}</span>
        <span class="badge ${stC} badge-sm">${stLbl(o.status)}</span>
      </div>
      <div class="rcard-meta">
        <span class="mono">${esc(o.player_id)}</span>
        <span>${esc(o.diamonds)} جوهرة</span>
        ${o.bot_assigned ? `<span style="color:var(--cl)">${esc(o.bot_assigned)}</span>` : ''}
      </div>
      ${o.fail_reason ? `<div class="rcard-reason" title="${esc(o.fail_reason)}">${esc((o.fail_reason||'').substring(0,90))}</div>` : ''}
      <div class="rcard-foot">
        ${thumb}
        <div class="rcard-btns">
          <button class="btn btn-xs btn-ghost" onclick="openFC(${o.id})">اكمال</button>
          <button class="btn btn-xs btn-ghost" onclick="orderAction(${o.id},'reset_pending')">اعادة</button>
          <button class="btn btn-xs btn-ghost" onclick="orderAction(${o.id},'force_failed')">الغاء</button>
          <button class="btn btn-xs btn-red-ghost" onclick="orderAction(${o.id},'delete_order')" title="حذف نهائي">حذف</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

// ── Archive ───────────────────────────────────────────────────────────────────
function renderArchive() {
  const list   = document.getElementById('archive-list');
  const orders = (dashData.orders||[]).filter(o => ['completed','failed'].includes(o.status));
  if (!orders.length) {
    list.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px 0;text-align:center">لا توجد طلبات منتهية</div>';
    return;
  }
  list.innerHTML = orders.map(o => {
    const stC  = o.status === 'completed' ? 'badge-online' : 'badge-offline';
    const thumb = o.evidence_path
      ? `<img class="review-thumb" src="view_evidence.php?file=${esc(o.evidence_path.split('/').pop())}" alt="ss" onclick="viewSS('${esc(o.evidence_path.split('/').pop())}')" onerror="this.style.display='none'">`
      : '';
    return `<div class="rcard" style="opacity:0.85">
      <div class="rcard-top">
        <span class="mono bold">#${o.id}</span>
        <span class="badge ${stC} badge-sm">${stLbl(o.status)}</span>
      </div>
      <div class="rcard-meta">
        <span class="mono">${esc(o.player_id)}</span>
        <span>${esc(o.diamonds)} جوهرة</span>
        <span style="color:var(--text3)">${(o.updated_at||'').split(' ')[1]||''}</span>
      </div>
      ${o.fail_reason ? `<div class="rcard-reason" title="${esc(o.fail_reason)}">${esc((o.fail_reason||'').substring(0,90))}</div>` : ''}
      <div class="rcard-foot">
        ${thumb}
        ${o.status === 'failed' ? `
        <div class="rcard-btns">
          <button class="btn btn-xs btn-ghost" onclick="openFC(${o.id})">اكمال</button>
          <button class="btn btn-xs btn-ghost" onclick="orderAction(${o.id},'reset_pending')">اعادة</button>
          <button class="btn btn-xs btn-red-ghost" onclick="orderAction(${o.id},'delete_order')" title="حذف نهائي">حذف</button>
        </div>` : ''}
      </div>
    </div>`;
  }).join('');
}

// ── Logs ──────────────────────────────────────────────────────────────────────
function renderLogs() {
  const filter = document.getElementById('log-filter').value;
  const logs   = (dashData.logs||[]).filter(l => !filter || l.bot_id===filter);
  const botIds = [...new Set((dashData.logs||[]).map(l=>l.bot_id))];
  const sel    = document.getElementById('log-filter');
  if (sel && sel.options.length <= 1)
    botIds.forEach(b => { const o=document.createElement('option'); o.value=b; o.textContent=b; sel.appendChild(o); });
  const feed = document.getElementById('log-feed');
  if (!feed) return;
  if (!logs.length) { feed.innerHTML='<span style="color:var(--text3)">لا سجلات...</span>'; return; }
  feed.innerHTML = logs.map(l => {
    const ok   = /success|ok|verified|complete|selected|clicked/i.test(l.result||'');
    const fail = /fail|error|mismatch|insufficient|exception/i.test(l.result||l.action||'');
    return `<div class="lrow">
      <span class="ltime">${(l.created_at||'').split(' ')[1]||''}</span>
      <span class="lbot">[${esc(l.bot_id)}]</span>
      <span class="lact">${l.order_id?'#'+l.order_id+' ':''}${esc(l.action)}</span>
      <span class="lres ${ok?'ok':fail?'fail':''}">${esc(l.result||'')}</span>
    </div>`;
  }).join('');
}

// ── Accounts ──────────────────────────────────────────────────────────────────
async function loadAccounts() {
  const r    = await api('get_accounts');
  const grid = document.getElementById('accounts-grid');
  const accs = r.data || [];
  if (!accs.length) {
    grid.innerHTML = '<div class="add-card" onclick="openAddAccount()">+ اضافة حساب جديد</div>';
    return;
  }
  grid.innerHTML = accs.map(a => `
    <div class="acard">
      <div class="acard-top">
        <div>
          <div class="acard-label">${esc(a.label||a.email)}</div>
          <div class="acard-sub mono">${esc(a.email)}</div>
        </div>
        <span class="badge bal-${a.balance_status||'unknown'} badge-sm">${balLbl(a.balance_status)}</span>
      </div>
      <div class="acard-meta">البوت المرتبط: <strong>${a.bot_id||'-'}</strong></div>
      <div class="acard-foot">
        <button class="btn btn-xs btn-ghost" onclick='editAccount(${JSON.stringify(a).replace(/"/g,"&quot;")})'>تعديل</button>
        <button class="btn btn-xs btn-red-ghost" onclick="deleteAccount(${a.id})">حذف</button>
      </div>
    </div>
  `).join('') + '<div class="add-card" onclick="openAddAccount()">+ اضافة حساب جديد</div>';
}

function openAddAccount() {
  ['acc-id','acc-label','acc-email','acc-password','acc-otp','acc-bot-id','acc-notes'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('m-acc-title').textContent = 'اضافة حساب ريزر';
  openModal('m-account');
}

function editAccount(a) {
  document.getElementById('acc-id').value      = a.id;
  document.getElementById('acc-label').value   = a.label||'';
  document.getElementById('acc-email').value   = a.email||'';
  document.getElementById('acc-password').value= '';
  document.getElementById('acc-otp').value     = a.otp_secret||'';
  document.getElementById('acc-bot-id').value  = a.bot_id||'';
  document.getElementById('acc-notes').value   = a.notes||'';
  document.getElementById('m-acc-title').textContent = 'تعديل الحساب';
  openModal('m-account');
}

async function saveAccount() {
  const id = +(document.getElementById('acc-id').value)||0;
  const r  = await api('save_account', {
    id, label: v('acc-label'), email: v('acc-email'),
    password: v('acc-password'), otp_secret: v('acc-otp'),
    bot_id: v('acc-bot-id'), notes: v('acc-notes'), is_active: 1
  });
  if (r.success) { closeModal('m-account'); loadAccounts(); showToast('تم الحفظ'); }
  else alert('خطأ: ' + r.error);
}

async function deleteAccount(id) {
  if (!confirm('حذف هذا الحساب؟')) return;
  await api('delete_account', {id});
  loadAccounts();
}

// ── Bots Config ───────────────────────────────────────────────────────────────
function renderBotsConfig() {
  const grid = document.getElementById('bots-cfg-grid');
  const bots = dashData.bots || [];
  const stMap = {online:'ONLINE',busy:'BUSY',offline:'OFFLINE',paused:'PAUSED',draining:'DRAINING'};
  if (!bots.length) {
    grid.innerHTML = '<div class="add-card" onclick="openModal(\'m-add-bot\')">+ اضافة بوت</div>';
    return;
  }
  grid.innerHTML = bots.map(b => `
    <div class="acard">
      <div class="acard-top">
        <div>
          <div class="acard-label mono">${esc(b.bot_id)}</div>
          <div class="acard-sub">Port: ${b.port} | ${b.ip||'localhost'}</div>
        </div>
        <span class="badge bot-st-${b.status||'offline'} badge-sm">${stMap[b.status]||'OFFLINE'}</span>
      </div>
      <div class="acard-meta">الحساب: <strong>${esc(b.razer_email||'-')}</strong></div>
      <code class="bot-cmd-code">python freefire_bot.py ${esc(b.bot_id)} ${b.port}</code>
      <div class="acard-foot">
        <button class="btn btn-xs btn-red-ghost" onclick="removeBot('${b.bot_id}')">ازالة</button>
      </div>
    </div>
  `).join('') + '<div class="add-card" onclick="openModal(\'m-add-bot\')">+ اضافة بوت جديد</div>';
}

async function addBot() {
  const r = await api('add_bot', {bot_id: v('nb-id'), port: +v('nb-port')});
  const rd = document.getElementById('nb-result');
  if (r.success) {
    rd.style.display = 'block';
    rd.innerHTML = `<div style="background:var(--ag);border:1px solid var(--amber);border-radius:6px;padding:10px;font-size:12px;color:var(--al)">
      تم اضافة البوت. شغله بالامر:<br><code style="font-family:monospace">${esc(r.cmd)}</code></div>`;
    setTimeout(poll, 800);
  } else { rd.style.display='block'; rd.innerHTML = `<div style="color:var(--rl)">خطأ: ${esc(r.error)}</div>`; }
}

async function removeBot(bid) {
  if (!confirm(`ازالة البوت "${bid}"؟`)) return;
  await api('remove_bot', {bot_id: bid});
  poll();
  renderBotsConfig();
}

// ── Actions ───────────────────────────────────────────────────────────────────
async function botCmd(botId, cmd) {
  if (cmd==='pause' && !confirm(`ايقاف البوت ${botId}?\n\nالطلب الجاري سينتقل الى مراجعة يدوية تلقائياً.`)) return;
  const r = await api('bot_command', {bot_id: botId, command: cmd});
  if (r.success) { showToast(cmd==='pause'?'تم الايقاف':'تم الاستئناف'); setTimeout(poll, 600); }
  else alert('خطأ: ' + r.error);
}

let isSubmittingAction = false;
async function orderAction(orderId, cmd) {
  const msgs = {
    force_failed:'الغاء هذا الطلب؟', 
    reset_pending:'اعادة الطلب للطابور؟',
    delete_order:'حذف هذا الطلب نهائياً من قاعدة البيانات والتاريخ؟ بضغطت سيتم مسحه بلا رجعه.'
  };
  if (msgs[cmd] && !confirm(msgs[cmd])) return;
  if (isSubmittingAction) return;
  isSubmittingAction = true;

  const r = await api('order_action', {order_id: orderId, command: cmd});
  isSubmittingAction = false;

  if (r.success) { showToast('تم'); poll(); }
  else {
    if (r.error === 'already completed') {
      showToast('الطلب مكتمل مسبقاً.');
      poll();
    } else {
      alert('خطأ: ' + r.error);
    }
  }
}

function openFC(orderId) {
  currentFCId = orderId;
  set('fc-oid', orderId);
  document.getElementById('fc-pw').value     = '';
  document.getElementById('fc-reason').value = '';
  openModal('m-fc');
}

async function submitFC() {
  if (isSubmittingAction) return;
  isSubmittingAction = true;
  const btn = document.querySelector('#m-fc .btn-red');
  const oldText = btn.textContent;
  btn.textContent = 'جاري التنفيذ...';

  const r = await api('order_action', {
    order_id: currentFCId, command: 'force_complete',
    admin_password: v('fc-pw'), reason: v('fc-reason')
  });

  btn.textContent = oldText;
  isSubmittingAction = false;

  if (r.success) {
    closeModal('m-fc');
    const woo = r.woo_updated==='updated' ? ' — تم تحديث المتجر'
              : r.woo_updated==='no_woo_id' ? ' — لا يوجد ID للمتجر'
              : ` — فشل تحديث المتجر (${r.woo_updated})`;
    showToast('تم الاكمال اليدوي' + woo);
    poll();
  } else {
    if (r.error === 'already completed') {
      showToast('الطلب مكتمل مسبقاً بالفعل.');
      closeModal('m-fc');
      poll();
    } else {
      alert('خطأ: ' + r.error);
    }
  }
}

async function globalAction(type) {
  await api(type==='pause'?'global_pause':'global_resume', {});
  showToast(type==='pause'?'تم ايقاف جميع البوتات':'تم استئناف جميع البوتات');
}

async function doEmergencyStop() {
  if (!confirm('ايقاف طارئ!\n\nسيتم ايقاف جميع البوتات وارجاع الطلبات الجارية فوراً.')) return;
  await api('emergency_stop', {});
  showToast('تم الايقاف الطارئ');
  poll();
}

// ── Screenshot ────────────────────────────────────────────────────────────────
function viewSS(filename) {
  document.getElementById('ss-img').src = 'view_evidence.php?file=' + encodeURIComponent(filename);
  document.getElementById('ss-overlay').style.display = 'flex';
}

// ── Page navigation ───────────────────────────────────────────────────────────
function showPage(name, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.ptab').forEach(t => t.classList.remove('active'));
  const pEl = document.getElementById('page-' + name);
  if (pEl) pEl.classList.add('active');
  if (el) el.classList.add('active');
  if (name==='accounts')  loadAccounts();
  if (name==='bots-cfg')  renderBotsConfig();
  if (name==='logs')      renderLogs();
  if (name==='systems')   loadSystems();
}

// ── Utilities ─────────────────────────────────────────────────────────────────
function openModal(id)  { const m = document.getElementById(id); if(m) m.classList.add('open'); }
function closeModal(id) { const m = document.getElementById(id); if(m) m.classList.remove('open'); }

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._tid);
  t._tid = setTimeout(() => t.classList.remove('show'), 3200);
}

function updateClock() {
  const el = document.getElementById('nav-clock');
  if (!el) return;
  try {
    el.textContent = new Date().toLocaleTimeString('ar');
  } catch(e) {
    el.textContent = new Date().toLocaleTimeString();
  }
}

async function api(action, body = null, sysOverride = null) {
  const sys = (sysOverride !== null && sysOverride !== undefined) ? sysOverride : selSys;
  try {
    const opts = {headers:{'Content-Type':'application/json'}};
    if (body !== null) { opts.method = 'POST'; opts.body = JSON.stringify(body); }
    const r = await fetch(`?action=${action}&sys=${sys}`, opts);
    if (r.status === 401) { window.location.href = '?'; return {error:'unauthenticated'}; }
    return await r.json();
  } catch(e) { return {success:false, error:e.message}; }
}


function esc(s) {
  return String(s||'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}
function v(id)    { const el = document.getElementById(id); return (el && el.value) ? el.value.trim() : ''; }
function set(id, val) { const el=document.getElementById(id); if(el) el.textContent=val; }

function stLbl(s) {
  return {pending:'انتظار',processing:'معالجة',completed:'مكتمل',failed:'فشل',
          manual_review:'مراجعة',stuck:'عالق'}[s] || s;
}

function balLbl(s) {
  return {sufficient:'كافٍ',low:'منخفض',insufficient:'نفد',unknown:'غير محدد'}[s||'unknown']||'?';
}

function fmtEl(sec) {
  sec = +sec;
  if (!sec || sec < 0) return '-';
  if (sec < 60)  return sec + 'ث';
  if (sec < 3600) return Math.floor(sec/60)+'د '+sec%60+'ث';
  return Math.floor(sec/3600)+'س '+Math.floor(sec%3600/60)+'د';
}
</script>
</body>
</html>
