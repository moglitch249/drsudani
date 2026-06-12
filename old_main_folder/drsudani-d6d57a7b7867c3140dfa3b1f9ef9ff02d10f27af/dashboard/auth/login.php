<?php
/**
 * صفحة تسجيل الدخول
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();

// إذا كان مسجل الدخول، وجهه للوحة المناسبة
if (!empty($_SESSION['agent_id'])) {
    $redirect = $_SESSION['role'] === 'admin'
        ? SITE_URL . '/admin/index.php'
        : SITE_URL . '/agent/index.php';
    header("Location: $redirect");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // حماية من Brute Force: أوقف المحاولة إذا تجاوز الحد
    loginRateLimit($username);

    if ($username === '' || $password === '') {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, username, password, full_name, role, permissions, is_active
             FROM agents WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $agent = $stmt->fetch();

        if ($agent && $agent['is_active'] && password_verify($password, $agent['password'])) {
            // نجاح! أعد عداد المحاولات
            loginRateLimitReset($username);
            session_regenerate_id(true);

            $_SESSION['agent_id']    = $agent['id'];
            $_SESSION['username']    = $agent['username'];
            $_SESSION['full_name']   = $agent['full_name'];
            $_SESSION['role']        = $agent['role'];
            $_SESSION['permissions'] = json_decode($agent['permissions'] ?? '{}', true) ?: [];
            $_SESSION['agent_name']  = $agent['full_name'];

            // تحديث وقت آخر دخول
            db()->prepare('UPDATE agents SET last_login = NOW() WHERE id = ?')
               ->execute([$agent['id']]);

            logAction('login', 'تسجيل دخول ناجح', $agent['id']);

            $redirect = $agent['role'] === 'admin'
                ? SITE_URL . '/admin/index.php'
                : SITE_URL . '/agent/index.php';

            header("Location: $redirect");
            exit;
        } else {
            // فشل! زد عداد المحاولات
            loginRateLimitFail($username);
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
            logAction('login_failed', "محاولة دخول فاشلة: $username");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">

        <!-- الشعار -->
        <div class="login-logo">
            <div class="logo-icon">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="10" fill="var(--color-primary)"/>
                    <path d="M10 18 L18 10 L26 18 L18 26 Z" fill="white" opacity="0.9"/>
                    <circle cx="18" cy="18" r="4" fill="white"/>
                </svg>
            </div>
            <h1 class="logo-text"><?= SITE_NAME ?></h1>
            <p class="logo-subtitle">منصة إدارة الوكلاء والمعاملات</p>
        </div>

        <!-- نموذج الدخول -->
        <form method="post" class="login-form" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?= clean($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success" style="background:var(--color-success-light); color:var(--color-success); border-color:rgba(16,185,129,0.2);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    تم تسجيل الخروج بنجاح.
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">اسم المستخدم</label>
                <div class="input-wrapper">
                    <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="أدخل اسم المستخدم"
                        value="<?= clean($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <div class="input-wrapper">
                    <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="أدخل كلمة المرور"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="toggle-password" title="إظهار/إخفاء">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="submitBtn">
                <span>تسجيل الدخول</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
            </button>
        </form>

        <p class="login-footer">© <?= date('Y') ?> <?= SITE_NAME ?> — جميع الحقوق محفوظة</p>
    </div>
</div>

<script>
// إظهار/إخفاء كلمة المرور
document.querySelector('.toggle-password')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
});

// تعطيل الزر أثناء الإرسال
document.getElementById('loginForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span>جارٍ الدخول...</span>';
});
</script>

</body>
</html>
