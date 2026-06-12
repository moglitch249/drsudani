<?php
/**
 * شريط التنقل العلوي المشترك
 * @var string $pageTitle عنوان الصفحة الحالية
 */
$unreadCount = 0;
try {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM notifications
         WHERE is_read = 0
           AND (target_role = ? OR target_role = "all")
           AND (target_agent_id IS NULL OR target_agent_id = ?)'
    );
    $stmt->execute([$_SESSION['role'] ?? 'agent', $_SESSION['agent_id'] ?? 0]);
    $unreadCount = (int) $stmt->fetchColumn();
} catch (PDOException) {}
?>
<div class="main-content">
    <!-- Top Navbar -->
    <header class="topbar">
        <div class="topbar-right">
            <button class="mobile-toggle" id="mobileToggle">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div class="page-breadcrumb">
                <h2 class="page-title"><?= isset($pageTitle) ? clean($pageTitle) : SITE_NAME ?></h2>
            </div>
        </div>

        <div class="topbar-left">
            <!-- أزرار الإشعارات والمحادثات -->
            <div class="notif-wrapper" id="notifWrapper" style="display:flex;align-items:center;gap:4px;">
                <?php if (hasPermission('can_chat')): ?>
                <a href="<?= SITE_URL ?>/admin/chat.php" class="topbar-btn notif-btn" title="المحادثات في الانتظار" style="display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="notif-badge hidden" id="globalChatBadge" style="background:var(--color-warning);">0</span>
                </a>
                <?php endif; ?>

                <button class="topbar-btn notif-btn" id="notifBtn" title="الإشعارات" style="display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge" id="notifBadge"><?= $unreadCount ?></span>
                    <?php else: ?>
                    <span class="notif-badge hidden" id="notifBadge">0</span>
                    <?php endif; ?>
                </button>

                <!-- قائمة الإشعارات -->
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>الإشعارات</span>
                        <button class="mark-all-read" id="markAllRead">تحديد كمقروء</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <?php
                        $notifItemsStmt = db()->prepare(
                            'SELECT * FROM notifications
                             WHERE is_read = 0
                               AND (target_role = ? OR target_role = "all")
                               AND (target_agent_id IS NULL OR target_agent_id = ?)
                             ORDER BY id DESC LIMIT 20'
                        );
                        $notifItemsStmt->execute([$_SESSION['role'] ?? 'agent', $_SESSION['agent_id'] ?? 0]);
                        $initialNotifs = $notifItemsStmt->fetchAll();
                        
                        if (empty($initialNotifs)):
                        ?>
                            <p class="notif-empty">لا توجد إشعارات جديدة</p>
                        <?php else: foreach ($initialNotifs as $n): 
                            $iconClass = $n['type'] === 'new_order' ? 'order' : 'transaction';
                            $svgIcon = $iconClass === 'order' 
                                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>'
                                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
                            
                            // Determine Link
                            $isAdmin = ($_SESSION['role'] ?? 'agent') === 'admin';
                            $link = '#';
                            if ($iconClass === 'order') {
                                $link = SITE_URL . ($isAdmin ? '/admin/orders.php' : '/agent/orders.php');
                            } else {
                                $link = SITE_URL . ($isAdmin ? '/admin/transactions.php' : '/agent/transactions.php');
                            }
                        ?>
                            <a href="<?= $link ?>" class="notif-item unread" style="text-decoration: none; color: inherit;">
                                <div class="notif-icon <?= $iconClass ?>"><?= $svgIcon ?></div>
                                <div class="notif-body">
                                    <div class="notif-title"><?= clean($n['title']) ?></div>
                                    <?php if (!empty($n['body'])): ?>
                                        <div class="notif-text"><?= clean($n['body']) ?></div>
                                    <?php endif; ?>
                                    <div class="notif-time">الآن</div>
                                </div>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- Theme Toggle -->
            <button class="topbar-btn" id="themeToggleBtn" title="تبديل الوضع الليلي/النهاري">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>

            <!-- معلومات المستخدم -->
            <div class="user-menu" style="cursor: default;">
                <div class="user-avatar"><?= mb_substr($_SESSION['full_name'] ?? 'م', 0, 1) ?></div>
                <div class="user-info">
                    <span class="user-name"><?= clean($_SESSION['full_name'] ?? '') ?></span>
                    <span class="user-role"><?= $_SESSION['role'] === 'admin' ? 'مدير' : 'وكيل' ?></span>
                </div>
            </div>
        </div>
    </header>

    <div class="page-content">
