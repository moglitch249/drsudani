<?php
/**
 * مزامنة المنتجات من ووكوميرس (إدارة المخزون)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/woocommerce.php';

startSecureSession();
requireAdmin();
verifyCsrf();

$db = db();
$wc = new WooCommerceAPI();

try {
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $totalSynced = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;

    // الحصول على حد المخزون المنخفض
    $stmtThreshold = $db->query("SELECT value FROM settings WHERE key_name = 'low_stock_threshold'");
    $thresholdStr = $stmtThreshold->fetchColumn();
    $threshold = $thresholdStr !== false ? (int)$thresholdStr : 5;

    $products = $wc->getProducts(['page' => $page, 'per_page' => 100]);
    
    if ($products === false) {
        throw new Exception("فشل الاتصال بـ WooCommerce API");
    }
    
    $hasMore = !empty($products);

    if ($hasMore) {
        $db->beginTransaction();

        $stmtUpsert = $db->prepare('
            INSERT INTO products (wc_product_id, name, price, stock_quantity, stock_status, image_url, category, product_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                price = VALUES(price),
                stock_quantity = VALUES(stock_quantity),
                stock_status = VALUES(stock_status),
                image_url = VALUES(image_url),
                category = VALUES(category),
                product_url = VALUES(product_url)
        ');

        // Notification Check Stmt
        $stmtCheckNotif = $db->prepare('
            SELECT id FROM notifications 
            WHERE reference_id = ? AND type = "system" AND title LIKE "%مخزون%" AND is_read = 0
            LIMIT 1
        ');

        $stmtInsertNotif = $db->prepare('
            INSERT INTO notifications (type, title, body, target_role, reference_id)
            VALUES ("system", ?, ?, "all", ?)
        ');

        foreach ($products as $prod) {
            $wc_id = $prod['id'];
            $name = $prod['name'];
            $price = (float)($prod['price'] ?: 0);
            $stock_qty = $prod['stock_quantity'] !== null ? (int)$prod['stock_quantity'] : null;
            $stock_status = $prod['stock_status'];
            $image_url = !empty($prod['images'][0]['src']) ? $prod['images'][0]['src'] : null;
            $category  = !empty($prod['categories'][0]['name']) ? $prod['categories'][0]['name'] : null;
            $product_url = $prod['permalink'] ?? null;

            $stmtUpsert->execute([$wc_id, $name, $price, $stock_qty, $stock_status, $image_url, $category, $product_url]);
            $totalSynced++;

            $isLowStock = ($stock_qty !== null && $stock_qty <= $threshold && $stock_qty > 0);
            $isOutOfStock = ($stock_status === 'outofstock' || ($stock_qty !== null && $stock_qty <= 0 && $stock_status !== 'onbackorder'));

            if ($isLowStock || $isOutOfStock) {
                if ($isOutOfStock) $outOfStockCount++;
                else $lowStockCount++;

                $stmtCheckNotif->execute([$wc_id]);
                if (!$stmtCheckNotif->fetch()) {
                    $notifTitle = $isOutOfStock ? "نفاد المخزون: $name" : "مخزون منخفض: $name";
                    $notifBody = $isOutOfStock 
                        ? "لقد نفدت الكمية المتاحة من هذا المنتج." 
                        : "تبقى $stock_qty فقط من هذا المنتج في المخزون.";
                    
                    $stmtInsertNotif->execute([$notifTitle, $notifBody, $wc_id]);
                }
            }
        }

        $db->commit();
    }

    // Update last sync time only on the first page or when done
    if (!$hasMore || $page == 1) {
        $db->prepare("INSERT INTO settings (key_name, value) VALUES ('wc_products_last_sync', ?) 
                      ON DUPLICATE KEY UPDATE value = VALUES(value)")
           ->execute([date('Y-m-d H:i:s')]);
    }

    if (!$hasMore && $page > 1) {
        logAction('sync_products', "تم إنهاء المزامنة الدورية المنتجات.");
    }

    jsonSuccess([
        'synced' => $totalSynced,
        'low_stock' => $lowStockCount,
        'out_of_stock' => $outOfStockCount,
        'has_more' => $hasMore,
        'next_page' => $page + 1
    ], "تم جلب وتحديث $totalSynced منتج بنجاح (الصفحة $page).");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("WC Products Sync Error: " . $e->getMessage());
    jsonError('تعذر مزامنة المنتجات: ' . $e->getMessage(), 500);
}
