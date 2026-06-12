<?php
/**
 * إدارة المستخدمين — الوكيل
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAgent();

if (!hasPermission('add_transaction')) {
    // إذا لم يكن لديه صلاحية، لا داعي لفتح هذه الصفحة
    header("Location: index.php");
    exit;
}

$db = db();
$pageTitle  = 'المستخدمون (لإضافة معاملة)';
$activePage = 'users';

// ترقيم الصفحات
$perPage = 20;
$page    = max(1, intval_safe($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');
$where  = '';
$params = [];

if ($search !== '') {
    $where    = 'WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?';
    $params   = ["%$search%", "%$search%", "%$search%"];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM wp_users_cache $where");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $db->prepare("SELECT * FROM wp_users_cache $where ORDER BY synced_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-agent.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">قاعدة المستخدمين (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <form method="get" class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="search" placeholder="بحث بالبريد أو الاسم..." value="<?= clean($search) ?>" autocomplete="off">
            </form>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>#WP</th>
                    <th>الاسم</th>
                    <th>البريد</th>
                    <th>اسم المستخدم</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $u['wp_user_id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;background:var(--color-primary);color:white;font-weight:700;font-size:0.85rem;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <?= mb_substr($u['full_name'] ?: $u['username'], 0, 1) ?>
                            </div>
                            <span class="fw-semibold"><?= clean($u['full_name'] ?: '—') ?></span>
                        </div>
                    </td>
                    <td class="text-sm"><?= clean($u['email']) ?></td>
                    <td class="text-sm text-muted"><?= clean($u['username']) ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm"
                                onclick="openTransactionModal(<?= (int)$u['wp_user_id'] ?>, <?= htmlspecialchars(json_encode($u['full_name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['email'] ?? ''), ENT_QUOTES) ?>)">
                            إضافة معاملة
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>لا يوجد مستخدمون</h3>
                            <p>يرجى الطلب من المدير مزامنة المستخدمين من ووكوميرس أولاً.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ترقيم الصفحات -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">‹</a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- تضمين نافذة إضافة المعاملة -->
<?php require_once dirname(__DIR__) . '/components/modal-transaction.php'; ?>
<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
