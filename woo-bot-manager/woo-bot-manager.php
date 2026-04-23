<?php
/**
 * Plugin Name: Bot Manager (Auto Topup)
 * Plugin URI: https://drsudani.com/
 * Description: النسخة الاحترافية v3.8 - إصلاح نهائي لمشكلة فقدان بيانات الأيدي في الطلبات مع الحفاظ على التوافق الكامل.
 * Version: 3.8
 * Author: DrSudani System
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// مفاتيح البيانات المشفرة
define('BOT_META_SIMPLE', '_ds_bot_qty_simple_secret');
define('BOT_META_VAR',    '_ds_bot_qty_var_secret');

// 1. إعدادات لوحة التحكم (ثابتة ومستقرة)
add_action( 'woocommerce_product_options_general_product_data', 'add_bot_fields_v38' );
function add_bot_fields_v38() {
    echo '<div class="options_group">';
    woocommerce_wp_checkbox( array( 'id' => '_is_bot_auto_topup', 'label' => 'شحن تلقائي عبر البوت' ) );
    woocommerce_wp_text_input( array( 'id' => BOT_META_SIMPLE, 'label' => 'جواهر ريزر (بسيط)', 'type' => 'number' ) );
    echo '</div>';
}
add_action( 'woocommerce_process_product_meta', 'save_bot_fields_v38' );
function save_bot_fields_v38( $post_id ) {
    update_post_meta( $post_id, '_is_bot_auto_topup', isset( $_POST['_is_bot_auto_topup'] ) ? 'yes' : 'no' );
    if ( isset( $_POST[BOT_META_SIMPLE] ) ) update_post_meta( $post_id, BOT_META_SIMPLE, sanitize_text_field( $_POST[BOT_META_SIMPLE] ) );
}
add_action( 'woocommerce_variation_options_pricing', 'add_bot_var_fields_v38', 10, 3 );
function add_bot_var_fields_v38( $loop, $variation_data, $variation ) {
    $val = get_post_meta( $variation->ID, BOT_META_VAR, true );
    ?>
    <div class="form-row form-row-full" style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">
        <label>جواهر ريزر لهذا الصنف:</label>
        <input type="text" name="ds_bot_v_data[<?php echo $variation->ID; ?>]" value="<?php echo esc_attr($val); ?>" style="width:100%;" />
    </div>
    <?php
}
add_action( 'woocommerce_save_product_variation', 'save_bot_var_fields_v38', 10, 2 );
function save_bot_var_fields_v38( $variation_id, $loop ) {
    if ( isset( $_POST['ds_bot_v_data'][$variation_id] ) ) update_post_meta( $variation_id, BOT_META_VAR, sanitize_text_field( $_POST['ds_bot_v_data'][$variation_id] ) );
}

// 2. إظهار حقل الأيدي (المكان الاستراتيجي داخل الفورم)
add_action( 'woocommerce_before_add_to_cart_button', 'display_bot_player_field_v38' );

function display_bot_player_field_v38() {
    global $product;
    if ( ! $product ) return;
    $main_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

    if ( get_post_meta( $main_id, '_is_bot_auto_topup', true ) === 'yes' ) {
        ?>
        <div class="ds-player-id-field-container" style="margin: 15px 0 20px 0; clear:both; width:100%;">
            <label for="bot_player_id" style="font-weight:bold; display:block; margin-bottom:8px; color:#333; font-size:15px;">أيدي اللاعب (Player ID) <span style="color:red;">*</span></label>
            <input type="text" 
                   name="bot_player_id" 
                   id="bot_player_id" 
                   placeholder="12345678" 
                   required 
                   class="input-text"
                   style="width:100% !important; padding:12px 15px !important; border:1px solid #ddd !important; border-radius:5px !important; box-sizing: border-box;" />
        </div>
        <div style="clear:both;"></div>
        <?php
    }
}

// 3. التقاط البيانات وحفظها في الطلب (Bulletproof Sync)
add_filter( 'woocommerce_add_cart_item_data', 'save_id_to_cart_v38', 10, 2 );
function save_id_to_cart_v38( $cart_item_data, $product_id ) {
    if ( isset( $_POST['bot_player_id'] ) && !empty($_POST['bot_player_id']) ) {
        $cart_item_data['bot_player_id'] = sanitize_text_field( $_POST['bot_player_id'] );
    }
    return $cart_item_data;
}

add_filter( 'woocommerce_get_item_data', 'display_id_in_cart_v38', 10, 2 );
function display_id_in_cart_v38( $item_data, $cart_item ) {
    if ( isset( $cart_item['bot_player_id'] ) ) {
        $item_data[] = array( 'key' => 'أيدي اللاعب', 'value' => $cart_item['bot_player_id'] );
    }
    return $item_data;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'save_bot_to_order_v38', 10, 4 );
function save_bot_to_order_v38( $item, $cart_item_key, $values, $order ) {
    // جلب الأيدي من السلة وحفظه في ميتاداتا الطلب
    if ( isset( $values['bot_player_id'] ) ) {
        $item->add_meta_data( 'Player ID', $values['bot_player_id'] );
    }
    
    // جلب عدد الجواهر المخصص وحفظه
    $p_id = $item->get_product_id();
    $v_id = $item->get_variation_id();
    $diamonds = $v_id ? get_post_meta($v_id, BOT_META_VAR, true) : get_post_meta($p_id, BOT_META_SIMPLE, true);
    if ($diamonds) {
        $item->add_meta_data( 'Razer Diamonds', $diamonds );
    }
}

// 4. إرسال البيانات لسيرفر البوت عبر الويبهوك (Payload)
add_filter( 'woocommerce_rest_prepare_order', 'add_bot_webhook_v38', 10, 3 );
function add_bot_webhook_v38( $response, $object, $request ) {
    $data = $response->get_data();
    if ( isset( $data['line_items'] ) ) {
        foreach ( $data['line_items'] as $key => $item ) {
            $p_id = $item['product_id'];
            $v_id = $item['variation_id'];
            $diamonds = $v_id ? get_post_meta($v_id, BOT_META_VAR, true) : get_post_meta($p_id, BOT_META_SIMPLE, true);
            $data['line_items'][$key]['razer_diamonds_fixed'] = $diamonds;
            $data['line_items'][$key]['is_bot_product'] = (get_post_meta( $p_id, '_is_bot_auto_topup', true ) === 'yes');
        }    
    }
    $response->set_data( $data );
    return $response;
}
