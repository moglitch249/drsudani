<?php
/**
 * معاملات الوكيل الخاصة
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAgent();

$db = db();
$pageTitle  = 'معاملاتي';
$activePage = 'transactions';
$agentId    = (int)$_SESSION['agent_id'];

$perPage = 25;
$page    = max(1, intval_safe($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');
$where  = 'WHERE t.agent_id = ?';
$params = [$agentId];

if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id $where");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $db->prepare(
    "SELECT t.*, u.full_name as user_name, u.email as user_email
     FROM transactions t
     LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id
     $where
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-agent.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">سجل معاملاتي (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <form method="get" class="search-box">
                <svg width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" placeholder="بحث بالمستخدم..." value="<?= clean($search) ?>">
            </form>
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
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th>الإيصال</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td class="text-sm text-muted">#<?= $tx['id'] ?></td>
                    <td class="fw-semibold"><?= clean($tx['user_name'] ?? 'مستخدم #' . $tx['wp_user_id']) ?></td>
                    <td class="fw-bold"><?= formatMoney((float)$tx['amount']) ?></td>
                    <td><?= match($tx['type']) {
                        'deposit' => '<span class="badge badge-success">إيداع</span>',
                        'withdraw' => '<span class="badge badge-danger">سحب</span>',
                        'adjustment' => '<span class="badge badge-warning">تعديل</span>',
                        default => $tx['type']
                    } ?></td>
                    <td class="text-sm"><?= match($tx['payment_method']) { 'bank_transfer' => 'تحويل بنكي', 'wallet' => 'محفظة', 'cash' => 'نقدي', default => $tx['payment_method'] } ?></td>
                    <td class="text-sm text-muted"><?= formatDate($tx['created_at']) ?></td>
                    <td><?= match($tx['status']) {
                        'confirmed'=>'<span class="badge badge-success">مؤكدة</span>',
                        'pending'=>'<span class="badge badge-warning">معلقة</span>',
                        'rejected'=>'<span class="badge badge-danger">مرفوضة</span>',
                        default=>$tx['status']
                    } ?></td>
                    <td>
                        <?php if ($tx['receipt_image']): ?>
                            <a href="../api/receipt.php?id=<?= $tx['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">عرض</a>
                        <?php else: ?>
                            <span class="text-muted text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
            <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
