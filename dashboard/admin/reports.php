<?php
/**
 * مركز التقارير والتحليلات (Analytics Hub)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'التقارير المتقدمة';
$activePage = 'reports';

// إحصائيات آخر 30 يوماً لطلبات ووكوميرس
$stmtOrders30 = $db->query("
    SELECT DATE(wc_created_at) as order_date, COUNT(id) as counts, SUM(total) as revenue 
    FROM orders 
    WHERE wc_created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(wc_created_at)
    ORDER BY order_date ASC
");
$orders30 = $stmtOrders30->fetchAll();

$datesOrders = [];
$countsOrders = [];
$revenueOrders = [];
foreach ($orders30 as $row) {
    if (!$row['order_date']) continue;
    $datesOrders[] = $row['order_date'];
    $countsOrders[] = (int)$row['counts'];
    $revenueOrders[] = (float)$row['revenue'];
}

// أداء الوكلاء (الإيداعات)
$stmtAgents = $db->query("
    SELECT a.full_name, COUNT(t.id) as tx_count, SUM(t.amount) as total_deposit
    FROM agents a
    LEFT JOIN transactions t ON a.id = t.agent_id AND t.type = 'deposit' AND t.status = 'confirmed'
    GROUP BY a.id, a.full_name
    ORDER BY total_deposit DESC
");
$agentStats = $stmtAgents->fetchAll();

$agentNames = [];
$agentDeposits = [];
foreach ($agentStats as $row) {
    $agentNames[] = clean($row['full_name']);
    $agentDeposits[] = (float)$row['total_deposit'];
}

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<div class="flex-between mb-24">
    <div>
        <h3 class="mb-8 mt-0 fw-bold">التقارير المكتملة والأداء</h3>
        <p class="text-muted text-sm mt-0">نظرة عامة على المبيعات، الطلبات، وأداء فريق العمل (آخر 30 يوماً).</p>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn btn-ghost btn-sm" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
            </svg>
            طباعة التقرير
        </button>
    </div>
</div>

<div class="charts-grid" style="margin-bottom:24px;">
    <!-- إيرادات الطلبات -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">إيرادات الطلبات (آخر 30 يوماً)</span>
        </div>
        <div class="card-body">
            <canvas id="revenueChart" height="250"></canvas>
        </div>
    </div>
    
    <!-- أداء الوكلاء -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">أفضل الوكلاء (الإيداعات)</span>
        </div>
        <div class="card-body">
            <canvas id="agentsChart" height="250"></canvas>
        </div>
    </div>
</div>

<div class="data-table-card">
    <div class="table-toolbar">
        <span class="table-title">لوحة أداء فريق العمل بالكامل</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ترتيب</th>
                    <th>الوكيل</th>
                    <th>عدد عمليات الإيداع</th>
                    <th>إجمالي مبالغ الإيداع</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agentStats as $i => $ag): ?>
                <tr>
                    <td class="text-muted fw-bold">#<?= $i + 1 ?></td>
                    <td class="fw-semibold">
                        <?= clean($ag['full_name']) ?>
                        <?php if($i === 0 && $ag['total_deposit'] > 0): ?>
                            <span class="badge badge-warning text-xs">الأفضل 🏆</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$ag['tx_count'] ?> عملية</td>
                    <td class="fw-bold text-success"><?= formatMoney((float)$ag['total_deposit']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // إعداد ألوان بناءً على الدارك/لايت
    const isDark = document.body.classList.contains('dark-theme');
    const textColor = isDark ? '#F1F5F9' : '#334155';
    const gridColor = isDark ? '#334155' : '#E2E8F0';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = "'Cairo', sans-serif";

    // رسم بياني لإيرادات الطلبات خطياً
    const ctxRevenue = document.getElementById('revenueChart')?.getContext('2d');
    if (ctxRevenue) {
        new Chart(ctxRevenue, {
            type: 'line',
            data: {
                labels: <?= json_encode($datesOrders) ?>,
                datasets: [{
                    label: 'الإيرادات',
                    data: <?= json_encode($revenueOrders) ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: gridColor }, ticks: { maxTicksLimit: 10 } },
                    y: { grid: { color: gridColor }, beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // رسم بياني للوكلاء دائري أو عمودي
    const ctxAgents = document.getElementById('agentsChart')?.getContext('2d');
    if (ctxAgents) {
        new Chart(ctxAgents, {
            type: 'bar',
            data: {
                labels: <?= json_encode($agentNames) ?>,
                datasets: [{
                    label: 'إجمالي التغذية',
                    data: <?= json_encode($agentDeposits) ?>,
                    backgroundColor: '#89CFF0',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: gridColor }, beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
