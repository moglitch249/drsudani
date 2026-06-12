<?php
/**
 * لوحة التحكم الرئيسية — المدير
 * Admin Analytics Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'لوحة التحكم';
$activePage = 'dashboard';

// ── إحصائيات اليوم ──────────────────────────────────────────
$today = date('Y-m-d');

$statToday = $db->prepare(
    'SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total
     FROM transactions WHERE DATE(created_at) = ?'
);
$statToday->execute([$today]);
$today_stats = $statToday->fetch();

$statDeposits = $db->prepare(
    'SELECT COALESCE(SUM(amount),0) as total FROM transactions
     WHERE type = "deposit" AND DATE(created_at) = ?'
);
$statDeposits->execute([$today]);
$today_deposits = (float)$statDeposits->fetchColumn();

// إيداعات الشهر
$currentMonth = date('Y-m');
$statMonthDeposits = $db->prepare(
    'SELECT COALESCE(SUM(amount),0) as total FROM transactions
     WHERE type = "deposit" AND DATE_FORMAT(created_at, "%Y-%m") = ?'
);
$statMonthDeposits->execute([$currentMonth]);
$month_deposits = (float)$statMonthDeposits->fetchColumn();

// إيداعات السنة
$currentYear = date('Y');
$statYearDeposits = $db->prepare(
    'SELECT COALESCE(SUM(amount),0) as total FROM transactions
     WHERE type = "deposit" AND YEAR(created_at) = ?'
);
$statYearDeposits->execute([$currentYear]);
$year_deposits = (float)$statYearDeposits->fetchColumn();

$total_orders = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$active_agents = (int)$db->query(
    "SELECT COUNT(*) FROM agents 
     WHERE role = 'agent' 
     AND last_seen >= NOW() - INTERVAL 5 MINUTE"
)->fetchColumn();

// ── معاملات آخر 7 أيام (للرسم البياني) ─────────────────────
$chart7 = $db->query(
    'SELECT DATE(created_at) as d,
            SUM(CASE WHEN type="deposit"  THEN amount ELSE 0 END) as dep,
            SUM(CASE WHEN type="withdraw" THEN amount ELSE 0 END) as wd
     FROM transactions
     WHERE created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(created_at)
     ORDER BY d ASC'
)->fetchAll();

$chartLabels = $chartDep = $chartWd = [];
for ($i = 6; $i >= 0; $i--) {
    $day   = date('Y-m-d', strtotime("-$i days"));
    $found = array_filter($chart7, fn($r) => $r['d'] === $day);
    $row   = $found ? array_values($found)[0] : ['dep' => 0, 'wd' => 0];
    $chartLabels[] = date('m/d', strtotime($day));
    $chartDep[]    = round((float)$row['dep'], 2);
    $chartWd[]     = round((float)$row['wd'], 2);
}

// ── توزيع أنواع المعاملات ─────────────────────────────────
$typeStats = $db->query(
    'SELECT type, COUNT(*) as cnt FROM transactions GROUP BY type'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$typesData = [
    $typeStats['deposit']    ?? 0,
    $typeStats['withdraw']   ?? 0,
    $typeStats['adjustment'] ?? 0,
];

// ── أفضل الوكلاء ─────────────────────────────────────────────
$topAgents = $db->query(
    'SELECT a.full_name, COUNT(t.id) as tx_count
     FROM agents a
     LEFT JOIN transactions t ON t.agent_id = a.id
     WHERE a.role = "agent"
     GROUP BY a.id
     ORDER BY tx_count DESC
     LIMIT 5'
)->fetchAll();

$agentLabels = array_column($topAgents, 'full_name');
$agentValues = array_column($topAgents, 'tx_count');

// ── آخر المعاملات ─────────────────────────────────────────────
$recentTx = $db->query(
    'SELECT t.*, a.full_name as agent_name,
            u.full_name as user_name, u.email as user_email
     FROM transactions t
     JOIN agents a ON a.id = t.agent_id
     LEFT JOIN wp_users_cache u ON u.wp_user_id = t.wp_user_id
     ORDER BY t.created_at DESC
     LIMIT 8'
)->fetchAll();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<!-- بطاقات الإحصائيات -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-info">
            <div class="stat-label">معاملات اليوم</div>
            <div class="stat-value"><?= number_format((int)$today_stats['cnt']) ?></div>
            <div class="stat-sub">مجموع: <?= formatMoney((float)$today_stats['total']) ?></div>
        </div>
        <div class="stat-icon" style="background:var(--color-primary);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
        </div>
    </div>

    <div class="stat-card green">
        <div class="stat-info">
            <div class="stat-label">الإيداعات اليوم</div>
            <div class="stat-value"><?= formatMoney($today_deposits) ?></div>
        </div>
        <div class="stat-icon" style="background:var(--color-success);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                <polyline points="17 6 23 6 23 12"/>
            </svg>
        </div>
    </div>

    <div class="stat-card" style="border-top-color: #10b981;">
        <div class="stat-info">
            <div class="stat-label">إيداعات الشهر الحالي</div>
            <div class="stat-value"><?= formatMoney($month_deposits) ?></div>
        </div>
        <div class="stat-icon" style="background:#10b981;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
    </div>

    <div class="stat-card" style="border-top-color: #059669;">
        <div class="stat-info">
            <div class="stat-label">إيداعات العام الحالي</div>
            <div class="stat-value"><?= formatMoney($year_deposits) ?></div>
        </div>
        <div class="stat-icon" style="background:#059669;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
    </div>

    <div class="stat-card orange">
        <div class="stat-info">
            <div class="stat-label">إجمالي الطلبات</div>
            <div class="stat-value"><?= number_format($total_orders) ?></div>
            <div class="stat-sub">من ووكوميرس</div>
        </div>
        <div class="stat-icon" style="background:var(--color-warning);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
        </div>
    </div>

    <div class="stat-card red">
        <div class="stat-info">
            <div class="stat-label">الوكلاء المتصلون</div>
            <div class="stat-value"><?= $active_agents ?></div>
            <div class="stat-sub">متاحون الآن</div>
        </div>
        <div class="stat-icon" style="background:var(--color-danger);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
    </div>
</div>

<!-- الرسوم البيانية -->
<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-title">المعاملات — آخر 7 أيام</div>
        <div class="chart-container">
            <canvas id="txChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-title">توزيع أنواع المعاملات</div>
        <div class="chart-container">
            <canvas id="typesChart"></canvas>
        </div>
    </div>
</div>

<!-- أفضل الوكلاء -->
<?php if (!empty($topAgents)): ?>
<div class="chart-card mb-24">
    <div class="chart-title">أفضل الوكلاء (بعدد المعاملات)</div>
    <div class="chart-container" style="height:180px;">
        <canvas id="agentsChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- آخر المعاملات -->
<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">آخر المعاملات</span>
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
                    <th>طريقة الدفع</th>
                    <th>الوكيل</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTx as $tx): ?>
                <tr>
                    <td class="text-muted text-sm">#<?= $tx['id'] ?></td>
                    <td>
                        <span class="fw-semibold"><?= clean($tx['user_name'] ?? 'مستخدم #' . $tx['wp_user_id']) ?></span>
                    </td>
                    <td class="fw-bold <?= $tx['type'] === 'deposit' ? '' : 'text-muted' ?>">
                        <?= formatMoney((float)$tx['amount']) ?>
                    </td>
                    <td><?= txTypeBadge($tx['type']) ?></td>
                    <td><?= txMethodLabel($tx['payment_method']) ?></td>
                    <td class="text-sm"><?= clean($tx['agent_name']) ?></td>
                    <td class="text-sm text-muted"><?= formatDate($tx['created_at']) ?></td>
                    <td><?= txStatusBadge($tx['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentTx)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:40px;">لا توجد معاملات بعد</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/components/footer.php';

// ── دوال المساعدة ─────────────────────────
function txTypeBadge(string $type): string {
    return match($type) {
        'deposit'    => '<span class="badge badge-success">إيداع</span>',
        'withdraw'   => '<span class="badge badge-danger">سحب</span>',
        'adjustment' => '<span class="badge badge-warning">تعديل</span>',
        default      => '<span class="badge badge-gray">' . clean($type) . '</span>',
    };
}

function txMethodLabel(string $method): string {
    return match($method) {
        'bank_transfer' => 'تحويل بنكي',
        'wallet'        => 'محفظة',
        'cash'          => 'نقدي',
        default         => $method,
    };
}

function txStatusBadge(string $status): string {
    return match($status) {
        'confirmed' => '<span class="badge badge-success">مؤكدة</span>',
        'pending'   => '<span class="badge badge-warning">معلقة</span>',
        'rejected'  => '<span class="badge badge-danger">مرفوضة</span>',
        default     => '<span class="badge badge-gray">' . clean($status) . '</span>',
    };
}
?>

<script src="<?= SITE_URL ?>/assets/js/charts.js"></script>
<script>
initTransactionsChart('txChart',
    <?= json_encode($chartLabels) ?>,
    <?= json_encode($chartDep) ?>,
    <?= json_encode($chartWd) ?>
);
initTypesChart('typesChart', <?= json_encode($typesData) ?>);
<?php if (!empty($agentLabels)): ?>
initAgentsChart('agentsChart',
    <?= json_encode($agentLabels) ?>,
    <?= json_encode($agentValues) ?>
);
<?php endif; ?>
</script>
