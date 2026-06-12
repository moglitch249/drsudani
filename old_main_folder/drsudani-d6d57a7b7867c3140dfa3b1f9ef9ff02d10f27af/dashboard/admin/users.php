<?php
/**
 * إدارة المستخدمين — المدير
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'المستخدمون';
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

$stmt = $db->prepare("SELECT * FROM wp_users_cache $where ORDER BY synced_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$users = $stmt->fetchAll();

// مزامنة سريعة مع ووكوميرس
$syncMsg = '';
if (isset($_GET['sync']) && $_GET['sync'] === '1') {
    require_once dirname(__DIR__) . '/config/woocommerce.php';
    $wc       = new WooCommerceAPI();
    $customers = $wc->getCustomers(['role' => 'all']);
    if ($customers !== false) {
        if (empty($customers)) {
            $syncMsg = 'تم الاتصال بالمتجر، ولكن لم يتم العثور على مستخدمين مسجلين بدور "عميل" (Customer).';
        } else {
            $upsert = $db->prepare(
                'INSERT INTO wp_users_cache (wp_user_id, username, email, full_name)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE email=VALUES(email), full_name=VALUES(full_name), synced_at=NOW()'
            );
            foreach ($customers as $c) {
                $upsert->execute([
                    $c['id'],
                    $c['username']  ?? '',
                    $c['email']     ?? '',
                    trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
                ]);
            }
            logAction('sync_customers', 'مزامنة ' . count($customers) . ' مستخدم من ووكوميرس');
            $syncMsg = 'تمت مزامنة ' . count($customers) . ' مستخدم بنجاح.';
        }
    } else {
        $syncMsg = 'تحذير: فشل الاتصال بواجهة ووكوميرس (تأكد من صحة الرابط ومفاتيح API في الإعدادات).';
    }
}

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<?php if ($syncMsg): ?>
    <div class="alert alert-success mb-16"><?= clean($syncMsg) ?></div>
<?php endif; ?>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">المستخدمون (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls">
            <form method="get" class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="search" placeholder="بحث..." value="<?= clean($search) ?>"
                       autocomplete="off">
            </form>
            <a href="?sync=1" class="btn btn-primary btn-sm">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                </svg>
                مزامنة من ووكوميرس
            </a>
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
                    <th>رصيد المحفظة</th>
                    <th>تاريخ التسجيل</th>
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
                    <td class="fw-bold"><?= formatMoney((float)$u['wallet_balance']) ?></td>
                    <td class="text-sm text-muted">
                        <?= $u['registered_at'] ? formatDate($u['registered_at']) : '—' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:8px;">
                            <a href="user-profile.php?id=<?= $u['wp_user_id'] ?>"
                               class="btn btn-ghost btn-sm">عرض الملف</a>
                            <button class="btn btn-primary btn-sm"
                                     onclick="openTransactionModal(<?= (int)$u['wp_user_id'] ?>, <?= htmlspecialchars(json_encode($u['full_name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['email'] ?? ''), ENT_QUOTES) ?>)">
                                إضافة معاملة
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>لا يوجد مستخدمون</h3>
                            <p>استخدم زر المزامنة لجلب المستخدمين من ووكوميرس</p>
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

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
