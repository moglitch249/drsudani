<?php
/**
 * Plugin Name: DrSudani Wallet API
 * Description: Secure proxy endpoint & Server-Side Checkout
 * Version: 1.2.0
 * Author: DrSudani
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// 1. مفاتيح WooCommerce وسر التطبيق - يجب تعريفها في wp-config.php
// ============================================================
// تأكد من إضافة السطور التالية إلى ملف wp-config.php الخاص بك:
// define('DS_WC_KEY', 'ck_...');
// define('DS_WC_SECRET', 'cs_...');
// define('DS_APP_SECRET', 'drsudani-mobile-v1-x9k2p7q4');
// define('DS_JWT_SECRET', 'nmd9QeK86uvb3EBdNdH9JRKQSy5kyP_£$');

if (!defined('DS_WC_KEY'))    define('DS_WC_KEY', '');
if (!defined('DS_WC_SECRET')) define('DS_WC_SECRET', '');
if (!defined('DS_APP_SECRET'))define('DS_APP_SECRET', '');
if (!defined('DS_JWT_SECRET'))define('DS_JWT_SECRET', '');

// ============================================================
// 2. حقن مفاتيح WooCommerce للمسارات الآمنة فقط (Smart Proxy)
// ============================================================
add_action('rest_api_init', function() {
    $app_secret = isset($_SERVER['HTTP_X_APP_SECRET']) ? $_SERVER['HTTP_X_APP_SECRET'] : '';
    if ($app_secret !== DS_APP_SECRET || empty(DS_APP_SECRET)) {
        return; // يتطلب سر التطبيق
    }

    $request_uri = $_SERVER['REQUEST_URI'];
    
    // 1. المسارات العامة الآمنة (المنتجات، التصنيفات)
    if (strpos($request_uri, '/wc/v3/products') !== false) {
        $_GET['consumer_key'] = DS_WC_KEY;
        $_GET['consumer_secret'] = DS_WC_SECRET;
        return;
    }

    // 2. مسارات تتطلب تسجيل الدخول (الطلبات، العملاء)
    if (strpos($request_uri, '/wc/v3/orders') !== false || strpos($request_uri, '/wc/v3/customers') !== false) {
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        $token = str_replace('Bearer ', '', $auth_header);
        
        if (empty($token)) return;
        
        $user_id = ds_get_user_id_from_token_fast($token);
        if ($user_id <= 0) return;

        // السماح بجلب الطلبات الخاصة بالمستخدم فقط
        if (strpos($request_uri, '/wc/v3/orders') !== false) {
            // نأخذ الـ user_id الحقيقي من التوكن ونجبره في الطلب
            $_GET['customer'] = $user_id;
            $_GET['consumer_key'] = DS_WC_KEY;
            $_GET['consumer_secret'] = DS_WC_SECRET;
            unset($_SERVER['HTTP_AUTHORIZATION']); // Prevent WP from authenticating with Bearer
        }
        
        // السماح بجلب بيانات العميل الخاصة به فقط
        if (preg_match('/\/wc\/v3\/customers\/(\d+)/', $request_uri, $matches)) {
            $requested_customer = intval($matches[1]);
            if ($requested_customer === $user_id) {
                $_GET['consumer_key'] = DS_WC_KEY;
                $_GET['consumer_secret'] = DS_WC_SECRET;
            }
        }
    }
}, 5);

function ds_get_user_id_from_token_fast($token) {
    $user = apply_filters('simple_jwt_login_user_from_token', null, $token);
    if ($user && !is_wp_error($user)) return $user->ID;

    if (!ds_verify_jwt_signature($token, DS_JWT_SECRET)) return 0;
    $decoded = ds_decode_jwt_payload($token);
    if (!$decoded) return 0;

    if (isset($decoded->id)) return intval($decoded->id);
    if (isset($decoded->data->user->id)) return intval($decoded->data->user->id);
    if (isset($decoded->email)) {
        $user_by_email = get_user_by('email', $decoded->email);
        return $user_by_email ? $user_by_email->ID : 0;
    }
    return 0;
}

// ============================================================
// تسجيل نقاط النهاية الخاصة بالتطبيق
// ============================================================
add_action('rest_api_init', function () {
    // 1. مسار المحفظة (Wallet Balance)
    register_rest_route('drsudani/v1', '/wallet', [
        'methods'             => 'GET',
        'callback'            => 'ds_get_wallet_balance',
        'permission_callback' => 'ds_verify_request',
    ]);

    // 2. مسار الدفع الآمن (Secure Checkout)
    register_rest_route('drsudani/v1', '/checkout', [
        'methods'             => 'POST',
        'callback'            => 'ds_process_checkout',
        'permission_callback' => 'ds_verify_request',
    ]);

    // 3. مسار الطلبات الخاص بالمستخدم (My Orders)
    register_rest_route('drsudani/v1', '/orders', [
        'methods'             => 'GET',
        'callback'            => 'ds_get_my_orders',
        'permission_callback' => 'ds_verify_request',
    ]);
});

function ds_get_my_orders(WP_REST_Request $request) {
    $user_id = (int) $request->get_param('_ds_user_id');
    if ($user_id <= 0) {
        return new WP_Error('no_user', 'Invalid user', ['status' => 400]);
    }

    $orders = wc_get_orders(['customer' => $user_id, 'limit' => -1]);
    $result = [];
    foreach ($orders as $order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            ];
        }
        $result[] = [
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d\TH:i:s') : '',
            'line_items' => $items
        ];
    }
    return rest_ensure_response($result);
}

/**
 * التحقق الأمني من الطلب:
 * - فحص سر التطبيق (X-App-Secret)
 * - فحص التوكن (JWT) وتوقيعه (Signature) لمنع التزييف
 * - Rate Limiting لمنع هجمات الـ Brute-Force
 */
function ds_verify_request(WP_REST_Request $request) {
    // === Rate Limiting (الحماية من هجمات الحرمان من الخدمة) ===
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    if ($ip !== 'unknown') {
        $rate_key = 'ds_rate_limit_' . md5($ip);
        $requests = (int) get_transient($rate_key);
        
        if ($requests > 40) { // الحد الأقصى 40 طلب في الدقيقة لكل IP
            return new WP_Error('too_many_requests', 'Rate limit exceeded. Please wait a minute.', ['status' => 429]);
        }
        set_transient($rate_key, $requests + 1, 60);
    }

    // === Layer 1: التحقق من سر التطبيق ===
    $app_secret = $request->get_header('X-App-Secret');
    if (!$app_secret || trim($app_secret) !== trim(DS_APP_SECRET)) {
        return new WP_Error('unauthorized', 'Unauthorized application.', ['status' => 401]);
    }

    // === Layer 1.5: HMAC Payload Verification (منع تعديل البيانات وإعادة الإرسال) ===
    $timestamp = $request->get_header('X-App-Timestamp');
    $client_signature = $request->get_header('X-App-Signature');
    
    if (!$timestamp || !$client_signature) {
        return new WP_Error('unauthorized', 'Missing request signature.', ['status' => 401]);
    }
    
    // منع الطلبات القديمة جداً (Replay Attack) - أكثر من 5 دقائق
    $now = round(microtime(true) * 1000);
    if (abs($now - intval($timestamp)) > 300000) {
        return new WP_Error('unauthorized', 'Request expired.', ['status' => 401]);
    }
    
    // إعادة حساب التوقيع الرقمي ومطابقته
    $path = $request->get_route();
    $body_str = $request->get_body() ? $request->get_body() : '';
    $payload = $path . $timestamp . $body_str;
    $server_signature = hash_hmac('sha256', $payload, DS_APP_SECRET);
    
    if (!hash_equals($server_signature, $client_signature)) {
        return new WP_Error('unauthorized', 'Invalid request signature.', ['status' => 401]);
    }

    // === Layer 2: التحقق من التوكن (JWT) وتوقيعه ===
    $auth_header = $request->get_header('Authorization');
    $token = '';
    
    if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
        $token = str_replace('Bearer ', '', $auth_header);
    }

    if (!$token) {
        return new WP_Error('no_token', 'Authorization token missing', ['status' => 401]);
    }

    // التحقق عبر Simple JWT Login plugin بشكل أصلي (وهذا يفحص التوقيع تلقائياً)
    $user = apply_filters('simple_jwt_login_user_from_token', null, $token);

    if (!$user || is_wp_error($user)) {
        // Fallback: فك التشفير اليدوي "مع التحقق من التوقيع" 
        $jwt_key_to_use = DS_JWT_SECRET;
        
        if ($jwt_key_to_use === 'PUT_YOUR_SIMPLE_JWT_LOGIN_SECRET_HERE' || empty($jwt_key_to_use)) {
            return new WP_Error('invalid_token', 'JWT secret not configured on server', ['status' => 500]);
        }

        if (!ds_verify_jwt_signature($token, $jwt_key_to_use)) {
            return new WP_Error('invalid_signature', 'Token signature verification failed. Tampering detected.', ['status' => 401]);
        }

        $decoded = ds_decode_jwt_payload($token);
        if (!$decoded) {
            return new WP_Error('invalid_token', 'Invalid or expired token', ['status' => 401]);
        }
        
        $user_id = 0;
        if (isset($decoded->id)) {
            $user_id = intval($decoded->id);
        } elseif (isset($decoded->data->user->id)) {
            $user_id = intval($decoded->data->user->id);
        } elseif (isset($decoded->email)) {
            $user_by_email = get_user_by('email', $decoded->email);
            if ($user_by_email) {
                $user_id = $user_by_email->ID;
            }
        }
        
        if (!$user_id) {
            return new WP_Error('no_user_in_token', 'Cannot extract user from token', ['status' => 401]);
        }
    } else {
        $user_id = $user->ID;
    }

    $request->set_param('_ds_user_id', $user_id);
    return true;
}

/**
 * التحقق من التوقيع الرقمي للـ JWT باستخدام HMAC SHA256
 */
function ds_verify_jwt_signature($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    $header = $parts[0];
    $payload = $parts[1];
    $signature_provided = $parts[2];

    $signature_expected = hash_hmac('sha256', "$header.$payload", $secret, true);
    $base64_url_signature_expected = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature_expected));

    return hash_equals($base64_url_signature_expected, $signature_provided);
}

function ds_decode_jwt_payload($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]) . '==');
    return $payload ? json_decode($payload) : null;
}

/**
 * جلب رصيد المحفظة — السيرفر يقرأ مباشرة من DB
 */
function ds_get_wallet_balance(WP_REST_Request $request) {
    $user_id = (int) $request->get_param('_ds_user_id');

    if ($user_id <= 0) {
        return new WP_Error('no_user', 'Invalid user', ['status' => 400]);
    }

    $balance    = ds_get_balance_from_meta($user_id);
    $first_name = get_user_meta($user_id, 'first_name', true) ?: get_userdata($user_id)->display_name;

    return rest_ensure_response([
        'success'    => true,
        'balance'    => $balance,
        'first_name' => $first_name,
    ]);
}

/**
 * معالجة الدفع: التأكد من الأسعار، خصم الرصيد، وإنشاء الطلب بأمان
 */
function ds_process_checkout(WP_REST_Request $request) {
    $user_id = (int) $request->get_param('_ds_user_id');
    if ($user_id <= 0) {
        return new WP_Error('no_user', 'Invalid user', ['status' => 400]);
    }

    // 1. Idempotency Check (الحماية من تكرار الطلب)
    $idempotency_key = $request->get_header('Idempotency-Key');
    if ($idempotency_key) {
        $transient_key = 'ds_checkout_' . md5($idempotency_key);
        if (get_transient($transient_key)) {
            return new WP_Error('duplicate_request', 'This order is already being processed.', ['status' => 409]);
        }
        set_transient($transient_key, true, 60); // قفل لمدة 60 ثانية
    }

    // 2. تحليل سلة المشتريات (السيرفر يحسب الأسعار، لا نثق بسعر التطبيق)
    $params = $request->get_json_params();
    $line_items_raw = isset($params['line_items']) ? $params['line_items'] : [];
    
    if (empty($line_items_raw)) {
        return new WP_Error('empty_cart', 'Cart is empty', ['status' => 400]);
    }

    $total_amount = 0.0;
    $order_items = [];

    foreach ($line_items_raw as $item) {
        $product_id = isset($item['product_id']) ? intval($item['product_id']) : 0;
        $variation_id = isset($item['variation_id']) ? intval($item['variation_id']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;

        if ($product_id <= 0 || $quantity <= 0) continue;

        // استخراج المنتج الحقيقي من الداتا بيز
        $product = wc_get_product($variation_id > 0 ? $variation_id : $product_id);
        if (!$product || $product->get_status() !== 'publish') {
            return new WP_Error('invalid_product', "Product ID {$product_id} is invalid or unavailable.", ['status' => 400]);
        }

        $real_price = (float) $product->get_price();
        $total_amount += ($real_price * $quantity);

        $order_items[] = [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'subtotal'     => $real_price * $quantity,
            'total'        => $real_price * $quantity,
            'meta_data'    => isset($item['meta_data']) ? $item['meta_data'] : []
        ];
    }

    if ($total_amount <= 0) {
        return new WP_Error('invalid_total', 'Order total must be greater than zero.', ['status' => 400]);
    }

    // 3. التحقق من رصيد المحفظة الفعلي
    $current_balance = ds_get_balance_from_meta($user_id);
    if ($current_balance < $total_amount) {
        return new WP_Error('insufficient_funds', 'رصيد المحفظة غير كافٍ لإتمام العملية.', ['status' => 402]);
    }

    // 4. إنشاء الطلب في WooCommerce قبل الخصم للضمان
    try {
        $order = wc_create_order(['customer_id' => $user_id]);
        
        foreach ($order_items as $o_item) {
            $item_id = $order->add_product(wc_get_product($o_item['variation_id'] > 0 ? $o_item['variation_id'] : $o_item['product_id']), $o_item['quantity'], [
                'subtotal' => $o_item['subtotal'],
                'total'    => $o_item['total']
            ]);
            
            // إضافة البيانات الوصفية (مثل الآيدي الخاص باللعبة)
            if (!empty($o_item['meta_data']) && $item_id) {
                $order_item = $order->get_item($item_id);
                foreach ($o_item['meta_data'] as $meta) {
                    $order_item->add_meta_data($meta['key'], $meta['value']);
                }
                $order_item->save();
            }
        }

        $order->set_payment_method('woo-wallet');
        $order->set_payment_method_title('المحفظة الإلكترونية');
        $order->calculate_totals();

        // 5. خصم الرصيد فعلياً
        if (function_exists('woo_wallet')) {
            $debit_success = woo_wallet()->wallet->debit($user_id, $total_amount, 'Payment for order #' . $order->get_id());
            if (!$debit_success) {
                $order->update_status('failed', 'فشل في خصم الرصيد.');
                return new WP_Error('wallet_debit_failed', 'حدث خطأ أثناء خصم الرصيد.', ['status' => 500]);
            }
        } else {
            // محاولة خصم يدوية كحل بديل (لا يُنصح به لكن لتجنب الأعطال)
            $new_balance = $current_balance - $total_amount;
            update_user_meta($user_id, 'woo_wallet_current_balance', $new_balance);
        }

        // 6. تحديث حالة الطلب إلى Processing
        $order->update_status('processing', 'تم الدفع بنجاح عبر المحفظة الإلكترونية.');

        return rest_ensure_response([
            'success'     => true,
            'order_id'    => $order->get_id(),
            'total_paid'  => $total_amount,
            'new_balance' => ds_get_balance_from_meta($user_id),
            'message'     => 'تم إنشاء الطلب وخصم الرصيد بنجاح.'
        ]);

    } catch (Exception $e) {
        return new WP_Error('order_creation_failed', 'Failed to create order: ' . $e->getMessage(), ['status' => 500]);
    }
}

function ds_get_balance_from_meta($user_id) {
    if (function_exists('woo_wallet')) {
        $balance = woo_wallet()->wallet->get_wallet_balance($user_id, 'edit');
        if (is_numeric($balance)) return (float) $balance;
    }
    
    foreach (['_uw_balance', 'woo_wallet_current_balance', '_wwallet_balance', 'wallet_balance', 'tera_wallet_balance'] as $key) {
        $val = get_user_meta($user_id, $key, true);
        if ($val !== '' && $val !== false && is_numeric($val)) return (float) $val;
    }
    return 0.0;
}
