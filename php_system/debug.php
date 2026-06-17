<?php
/**
 * DEBUG TOOL - افتح هذا الملف مباشرة من المتصفح
 * https://bot.drsudani.com/debug.php
 * 
 * يفحص كل شيء: قاعدة البيانات، الجداول، الاستعلامات، PHP، الجلسات، الكتابة على القرص
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<html dir='rtl'><head><meta charset='utf-8'><title>Debug</title>";
echo "<style>body{font-family:monospace;background:#0d1117;color:#c9d1d9;padding:20px;font-size:14px;line-height:1.8}";
echo ".ok{color:#3fb950}.fail{color:#f85149}.warn{color:#d29922}.section{border:1px solid #30363d;padding:15px;margin:15px 0;border-radius:8px;background:#161b22}";
echo "h2{color:#58a6ff;margin:0 0 10px}pre{background:#0d1117;padding:10px;border-radius:4px;overflow-x:auto;white-space:pre-wrap}</style></head><body>";

echo "<h1>🔍 تشخيص النظام</h1>";
echo "<p>الوقت: " . date('Y-m-d H:i:s') . "</p>";

// ════════════════════════════════════════════════════════════════
// 1. PHP Version & Extensions
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>1️⃣ PHP Environment</h2>";
echo "PHP Version: <span class='ok'>" . phpversion() . "</span><br>";
$required_exts = ['pdo', 'pdo_mysql', 'openssl', 'json', 'curl', 'mbstring'];
foreach ($required_exts as $ext) {
    $loaded = extension_loaded($ext);
    echo "Extension [$ext]: <span class='" . ($loaded ? 'ok' : 'fail') . "'>" . ($loaded ? '✅ OK' : '❌ MISSING') . "</span><br>";
}
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "session.save_path: " . (session_save_path() ?: '(default)') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
echo "__DIR__: " . __DIR__ . "<br>";
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 2. Config File
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>2️⃣ Config File</h2>";
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    echo "<span class='ok'>✅ config.php موجود</span><br>";
    try {
        require_once $configPath;
        echo "DB_HOST: <span class='ok'>" . (defined('DB_HOST') ? DB_HOST : '❌ NOT SET') . "</span><br>";
        echo "DB_NAME: <span class='ok'>" . (defined('DB_NAME') ? DB_NAME : '❌ NOT SET') . "</span><br>";
        echo "DB_USER: <span class='ok'>" . (defined('DB_USER') ? DB_USER : '❌ NOT SET') . "</span><br>";
        echo "DB_PASS: <span class='ok'>" . (defined('DB_PASS') ? '***SET***' : '❌ NOT SET') . "</span><br>";
    } catch (Exception $e) {
        echo "<span class='fail'>❌ خطأ في تحميل config.php: " . $e->getMessage() . "</span><br>";
    }
} else {
    echo "<span class='fail'>❌ config.php غير موجود في: $configPath</span><br>";
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 3. Database Connection
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>3️⃣ Database Connection</h2>";
if (isset($pdo)) {
    echo "<span class='ok'>✅ اتصال قاعدة البيانات ناجح (PDO)</span><br>";
    
    // Server info
    try {
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
        echo "MySQL Version: <span class='ok'>$ver</span><br>";
    } catch (Exception $e) {
        echo "<span class='fail'>❌ " . $e->getMessage() . "</span><br>";
    }
    
    // Check max_connections and current connections
    try {
        $maxConn = $pdo->query("SHOW VARIABLES LIKE 'max_connections'")->fetch();
        $curConn = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch();
        echo "Max Connections: <span class='ok'>" . ($maxConn[1] ?? '?') . "</span><br>";
        echo "Current Connections: <span class='" . ((int)($curConn[1] ?? 0) > (int)($maxConn[1] ?? 100) * 0.8 ? 'fail' : 'ok') . "'>" . ($curConn[1] ?? '?') . "</span><br>";
    } catch (Exception $e) {
        echo "<span class='warn'>⚠ لا يمكن قراءة حالة الاتصالات: " . $e->getMessage() . "</span><br>";
    }

    // Check wait_timeout
    try {
        $wt = $pdo->query("SHOW VARIABLES LIKE 'wait_timeout'")->fetch();
        echo "wait_timeout: " . ($wt[1] ?? '?') . "s<br>";
    } catch (Exception $e) {}

} else {
    echo "<span class='fail'>❌ لا يوجد اتصال بقاعدة البيانات (PDO غير معرف)</span><br>";
    echo "<span class='warn'>هذا يعني أن config.php فشل في الاتصال</span><br>";
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 4. Tables Check
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>4️⃣ Tables & Row Counts</h2>";
if (isset($pdo)) {
    $tables = ['orders', 'bot_workers', 'razer_accounts', 'bot_action_logs', 'processed_keys', 'system_config', 'systems'];
    foreach ($tables as $tbl) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
            echo "Table [$tbl]: <span class='ok'>✅ $count rows</span><br>";
        } catch (Exception $e) {
            echo "Table [$tbl]: <span class='fail'>❌ " . $e->getMessage() . "</span><br>";
        }
    }
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 5. Query Performance (exactly like get_dashboard_data)
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>5️⃣ Query Performance (نفس استعلامات get_dashboard_data)</h2>";
if (isset($pdo)) {
    $queries = [
        'bot_workers_update' => "UPDATE bot_workers SET status='offline' WHERE last_heartbeat < NOW()-INTERVAL 60 SECOND AND status NOT IN ('offline','paused')",
        
        'offline_bots_select' => "SELECT bot_id FROM bot_workers WHERE last_heartbeat < NOW()-INTERVAL 120 SECOND",
        
        'bots_query' => "SELECT b.*, a.email razer_email, a.balance_status acct_balance,
                   TIMESTAMPDIFF(SECOND, b.last_heartbeat, NOW()) secs_since_hb,
                   TIMESTAMPDIFF(MINUTE, b.session_start, NOW()) session_mins
            FROM bot_workers b LEFT JOIN razer_accounts a ON a.bot_id=b.bot_id ORDER BY b.bot_id",
        
        'orders_query' => "SELECT id, woo_order_id, player_id, diamonds, status, bot_assigned, checkout_clicked,
                   requires_human, financial_risk, created_at, dispatched_at,
                   updated_at, fail_reason, evidence_path,
                   TIMESTAMPDIFF(SECOND, IFNULL(dispatched_at,created_at), NOW()) elapsed
            FROM orders
            WHERE status IN ('pending','processing','manual_review','stuck')
               OR (status IN ('completed','failed') AND updated_at > NOW()-INTERVAL 48 HOUR)
            ORDER BY FIELD(status,'stuck','manual_review','processing','pending','failed','completed'),
                     updated_at DESC LIMIT 500",
        
        'today_query' => "SELECT COUNT(*) total,
                   SUM(status='completed') done,
                   SUM(status='failed') failed,
                   ROUND(AVG(CASE WHEN status='completed' THEN duration_seconds END)) avg_sec
            FROM orders WHERE created_at >= NOW() - INTERVAL 24 HOUR",
        
        'risk_query' => "SELECT SUM(status='manual_review') mr_count,
                   SUM(status='processing' AND dispatched_at < NOW()-INTERVAL 10 MINUTE) stuck_count,
                   SUM(status='pending') queue_depth
            FROM orders",
        
        'logs_query' => "SELECT bot_id,order_id,action,result,created_at
            FROM bot_action_logs ORDER BY created_at DESC LIMIT 60",
    ];
    
    $totalTime = 0;
    foreach ($queries as $name => $sql) {
        $t = microtime(true);
        try {
            if (strpos(strtoupper(trim($sql)), 'UPDATE') === 0) {
                $pdo->exec($sql);
                $elapsed = round((microtime(true) - $t) * 1000);
                $totalTime += $elapsed;
                $cls = $elapsed > 1000 ? 'fail' : ($elapsed > 200 ? 'warn' : 'ok');
                echo "[$name]: <span class='$cls'>{$elapsed}ms ✅</span><br>";
            } else {
                $rows = $pdo->query($sql)->fetchAll();
                $elapsed = round((microtime(true) - $t) * 1000);
                $totalTime += $elapsed;
                $cls = $elapsed > 1000 ? 'fail' : ($elapsed > 200 ? 'warn' : 'ok');
                echo "[$name]: <span class='$cls'>{$elapsed}ms — " . count($rows) . " rows ✅</span><br>";
            }
        } catch (Exception $e) {
            $elapsed = round((microtime(true) - $t) * 1000);
            $totalTime += $elapsed;
            echo "[$name]: <span class='fail'>❌ {$elapsed}ms — " . $e->getMessage() . "</span><br>";
        }
    }
    echo "<br><strong>Total query time: <span class='" . ($totalTime > 2000 ? 'fail' : 'ok') . "'>{$totalTime}ms</span></strong><br>";
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 6. Locked Tables / Processes
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>6️⃣ MySQL Processes (Active Queries)</h2>";
if (isset($pdo)) {
    try {
        $procs = $pdo->query("SHOW PROCESSLIST")->fetchAll(PDO::FETCH_ASSOC);
        echo "Active processes: " . count($procs) . "<br>";
        echo "<pre>";
        foreach ($procs as $p) {
            $time = $p['Time'] ?? 0;
            $cls = $time > 10 ? 'fail' : ($time > 3 ? 'warn' : 'ok');
            echo "<span class='$cls'>ID:{$p['Id']} | User:{$p['User']} | Time:{$time}s | State:" . ($p['State'] ?? '-') . " | Query:" . substr($p['Info'] ?? '-', 0, 120) . "</span>\n";
        }
        echo "</pre>";
    } catch (Exception $e) {
        echo "<span class='warn'>⚠ " . $e->getMessage() . "</span><br>";
    }
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 7. Disk Write Test
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>7️⃣ Disk Write Test</h2>";
$testFile = __DIR__ . '/debug_write_test.txt';
$written = @file_put_contents($testFile, "test write at " . date('Y-m-d H:i:s'));
if ($written !== false) {
    echo "<span class='ok'>✅ الكتابة على القرص تعمل ($written bytes)</span><br>";
    @unlink($testFile);
} else {
    echo "<span class='fail'>❌ فشل الكتابة على القرص! هذا يعني أن dbg_log لن يعمل!</span><br>";
    echo "<span class='fail'>المسار: $testFile</span><br>";
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 8. Session Test
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>8️⃣ Session Test</h2>";
$sessionStarted = false;
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionStarted = true;
    echo "<span class='ok'>✅ Sessions تعمل</span><br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "bot_ok: " . (isset($_SESSION['bot_ok']) ? '<span class="ok">✅ مسجل دخول</span>' : '<span class="warn">⚠ غير مسجل دخول</span>') . "<br>";
    session_write_close(); // إغلاق الجلسة لمنع Deadlock مع cURL
} catch (Exception $e) {
    echo "<span class='fail'>❌ Sessions لا تعمل: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 9. Full get_dashboard_data simulation
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>9️⃣ Full API Simulation (get_dashboard_data)</h2>";
if (isset($pdo)) {
    $fullStart = microtime(true);
    try {
        // Simulate exactly what index.php does
        $pdo->exec("UPDATE bot_workers SET status='offline' WHERE last_heartbeat < NOW()-INTERVAL 60 SECOND AND status NOT IN ('offline','paused')");
        
        $offline_bots = $pdo->query("SELECT bot_id FROM bot_workers WHERE last_heartbeat < NOW()-INTERVAL 120 SECOND")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($offline_bots)) {
            $in_clause = implode(',', array_fill(0, count($offline_bots), '?'));
            $pdo->prepare("UPDATE orders SET status='manual_review', requires_human=1, fail_reason='[SYSTEM] البوت فقد الاتصال' WHERE status='processing' AND bot_assigned IN ($in_clause)")->execute($offline_bots);
        }
        
        $bots = $pdo->query("SELECT b.*, a.email razer_email, a.balance_status acct_balance, TIMESTAMPDIFF(SECOND, b.last_heartbeat, NOW()) secs_since_hb, TIMESTAMPDIFF(MINUTE, b.session_start, NOW()) session_mins FROM bot_workers b LEFT JOIN razer_accounts a ON a.bot_id=b.bot_id ORDER BY b.bot_id")->fetchAll(PDO::FETCH_ASSOC);
        
        $orders = $pdo->query("SELECT id, woo_order_id, player_id, diamonds, status, bot_assigned, checkout_clicked, requires_human, financial_risk, created_at, dispatched_at, updated_at, fail_reason, evidence_path, TIMESTAMPDIFF(SECOND, IFNULL(dispatched_at,created_at), NOW()) elapsed FROM orders WHERE status IN ('pending','processing','manual_review','stuck') OR (status IN ('completed','failed') AND updated_at > NOW()-INTERVAL 48 HOUR) ORDER BY FIELD(status,'stuck','manual_review','processing','pending','failed','completed'), updated_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        
        $today = $pdo->query("SELECT COUNT(*) total, SUM(status='completed') done, SUM(status='failed') failed, ROUND(AVG(CASE WHEN status='completed' THEN duration_seconds END)) avg_sec FROM orders WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetch(PDO::FETCH_ASSOC);
        
        $risk = $pdo->query("SELECT SUM(status='manual_review') mr_count, SUM(status='processing' AND dispatched_at < NOW()-INTERVAL 10 MINUTE) stuck_count, SUM(status='pending') queue_depth FROM orders")->fetch(PDO::FETCH_ASSOC);
        
        $logs = $pdo->query("SELECT bot_id,order_id,action,result,created_at FROM bot_action_logs ORDER BY created_at DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
        
        $result = json_encode([
            'bots' => $bots, 'orders' => $orders, 'today' => $today, 'risk' => $risk, 'logs' => $logs,
            'server_time' => date('Y-m-d H:i:s')
        ]);
        
        $fullElapsed = round((microtime(true) - $fullStart) * 1000);
        $jsonSize = strlen($result);
        $cls = $fullElapsed > 2000 ? 'fail' : ($fullElapsed > 500 ? 'warn' : 'ok');
        echo "<span class='$cls'>✅ Full simulation OK — {$fullElapsed}ms — JSON size: " . round($jsonSize/1024, 1) . "KB</span><br>";
        echo "Bots: " . count($bots) . " | Orders: " . count($orders) . " | Logs: " . count($logs) . "<br>";
        
    } catch (Exception $e) {
        $fullElapsed = round((microtime(true) - $fullStart) * 1000);
        echo "<span class='fail'>❌ FAILED at {$fullElapsed}ms: " . $e->getMessage() . "</span><br>";
    }
}
echo "</div>";

// ════════════════════════════════════════════════════════════════
// 10. index.php AJAX test
// ════════════════════════════════════════════════════════════════
echo "<div class='section'><h2>🔟 index.php AJAX Test (from server-side)</h2>";
$t = microtime(true);
$testUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php?action=get_dashboard_data&sys=1';
echo "Testing URL: <code>$testUrl</code><br>";
$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
$elapsed = round((microtime(true) - $t) * 1000);

if ($curlError) {
    echo "<span class='fail'>❌ cURL Error: $curlError ({$elapsed}ms)</span><br>";
} else {
    $cls = $elapsed > 3000 ? 'fail' : ($elapsed > 1000 ? 'warn' : 'ok');
    echo "HTTP Status: <span class='" . ($httpCode == 200 ? 'ok' : 'fail') . "'>$httpCode</span><br>";
    echo "Response Time: <span class='$cls'>{$elapsed}ms</span><br>";
    echo "Response Size: " . strlen($response) . " bytes<br>";
    
    $decoded = json_decode($response, true);
    if ($decoded === null && strlen($response) > 0) {
        echo "<span class='fail'>❌ Response is NOT valid JSON!</span><br>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 2000)) . "</pre>";
    } elseif ($decoded === null) {
        echo "<span class='fail'>❌ Empty response!</span><br>";
    } else {
        echo "<span class='ok'>✅ Valid JSON response</span><br>";
        if (isset($decoded['error'])) {
            echo "<span class='warn'>⚠ Error in response: " . htmlspecialchars($decoded['error']) . "</span><br>";
        }
    }
}
echo "</div>";

echo "<hr><p class='ok'>✅ Debug complete at " . date('H:i:s') . "</p>";
echo "</body></html>";
