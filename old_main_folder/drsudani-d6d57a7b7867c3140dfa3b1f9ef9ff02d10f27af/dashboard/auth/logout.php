<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();

if (!empty($_SESSION['agent_id'])) {
    logAction('logout', 'تسجيل خروج');
}

session_unset();
session_destroy();

header('Location: ' . SITE_URL . '/auth/login.php?logout=success');
exit;
