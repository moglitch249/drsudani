<?php
/**
 * إدارة علاقات العملاء (CRM)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'العملاء (CRM)';
$activePage = 'customers';

$search  = trim($_GET['search'] ?? '');
$sortBy  = $_GET['sort'] ?? 'total_spent';
$perPage = 25;
$page    = max(1, intval_safe($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$having = '';
$params = [];

if ($search !== '') {
    $having = 'HAVING customer_name LIKE ? OR customer_email LIKE ?';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Order by logic
$orderClause = 'ORDER BY total_spent DESC';
if ($sortBy === 'orders')     $orderClause = 'ORDER BY orders_count DESC';
if ($sortBy === 'last_order') $orderClause = 'ORDER BY last_order_date DESC';

// Count total unique customers
$countSql = "SELECT COUNT(DISTINCT customer_email) FROM orders";
if ($search !== '') {
    $countSql .= " WHERE customer_name LIKE ? OR customer_email LIKE ?";
}
$countStmt = $db->prepare($countSql);
if ($search !== '') {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$sql = "
    SELECT 
        customer_name, 
        customer_email, 
        COUNT(id) as orders_count, 
        SUM(total) as total_spent,
        MAX(wc_created_at) as last_order_date
    FROM orders 
    GROUP BY customer_email, customer_name
    $having
    $orderClause
    LIMIT $perPage OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="flex-between mb-16">
    <div>
        <h3 class="mb-8 mt-0 fw-bold">العملاء وقيمة العميل مدى الحياة (LTV)</h3>
        <p class="text-muted text-sm mt-0">يتم تجميع أسماء العملاء تلقائياً من الطلبات المدخلة أو المتزامنة.</p>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn btn-ghost btn-sm" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
            </svg>
            طباعة القائمة
        </button>
    </div>
</div>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">قائمة العملاء (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" placeholder="بحث باسم أو إيميل..." value="<?= clean($search) ?>"
                       onchange="applyFilter('search', this.value)">
            </div>
            <select class="filter-select" onchange="applyFilter('sort', this.value)">
                <option value="total_spent" <?= $sortBy==='total_spent'?'selected':'' ?>>ترتيب: الأكثر إنفاقاً</option>
                <option value="orders" <?= $sortBy==='orders'?'selected':'' ?>>ترتيب: الأكثر طلباً</option>
                <option value="last_order" <?= $sortBy==='last_order'?'selected':'' ?>>ترتيب: أحدث طلب</option>
            </select>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="customersTable">
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>البريد الإلكتروني</th>
                    <th>إجمالي المنفق</th>
                    <th>عدد الطلبات</th>
                    <th>تاريخ آخر طلب</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $i => $cust): 
                    $isVip = $i < 3 && $page == 1 && $sortBy === 'total_spent'; // Top 3 spenders on page 1
                ?>
                <tr>
                    <td class="fw-bold" style="display:flex;align-items:center;gap:8px;">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--color-primary-light);color:var(--color-primary-dark);display:flex;align-items:center;justify-content:center;font-weight:700;">
                            <?= mb_substr(clean($cust['customer_name']), 0, 1) ?: '?' ?>
                        </div>
                        <div>
                            <?= clean($cust['customer_name'] ?: 'عميل غير معروف') ?>
                            <?php if($isVip): ?>
                                <span class="badge badge-warning text-xs" style="margin-right:4px;">VIP</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-sm text-muted" style="direction:ltr; text-align:right;">
                        <?= clean($cust['customer_email'] ?: '—') ?>
                    </td>
                    <td class="fw-bold text-success"><?= formatMoney((float)$cust['total_spent']) ?></td>
                    <td class="fw-semibold">
                        <span style="background:var(--color-gray-100);padding:4px 10px;border-radius:12px;">
                            <?= (int)$cust['orders_count'] ?>
                        </span>
                    </td>
                    <td class="text-sm text-muted">
                        <?= $cust['last_order_date'] ? formatDate($cust['last_order_date']) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted" style="padding:40px;">لا توجد بيانات عملاء مسجلة.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <?php if ($i===$page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&sort=<?= urlencode($sortBy) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function applyFilter(key, val) {
    const url = new URL(window.location.href);
    url.searchParams.set(key, val);
    url.searchParams.set('page', '1');
    window.location = url.toString();
}
</script>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
