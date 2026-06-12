<?php
/**
 * نافذة منبثقة لتعديل حالة الطلب
 */
?>
<div class="modal-overlay" id="orderModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="orderModalTitle">تعديل الطلب</h3>
            <button class="modal-close" onclick="closeOrderModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="orderForm" onsubmit="submitOrderForm(event)">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="order_id" id="o_order_id">

            <div class="modal-user-info" style="margin-bottom: 20px;">
                <div class="mu-avatar" id="o_avatar" style="background:var(--color-primary-dark)">ط</div>
                <div>
                    <div class="mu-name" id="o_customer_name">-</div>
                    <div class="mu-email" id="o_customer_email" style="direction: ltr; text-align: right;">-</div>
                </div>
            </div>

            <div class="modal-body">
                <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                    <div style="flex:1;">
                        <label class="form-label text-sm text-muted">إجمالي الطلب</label>
                        <div class="fw-bold" id="o_total" style="font-size: 1.1rem; color: var(--color-gray-800);">-</div>
                    </div>
                    <div style="flex:1;">
                        <label class="form-label text-sm text-muted">طريقة الدفع</label>
                        <div class="fw-semibold" id="o_method" style="font-size: 0.95rem; color: var(--color-gray-700);">-</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">تفاصيل المنتجات</label>
                    <div id="o_products_list" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; max-height:200px; overflow-y:auto;">
                        <span class="text-muted text-sm">جاري التحميل...</span>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label">تحديث حالة الطلب</label>
                    <select class="form-control" name="status" id="o_status" required>
                        <option value="pending">معلق (Pending)</option>
                        <option value="processing">قيد المعالجة (Processing)</option>
                        <option value="on-hold">قيد الانتظار (On-Hold)</option>
                        <option value="completed">مكتمل (Completed)</option>
                        <option value="cancelled">ملغي (Cancelled)</option>
                        <option value="refunded">مسترد (Refunded)</option>
                    </select>
                    <small class="text-muted mt-2 d-block">سيتم مزامنة وتغيير الحالة فوراً في متجر ووكوميرس المربوط.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary" id="orderSubmitBtn">
                    حفظ ومزامنة
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openOrderModal(orderId, name, email, total, method, status, itemsJsonBase64 = '') {
    document.getElementById('o_order_id').value = orderId;
    document.getElementById('orderModalTitle').textContent = 'إدارة الطلب #' + orderId;
    
    document.getElementById('o_customer_name').textContent = name || 'غير محدد';
    document.getElementById('o_customer_email').textContent = email || '—';
    document.getElementById('o_avatar').textContent = name ? name.charAt(0).toUpperCase() : 'ط';
    
    document.getElementById('o_total').textContent = total;
    document.getElementById('o_method').textContent = method || '—';
    
    // Render products
    const productsContainer = document.getElementById('o_products_list');
    productsContainer.innerHTML = '';
    try {
        if (itemsJsonBase64) {
            const decodedStr = atob(itemsJsonBase64);
            const items = JSON.parse(decodedStr);
            if (items && items.length > 0) {
                let html = '<ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:8px;">';
                items.forEach(item => {
                    const price = item.total ? parseFloat(item.total).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '0.00';
                    html += `
                        <li style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                            <div style="display:flex; flex-direction:column;">
                                <span class="fw-semibold text-sm" style="color:var(--color-gray-800);">${item.name}</span>
                                <span class="text-xs text-muted">الكمية: ${item.quantity}</span>
                            </div>
                            <span class="fw-bold text-sm" style="color:var(--color-primary-dark);">${price}</span>
                        </li>
                    `;
                });
                html += '</ul>';
                productsContainer.innerHTML = html;
            } else {
                productsContainer.innerHTML = '<span class="text-muted text-sm">لا توجد منتجات مسجلة.</span>';
            }
        } else {
            productsContainer.innerHTML = '<span class="text-muted text-sm">لا توجد بيانات تفصيلية (تم جلبها في نسخة سابقة). يرجى الضغط على مزامنة الطلبات لجلبها.</span>';
        }
    } catch (e) {
        console.error("Error parsing items JSON", e);
        productsContainer.innerHTML = '<span class="text-danger text-sm">خطأ في قراءة بيانات المنتجات.</span>';
    }
    
    const statusSelect = document.getElementById('o_status');
    for (let i = 0; i < statusSelect.options.length; i++) {
        if (statusSelect.options[i].value === status) {
            statusSelect.selectedIndex = i;
            break;
        }
    }

    document.getElementById('orderModal').classList.add('open');
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('open');
}

async function submitOrderForm(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('orderSubmitBtn');
    
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span> جاري الحفظ...';

    const formData = new FormData(form);

    try {
        const res = await fetch('../api/update-order.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.success) {
            window.showToast?.('نجاح', data.message, 'success');
            closeOrderModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.showToast?.('خطأ', data.message || 'حدث خطأ غير متوقع', 'error');
        }
    } catch (err) {
        window.showToast?.('خطأ', 'تعذر الاتصال بالخادم', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>
