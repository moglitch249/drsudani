<?php
/**
 * إدارة المعاملات — المدير
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'المعاملات';
$activePage = 'transactions';

// فلتر
$filterType   = $_GET['type']   ?? '';
$filterMethod = $_GET['method'] ?? '';
$filterAgent  = intval_safe($_GET['agent'] ?? 0);
$search       = trim($_GET['search'] ?? '');
$perPage      = 25;
$page         = max(1, intval_safe($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$conditions = [];
$params     = [];

if ($filterType !== '')   { $conditions[] = 't.type = ?';            $params[] = $filterType; }
if ($filterMethod !== '') { $conditions[] = 't.payment_method = ?';  $params[] = $filterMethod; }
if ($filterAgent > 0)     { $conditions[] = 't.agent_id = ?';        $params[] = $filterAgent; }
if ($search !== '')       {
    $conditions[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}

$where    = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM transactions t
     LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id
     JOIN agents a ON a.id = t.agent_id
     $where"
);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

// التصدير إلى Excel/CSV
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $stmtExport = $db->prepare(
        "SELECT t.*, a.full_name as agent_name, u.full_name as user_name, u.email as user_email
         FROM transactions t
         JOIN agents a ON a.id = t.agent_id
         LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id
         $where ORDER BY t.created_at DESC"
    );
    $stmtExport->execute($params);
    $exportData = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=transactions_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8 Arabic Excel compatibility
    fputcsv($output, ['رقم المعاملة', 'اسم المستخدم', 'البريد الإلكتروني', 'المبلغ', 'النوع', 'طريقة الدفع', 'الوكيل', 'ملاحظات', 'تاريخ المعاملة', 'الحالة']);

    foreach ($exportData as $row) {
        $type = match($row['type']) { 'deposit'=>'إيداع', 'withdraw'=>'سحب', 'adjustment'=>'تعديل', default=>$row['type'] };
        $method = match($row['payment_method']) { 'bank_transfer'=>'تحويل بنكي', 'wallet'=>'محفظة', 'cash'=>'نقدي', default=>$row['payment_method'] };
        $status = match($row['status']) { 'confirmed'=>'مؤكدة', 'pending'=>'معلقة', 'rejected'=>'مرفوضة', default=>$row['status'] };
        
        fputcsv($output, [
            "#" . $row['id'],
            $row['user_name'] ?? '—',
            $row['user_email'] ?? '—',
            $row['amount'],
            $type,
            $method,
            $row['agent_name'],
            $row['notes'] ?? '—',
            $row['created_at'],
            $status
        ]);
    }
    fclose($output);
    exit;
}

$stmt = $db->prepare(
    "SELECT t.*, a.full_name as agent_name,
            u.full_name as user_name, u.email as user_email
     FROM transactions t
     JOIN agents a ON a.id = t.agent_id
     LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id
     $where
     ORDER BY t.created_at DESC
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// قائمة الوكلاء للفلتر
$agents = $db->query("SELECT id, full_name FROM agents WHERE role='agent' ORDER BY full_name")->fetchAll();

function txTypeBadge(string $t): string {
    return match($t) {
        'deposit'    => '<span class="badge badge-success">إيداع</span>',
        'withdraw'   => '<span class="badge badge-danger">سحب</span>',
        'adjustment' => '<span class="badge badge-warning">تعديل</span>',
        default      => '<span class="badge badge-gray">' . clean($t) . '</span>',
    };
}
function txStatusBadge(string $s): string {
    return match($s) {
        'confirmed' => '<span class="badge badge-success">مؤكدة</span>',
        'pending'   => '<span class="badge badge-warning">معلقة</span>',
        'rejected'  => '<span class="badge badge-danger">مرفوضة</span>',
        default     => '<span class="badge badge-gray">' . clean($s) . '</span>',
    };
}

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="data-table-card">
    <div class="table-toolbar flex-between">
        <span class="table-title">المعاملات (<?= number_format($totalCount) ?>)</span>
        <button class="btn btn-ghost btn-sm" onclick="exportData()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            تصدير كـ CSV
        </button>
    </div>
    <div class="table-toolbar" style="background: var(--color-gray-50); border-top: 1px solid var(--color-gray-100);">
        <div class="table-controls" style="width: 100%; display: flex; gap: 12px; align-items: center;">
            <div class="search-box" style="flex: 1;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="right: 14px;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" placeholder="بحث باسم المستخدم أو البريد الإلكتروني..." value="<?= clean($search) ?>"
                       style="width: 100%; padding-right: 44px; height: 42px;"
                       onchange="applyFilter('search', this.value)">
            </div>
            <div style="display: flex; gap: 8px;">
                <select class="filter-select" style="height: 42px;" onchange="applyFilter('type', this.value)">
                    <option value="">كل الأنواع</option>
                    <option value="deposit"    <?= $filterType==='deposit'?'selected':'' ?>>إيداع</option>
                    <option value="withdraw"   <?= $filterType==='withdraw'?'selected':'' ?>>سحب</option>
                    <option value="adjustment" <?= $filterType==='adjustment'?'selected':'' ?>>تعديل</option>
                </select>
                <select class="filter-select" style="height: 42px;" onchange="applyFilter('method', this.value)">
                    <option value="">كل الطرق</option>
                    <option value="bank_transfer" <?= $filterMethod==='bank_transfer'?'selected':'' ?>>تحويل بنكي</option>
                    <option value="wallet"        <?= $filterMethod==='wallet'?'selected':'' ?>>محفظة</option>
                    <option value="cash"          <?= $filterMethod==='cash'?'selected':'' ?>>نقدي</option>
                </select>
                <select class="filter-select" style="height: 42px; min-width: 140px;" onchange="applyFilter('agent', this.value)">
                    <option value="0">كل الوكلاء</option>
                    <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $filterAgent==$ag['id']?'selected':'' ?>>
                            <?= clean($ag['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المستخدم</th>
                    <th>المبلغ</th>
                    <th>النوع</th>
                    <th>طريقة الدفع</th>
                    <th>الوكيل</th>
                    <th>ملاحظات</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th>الإيصال</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td class="text-muted text-sm">#<?= $tx['id'] ?></td>
                    <td>
                        <a href="user-profile.php?id=<?= $tx['wp_user_id'] ?>"
                           style="color:var(--color-primary-dark);font-weight:600;font-size:0.9rem;">
                            <?= clean($tx['user_name'] ?? '#' . $tx['wp_user_id']) ?>
                        </a>
                        <?php if ($tx['user_email']): ?>
                            <div class="text-xs text-muted"><?= clean($tx['user_email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="fw-bold text-nowrap"><?= formatMoney((float)$tx['amount']) ?></td>
                    <td><?= txTypeBadge($tx['type']) ?></td>
                    <td class="text-sm">
                        <?= match($tx['payment_method']) {
                            'bank_transfer' => 'تحويل بنكي',
                            'wallet' => 'محفظة', 'cash' => 'نقدي',
                            default => clean($tx['payment_method'])
                        } ?>
                    </td>
                    <td class="text-sm"><?= clean($tx['agent_name']) ?></td>
                    <td class="col-notes">
                        <?php if ($tx['notes']): ?>
                            <button class="view-note-btn" onclick="showNote(<?= htmlspecialchars(json_encode($tx['notes'])) ?>)" title="عرض الملاحظة">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <?= clean($tx['notes']) ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted text-nowrap"><?= formatDate($tx['created_at']) ?></td>
                    <td><?= txStatusBadge($tx['status']) ?></td>
                    <td>
                        <?php if ($tx['receipt_image']): ?>
                            <a href="../api/receipt.php?id=<?= $tx['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">عرض</a>
                        <?php else: ?>
                            <span class="text-muted text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:40px;">لا توجد نتائج مطابقة</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&type=<?= urlencode($filterType) ?>&method=<?= urlencode($filterMethod) ?>&agent=<?= $filterAgent ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal for viewing full notes -->
<div id="noteModal" class="note-modal" onclick="closeNote()">
    <div class="note-modal-content" onclick="event.stopPropagation()">
        <button class="note-modal-close" onclick="closeNote()">✕</button>
        <h4 style="margin-top:0;margin-bottom:15px;color:var(--color-primary);">الملاحظة الكاملة</h4>
        <div id="noteContent" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.95rem; color: var(--color-text);"></div>
    </div>
</div>

<script>
function showNote(text) {
    document.getElementById('noteContent').textContent = text;
    document.getElementById('noteModal').style.display = 'flex';
}
function closeNote() {
    document.getElementById('noteModal').style.display = 'none';
}
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
</script>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
