<?php
/**
 * الشريط الجانبي — الوكيل
 */
$activePage = $activePage ?? '';
?>
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
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/agent/index.php"
                   class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <span class="nav-label">لوحة التحكم</span>
                </a>
            </li>
            <?php if (hasPermission('view_orders')): ?>
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/agent/orders.php"
                   class="nav-link <?= $activePage === 'orders' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <span class="nav-label">الطلبات</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('add_transaction')): ?>
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/agent/users.php"
                   class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="nav-label">المستخدمون</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/agent/transactions.php"
                   class="nav-link <?= $activePage === 'transactions' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                    <span class="nav-label">معاملاتي</span>
                </a>
            </li>
            <?php if (hasPermission('can_chat')): ?>
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/admin/chat.php"
                   class="nav-link <?= $activePage === 'chat' ? 'active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="nav-label">المحادثات</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar"><?= mb_substr($_SESSION['full_name'] ?? 'و', 0, 1) ?></div>
            <div class="agent-info">
                <span class="agent-name"><?= clean($_SESSION['full_name'] ?? '') ?></span>
                <span class="agent-role">وكيل</span>
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
