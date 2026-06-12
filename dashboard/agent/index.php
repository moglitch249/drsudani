<?php
/**
 * لوحة تحكم الوكيل
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAgent();

$db = db();
$pageTitle  = 'لوحة تحكم الوكيل';
$activePage = 'dashboard';
$agentId    = (int)$_SESSION['agent_id'];

// إحصائيات الوكيل لليوم
$today = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

// إحصائيات الوكيل لليوم، الشهر، والسنة
$statsStmt = $db->prepare(
    "SELECT 
        COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as tx_count_today,
        COALESCE(SUM(CASE WHEN type='deposit' AND DATE(created_at) = ? THEN amount ELSE 0 END), 0) as deposits_today,
        COALESCE(SUM(CASE WHEN type='deposit' AND DATE_FORMAT(created_at, '%Y-%m') = ? THEN amount ELSE 0 END), 0) as deposits_month,
        COALESCE(SUM(CASE WHEN type='deposit' AND YEAR(created_at) = ? THEN amount ELSE 0 END), 0) as deposits_year
     FROM transactions WHERE agent_id = ?"
);
$statsStmt->execute([$today, $today, $currentMonth, $currentYear, $agentId]);
$stats = $statsStmt->fetch();

$commissionRate = (float)getSetting($db, 'agent_commission_percent', '0');
$earningsToday = ($stats['deposits_today'] * $commissionRate) / 100;
$earningsMonth = ($stats['deposits_month'] * $commissionRate) / 100;
$earningsYear = ($stats['deposits_year'] * $commissionRate) / 100;

// الطلبات قيد المعالجة (إذا كان لديه صلاحية)
$pendingOrders = 0;
$recentOrders = [];
if (hasPermission('view_orders')) {
    $pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'processing')")->fetchColumn();
    $recentOrders = $db->query("SELECT wc_order_id, total as total_amount, status, wc_created_at as created_at, customer_name FROM orders WHERE status IN ('pending', 'processing') ORDER BY wc_created_at DESC LIMIT 5")->fetchAll();
}

// إحصائيات المحادثات (إذا كان لديه صلاحية)
$activeChats = 0;
$waitingChats = 0;
if (hasPermission('can_chat')) {
    $activeChats = (int)$db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='active' AND agent_id=?")->execute([$agentId]) ? $db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='active' AND agent_id=?") : 0;
    $stmt = $db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status='active' AND agent_id=?");
    $stmt->execute([$agentId]);
    $activeChats = (int)$stmt->fetchColumn();
    
    $waitingChats = (int)$db->query("SELECT COUNT(*) FROM chat_sessions WHERE status='waiting'")->fetchColumn();
}

// أحدث معاملاته
$recentTx = $db->prepare(
    'SELECT t.*, u.full_name as user_name
     FROM transactions t
     LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id
     WHERE t.agent_id = ?
     ORDER BY t.created_at DESC LIMIT 5'
);
$recentTx->execute([$agentId]);
$transactions = $recentTx->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-agent.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-info">
            <div class="stat-label">معاملاتي اليوم</div>
            <div class="stat-value"><?= (int)$stats['tx_count_today'] ?></div>
        </div>
        <div class="stat-icon" style="background:var(--color-primary);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect x="2" y="7" width="20" height="14" rx="2"/></svg></div>
    </div>
    
    <div class="stat-card green">
        <div class="stat-info">
            <div class="stat-label">إيداعاتي اليوم</div>
            <div class="stat-value"><?= formatMoney((float)$stats['deposits_today']) ?></div>
        </div>
        <div class="stat-icon" style="background:var(--color-success);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
    </div>

    <div class="stat-card" style="border-top-color: #10b981;">
        <div class="stat-info">
            <div class="stat-label">إيداعاتي هذا الشهر</div>
            <div class="stat-value"><?= formatMoney((float)$stats['deposits_month']) ?></div>
        </div>
        <div class="stat-icon" style="background:#10b981;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
    </div>

    <div class="stat-card" style="border-top-color: #059669;">
        <div class="stat-info">
            <div class="stat-label">إيداعاتي هذا العام</div>
            <div class="stat-value"><?= formatMoney((float)$stats['deposits_year']) ?></div>
        </div>
        <div class="stat-icon" style="background:#059669;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
    </div>

    <?php if (hasPermission('can_chat')): ?>
    <div class="stat-card" style="border-top-color: #3b82f6;">
        <div class="stat-info">
            <div class="stat-label">محادثاتي النشطة</div>
            <div class="stat-value"><?= $activeChats ?></div>
        </div>
        <div class="stat-icon" style="background:#3b82f6;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
    </div>
    <div class="stat-card" style="border-top-color: #f59e0b;">
        <div class="stat-info">
            <div class="stat-label">محادثات في الانتظار</div>
            <div class="stat-value"><?= $waitingChats ?></div>
        </div>
        <div class="stat-icon" style="background:#f59e0b;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    </div>
    <?php endif; ?>
    <?php if (hasPermission('view_orders')): ?>
    <div class="stat-card orange">
        <div class="stat-info">
            <div class="stat-label">طلبات غير مكتملة</div>
            <div class="stat-value"><?= $pendingOrders ?></div>
        </div>
        <div class="stat-icon" style="background:var(--color-warning);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    </div>
    <?php endif; ?>
    <div class="stat-card" style="border-top-color: #8B5CF6;">
        <div class="stat-info">
            <div class="stat-label">أرباحي اليوم (<?= $commissionRate ?>%)</div>
            <div class="stat-value text-success"><?= formatMoney($earningsToday) ?></div>
        </div>
        <div class="stat-icon" style="background:#8B5CF6;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    </div>
    <div class="stat-card" style="border-top-color: #7c3aed;">
        <div class="stat-info">
            <div class="stat-label">مرتبي هذا الشهر</div>
            <div class="stat-value text-success"><?= formatMoney($earningsMonth) ?></div>
        </div>
        <div class="stat-icon" style="background:#7c3aed;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
    </div>
    <div class="stat-card" style="border-top-color: #6d28d9;">
        <div class="stat-info">
            <div class="stat-label">أرباحي السنوية</div>
            <div class="stat-value text-success"><?= formatMoney($earningsYear) ?></div>
        </div>
        <div class="stat-icon" style="background:#6d28d9;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
    </div>
</div>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">أحدث معاملاتي</span>
        <a href="transactions.php" class="btn btn-ghost btn-sm">عرض الكل</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المستخدم</th>
                    <th>المبلغ</th>
                    <th>النوع</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td class="text-muted text-sm">#<?= $tx['id'] ?></td>
                    <td class="fw-semibold"><?= clean($tx['user_name'] ?? 'مستخدم #' . $tx['wp_user_id']) ?></td>
                    <td class="fw-bold"><?= formatMoney((float)$tx['amount']) ?></td>
                    <td><?= match($tx['type']) {
                        'deposit' => '<span class="badge badge-success">إيداع</span>',
                        'withdraw' => '<span class="badge badge-danger">سحب</span>',
                        'adjustment' => '<span class="badge badge-warning">تعديل</span>',
                        default => $tx['type']
                    } ?></td>
                    <td class="text-sm text-muted"><?= formatDate($tx['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:40px;">لا توجد معاملات بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (hasPermission('view_orders') && !empty($recentOrders)): ?>
<div class="data-table-card mt-24" style="margin-top:24px;">
    <div class="table-toolbar">
        <span class="table-title">أحدث الطلبات (قيد المعالجة)</span>
        <a href="../admin/orders.php" class="btn btn-ghost btn-sm">إدارة الطلبات</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td class="fw-bold">#<?= $order['wc_order_id'] ?></td>
                    <td class="fw-semibold"><?= clean($order['customer_name'] ?: 'بدون اسم') ?></td>
                    <td class="fw-bold"><?= formatMoney((float)$order['total_amount']) ?></td>
                    <td>
                        <?php if ($order['status'] === 'pending'): ?>
                            <span class="badge badge-warning">قيد الانتظار</span>
                        <?php else: ?>
                            <span class="badge badge-info">قيد المعالجة</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted"><?= formatDate($order['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
