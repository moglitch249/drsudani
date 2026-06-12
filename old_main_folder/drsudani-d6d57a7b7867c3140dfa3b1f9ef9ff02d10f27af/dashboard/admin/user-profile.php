<?php
/**
 * ملف المستخدم مع كامل تاريخ معاملاته
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$wpUserId = intval_safe($_GET['id'] ?? 0);

if (!$wpUserId) {
    header('Location: users.php');
    exit;
}

// جلب بيانات المستخدم
$userStmt = $db->prepare('SELECT * FROM wp_users_cache WHERE wp_user_id = ?');
$userStmt->execute([$wpUserId]);
$user = $userStmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// ملخص معاملاته
$summary = $db->prepare(
    'SELECT
       COUNT(*)  as total_count,
       COALESCE(SUM(CASE WHEN type="deposit"  THEN amount ELSE 0 END), 0) as total_deposits,
       COALESCE(SUM(CASE WHEN type="withdraw" THEN amount ELSE 0 END), 0) as total_withdrawals
     FROM transactions WHERE wp_user_id = ?'
);
$summary->execute([$wpUserId]);
$stats = $summary->fetch();

// سجل المعاملات
$txStmt = $db->prepare(
    'SELECT t.*, a.full_name as agent_name
     FROM transactions t
     JOIN agents a ON a.id = t.agent_id
     WHERE t.wp_user_id = ?
     ORDER BY t.created_at DESC'
);
$txStmt->execute([$wpUserId]);
$transactions = $txStmt->fetchAll();

$pageTitle  = 'ملف ' . ($user['full_name'] ?: $user['username']);
$activePage = 'users';

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';

// Helpers
function txTypeBadge(string $type): string {
    return match($type) {
        'deposit'    => '<span class="badge badge-success">إيداع</span>',
        'withdraw'   => '<span class="badge badge-danger">سحب</span>',
        'adjustment' => '<span class="badge badge-warning">تعديل</span>',
        default      => '<span class="badge badge-gray">' . clean($type) . '</span>',
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
?>

<!-- رأس الملف الشخصي -->
<div class="user-profile-header">
    <div class="profile-avatar">
        <?= mb_substr($user['full_name'] ?: $user['username'], 0, 1) ?>
    </div>
    <div class="profile-info">
        <div class="profile-name"><?= clean($user['full_name'] ?: $user['username']) ?></div>
        <div class="profile-email"><?= clean($user['email']) ?></div>
        <div class="profile-meta">
            <span class="profile-meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>
                رصيد المحفظة: <strong><?= formatMoney((float)$user['wallet_balance']) ?></strong>
            </span>
            <span class="profile-meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                مسجل منذ: <strong><?= $user['registered_at'] ? formatDate($user['registered_at']) : '—' ?></strong>
            </span>
        </div>
    </div>
    <div style="flex-shrink:0;">
        <button class="btn btn-primary"
                onclick="openTransactionModal(<?= (int)$wpUserId ?>, <?= htmlspecialchars(json_encode($user['full_name'] ?: $user['username']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($user['email'] ?? ''), ENT_QUOTES) ?>)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            إضافة معاملة
        </button>
    </div>
</div>

<!-- بطاقات ملخص المعاملات -->
<div class="stats-grid mb-24">
    <div class="stat-card blue" style="--card-color:var(--color-primary)">
        <div class="stat-info">
            <div class="stat-label">إجمالي المعاملات</div>
            <div class="stat-value"><?= (int)$stats['total_count'] ?></div>
        </div>
    </div>
    <div class="stat-card green" style="--card-color:var(--color-success)">
        <div class="stat-info">
            <div class="stat-label">إجمالي الإيداعات</div>
            <div class="stat-value"><?= formatMoney((float)$stats['total_deposits']) ?></div>
        </div>
    </div>
    <div class="stat-card red" style="--card-color:var(--color-danger)">
        <div class="stat-info">
            <div class="stat-label">إجمالي السحوبات</div>
            <div class="stat-value"><?= formatMoney((float)$stats['total_withdrawals']) ?></div>
        </div>
    </div>
</div>

<!-- جدول المعاملات -->
<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">سجل المعاملات (<?= count($transactions) ?>)</span>
        <div class="table-controls">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" placeholder="بحث..." data-search-table="txTable">
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="txTable">
            <thead>
                <tr>
                    <th>#</th>
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
                    <td class="fw-bold"><?= formatMoney((float)$tx['amount']) ?></td>
                    <td><?= txTypeBadge($tx['type']) ?></td>
                    <td class="text-sm">
                        <?= match($tx['payment_method']) {
                            'bank_transfer' => 'تحويل بنكي',
                            'wallet'        => 'محفظة',
                            'cash'          => 'نقدي',
                            default         => clean($tx['payment_method'])
                        } ?>
                    </td>
                    <td class="text-sm"><?= clean($tx['agent_name']) ?></td>
                    <td class="text-sm text-muted"><?= clean(mb_substr($tx['notes'] ?? '', 0, 50)) ?></td>
                    <td class="text-sm text-muted"><?= formatDate($tx['created_at']) ?></td>
                    <td><?= txStatusBadge($tx['status']) ?></td>
                    <td>
                        <?php if ($tx['receipt_image']): ?>
                            <a href="../api/receipt.php?id=<?= $tx['id'] ?>" target="_blank"
                               class="btn btn-ghost btn-sm">عرض</a>
                        <?php else: ?>
                            <span class="text-muted text-xs">لا يوجد</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:40px;">
                    لم يتم تسجيل أي معاملات لهذا المستخدم بعد
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
