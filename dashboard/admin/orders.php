<?php
/**
 * إدارة الطلبات (ووكوميرس) — المدير
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'الطلبات';
$activePage = 'orders';

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$perPage      = 25;
$page         = max(1, intval_safe($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$conditions = [];
$params     = [];

if ($filterStatus !== '') {
    $conditions[] = 'status = ?';
    $params[]     = $filterStatus;
}
if ($search !== '') {
    $conditions[] = '(customer_name LIKE ? OR customer_email LIKE ? OR wc_order_id = ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = (int)$search;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders $where");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

// التصدير إلى Excel/CSV
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $stmtExport = $db->prepare("SELECT * FROM orders $where ORDER BY wc_created_at DESC");
    $stmtExport->execute($params);
    $exportData = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=orders_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8 Arabic Excel compatibility
    fputcsv($output, ['رقم الطلب', 'اسم العميل', 'البريد الإلكتروني', 'المبلغ الإجمالي', 'عدد المنتجات', 'طريقة الدفع', 'تاريخ الطلب', 'الحالة']);

    foreach ($exportData as $row) {
        fputcsv($output, [
            "#" . $row['wc_order_id'],
            $row['customer_name'] ?? '—',
            $row['customer_email'] ?? '—',
            $row['total'],
            $row['items_count'],
            $row['payment_method'] ?? '—',
            $row['wc_created_at'],
            $row['status']
        ]);
    }
    fclose($output);
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders $where ORDER BY wc_created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// حالة آخر مزامنة
$lastSync = $db->query("SELECT value FROM settings WHERE key_name='wc_last_sync'")->fetchColumn();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';

function orderStatusBadge(string $s): string {
    return match($s) {
        'completed'  => '<span class="badge badge-success">مكتمل</span>',
        'processing' => '<span class="badge badge-info">قيد المعالجة</span>',
        'pending'    => '<span class="badge badge-warning">معلق</span>',
        'cancelled'  => '<span class="badge badge-danger">ملغي</span>',
        'refunded'   => '<span class="badge badge-gray">مسترد</span>',
        'on-hold'    => '<span class="badge badge-warning">قيد الانتظار</span>',
        default      => '<span class="badge badge-gray">' . clean($s) . '</span>',
    };
}
?>

<div class="flex-between mb-16">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn btn-ghost btn-sm" onclick="exportData()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            تصدير كـ CSV
        </button>
        <?php if ($lastSync): ?>
            <span class="text-sm text-muted">آخر مزامنة: <?= formatDate($lastSync) ?></span>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" id="syncBtn" onclick="syncOrders()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
            </svg>
            مزامنة الطلبات
        </button>
    </div>
</div>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">الطلبات (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" placeholder="بحث..." value="<?= clean($search) ?>"
                       onchange="applyFilter('search', this.value)">
            </div>
            <select class="filter-select" onchange="applyFilter('status', this.value)">
                <option value="">كل الحالات</option>
                <option value="pending"    <?= $filterStatus==='pending'?'selected':'' ?>>معلق</option>
                <option value="processing" <?= $filterStatus==='processing'?'selected':'' ?>>قيد المعالجة</option>
                <option value="completed"  <?= $filterStatus==='completed'?'selected':'' ?>>مكتمل</option>
                <option value="cancelled"  <?= $filterStatus==='cancelled'?'selected':'' ?>>ملغي</option>
                <option value="refunded"   <?= $filterStatus==='refunded'?'selected':'' ?>>مسترد</option>
            </select>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>العميل</th>
                    <th>البريد</th>
                    <th>المبلغ</th>
                    <th>عدد المنتجات</th>
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
                    <td class="text-sm text-muted"><?= clean($order['customer_email'] ?? '—') ?></td>
                    <td class="fw-bold"><?= formatMoney((float)$order['total']) ?></td>
                    <td class="text-sm"><?= (int)$order['items_count'] ?></td>
                    <td class="text-sm"><?= clean($order['payment_method'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= $order['wc_created_at'] ? formatDate($order['wc_created_at']) : '—' ?></td>
                    <td><?= orderStatusBadge($order['status']) ?></td>
                    <td>
                        <?php 
                        $itemsJson = $order['items_data'] ? base64_encode($order['items_data']) : '';
                        ?>
                        <button class="btn btn-sm btn-manage" 
                                onclick="openOrderModal(
                                    <?= $order['wc_order_id'] ?>, 
                                    '<?= htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES | ENT_HTML5) ?>', 
                                    '<?= htmlspecialchars($order['customer_email'] ?? '', ENT_QUOTES | ENT_HTML5) ?>', 
                                    '<?= formatMoney((float)$order['total']) ?>', 
                                    '<?= htmlspecialchars($order['payment_method'] ?? '—', ENT_QUOTES | ENT_HTML5) ?>', 
                                    '<?= $order['status'] ?>',
                                    '<?= $itemsJson ?>'
                                )">
                            إدارة
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:40px;">
                    لا توجد طلبات. اضغط "مزامنة الطلبات" لجلبها من ووكوميرس.
                </td></tr>
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
                <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
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
    url.searchParams.delete('export');
    window.location = url.toString();
}

function exportData() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.location = url.toString();
}

async function syncOrders() {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="border-color:rgba(255,255,255,0.3);border-top-color:white;"></span> جارٍ المزامنة...';

    try {
        const res  = await fetch('../api/wc-sync.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
        });
        const data = await res.json();

        if (data.success) {
            window.showToast?.('تمت المزامنة', data.message, 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            window.showToast?.('خطأ', data.message, 'error');
        }
    } catch {
        window.showToast?.('خطأ في الاتصال', 'تعذر الاتصال بالخادم', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg> مزامنة الطلبات';
    }
}
</script>

<?php 
require_once dirname(__DIR__) . '/components/modal-order.php';
require_once dirname(__DIR__) . '/components/footer.php'; 
?>
