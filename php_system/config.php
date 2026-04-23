<?php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'freefire_bot_db');

// WooCommerce API Configuration (لإرسال التحديثات العكسية للمتجر)
define('WOO_STORE_URL', 'https://drsudani.com'); 
define('WOO_CONSUMER_KEY', 'ضع_هنا_Consumer_Key_الخاص_بمتجرك'); 
define('WOO_CONSUMER_SECRET', 'ضع_هنا_Consumer_Secret_الخاص_بمتجرك'); 
// غير هذا للـ Consumer Secret

// Security Token (لتحمي الـ API الخاص بك من أي تدخل خارجي)
define('API_SECRET_TOKEN', 'super_secret_token_123');
define('ADMIN_PASSWORD',   'admin_2024_secure');   // ← غيّر هذا لكلمة سر قوية

// إعدادات اتصال السستم بالبوت (VPS Python)
define('BOT_API_URL', 'http://127.0.0.1:5000'); // غير هذا إلى IP الـ VPS الخاص بالبوت
define('BOT_API_TOKEN', 'bot_super_secret_123'); // كلمة سر للاتصال بالبوت لضمان الأمان

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Secure SQL configuration
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // OWASP A05: Security Misconfiguration - Do not leak database details on error
    error_log("Database Connection Failed: " . $e->getMessage());
    die("A technical error occurred while connecting to the database.");
}

// ── Secure Encryption API (AES-256-CBC) ──
function encrypt_payload($data) {
    $key = hash('sha256', API_SECRET_TOKEN, true); // 32-byte key
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt(json_encode($data), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

function decrypt_payload($payload) {
    if (!$payload) return null;
    $raw = base64_decode($payload);
    if (strlen($raw) < 16) return null;
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $key = hash('sha256', API_SECRET_TOKEN, true);
    $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted ? json_decode($decrypted, true) : null;
}
