<?php
/**
 * عرض الطلبات للوكيل
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAgent();

if (!hasPermission('view_orders')) {
    header('Location: index.php');
    exit;
}

$db = db();
$pageTitle  = 'الطلبات المتاحة';
$activePage = 'orders';

$perPage = 25;
$page    = max(1, intval_safe($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$conditions = [];
$params     = [];

if ($status !== '') { $conditions[] = 'status = ?'; $params[] = $status; }
if ($search !== '') {
    $conditions[] = '(customer_name LIKE ? OR wc_order_id = ?)';
    $params[] = "%$search%"; $params[] = (int)$search;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders $where");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $db->prepare("SELECT * FROM orders $where ORDER BY wc_created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-agent.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">الطلبات (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <form method="get" class="search-box">
                <svg width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" placeholder="رقم الطلب أو العميل..." value="<?= clean($search) ?>">
            </form>
            <select class="filter-select" onchange="window.location.href='?status='+this.value+'&search=<?= urlencode($search) ?>'">
                <option value="">كل الحالات</option>
                <option value="pending" <?= $status==='pending'?'selected':'' ?>>معلق</option>
                <option value="processing" <?= $status==='processing'?'selected':'' ?>>قيد المعالجة</option>
                <option value="completed" <?= $status==='completed'?'selected':'' ?>>مكتمل</option>
            </select>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>طريقة الدفع</th>
                    <th>تاريخ الطلب</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="fw-bold">#<?= $order['wc_order_id'] ?></td>
                    <td class="fw-semibold"><?= clean($order['customer_name'] ?? '—') ?></td>
                    <td class="fw-bold"><?= formatMoney((float)$order['total']) ?></td>
                    <td class="text-sm"><?= clean($order['payment_method'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= $order['wc_created_at'] ? formatDate($order['wc_created_at']) : '—' ?></td>
                    <td><?= match($order['status']) {
                        'completed'=>'<span class="badge badge-success">مكتمل</span>',
                        'processing'=>'<span class="badge badge-info">قيد المعالجة</span>',
                        'pending'=>'<span class="badge badge-warning">معلق</span>',
                        'cancelled'=>'<span class="badge badge-danger">ملغي</span>',
                        default=>'<span class="badge badge-gray">'.clean($order['status']).'</span>'
                    } ?></td>
                    <td>
                        <?php 
                        $itemsJson = !empty($order['items_data']) ? base64_encode($order['items_data']) : '';
                        ?>
                        <button class="btn btn-sm" style="background:var(--color-primary-bg); color:var(--color-primary-dark); border:1px solid var(--color-primary-light);" 
                                onclick="openOrderModal(
                                    <?= (int)$order['wc_order_id'] ?>, 
                                    <?= htmlspecialchars(json_encode($order['customer_name'] ?? ''), ENT_QUOTES) ?>, 
                                    <?= htmlspecialchars(json_encode($order['customer_email'] ?? ''), ENT_QUOTES) ?>, 
                                    <?= htmlspecialchars(json_encode(formatMoney((float)$order['total']), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, 
                                    <?= htmlspecialchars(json_encode($order['payment_method'] ?? '—', JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, 
                                    <?= htmlspecialchars(json_encode($order['status'] ?? ''), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($itemsJson), ENT_QUOTES) ?>
                                )">
                            إدارة
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">لا توجد طلبات مطابقة</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
            <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php 
require_once dirname(__DIR__) . '/components/modal-order.php';
require_once dirname(__DIR__) . '/components/footer.php'; 
?>
