<?php
/**
 * الشريط الجانبي — المدير
 * @var string $activePage الصفحة النشطة
 */
$activePage = $activePage ?? '';
$navItems = [
    ['href' => SITE_URL . '/admin/index.php',        'icon' => 'dashboard', 'label' => 'لوحة التحكم',    'key' => 'dashboard'],
    ['href' => SITE_URL . '/admin/users.php',         'icon' => 'users',     'label' => 'المستخدمون',      'key' => 'users'],
    ['href' => SITE_URL . '/admin/customers.php',     'icon' => 'customers', 'label' => 'العملاء',         'key' => 'customers'],
    ['href' => SITE_URL . '/admin/transactions.php',  'icon' => 'wallet',    'label' => 'المعاملات',       'key' => 'transactions'],
    ['href' => SITE_URL . '/admin/orders.php',        'icon' => 'orders',    'label' => 'الطلبات',         'key' => 'orders'],
    ['href' => SITE_URL . '/admin/inventory.php',     'icon' => 'inventory', 'label' => 'المخزون',         'key' => 'inventory'],
    ['href' => SITE_URL . '/admin/reports.php',       'icon' => 'reports',   'label' => 'التقارير',        'key' => 'reports'],
    ['href' => SITE_URL . '/admin/agents.php',        'icon' => 'agents',    'label' => 'الوكلاء',         'key' => 'agents'],
    ['href' => SITE_URL . '/admin/chat.php',          'icon' => 'chat',      'label' => 'المحادثات',       'key' => 'chat'],
    ['href' => SITE_URL . '/admin/logs.php',          'icon' => 'logs',      'label' => 'سجل النظام',      'key' => 'logs'],
    ['href' => SITE_URL . '/admin/settings.php',      'icon' => 'settings',  'label' => 'الإعدادات',       'key' => 'settings'],
];
?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo" style="justify-content: center;">
            <?php $logoUrl = getSetting(db(), 'logo_url', ''); if ($logoUrl): ?>
                <img src="<?= clean($logoUrl) ?>" alt="Logo" style="max-height: 48px; width: auto; object-fit: contain;">
            <?php else: ?>
                <div class="logo-mark">
                    <svg width="28" height="28" viewBox="0 0 36 36" fill="none">
                        <rect width="36" height="36" rx="9" fill="var(--color-primary)"/>
                        <path d="M10 18 L18 10 L26 18 L18 26 Z" fill="white" opacity="0.9"/>
                        <circle cx="18" cy="18" r="4" fill="white"/>
                    </svg>
                </div>
                <span class="logo-name"><?= SITE_NAME ?></span>
            <?php endif; ?>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" title="إخفاء القائمة">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($navItems as $item): ?>
            <li class="nav-item">
                <a href="<?= $item['href'] ?>"
                   class="nav-link <?= $activePage === $item['key'] ? 'active' : '' ?>">
                    <?= getSvgIcon($item['icon']) ?>
                    <span class="nav-label"><?= $item['label'] ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar"><?= mb_substr($_SESSION['full_name'] ?? 'م', 0, 1) ?></div>
            <div class="agent-info">
                <span class="agent-name"><?= clean($_SESSION['full_name'] ?? '') ?></span>
                <span class="agent-role">مدير النظام</span>
            </div>
        </div>
        <a href="<?= SITE_URL ?>/auth/logout.php" class="btn-logout" title="تسجيل الخروج">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </a>
    </div>
</aside>
<?php

function getSvgIcon(string $name): string {
    $icons = [
        'dashboard' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'users'     => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'customers' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
        'wallet'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
        'orders'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
        'inventory' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'reports'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        'agents'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="14" x2="12" y2="21"/></svg>',
        'chat'      => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'logs'      => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'settings'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.8.99 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    ];
    return $icons[$name] ?? '';
}
?>
