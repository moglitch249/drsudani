<?php
/**
 * API: تعديل بيانات منتج (محلياً و/أو على WooCommerce)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/woocommerce.php';

startSecureSession();
requireAdmin();
verifyCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$db = db();

$productId  = intval_safe($_POST['product_id'] ?? 0);
$wcId       = intval_safe($_POST['wc_product_id'] ?? 0);
$price      = isset($_POST['price']) ? (float)$_POST['price'] : null;
$stockQty   = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? (int)$_POST['stock_quantity'] : null;
$stockStatus= in_array($_POST['stock_status'] ?? '', ['instock','outofstock','onbackorder']) 
              ? $_POST['stock_status'] 
              : null;
$notes      = trim($_POST['internal_notes'] ?? '');
$override   = isset($_POST['low_stock_override']) && $_POST['low_stock_override'] !== '' ? (int)$_POST['low_stock_override'] : null;
$pushToWc   = !empty($_POST['push_to_wc']); // بولين: هل نرفع للـ WooCommerce؟

if (!$productId || !$wcId) {
    jsonError('معرّف المنتج مفقود', 422);
}

try {
    // 1. تحديث قاعدة البيانات المحلية
    $updateFields = [];
    $params       = [];

    if ($price !== null)       { $updateFields[] = 'price = ?';              $params[] = $price; }
    if ($stockQty !== null)    { $updateFields[] = 'stock_quantity = ?';     $params[] = $stockQty; }
    if ($stockStatus !== null) { $updateFields[] = 'stock_status = ?';       $params[] = $stockStatus; }
    if ($notes !== '')         { $updateFields[] = 'internal_notes = ?';     $params[] = $notes; }
    if ($override !== null)    { $updateFields[] = 'low_stock_override = ?'; $params[] = $override; }
    elseif (isset($_POST['low_stock_override']) && $_POST['low_stock_override'] === '') {
        $updateFields[] = 'low_stock_override = NULL';
    }

    if (empty($updateFields)) {
        jsonError('لم يتم تقديم أي تعديلات', 422);
    }

    $params[] = $productId;
    $db->prepare("UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);

    // 2. دفع التعديل إلى WooCommerce (اختياري)
    $wcResult = null;
    if ($pushToWc) {
        $wc = new WooCommerceAPI();
        $wcData = [];
        if ($price !== null)       $wcData['regular_price'] = (string)$price;
        if ($stockQty !== null)    $wcData['stock_quantity'] = $stockQty;
        if ($stockStatus !== null) $wcData['stock_status']   = $stockStatus;
        if ($stockQty !== null)    $wcData['manage_stock']   = true;

        $wcResult = $wc->updateProduct($wcId, $wcData);
        if (is_array($wcResult) && isset($wcResult['__error'])) {
            // WooCommerce API returned an error — return it to the user clearly
            $wcErrMsg = $wcResult['__error'];
            $wcErrCode = $wcResult['__code'] ?? 0;
            logAction('update_product_wc_fail', "فشل تحديث المنتج #$wcId على WooCommerce [$wcErrCode]: $wcErrMsg");
            jsonSuccess(['wc_synced' => false, 'wc_error' => $wcErrMsg], "تم الحفظ محلياً. WooCommerce: $wcErrMsg");
        } elseif ($wcResult === false) {
            logAction('update_product_wc_fail', "فشل تحديث المنتج #$wcId على WooCommerce");
            jsonSuccess(['wc_synced' => false], 'تم الحفظ محلياً. فشل الاتصال بـ WooCommerce.');
        }
    }

    logAction('update_product', "تعديل المنتج #$wcId محلياً" . ($pushToWc ? " ومزامنة WC" : ""));
    jsonSuccess(['wc_synced' => $pushToWc && $wcResult !== false], 'تم تحديث المنتج بنجاح.');

} catch (PDOException $e) {
    error_log("Update Product Error: " . $e->getMessage());
    jsonError('حدث خطأ في قاعدة البيانات', 500);
}
