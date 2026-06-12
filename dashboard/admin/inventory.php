<?php
/**
 * إدارة المخزون المتقدمة (Phase 17)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';

startSecureSession();
requireAdmin();

$db = db();
$pageTitle  = 'إدارة المنتجات';
$activePage = 'inventory';

$filterStatus   = $_GET['status'] ?? '';
$filterCategory = trim($_GET['category'] ?? '');
$search         = trim($_GET['search'] ?? '');
$sortBy         = $_GET['sort'] ?? 'stock';
$perPage        = 25;
$page           = max(1, intval_safe($_GET['page'] ?? 1));
$offset         = ($page - 1) * $perPage;

// الحصول على حد المخزون المنخفض العام
$threshold = (int)(getSetting($db, 'low_stock_threshold', '5') ?: 5);

// قائمة الفئات للفلتر
$categories = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// بناء الشروط
$conditions = [];
$params     = [];

if ($filterStatus === 'low_stock') {
    $conditions[] = 'stock_quantity IS NOT NULL AND stock_quantity <= COALESCE(low_stock_override, ?) AND stock_quantity > 0 AND stock_status != "outofstock"';
    $params[]     = $threshold;
} elseif ($filterStatus === 'outofstock') {
    $conditions[] = '(stock_status = "outofstock" OR (stock_quantity IS NOT NULL AND stock_quantity <= 0))';
} elseif ($filterStatus === 'instock') {
    $conditions[] = 'stock_status = "instock" AND (stock_quantity IS NULL OR stock_quantity > COALESCE(low_stock_override, ?))';
    $params[]     = $threshold;
}

if ($filterCategory !== '') {
    $conditions[] = 'category = ?';
    $params[]     = $filterCategory;
}

if ($search !== '') {
    $conditions[] = '(name LIKE ? OR wc_product_id = ?)';
    $params[] = "%$search%";
    $params[] = (int)$search;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$orderClause = match($sortBy) {
    'price_asc'  => 'ORDER BY price ASC',
    'price_desc' => 'ORDER BY price DESC',
    'qty_asc'    => 'ORDER BY stock_quantity ASC',
    'qty_desc'   => 'ORDER BY stock_quantity DESC',
    'name'       => 'ORDER BY name ASC',
    default      => 'ORDER BY stock_quantity ASC, updated_at DESC',
};

$countParams = $params;
$countStmt = $db->prepare("SELECT COUNT(*) FROM products $where");
$countStmt->execute($countParams);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $db->prepare("SELECT * FROM products $where $orderClause LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
$stmt->execute($params);
$products = $stmt->fetchAll();

$lastSync = $db->query("SELECT value FROM settings WHERE key_name='wc_products_last_sync'")->fetchColumn();

require_once dirname(__DIR__) . '/components/header.php';
require_once dirname(__DIR__) . '/components/sidebar-admin.php';
require_once dirname(__DIR__) . '/components/topbar.php';

function stockBadge($qty, $status, $threshold, $override) {
    $limit = $override ?? $threshold;
    if ($status === 'outofstock' || ($qty !== null && $qty <= 0)) {
        return '<span class="badge badge-danger">نفد المخزون</span>';
    }
    if ($qty !== null && $qty <= $limit) {
        return '<span class="badge badge-warning">منخفض ('.$qty.')</span>';
    }
    if ($status === 'onbackorder') {
        return '<span class="badge badge-info">طلب مسبق</span>';
    }
    return '<span class="badge badge-success">متوفر ' . ($qty !== null ? '('.$qty.')' : '∞') . '</span>';
}
?>

<!-- شريط الإجراءات العلوي -->
<div class="flex-between mb-16">
    <div>
        <p class="text-muted text-sm mt-0">إجمالي: <?= number_format($totalCount) ?> منتج — حد التنبيه العام: <?= $threshold ?> وحدة</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <?php if ($lastSync): ?>
            <span class="text-sm text-muted">آخر مزامنة: <?= formatDate($lastSync) ?></span>
        <?php endif; ?>
        <button class="btn btn-ghost btn-sm" id="exportBtn" onclick="exportSelected()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            تصدير CSV
        </button>
        <button class="btn btn-primary btn-sm" id="syncBtn" onclick="syncProducts()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
            </svg>
            مزامنة المنتجات
        </button>
    </div>
</div>

<div class="data-table-card">
    <!-- شريط الفلترة -->
    <div class="table-toolbar" style="gap:8px;flex-wrap:wrap;">
        <span class="table-title">المنتجات (<?= number_format($totalCount) ?>)</span>
        <div class="table-controls" style="flex-wrap:wrap;">
            <div class="search-box">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="بحث..." value="<?= clean($search) ?>" onchange="applyFilter('search', this.value)">
            </div>
            <select class="filter-select" onchange="applyFilter('status', this.value)">
                <option value="">كل الحالات</option>
                <option value="instock"    <?= $filterStatus==='instock'?'selected':'' ?>>متوفر</option>
                <option value="low_stock"  <?= $filterStatus==='low_stock'?'selected':'' ?>>منخفض</option>
                <option value="outofstock" <?= $filterStatus==='outofstock'?'selected':'' ?>>نفد</option>
            </select>
            <?php if (!empty($categories)): ?>
            <select class="filter-select" onchange="applyFilter('category', this.value)">
                <option value="">كل الفئات</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= clean($cat) ?>" <?= $filterCategory===$cat?'selected':'' ?>><?= clean($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select class="filter-select" onchange="applyFilter('sort', this.value)">
                <option value="stock"      <?= $sortBy==='stock'?'selected':'' ?>>ترتيب: المخزون ↑</option>
                <option value="qty_desc"   <?= $sortBy==='qty_desc'?'selected':'' ?>>ترتيب: المخزون ↓</option>
                <option value="price_asc"  <?= $sortBy==='price_asc'?'selected':'' ?>>ترتيب: السعر ↑</option>
                <option value="price_desc" <?= $sortBy==='price_desc'?'selected':'' ?>>ترتيب: السعر ↓</option>
                <option value="name"       <?= $sortBy==='name'?'selected':'' ?>>ترتيب: الاسم</option>
            </select>
        </div>
    </div>

    <!-- الجدول -->
    <div class="table-wrapper">
        <table id="productsTable">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                    <th>الصورة</th>
                    <th>المنتج</th>
                    <th>الفئة</th>
                    <th>السعر</th>
                    <th>المخزون</th>
                    <th>الحالة</th>
                    <th>آخر تحديث</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $prod): ?>
                <tr data-id="<?= $prod['id'] ?>" data-wc="<?= $prod['wc_product_id'] ?>">
                    <td><input type="checkbox" class="row-check" value="<?= $prod['id'] ?>"></td>
                    <td>
                        <?php if ($prod['image_url']): ?>
                            <img src="<?= clean($prod['image_url']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border);">
                        <?php else: ?>
                            <div style="width:44px;height:44px;border-radius:8px;background:var(--color-gray-100);display:flex;align-items:center;justify-content:center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-gray-400)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= clean($prod['name']) ?>">
                            <?= clean($prod['name']) ?>
                        </div>
                        <div class="text-xs text-muted">#<?= $prod['wc_product_id'] ?>
                            <?php if ($prod['product_url']): ?>
                                <a href="<?= clean($prod['product_url']) ?>" target="_blank" style="color:var(--color-primary);margin-right:4px;">↗</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($prod['internal_notes']): ?>
                            <div class="text-xs text-muted" style="font-style:italic;margin-top:2px;">📝 <?= clean(mb_substr($prod['internal_notes'], 0, 60)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm"><?= $prod['category'] ? clean($prod['category']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="fw-bold"><?= formatMoney((float)$prod['price']) ?></td>
                    <td class="fw-bold text-center">
                        <?php if ($prod['low_stock_override']): ?>
                            <span title="حد مخصص: <?= $prod['low_stock_override'] ?>" style="cursor:help;">
                        <?php endif; ?>
                        <?= $prod['stock_quantity'] !== null ? (int)$prod['stock_quantity'] : '<span class="text-muted">∞</span>' ?>
                        <?php if ($prod['low_stock_override']): ?>
                            <span class="text-xs text-muted">(⚙ <?= $prod['low_stock_override'] ?>)</span></span>
                        <?php endif; ?>
                    </td>
                    <td><?= stockBadge($prod['stock_quantity'], $prod['stock_status'], $threshold, $prod['low_stock_override'] ?? null) ?></td>
                    <td class="text-xs text-muted"><?= $prod['updated_at'] ? formatDate($prod['updated_at']) : '—' ?></td>
                    <td>
                        <button class="btn btn-ghost btn-sm"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                'id'                 => (int)$prod['id'],
                                'wc_product_id'     => (int)$prod['wc_product_id'],
                                'name'              => $prod['name'],
                                'price'             => (float)$prod['price'],
                                'stock_quantity'    => $prod['stock_quantity'],
                                'stock_status'      => $prod['stock_status'],
                                'internal_notes'    => $prod['internal_notes'] ?? '',
                                'low_stock_override'=> $prod['low_stock_override'],
                                'product_url'       => $prod['product_url'] ?? '',
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            تعديل
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:48px;">لا توجد منتجات. اضغط "مزامنة المنتجات" أولاً.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <?php if ($i===$page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&category=<?= urlencode($filterCategory) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sortBy) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ===== نافذة تعديل المنتج ===== -->
<div class="modal-overlay" id="editProductModal" onclick="if(this===event.target)closeEditModal()">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalProductName">تعديل المنتج</h3>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="editProductForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="product_id" id="ep_id">
                <input type="hidden" name="wc_product_id" id="ep_wc_id">

                <div class="form-row">
                    <div class="form-group">
                        <label for="ep_price">السعر</label>
                        <input type="number" step="0.01" min="0" id="ep_price" name="price" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="ep_qty">الكمية المتوفرة</label>
                        <input type="number" min="0" id="ep_qty" name="stock_quantity" class="form-control" placeholder="عدد الوحدات">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ep_status">حالة المخزون</label>
                        <select id="ep_status" name="stock_status" class="form-control">
                            <option value="instock">متوفر</option>
                            <option value="outofstock">نفد المخزون</option>
                            <option value="onbackorder">طلب مسبق</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ep_override">حد التنبيه المخصص <small class="text-muted">(اختياري)</small></label>
                        <input type="number" min="0" id="ep_override" name="low_stock_override" class="form-control" placeholder="اتركه فارغاً للحد العام (<?= $threshold ?>)">
                    </div>
                </div>

                <div class="form-group">
                    <label for="ep_notes">ملاحظات داخلية</label>
                    <textarea id="ep_notes" name="internal_notes" class="form-control" rows="3" placeholder="ملاحظات للإدارة فقط (لا تظهر للعملاء)..."></textarea>
                </div>

                <div class="form-group" style="background:var(--color-warning-light,#fffbe6);border:1px solid var(--color-warning);border-radius:10px;padding:12px;">
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="push_to_wc" id="ep_push_wc" value="1">
                        <span>تحديث على ووكوميرس أيضاً (يُرسل الكمية والسعر للمتجر)</span>
                    </label>
                    <small class="text-muted">تأكد من صحة مفاتيح WooCommerce API في الإعدادات قبل التفعيل.</small>
                </div>

                <div id="editAlert" class="alert hidden"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeEditModal()">إلغاء</button>
            <button class="btn btn-primary" id="saveProductBtn" onclick="saveProduct()">
                حفظ التعديلات
            </button>
        </div>
    </div>
</div>

<script>
// ── فلترة ──────────────────────────────────────
function applyFilter(key, val) {
    const url = new URL(window.location.href);
    url.searchParams.set(key, val);
    url.searchParams.set('page', '1');
    url.searchParams.delete('export');
    window.location = url.toString();
}

// ── تحديد الكل ─────────────────────────────────
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
}

// ── تصدير CSV ──────────────────────────────────
function exportSelected() {
    const checked = [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    if (checked.length) url.searchParams.set('selected_ids', checked.join(','));
    window.location = url.toString();
}

// ── مزامنة المنتجات (pagination) ───────────────
async function syncProducts(page = 1) {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner" style="border-color:rgba(255,255,255,0.3);border-top-color:white;"></span> صفحة ${page}...`;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const fd = new FormData();
        fd.append('page', page);
        fd.append('csrf_token', csrfToken);

        const res  = await fetch('../api/wc-products-sync.php', { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrfToken } });
        const data = await res.json();

        if (data.success) {
            if (data.data?.has_more) {
                await syncProducts(data.data.next_page);
            } else {
                window.showToast?.('تمت المزامنة ✓', 'تم جلب جميع المنتجات بنجاح.', 'success');
                setTimeout(() => window.location.reload(), 1800);
            }
        } else {
            window.showToast?.('خطأ', data.message, 'error');
            resetSyncBtn();
        }
    } catch(e) {
        console.error(e);
        window.showToast?.('خطأ في الاتصال', e.message, 'error');
        resetSyncBtn();
    }
}
function resetSyncBtn() {
    const btn = document.getElementById('syncBtn');
    btn.disabled = false;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg> مزامنة المنتجات';
}

// ── Modal تعديل المنتج ──────────────────────────
function openEditModal(prod) {
    document.getElementById('modalProductName').textContent = prod.name;
    document.getElementById('ep_id').value            = prod.id;
    document.getElementById('ep_wc_id').value         = prod.wc_product_id;
    document.getElementById('ep_price').value         = prod.price;
    document.getElementById('ep_qty').value           = prod.stock_quantity ?? '';
    document.getElementById('ep_status').value        = prod.stock_status;
    document.getElementById('ep_override').value      = prod.low_stock_override ?? '';
    document.getElementById('ep_notes').value         = prod.internal_notes ?? '';
    document.getElementById('ep_push_wc').checked     = false;
    document.getElementById('editAlert').className    = 'alert hidden';
    document.getElementById('editProductModal').classList.add('open');
    // تحديد كل النص في حقل السعر تلقائياً لسهولة التعديل
    setTimeout(() => {
        const priceField = document.getElementById('ep_price');
        priceField.focus();
        priceField.select();
    }, 150);
}

function closeEditModal() {
    document.getElementById('editProductModal').classList.remove('open');
}

async function saveProduct() {
    const btn   = document.getElementById('saveProductBtn');
    const alert = document.getElementById('editAlert');
    btn.disabled = true;
    btn.textContent = 'جارٍ الحفظ...';
    alert.className = 'alert hidden';

    const fd = new FormData(document.getElementById('editProductForm'));

    try {
        const res  = await fetch('../api/update-product.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            alert.textContent = data.message + (data.data?.wc_synced ? ' ✓ WooCommerce' : '');
            alert.className   = 'alert alert-success';
            setTimeout(() => { closeEditModal(); window.location.reload(); }, 1500);
        } else {
            alert.textContent = data.message;
            alert.className   = 'alert alert-error';
        }
    } catch(e) {
        alert.textContent = 'تعذر الاتصال بالخادم';
        alert.className   = 'alert alert-error';
    } finally {
        btn.disabled = false;
        btn.textContent = 'حفظ التعديلات';
    }
}

// ESC to close modal
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeEditModal(); });
</script>

<?php
// ── تصدير CSV ──────────────────────────────────
if (isset($_GET['export'])) {
    $selectedIds = [];
    if (!empty($_GET['selected_ids'])) {
        $selectedIds = array_filter(array_map('intval', explode(',', $_GET['selected_ids'])));
    }

    if (!empty($selectedIds)) {
        $in  = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmtExport = $db->prepare("SELECT * FROM products WHERE id IN ($in)");
        $stmtExport->execute($selectedIds);
    } else {
        $stmtExport = $db->prepare("SELECT * FROM products $where $orderClause");
        $stmtExport->execute($params);
    }

    $rows = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID المنتج', 'الاسم', 'الفئة', 'السعر', 'الكمية', 'الحالة', 'رابط المنتج', 'ملاحظات', 'آخر تحديث']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['wc_product_id'], $row['name'], $row['category'] ?? '',
            $row['price'], $row['stock_quantity'] ?? 'غير محدود', $row['stock_status'],
            $row['product_url'] ?? '', $row['internal_notes'] ?? '', $row['updated_at']
        ]);
    }
    fclose($out);
    exit;
}

require_once dirname(__DIR__) . '/components/footer.php';
?>
