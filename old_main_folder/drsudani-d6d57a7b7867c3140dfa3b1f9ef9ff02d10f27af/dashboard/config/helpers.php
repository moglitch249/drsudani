<?php
/**
 * دوال مساعدة عامة
 */

require_once __DIR__ . '/db.php';

// ── CSRF ────────────────────────────────────────────────────

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        $msg = 'رمز الأمان غير صالح.';
        if (defined('DEV_MODE') && DEV_MODE) {
            $msg .= ' (Debug: Session status: ' . session_status() . ')';
        }
        die(json_encode(['success' => false, 'message' => $msg]));
    }
}

// ── الجلسات ─────────────────────────────────────────────────

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Lax');
        
        // تفعيل Secure في حالة HTTPS (بما في ذلك الوكلاء مثل Cloudflare)
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ||
            ($_SERVER['SERVER_PORT'] ?? 0) == 443
        );
        
        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }
        session_start();
    }

    // تحقق من انتهاء الجلسة
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function requireLogin(): void
{
    if (empty($_SESSION['agent_id'])) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }

    if ($_SESSION['role'] === 'agent') {
        try {
            $stmt = db()->prepare("SELECT permissions, is_active FROM agents WHERE id = ?");
            $stmt->execute([$_SESSION['agent_id']]);
            $agent = $stmt->fetch();
            if (!$agent || !$agent['is_active']) {
                session_unset();
                session_destroy();
                header('Location: ' . SITE_URL . '/auth/login.php');
                exit;
            }
            $_SESSION['permissions'] = json_decode($agent['permissions'] ?? '{}', true) ?: [];
        } catch (Exception $e) {}
    }
}

function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/agent/index.php');
        exit;
    }
}

function requireAgent(): void
{
    requireLogin();
    if (!in_array($_SESSION['role'], ['admin', 'agent'])) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
}

function hasPermission(string $perm): bool
{
    if ($_SESSION['role'] === 'admin') return true;
    $perms = $_SESSION['permissions'] ?? [];
    return !empty($perms[$perm]);
}

// ── الإدخال ──────────────────────────────────────────────────

function clean(string $val): string
{
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function intval_safe($val): int
{
    return (int) filter_var($val, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
}

// ── الإعدادات ──────────────────────────────────────────────────

function getSetting(?PDO $db, string $key, string $default = ''): string
{
    if (!$db) return $default;
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// ── السجلات ──────────────────────────────────────────────────

function logAction(string $action, string $details = '', ?int $agentId = null): void
{
    $agentId = $agentId ?? ($_SESSION['agent_id'] ?? null);
    // إصلاح ثغرة IP Spoofing: نستخدم REMOTE_ADDR فقط ولا نثق بالهيدرات التي يمكن تزويرها
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    try {
        $stmt = db()->prepare(
            'INSERT INTO system_logs (agent_id, action, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$agentId, $action, $details, $ip, $ua]);
    } catch (PDOException) {
        // لا نوقف التنفيذ بسبب خطأ في السجل
    }
}

// ── الإشعارات ────────────────────────────────────────────────

function createNotification(
    string $type,
    string $title,
    string $body = '',
    ?int $referenceId = null,
    string $targetRole = 'all',
    ?int $targetAgentId = null
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications
               (type, title, body, reference_id, target_role, target_agent_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$type, $title, $body, $referenceId, $targetRole, $targetAgentId]);
    } catch (PDOException) {}
}

// ── تنسيق العملة ─────────────────────────────────────────────

function formatMoney(float $amount): string
{
    $symbol = getSetting(db(), 'currency_symbol', 'ر.س');
    return number_format($amount, 2) . ' ' . clean($symbol);
}

function formatDate(string $date): string
{
    return date('Y/m/d H:i', strtotime($date));
}

// ── JSON Response ────────────────────────────────────────────

function jsonSuccess(array $data = [], string $message = 'تمت العملية بنجاح')
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
// حماية من هجمات Brute Force على صفحة الدخول
// يسمح بعدد 10 محاولات في 15 دقيقة بناءً على عنوان IP

function loginRateLimit(string $identifier): void
{
    $key    = 'login_fail_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $maxTry = 10;
    $window = 900; // 15 دقيقة بالثانية

    $tries   = (int)($_SESSION[$key]['count'] ?? 0);
    $since   = (int)($_SESSION[$key]['since'] ?? 0);

    // إعادة ضبط النافذة إذا مضى وقت كافي
    if ($since > 0 && (time() - $since) > $window) {
        unset($_SESSION[$key]);
        $tries = 0;
    }

    if ($tries >= $maxTry) {
        $remaining = $window - (time() - $since);
        http_response_code(429);
        die('تم تجاوز عدد المحاولات المسموحة. تقدر تحاول مرة ثانية بعد ' . ceil($remaining / 60) . ' دقيقة.');
    }
}

function loginRateLimitFail(string $identifier): void
{
    $key  = 'login_fail_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $data = $_SESSION[$key] ?? ['count' => 0, 'since' => time()];
    $data['count']++;
    if ($data['count'] === 1) $data['since'] = time();
    $_SESSION[$key] = $data;
}

function loginRateLimitReset(string $identifier): void
{
    $key = 'login_fail_' . md5($identifier . ($_SERVER['REMOTE_ADDR'] ?? ''));
    unset($_SESSION[$key]);
}
