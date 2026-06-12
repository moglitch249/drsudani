<?php
/**
 * Plugin Name: Dashboard TeraWallet Sync
 * Description: رابط أمني متوافق 100% لمزامنة رصيد محفظة TeraWallet من لوحة التحكم الخارجية.
 * Version: 1.1
 * Author: Dashboard System
 */

if (!defined('ABSPATH')) {
    exit;
}

// إضافة التوكن لصفحة إعدادات ووردبريس (مرة واحدة عند أول تثبيت)
add_action('admin_init', function () {
    if (!get_option('dashboard_sync_token')) {
        update_option('dashboard_sync_token', bin2hex(random_bytes(32)));
    }
});

add_action('rest_api_init', function () {
    register_rest_route('dashboard/v1', '/wallet', [
        'methods'             => 'POST',
        'callback'            => 'dashboard_update_terawallet',
        'permission_callback' => '__return_true' // الحماية الحقيقية داخل الدالة
    ]);
});

function dashboard_update_terawallet(WP_REST_Request $request) {
    // Rate limiting: لا أكثر من 10 محاولات فاشلة في الدقيقة من نفس الـ IP
    $client_ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key   = 'tw_rl_' . md5($client_ip);
    $attempts   = (int)get_transient($rate_key);
    if ($attempts >= 10) {
        return new WP_Error('rate_limited', 'تم تجاوز الحد الأقصى للمحاولات. حاول بعد دقيقة.', ['status' => 429]);
    }

    // 1. قراءة التوكن من إعدادات ووردبريس (ليس hardcoded!)
    $secret_token = get_option('dashboard_sync_token', '');
    if (empty($secret_token)) {
        return new WP_Error('config_error', 'لم يتم إعداد التوكن على الخادم.', ['status' => 500]);
    }

    $headers = $request->get_headers();
    $token   = isset($headers['authorization']) 
               ? str_replace('Bearer ', '', $headers['authorization'][0]) 
               : '';
    if (empty($token) && isset($headers['x_dashboard_token'])) {
        $token = $headers['x_dashboard_token'][0];
    }

    // مقارنة آمنة مقاومة لهجمات Timing Attack
    if (!hash_equals($secret_token, $token)) {
        // زيادة عداد المحاولات الفاشلة
        set_transient($rate_key, $attempts + 1, 60);
        return new WP_Error('unauthorized', 'مفتاح الربط غير مصرح به.', ['status' => 401]);
    }

    // إعادة تعيين العداد عند النجاح
    delete_transient($rate_key);

    // 2. استخراج البيانات
    $user_id = (int)$request->get_param('user_id');
    $amount  = (float)$request->get_param('amount');
    $type    = $request->get_param('type'); // deposit, withdraw, adjustment
    $note    = sanitize_text_field($request->get_param('note'));

    if (!$user_id || $amount <= 0) {
        return new WP_Error('invalid_data', 'بيانات غير صالحة: المبلغ أو معرّف المستخدم.', ['status' => 400]);
    }

    if (!in_array($type, ['deposit', 'withdraw', 'adjustment'], true)) {
        return new WP_Error('invalid_type', 'نوع المعاملة غير مدعوم.', ['status' => 400]);
    }

    // التحقق من وجود المستخدم
    if (!get_user_by('id', $user_id)) {
        return new WP_Error('user_not_found', 'المستخدم غير موجود على الموقع.', ['status' => 404]);
    }

    // 3. التحقق من وجود إضافة TeraWallet
    if (!function_exists('woo_wallet') || !class_exists('Woo_Wallet_Wallet')) {
        return new WP_Error('no_wallet', 'إضافة TeraWallet غير مفعلة على الموقع.', ['status' => 500]);
    }

    // 4. تحديث الرصيد للمستخدم
    try {
        $wallet = woo_wallet()->wallet;

        if ($type === 'deposit' || $type === 'adjustment') {
            $wallet->credit($user_id, $amount, $note);
        } elseif ($type === 'withdraw') {
            $balance = $wallet->get_wallet_balance($user_id, 'edit');
            if ($balance < $amount) {
                return new WP_Error('insufficient_funds', 'رصيد العميل في المحفظة غير كافٍ.', ['status' => 400]);
            }
            $wallet->debit($user_id, $amount, $note);
        }

        $new_balance = $wallet->get_wallet_balance($user_id, 'edit');
        return rest_ensure_response([
            'success'     => true,
            'message'     => 'تم دمج المعاملة بنجاح مع محفظة TeraWallet.',
            'new_balance' => $new_balance
        ]);

    } catch (Exception $e) {
        // لا نكشف رسالة الخطأ الداخلية للمستدعي
        error_log('[Dashboard TeraWallet] Error: ' . $e->getMessage());
        return new WP_Error('wallet_error', 'حدث خطأ داخلي في المحفظة. راجع سجل الخادم.', ['status' => 500]);
    }
}
