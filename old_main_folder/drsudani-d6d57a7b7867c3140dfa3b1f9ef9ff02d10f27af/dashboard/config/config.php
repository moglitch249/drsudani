<?php
/**
 * إعدادات النظام العامة
 * System Configuration
 */

// ── قاعدة البيانات ──────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'u981163816_dashboard_db');
define('DB_USER',     'u981163816_dashboard');
define('DB_PASS',     'Khaled4356780@gmail.com');
define('DB_CHARSET',  'utf8mb4');

// ── ووكوميرس REST API ────────────────────────────────────────
define('WC_API_URL',  'https://drsudani.com/');   // عنوان موقع ووردبريس
define('WC_KEY',      'ck_99d58f21353d7a442a7f70cf314790a0051185fc'); // مفتاح API ووكوميرس
define('WC_SECRET',   'cs_1f706ddade56516ced1ab373dc90f42c5c086520'); // سر API ووكوميرس

// مفتاح التزامن مع إضافة TeraWallet (للأمان)
define('TERAWALLET_TOKEN', 'b8b7151dad11e774056cacf088b63bf9e3949c2e6733185f771234567890abcd');

// سر توقيع WooCommerce Webhook - يجب أن يطابق ما تضعه في WooCommerce لوحة التحكم بالضبط
define('WC_WEBHOOK_SECRET', 'T09mU3VwZXJTZWNyZXRXZWJob29rMjAyNl9YeVo'); // غيّره لسر قوي!


// ── النظام ──────────────────────────────────────────────────
define('SITE_NAME',   'لوحة التحكم');
define('SITE_URL',    'http://server.drsudani.com/dashboard');
define('BASE_PATH',   dirname(__DIR__));

// ── الرفع ───────────────────────────────────────────────────
define('UPLOAD_MAX_SIZE',    2 * 1024 * 1024); // 2 MB
define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);

// ── الجلسات ─────────────────────────────────────────────────
define('SESSION_LIFETIME',   7200); // ثانيتان = ساعتان

// ── التطوير ─────────────────────────────────────────────────
define('DEV_MODE', true); // false في الإنتاج

if (DEV_MODE == true) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
