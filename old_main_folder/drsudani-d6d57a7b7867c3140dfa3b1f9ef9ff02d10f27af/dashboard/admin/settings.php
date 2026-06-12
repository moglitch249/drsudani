<?php
/**
 * إعدادات النظام (Admin Only)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$pageTitle = 'إعدادات النظام';
$activePage = 'settings';

$db = db();
$currentLogo = getSetting($db, 'logo_url', '');
$currentCurrency = getSetting($db, 'currency_symbol', 'ر.س');
$currentCommission = getSetting($db, 'agent_commission_percent', '0');
$currentThreshold  = getSetting($db, 'low_stock_threshold', '5');

require dirname(__DIR__) . '/components/header.php';
require dirname(__DIR__) . '/components/sidebar-admin.php';
require dirname(__DIR__) . '/components/topbar.php';
?>

<div class="card mb-24">
    <div class="card-header">
        <h3 class="card-title">الإعدادات العامة</h3>
    </div>
    <div class="card-body">
        <div id="settingsAlert" class="alert hidden"></div>

        <form id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="logo_url">رابط الشعار (Logo URL)</label>
                    <input type="url" id="logo_url" name="logo_url" class="form-control" 
                           placeholder="https://example.com/logo.png" 
                           value="<?= clean($currentLogo) ?>">
                    <small class="text-muted mt-8" style="display:block;">اتركه فارغاً للعودة للشعار الافتراضي.</small>
                </div>

                <div class="form-group">
                    <label for="currency_symbol">رمز العملة (Currency Symbol) <span class="required">*</span></label>
                    <input type="text" id="currency_symbol" name="currency_symbol" class="form-control" 
                           placeholder="مثال: ر.س، $، SDG" 
                           value="<?= clean($currentCurrency) ?>" required>
                    <small class="text-muted mt-8" style="display:block;">سيتم تغيير كل المبالغ في لوحة التحكم لاستخدام هذه العملة.</small>
                </div>

                <div class="form-group">
                    <label for="agent_commission_percent">نسبة عمولة الوكيل (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="agent_commission_percent" name="agent_commission_percent" class="form-control" 
                           placeholder="مثال: 5 للحصول على 5%" 
                           value="<?= clean($currentCommission) ?>">
                    <small class="text-muted mt-8" style="display:block;">تُحسب تلقائياً من إجمالي المعاملات والطلبات التي يديرها الوكيل.</small>
                </div>

                <div class="form-group">
                    <label for="low_stock_threshold">حد التنبيه بانخفاض المخزون</label>
                    <input type="number" min="0" id="low_stock_threshold" name="low_stock_threshold" class="form-control" 
                           placeholder="مثال: 5" 
                           value="<?= clean($currentThreshold) ?>">
                    <small class="text-muted mt-8" style="display:block;">سيصلك إشعار إذا قل مخزون أي منتج متصل عن هذا الرقم.</small>
                </div>
            </div>

            <div class="mt-16 text-end">
                <button type="submit" class="btn btn-primary btn-lg" id="submitSettings">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-danger">
    <div class="card-header bg-danger-light">
        <h3 class="card-title text-danger">المنطقة الخطرة (Danger Zone)</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-16">
            سيقوم هذا الإجراء بمسح كافة البيانات التشغيلية للنظام (المعاملات، الطلبات، المحادثات، السجلات). 
            <strong>لا يمكن التراجع عن هذا الإجراء.</strong> سيتم الاحتفاظ بحسابات الموظفين والإعدادات الحالية فقط.
        </p>
        <div id="resetAlert" class="alert hidden"></div>
        <button type="button" class="btn btn-danger" id="resetSystemBtn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
            </svg>
            تصفير بيانات النظام بالكامل
        </button>
    </div>
</div>

<script>
document.getElementById('settingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('submitSettings');
    const alertBox = document.getElementById('settingsAlert');
    const originalHTML = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = 'جارٍ الحفظ...';
    alertBox.className = 'alert hidden';

    const formData = new FormData(this);

    try {
        const res = await fetch('../api/update-settings.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success) {
            alertBox.textContent = data.message;
            alertBox.className = 'alert alert-success';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            alertBox.textContent = data.message || 'حدث خطأ غير معروف';
            alertBox.className = 'alert alert-error';
        }
    } catch (err) {
        alertBox.textContent = 'تعذر الاتصال بالخادم';
        alertBox.className = 'alert alert-error';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

// تصفير النظام
document.getElementById('resetSystemBtn').addEventListener('click', async function() {
    const btn = this;
    const alertBox = document.getElementById('resetAlert');
    const originalHTML = btn.innerHTML;

    // التأكيد الأول
    if (!confirm('هل أنت متأكد تماماً من رغبتك في تصفير كافة بيانات النظام؟\nسيتم مسح المعاملات والطلبات والمحادثات نهائياً.')) {
        return;
    }

    // التأكيد الثاني (كتابة عبارة)
    const confirmPhrase = prompt('لتأكيد عملية المسح النهائي، يرجى كتابة "مسح الكل" في الحقل أدناه:');
    if (confirmPhrase !== 'مسح الكل' && confirmPhrase !== 'CLEAR ALL') {
        if (confirmPhrase !== null) alert('العبارة غير صحيحة. تم إلغاء العملية.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'جارٍ التصفير...';
    alertBox.className = 'alert hidden';

    const formData = new FormData();
    formData.append('csrf_token', '<?= csrfToken() ?>');
    formData.append('confirm_phrase', confirmPhrase);

    try {
        const res = await fetch('../api/reset-system.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.success) {
            alertBox.textContent = data.message;
            alertBox.className = 'alert alert-success';
            setTimeout(() => window.location.reload(), 2000);
        } else {
            alertBox.textContent = data.message || 'حدث خطأ أثناء التصفير';
            alertBox.className = 'alert alert-error';
        }
    } catch (err) {
        alertBox.textContent = 'تعذر الاتصال بالخادم';
        alertBox.className = 'alert alert-error';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});
</script>

<?php require dirname(__DIR__) . '/components/footer.php'; ?>
