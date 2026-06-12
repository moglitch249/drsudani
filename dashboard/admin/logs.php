<?php
/**
 * سجل النظام — المدير
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'سجل النظام';
$activePage = 'logs';

$search  = trim($_GET['search'] ?? '');
$perPage = 30;
$page    = max(1, intval_safe($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = $search !== '' ? 'WHERE l.action LIKE ? OR l.details LIKE ? OR a.full_name LIKE ? OR l.ip_address LIKE ?' : '';
$params = $search !== '' ? ["%$search%", "%$search%", "%$search%", "%$search%"] : [];

$countStmt = $db->prepare("SELECT COUNT(*) FROM system_logs l LEFT JOIN agents a ON a.id = l.agent_id $where");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $db->prepare(
    "SELECT l.*, a.full_name as agent_name, a.username
     FROM system_logs l
     LEFT JOIN agents a ON a.id = l.agent_id
     $where
     ORDER BY l.created_at DESC
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';

function actionBadge(string $action): string {
    $danger = ['delete','agent_deleted','login_failed'];
    $success= ['login','agent_added','add_transaction','sync_customers'];
    $warning= ['logout','agent_edited'];

    if (in_array($action, $danger))  return '<span class="badge badge-danger">'  . clean($action) . '</span>';
    if (in_array($action, $success)) return '<span class="badge badge-success">' . clean($action) . '</span>';
    if (in_array($action, $warning)) return '<span class="badge badge-warning">' . clean($action) . '</span>';
    return '<span class="badge badge-gray">' . clean($action) . '</span>';
}
?>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">سجل النظام (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <form method="get" class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="search" placeholder="بحث..." value="<?= clean($search) ?>">
            </form>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المنفذ</th>
                    <th>الإجراء</th>
                    <th>التفاصيل</th>
                    <th>عنوان IP</th>
                    <th>التاريخ والوقت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-muted text-xs"><?= $log['id'] ?></td>
                    <td>
                        <?php if ($log['agent_name']): ?>
                            <span class="fw-semibold text-sm"><?= clean($log['agent_name']) ?></span>
                            <div class="text-xs text-muted"><?= clean($log['username'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-muted text-sm">النظام / ضيف</span>
                        <?php endif; ?>
                    </td>
                    <td><?= actionBadge($log['action']) ?></td>
                    <td class="text-sm text-muted" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= clean($log['details'] ?? '—') ?>
                    </td>
                    <td class="text-sm" style="direction:ltr;text-align:left;"><?= clean($log['ip_address']) ?></td>
                    <td class="text-sm text-muted"><?= formatDate($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:40px;">لا توجد سجلات</td></tr>
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
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
