<?php
/**
 * إدارة الوكلاء (CRUD + صلاحيات) — المدير
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'إدارة الوكلاء';
$activePage = 'agents';

$msg     = '';
$msgType = 'success';

// ── معالجة النموذج ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id       = intval_safe($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin','agent']) ? $_POST['role'] : 'agent';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $permissions = [
            'view_orders'              => isset($_POST['perm_view_orders']),
            'add_transaction'          => isset($_POST['perm_add_transaction']),
            'view_own_transactions_only' => isset($_POST['perm_own_only']),
            'can_chat'                 => isset($_POST['perm_can_chat']),
            'chat_support'             => isset($_POST['perm_chat_support']),
            'chat_wallet'              => isset($_POST['perm_chat_wallet']),
        ];

        if ($username === '' || $fullName === '' || $email === '') {
            $msg = 'يرجى ملء جميع الحقول المطلوبة.';
            $msgType = 'error';
        } else {
            if ($action === 'add') {
                if ($password === '') { $msg = 'كلمة المرور مطلوبة.'; $msgType = 'error'; }
                else {
                    try {
                        $db->prepare(
                            'INSERT INTO agents (username, password, full_name, email, role, permissions, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        )->execute([
                            $username,
                            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                            $fullName, $email, $role,
                            json_encode($permissions, JSON_UNESCAPED_UNICODE),
                            $isActive
                        ]);
                        logAction('agent_added', "إضافة وكيل: $username");
                        $msg = 'تمت إضافة الوكيل بنجاح.';
                    } catch (PDOException $e) {
                        $msg = 'اسم المستخدم أو البريد مستخدم مسبقاً.';
                        $msgType = 'error';
                    }
                }
            } else { // edit
                $updateParams = [$fullName, $email, $role, json_encode($permissions, JSON_UNESCAPED_UNICODE), $isActive, $id];
                if ($password !== '') {
                    $db->prepare(
                        'UPDATE agents SET full_name=?, email=?, role=?, permissions=?, is_active=?, password=? WHERE id=?'
                    )->execute([$fullName, $email, $role, json_encode($permissions,JSON_UNESCAPED_UNICODE), $isActive, password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]), $id]);
                } else {
                    $db->prepare(
                        'UPDATE agents SET full_name=?, email=?, role=?, permissions=?, is_active=? WHERE id=?'
                    )->execute($updateParams);
                }
                logAction('agent_edited', "تعديل وكيل: id=$id");
                $msg = 'تم تحديث بيانات الوكيل.';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval_safe($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['agent_id']) {
            $msg = 'لا يمكنك حذف حسابك أنت.'; $msgType = 'error';
        } else {
            $db->prepare('DELETE FROM agents WHERE id=?')->execute([$id]);
            logAction('agent_deleted', "حذف وكيل: id=$id");
            $msg = 'تم حذف الوكيل.';
        }
    }
}

// جلب الوكلاء
$currentMonth = date('Y-m');
$agentsStmt = $db->prepare(
    "SELECT a.*, COUNT(t.id) as tx_count,
            COALESCE(SUM(CASE WHEN t.type='deposit' AND DATE_FORMAT(t.created_at, '%Y-%m') = ? THEN t.amount ELSE 0 END), 0) as month_deposits
     FROM agents a
     LEFT JOIN transactions t ON t.agent_id = a.id
     GROUP BY a.id
     ORDER BY a.created_at DESC"
);
$agentsStmt->execute([$currentMonth]);
$agents = $agentsStmt->fetchAll();

$commissionRate = (float)getSetting($db, 'agent_commission_percent', '0');

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?> mb-16"><?= clean($msg) ?></div>
<?php endif; ?>

<!-- لوحة مراقبة الوكلاء -->
<div class="card mb-24" style="border:none">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3 class="card-title" style="margin:0">🟢 حالة الوكلاء الآن</h3>
        <small class="text-muted" id="agStatusTime">تحديث تلقائي كل 15 ثانية</small>
    </div>
    <div class="card-body">
        <div id="agStatusGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
            <div class="text-muted text-sm">جاري التحميل...</div>
        </div>
    </div>
</div>

<div class="agents-main-grid">

    <!-- جدول الوكلاء -->
    <div class="data-table-card">
        <div class="table-toolbar">
            <span class="table-title">الوكلاء (<?= count($agents) ?>)</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الدور</th>
                        <th>المعاملات</th>
                        <th>إيداعات الشهر</th>
                        <th>المرتب (<?= $commissionRate ?>%)</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;background:var(--color-primary);color:white;font-weight:700;font-size:0.9rem;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <?= mb_substr($agent['full_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold" style="font-size:0.9rem;"><?= clean($agent['full_name']) ?></div>
                                    <div class="text-xs text-muted"><?= clean($agent['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-sm"><?= clean($agent['username']) ?></td>
                        <td>
                            <?= $agent['role'] === 'admin'
                                ? '<span class="badge badge-primary">مدير</span>'
                                : '<span class="badge badge-info">وكيل</span>' ?>
                        </td>
                        <td class="text-sm fw-bold text-center"><?= (int)$agent['tx_count'] ?></td>
                        <td class="text-sm fw-bold text-success text-center"><?= formatMoney((float)$agent['month_deposits']) ?></td>
                        <td class="text-sm fw-bold text-center" style="color:#7c3aed;">
                            <?= $agent['role'] === 'agent' ? formatMoney(((float)$agent['month_deposits'] * $commissionRate) / 100) : '-' ?>
                        </td>
                        <td>
                            <?= $agent['is_active']
                                ? '<span class="badge badge-success">نشط</span>'
                                : '<span class="badge badge-gray">معطل</span>' ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-ghost btn-sm"
                                        onclick="editAgent(<?= htmlspecialchars(json_encode($agent, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                                    تعديل
                                </button>
                                <?php if ($agent['id'] !== (int)$_SESSION['agent_id']): ?>
                                <form method="post" onsubmit="return confirm('هل أنت متأكد من حذف هذا الوكيل؟');">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id"     value="<?= $agent['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- نموذج الإضافة/التعديل -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" id="formTitle">إضافة وكيل جديد</h3>
        </div>
        <div class="card-body">
            <form method="post" id="agentForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action"     id="formAction" value="add">
                <input type="hidden" name="id"         id="formId"     value="0">

                <div class="form-group">
                    <label>الاسم الكامل <span class="required">*</span></label>
                    <input type="text" name="full_name" id="fFullName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>اسم المستخدم <span class="required">*</span></label>
                    <input type="text" name="username" id="fUsername" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>البريد الإلكتروني <span class="required">*</span></label>
                    <input type="email" name="email" id="fEmail" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>كلمة المرور <span id="passHint" class="text-muted text-xs">(مطلوبة عند الإضافة)</span></label>
                    <input type="password" name="password" id="fPassword" class="form-control" placeholder="••••••••">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>الدور</label>
                        <select name="role" id="fRole" class="form-control">
                            <option value="agent">وكيل</option>
                            <option value="admin">مدير</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:8px;margin-top:28px;">
                        <input type="checkbox" name="is_active" id="fActive" value="1" checked style="accent-color:var(--color-primary-dark);width:18px;height:18px;">
                        <label for="fActive" style="margin:0;cursor:pointer;">نشط</label>
                    </div>
                </div>

                <!-- الصلاحيات -->
                <div class="form-group">
                    <label>الصلاحيات</label>
                    <div class="permissions-list">
                        <label class="permission-item">
                            <input type="checkbox" name="perm_view_orders" id="pViewOrders" value="1" checked>
                            <span class="permission-label">عرض الطلبات</span>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perm_add_transaction" id="pAddTx" value="1" checked>
                            <span class="permission-label">إضافة معاملات</span>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perm_own_only" id="pOwnOnly" value="1" checked>
                            <span class="permission-label">يرى معاملاته فقط</span>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perm_can_chat" id="pCanChat" value="1" checked>
                            <span class="permission-label">الوصول للشات (عام)</span>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perm_chat_support" id="pChatSupport" value="1">
                            <span class="permission-label">دعم فني (Support)</span>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perm_chat_wallet" id="pChatWallet" value="1">
                            <span class="permission-label">شحن محفظة (Wallet)</span>
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">حفظ</button>
                    <button type="button" class="btn btn-ghost" onclick="resetForm()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAgent(agent) {
    document.getElementById('formTitle').textContent  = 'تعديل الوكيل';
    document.getElementById('formAction').value        = 'edit';
    document.getElementById('formId').value            = agent.id;
    document.getElementById('fFullName').value         = agent.full_name;
    document.getElementById('fUsername').value         = agent.username;
    document.getElementById('fEmail').value            = agent.email;
    document.getElementById('fPassword').value         = '';
    document.getElementById('fRole').value             = agent.role;
    document.getElementById('fActive').checked         = agent.is_active == 1;
    document.getElementById('passHint').textContent    = '(اتركها فارغة للإبقاء على الحالية)';

    const perms = typeof agent.permissions === 'string'
        ? JSON.parse(agent.permissions || '{}')
        : (agent.permissions || {});

    document.getElementById('pViewOrders').checked = !!perms.view_orders;
    document.getElementById('pAddTx').checked      = !!perms.add_transaction;
    document.getElementById('pOwnOnly').checked    = !!perms.view_own_transactions_only;
    document.getElementById('pCanChat').checked    = !!perms.can_chat;
    document.getElementById('pChatSupport').checked = !!perms.chat_support;
    document.getElementById('pChatWallet').checked  = !!perms.chat_wallet;

    document.getElementById('agentForm').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('agentForm').reset();
    document.getElementById('formTitle').textContent = 'إضافة وكيل جديد';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value     = '0';
    document.getElementById('passHint').textContent = '(مطلوبة عند الإضافة)';
    document.getElementById('fActive').checked  = true;
    document.getElementById('pViewOrders').checked = true;
    document.getElementById('pAddTx').checked      = true;
    document.getElementById('pOwnOnly').checked    = true;
    document.getElementById('pCanChat').checked    = true;
    document.getElementById('pChatSupport').checked = false;
    document.getElementById('pChatWallet').checked  = false;
}
</script>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>

<script>
// ======= Agent Status Monitor =======
async function loadAgentStatus(){
    try{
        var res = await fetch('../api/agent-status.php');
        var data = await res.json();
        if(!data.success) return;
        var grid = document.getElementById('agStatusGrid');
        grid.innerHTML = '';
        var now = new Date();
        data.data.agents.forEach(function(a){
            var online = a.is_online == 1;
            var card = document.createElement('div');
            card.style.cssText = 'padding:14px 16px;border-radius:12px;border:1.5px solid ' + (online ? 'var(--color-primary-light)' : 'var(--color-border)') + ';background:' + (online ? 'rgba(89,184,240,.07)' : 'var(--color-surface)') + ';display:flex;align-items:center;gap:12px;min-height:82px;transition:var(--transition);';
            if(online) card.style.boxShadow = '0 4px 12px rgba(var(--primary-h), var(--primary-s), var(--primary-l), 0.1)';

            // Dot indicator
            var dot = document.createElement('span');
            dot.style.cssText = 'width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0;' + (online ? 'background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2);' : 'background:#94a3b8;');

            // Info container
            var info = document.createElement('div');
            info.style.minWidth = '0';

            // Name — textContent prevents XSS
            var nameEl = document.createElement('div');
            nameEl.style.cssText = 'font-weight:700;font-size:14px;color:var(--color-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
            nameEl.textContent = a.full_name;

            // Status line
            var statusEl = document.createElement('div');
            statusEl.style.cssText = 'font-size:11px;color:var(--color-text-muted);margin-top:2px;';
            var lastSeen = a.last_seen ? 'آخر نشاط: ' + new Date(a.last_seen).toLocaleTimeString('ar') : 'لم يتصل بعد';
            statusEl.textContent = online ? '• متصل' : lastSeen;

            info.appendChild(nameEl);
            info.appendChild(statusEl);

            if (a.active_chats > 0) {
                var badgeWrap = document.createElement('div');
                badgeWrap.style.marginTop = '4px';
                var chatsEl = document.createElement('span');
                chatsEl.style.cssText = 'font-size:11px;background:var(--color-primary);color:#fff;border-radius:6px;padding:2px 8px;font-weight:600;display:inline-block;';
                chatsEl.textContent = a.active_chats + ' محادثة نشطة';
                badgeWrap.appendChild(chatsEl);
                info.appendChild(badgeWrap);
            } else {
                var spacer = document.createElement('div');
                spacer.style.height = '18px';
                spacer.style.marginTop = '4px';
                info.appendChild(spacer);
            }

            card.appendChild(dot);
            card.appendChild(info);
            grid.appendChild(card);
        });
        if(data.data.agents.length === 0){
            grid.innerHTML = '<div class="text-muted text-sm">لا يوجد وكلاء</div>';
        }
        document.getElementById('agStatusTime').textContent = 'آخر تحديث: ' + new Date().toLocaleTimeString('ar');
    }catch(e){}
}
loadAgentStatus();
setInterval(loadAgentStatus, 15000);
</script>
