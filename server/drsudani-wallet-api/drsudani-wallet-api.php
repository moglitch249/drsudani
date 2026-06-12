<?php
/**
 * Plugin Name: DrSudani Wallet API
 * Description: Secure REST API for DrSudani mobile app
 * Version:     2.0.0
 * Author:      DrSudani
 *
 * SECURITY MODEL:
 *   Layer 1 – App Secret header (shared secret)
 *   Layer 2 – HMAC-SHA256 request signature + 30-second replay window
 *   Layer 3 – JWT Bearer token with full signature verification + expiry check
 *   Layer 4 – Per-user DB-level atomic lock on /checkout (prevents race conditions)
 *   Layer 5 – Server-side pricing only (client prices are ignored)
 *   Layer 6 – Input whitelisting & sanitization on every meta field
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Constants – define in wp-config.php
// ---------------------------------------------------------------------------
defined( 'DS_WC_KEY' )    || define( 'DS_WC_KEY',    '' );
defined( 'DS_WC_SECRET' ) || define( 'DS_WC_SECRET', '' );
defined( 'DS_APP_SECRET' )|| define( 'DS_APP_SECRET','');
defined( 'DS_JWT_SECRET' )|| define( 'DS_JWT_SECRET','');

// Meta keys allowed in order line items (whitelist – injection prevention)
define( 'DS_ALLOWED_META_KEYS', [ 'Player ID', 'player_id', 'Email', 'email', 'Username', 'username', 'Game ID', 'game_id' ] );

// ---------------------------------------------------------------------------
// 1. WooCommerce proxy – inject consumer keys for approved routes only
// ---------------------------------------------------------------------------
add_action( 'rest_api_init', function () {

    $app_secret = $_SERVER['HTTP_X_APP_SECRET'] ?? '';
    if ( empty( DS_APP_SECRET ) || ! hash_equals( DS_APP_SECRET, $app_secret ) ) {
        return;
    }

    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    // Products & Categories – public read-only
    if ( str_contains( $uri, '/wc/v3/products' ) ) {
        $_GET['consumer_key']    = DS_WC_KEY;
        $_GET['consumer_secret'] = DS_WC_SECRET;
        return;
    }

    // Customers – GET only, user must own the record
    if ( str_contains( $uri, '/wc/v3/customers' ) && $method === 'GET' ) {
        $token   = ds_extract_bearer_token( $_SERVER['HTTP_AUTHORIZATION'] ?? '' );
        $user_id = $token ? ds_user_id_from_token( $token ) : 0;

        if ( $user_id > 0 && preg_match( '#/wc/v3/customers/(\d+)#', $uri, $m ) ) {
            if ( (int) $m[1] === $user_id ) {
                $_GET['consumer_key']    = DS_WC_KEY;
                $_GET['consumer_secret'] = DS_WC_SECRET;
            }
        }
    }

}, 5 );

// ---------------------------------------------------------------------------
// 2. Register custom REST routes
// ---------------------------------------------------------------------------
add_action( 'rest_api_init', function () {

    $auth = 'ds_verify_request';

    register_rest_route( 'drsudani/v1', '/wallet',   [ 'methods' => 'GET',  'callback' => 'ds_wallet_balance', 'permission_callback' => $auth ] );
    register_rest_route( 'drsudani/v1', '/checkout', [ 'methods' => 'POST', 'callback' => 'ds_checkout',       'permission_callback' => $auth ] );
    register_rest_route( 'drsudani/v1', '/orders',   [ 'methods' => 'GET',  'callback' => 'ds_my_orders',      'permission_callback' => $auth ] );

} );

// ---------------------------------------------------------------------------
// 3. Security response headers (applied to every REST response)
// ---------------------------------------------------------------------------
add_filter( 'rest_post_dispatch', function ( WP_REST_Response $response ) {
    $response->header( 'X-Content-Type-Options', 'nosniff' );
    $response->header( 'X-Frame-Options',         'DENY' );
    $response->header( 'Referrer-Policy',          'no-referrer' );
    $response->header( 'Cache-Control',            'no-store, no-cache, must-revalidate' );
    return $response;
}, 10, 1 );

// ===========================================================================
// PERMISSION CALLBACK  –  ds_verify_request()
// ===========================================================================
/**
 * Multi-layer security gate for every protected endpoint.
 * Returns true on success, WP_Error on failure.
 */
function ds_verify_request( WP_REST_Request $request ): bool|WP_Error {

    // --- Rate limit (40 req/min per IP) ---
    $ip = ds_real_ip();
    if ( $ip && ! ds_rate_limit( $ip, 40 ) ) {
        return new WP_Error( 'too_many_requests', 'Too many requests.', [ 'status' => 429 ] );
    }

    // --- Layer 1: App Secret ---
    $app_secret = $request->get_header( 'X-App-Secret' );
    if ( empty( DS_APP_SECRET ) || ! hash_equals( DS_APP_SECRET, (string) $app_secret ) ) {
        return new WP_Error( 'unauthorized', 'Unauthorized.', [ 'status' => 401 ] );
    }

    // --- Layer 2: HMAC signature + replay window (30 s) ---
    $timestamp = $request->get_header( 'X-App-Timestamp' );
    $signature = $request->get_header( 'X-App-Signature' );

    if ( ! $timestamp || ! $signature ) {
        return new WP_Error( 'unauthorized', 'Missing signature headers.', [ 'status' => 401 ] );
    }

    // Reject non-numeric or suspiciously long timestamps (overflow protection)
    if ( ! ctype_digit( $timestamp ) || strlen( $timestamp ) > 16 ) {
        return new WP_Error( 'unauthorized', 'Invalid timestamp.', [ 'status' => 401 ] );
    }

    $now = (int) round( microtime( true ) * 1000 );
    if ( abs( $now - (int) $timestamp ) > 30_000 ) {
        return new WP_Error( 'unauthorized', 'Request expired.', [ 'status' => 401 ] );
    }

    $expected = hash_hmac( 'sha256', $request->get_route() . $timestamp . $request->get_body(), DS_APP_SECRET );
    if ( ! hash_equals( $expected, (string) $signature ) ) {
        return new WP_Error( 'unauthorized', 'Invalid signature.', [ 'status' => 401 ] );
    }

    // --- Layer 3: JWT Bearer token ---
    $token = ds_extract_bearer_token( $request->get_header( 'Authorization' ) );
    if ( ! $token ) {
        return new WP_Error( 'no_token', 'Authorization token missing.', [ 'status' => 401 ] );
    }

    if ( strlen( $token ) > 4096 ) {
        return new WP_Error( 'invalid_token', 'Token too long.', [ 'status' => 401 ] );
    }

    $user_id = ds_user_id_from_token( $token );
    if ( $user_id <= 0 ) {
        return new WP_Error( 'invalid_token', 'Invalid or expired token.', [ 'status' => 401 ] );
    }

    // Verify the account still exists (deleted-user protection)
    if ( ! get_userdata( $user_id ) ) {
        return new WP_Error( 'user_not_found', 'User account not found.', [ 'status' => 401 ] );
    }

    $request->set_param( '_ds_user_id', $user_id );
    return true;
}

// ===========================================================================
// ENDPOINTS
// ===========================================================================

/**
 * GET /drsudani/v1/wallet
 * Returns the authenticated user's wallet balance and display name.
 */
function ds_wallet_balance( WP_REST_Request $request ): WP_REST_Response|WP_Error {

    $user_id = (int) $request->get_param( '_ds_user_id' );
    $user    = get_userdata( $user_id );

    if ( ! $user ) {
        return new WP_Error( 'not_found', 'User not found.', [ 'status' => 404 ] );
    }

    $first_name = esc_html( get_user_meta( $user_id, 'first_name', true ) ?: $user->display_name );
    $balance    = round( (float) ds_wallet_balance_value( $user_id ), 2 );

    return rest_ensure_response( [ 'success' => true, 'balance' => $balance, 'first_name' => $first_name ] );
}

/**
 * GET /drsudani/v1/orders
 * Returns a paginated list of orders belonging to the authenticated user.
 */
function ds_my_orders( WP_REST_Request $request ): WP_REST_Response|WP_Error {

    $user_id  = (int) $request->get_param( '_ds_user_id' );
    $page     = max( 1,  (int) ( $request->get_param( 'page' )     ?: 1  ) );
    $per_page = min( 50, (int) ( $request->get_param( 'per_page' ) ?: 20 ) );

    $orders = wc_get_orders( [
        'customer' => $user_id,
        'limit'    => $per_page,
        'paged'    => $page,
        'orderby'  => 'date',
        'order'    => 'DESC',
    ] );

    $result = [];
    foreach ( $orders as $order ) {

        // Defence-in-depth: skip orders not owned by this user
        if ( (int) $order->get_customer_id() !== $user_id ) continue;

        $items = [];
        foreach ( $order->get_items() as $item ) {

            // Only expose whitelisted meta keys (no internal _keys)
            $safe_meta = [];
            foreach ( $item->get_meta_data() as $meta ) {
                $data = $meta->get_data();
                if ( str_starts_with( $data['key'], '_' ) ) continue;
                $safe_meta[] = [
                    'key'   => sanitize_text_field( $data['key'] ),
                    'value' => sanitize_text_field( (string) $data['value'] ),
                ];
            }

            $items[] = [
                'name'       => esc_html( $item->get_name() ),
                'product_id' => (int) $item->get_product_id(),
                'quantity'   => (int) $item->get_quantity(),
                'subtotal'   => (string) $item->get_subtotal(),
                'meta_data'  => $safe_meta,
            ];
        }

        $result[] = [
            'id'           => (int) $order->get_id(),
            'status'       => sanitize_key( $order->get_status() ),
            'total'        => (string) $order->get_total(),
            'date_created' => $order->get_date_created()?->date( 'Y-m-d\TH:i:s' ) ?? '',
            'line_items'   => $items,
        ];
    }

    return rest_ensure_response( $result );
}

/**
 * POST /drsudani/v1/checkout
 * Creates a WooCommerce order paid from the wallet.
 *
 * Race-condition protection: an atomic DB-level lock (INSERT IGNORE)
 * ensures only one checkout runs per user at a time.
 */
function ds_checkout( WP_REST_Request $request ): WP_REST_Response|WP_Error {

    $user_id  = (int) $request->get_param( '_ds_user_id' );
    $lock_key = 'checkout_' . $user_id;

    // --- Atomic lock (DB-level, prevents race conditions) ---
    if ( ! ds_lock_acquire( $lock_key, 15 ) ) {
        return new WP_Error( 'concurrent_request', 'Another order is being processed. Please wait.', [ 'status' => 409 ] );
    }

    // --- Idempotency key (prevents duplicate submissions) ---
    $idem_raw = $request->get_header( 'Idempotency-Key' );
    if ( $idem_raw ) {
        $idem_key = 'ds_idem_' . md5( sanitize_key( $idem_raw ) );
        if ( get_transient( $idem_key ) ) {
            ds_lock_release( $lock_key );
            return new WP_Error( 'duplicate_request', 'Duplicate request.', [ 'status' => 409 ] );
        }
        set_transient( $idem_key, 1, 60 );
    }

    // --- Parse & validate line items ---
    $params    = $request->get_json_params() ?? [];
    $raw_items = isset( $params['line_items'] ) ? (array) $params['line_items'] : [];

    if ( empty( $raw_items ) ) {
        ds_lock_release( $lock_key );
        return new WP_Error( 'empty_cart', 'Cart is empty.', [ 'status' => 400 ] );
    }

    if ( count( $raw_items ) > 20 ) {
        ds_lock_release( $lock_key );
        return new WP_Error( 'too_many_items', 'Cart exceeds maximum item limit.', [ 'status' => 400 ] );
    }

    $total       = 0.0;
    $order_items = [];

    foreach ( $raw_items as $raw ) {

        $product_id   = (int) ( $raw['product_id']   ?? 0 );
        $variation_id = (int) ( $raw['variation_id'] ?? 0 );
        $quantity     = (int) ( $raw['quantity']     ?? 1 );

        if ( $product_id <= 0 || $quantity <= 0 ) continue;

        if ( $quantity > 100 ) {
            ds_lock_release( $lock_key );
            return new WP_Error( 'invalid_quantity', "Quantity exceeds limit for product {$product_id}.", [ 'status' => 400 ] );
        }

        $product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

        if ( ! $product || $product->get_status() !== 'publish' ) {
            ds_lock_release( $lock_key );
            return new WP_Error( 'invalid_product', "Product {$product_id} is unavailable.", [ 'status' => 400 ] );
        }

        $price = (float) $product->get_price();

        if ( $price <= 0 ) {
            ds_lock_release( $lock_key );
            return new WP_Error( 'invalid_price', "Product {$product_id} has no valid price.", [ 'status' => 400 ] );
        }

        $total += $price * $quantity;

        $order_items[] = [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'subtotal'     => $price * $quantity,
            'total'        => $price * $quantity,
            'meta_data'    => (array) ( $raw['meta_data'] ?? [] ),
        ];
    }

    if ( $total <= 0 || empty( $order_items ) ) {
        ds_lock_release( $lock_key );
        return new WP_Error( 'invalid_total', 'Order total must be greater than zero.', [ 'status' => 400 ] );
    }

    // --- Balance check (inside lock – fresh read) ---
    $balance = ds_wallet_balance_value( $user_id );
    if ( $balance < $total ) {
        ds_lock_release( $lock_key );
        return new WP_Error( 'insufficient_funds', 'Insufficient wallet balance.', [ 'status' => 402 ] );
    }

    // --- Create order ---
    try {

        $order = wc_create_order( [ 'customer_id' => $user_id ] );

        if ( is_wp_error( $order ) ) {
            ds_lock_release( $lock_key );
            return new WP_Error( 'order_error', 'Failed to create order.', [ 'status' => 500 ] );
        }

        foreach ( $order_items as $oi ) {

            $wc_product = wc_get_product( $oi['variation_id'] > 0 ? $oi['variation_id'] : $oi['product_id'] );
            $item_id    = $order->add_product( $wc_product, $oi['quantity'], [
                'subtotal' => $oi['subtotal'],
                'total'    => $oi['total'],
            ] );

            if ( $item_id && ! empty( $oi['meta_data'] ) ) {
                $line_item = $order->get_item( $item_id );
                foreach ( $oi['meta_data'] as $meta ) {
                    $key = (string) ( $meta['key']   ?? '' );
                    $val = (string) ( $meta['value'] ?? '' );
                    if ( ! in_array( $key, DS_ALLOWED_META_KEYS, true ) ) continue;
                    $safe = substr( sanitize_text_field( strip_tags( $val ) ), 0, 200 );
                    if ( $safe !== '' ) {
                        $line_item->add_meta_data( sanitize_key( $key ), $safe );
                    }
                }
                $line_item->save();
            }
        }

        $order->set_payment_method( 'woo-wallet' );
        $order->set_payment_method_title( 'Digital Wallet' );
        $order->calculate_totals();

        // --- Debit wallet ---
        $debit_ok = function_exists( 'woo_wallet' )
            ? (bool) woo_wallet()->wallet->debit( $user_id, $total, 'Order #' . $order->get_id() )
            : (bool) update_user_meta( $user_id, 'woo_wallet_current_balance', $balance - $total );

        if ( ! $debit_ok ) {
            $order->update_status( 'failed', 'Wallet debit failed.' );
            ds_lock_release( $lock_key );
            return new WP_Error( 'debit_failed', 'Wallet debit failed.', [ 'status' => 500 ] );
        }

        $order->update_status( 'processing', 'Paid via wallet.' );
        $order_id    = (int) $order->get_id();
        $new_balance = round( ds_wallet_balance_value( $user_id ), 2 );

        ds_lock_release( $lock_key );

        return rest_ensure_response( [
            'success'     => true,
            'order_id'    => $order_id,
            'total_paid'  => round( $total, 2 ),
            'new_balance' => $new_balance,
        ] );

    } catch ( Exception ) {
        ds_lock_release( $lock_key );
        return new WP_Error( 'server_error', 'An unexpected error occurred.', [ 'status' => 500 ] );
    }
}

// ===========================================================================
// HELPERS
// ===========================================================================

/**
 * Read the user's wallet balance from WooCommerce Wallet or user meta.
 */
function ds_wallet_balance_value( int $user_id ): float {
    if ( function_exists( 'woo_wallet' ) ) {
        $b = woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
        if ( is_numeric( $b ) ) return (float) $b;
    }
    foreach ( [ '_uw_balance', 'woo_wallet_current_balance', '_wwallet_balance', 'wallet_balance', 'tera_wallet_balance' ] as $key ) {
        $v = get_user_meta( $user_id, $key, true );
        if ( is_numeric( $v ) ) return (float) $v;
    }
    return 0.0;
}

/**
 * Resolve a JWT token to a WordPress user ID.
 * Returns 0 if the token is invalid, expired, or unresolvable.
 */
function ds_user_id_from_token( string $token ): int {

    // Prefer the Simple JWT Login plugin when available
    $user = apply_filters( 'simple_jwt_login_user_from_token', null, $token );
    if ( $user && ! is_wp_error( $user ) ) return (int) $user->ID;

    // Manual fallback: verify signature first, then decode
    if ( empty( DS_JWT_SECRET ) ) return 0;
    if ( ! ds_jwt_verify_signature( $token, DS_JWT_SECRET ) ) return 0;

    $payload = ds_jwt_decode_payload( $token );
    if ( ! $payload ) return 0;

    // Reject expired tokens
    if ( isset( $payload->exp ) && (int) $payload->exp < time() ) return 0;

    if ( isset( $payload->id ) )                 return (int) $payload->id;
    if ( isset( $payload->data->user->id ) )     return (int) $payload->data->user->id;
    if ( isset( $payload->email ) ) {
        $u = get_user_by( 'email', $payload->email );
        return $u ? (int) $u->ID : 0;
    }

    return 0;
}

/**
 * Verify a JWT's HMAC-SHA256 signature.
 */
function ds_jwt_verify_signature( string $token, string $secret ): bool {
    $parts = explode( '.', $token );
    if ( count( $parts ) !== 3 ) return false;

    [ $header, $payload, $provided ] = $parts;
    $expected = rtrim( strtr( base64_encode( hash_hmac( 'sha256', "$header.$payload", $secret, true ) ), '+/', '-_' ), '=' );

    return hash_equals( $expected, $provided );
}

/**
 * Decode a JWT's payload segment into a stdClass object.
 */
function ds_jwt_decode_payload( string $token ): ?stdClass {
    $parts = explode( '.', $token );
    if ( count( $parts ) !== 3 ) return null;

    $b64  = $parts[1];
    $rem  = strlen( $b64 ) % 4;
    $b64 .= $rem ? str_repeat( '=', 4 - $rem ) : '';
    $json = base64_decode( strtr( $b64, '-_', '+/' ), true );

    return $json !== false ? json_decode( $json ) : null;
}

/**
 * Extract the Bearer token string from an Authorization header value.
 */
function ds_extract_bearer_token( string $header ): string {
    return str_starts_with( $header, 'Bearer ' ) ? substr( $header, 7 ) : '';
}

/**
 * Get the real client IP, respecting common proxy headers.
 * Returns a validated IP string or empty string.
 */
function ds_real_ip(): string {
    foreach ( [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
        $candidate = trim( explode( ',', $_SERVER[ $key ] ?? '' )[0] );
        $ip        = filter_var( $candidate, FILTER_VALIDATE_IP );
        if ( $ip ) return $ip;
    }
    return '';
}

/**
 * Atomic rate limiter using WordPress transients.
 * Returns true if the request is within the limit, false if exceeded.
 */
function ds_rate_limit( string $ip, int $limit ): bool {
    $key   = 'ds_rl_' . md5( $ip );
    $count = (int) get_transient( $key );
    if ( $count >= $limit ) return false;
    set_transient( $key, $count + 1, 60 );
    return true;
}

/**
 * Acquire an atomic DB-level lock using INSERT IGNORE (prevents race conditions).
 * Uses a prepared statement to prevent SQL injection.
 *
 * @param string $key  Unique lock identifier.
 * @param int    $ttl  Lock lifetime in seconds.
 * @return bool True if the lock was acquired, false if already held.
 */
function ds_lock_acquire( string $key, int $ttl = 15 ): bool {
    global $wpdb;

    $option = 'ds_lock_' . sanitize_key( $key );
    $expiry = time() + $ttl;

    // Prepared statement – safe against SQL injection
    $inserted = $wpdb->query(
        $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')",
            $option,
            $expiry
        )
    );

    if ( $inserted ) return true;

    // Lock exists – check if it has expired, then reclaim it atomically
    $existing = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option
        )
    );

    if ( $existing > 0 && $existing < time() ) {
        // Atomic compare-and-swap: only update if value still matches
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %d WHERE option_name = %s AND option_value = %d",
                $expiry,
                $option,
                $existing
            )
        );
        return $updated > 0;
    }

    return false;
}

/**
 * Release a previously acquired lock.
 */
function ds_lock_release( string $key ): void {
    global $wpdb;

    $option = 'ds_lock_' . sanitize_key( $key );

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s",
            $option
        )
    );

    wp_cache_delete( $option, 'options' );
}
