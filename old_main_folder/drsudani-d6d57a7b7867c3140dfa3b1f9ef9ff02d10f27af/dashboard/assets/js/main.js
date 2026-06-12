/**
 * main.js — الجافاسكريبت الرئيسي
 */

'use strict';

// ── CSRF Token ──────────────────────────────────────────────
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Sidebar Toggle ──────────────────────────────────────────
const sidebar      = document.getElementById('sidebar');
const mainContent  = document.querySelector('.main-content');
const sidebarToggle = document.getElementById('sidebarToggle');
const mobileToggle  = document.getElementById('mobileToggle');

sidebarToggle?.addEventListener('click', () => {
    if (window.innerWidth <= 900) {
        sidebar?.classList.remove('mobile-open');
    } else {
        sidebar?.classList.toggle('collapsed');
        mainContent?.classList.toggle('expanded');
        localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('collapsed') ? '1' : '0');
    }
});

mobileToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('mobile-open');
});

// حفظ حالة الشريط الجانبي
if (localStorage.getItem('sidebarCollapsed') === '1') {
    sidebar?.classList.add('collapsed');
    mainContent?.classList.add('expanded');
}

// إغلاق الشريط الجانبي عند النقر خارجه (موبايل)
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 900 &&
        sidebar?.classList.contains('mobile-open') &&
        !sidebar.contains(e.target) &&
        e.target !== mobileToggle) {
        sidebar.classList.remove('mobile-open');
    }
});

// ── Notification Dropdown ────────────────────────────────────
const notifBtn      = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
const notifWrapper  = document.getElementById('notifWrapper');

notifBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDropdown?.classList.toggle('open');
});

document.addEventListener('click', (e) => {
    if (!notifWrapper?.contains(e.target)) {
        notifDropdown?.classList.remove('open');
    }
});

document.getElementById('markAllRead')?.addEventListener('click', () => {
    fetch('../api/poll.php?action=mark_all_read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
    }).then(() => {
        document.getElementById('notifBadge')?.classList.add('hidden');
        document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    });
});

// ── Modal Transaction ────────────────────────────────────────
const modal = document.getElementById('transactionModal');

function openTransactionModal(userId, userName, userEmail) {
    if (!modal) return;

    document.getElementById('modalUserId').value    = userId || '';
    document.getElementById('modalCsrf').value      = CSRF_TOKEN;
    document.getElementById('modalUserName').textContent  = userName  || '';
    document.getElementById('modalUserEmail').textContent = userEmail || '';
    document.getElementById('modalUserAvatar').textContent = (userName || 'م').charAt(0);

    if (userId) {
        document.getElementById('modalUserInfo').style.display = 'flex';
    }

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // إعادة ضبط النموذج
    document.getElementById('transactionForm').reset();
    document.getElementById('receiptPreview').style.display = 'none';
    document.getElementById('fileUploadPlaceholder').style.display = 'flex';
    document.getElementById('modalError').style.display   = 'none';
    document.getElementById('modalSuccess').style.display = 'none';
}

function closeTransactionModal() {
    modal?.classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('closeModal')?.addEventListener('click',  closeTransactionModal);
document.getElementById('cancelModal')?.addEventListener('click', closeTransactionModal);

modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeTransactionModal();
});

// رفع الصورة بالسحب والإفلات
const fileUploadArea = document.getElementById('fileUploadArea');
const txReceipt      = document.getElementById('txReceipt');

fileUploadArea?.addEventListener('click', () => txReceipt?.click());

fileUploadArea?.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.classList.add('drag-over');
});

fileUploadArea?.addEventListener('dragleave', () => {
    fileUploadArea.classList.remove('drag-over');
});

fileUploadArea?.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleReceiptFile(file);
});

txReceipt?.addEventListener('change', function () {
    if (this.files[0]) handleReceiptFile(this.files[0]);
});

function handleReceiptFile(file) {
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        showModalError('نوع الملف غير مسموح. استخدم JPG أو PNG أو WebP.');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showModalError('حجم الملف يتجاوز 2 ميجابايت.');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const preview = document.getElementById('receiptPreview');
        preview.src = e.target.result;
        preview.style.display = 'block';
        document.getElementById('fileUploadPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

// إرسال نموذج المعاملة
document.getElementById('transactionForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const btn = document.getElementById('submitTransaction');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> جارٍ الحفظ...';

    const formData = new FormData(this);

    try {
        const res  = await fetch('../api/add-transaction.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            showModalSuccess(data.message || 'تمت إضافة المعاملة بنجاح.');
            setTimeout(() => {
                closeTransactionModal();
                window.location.reload();
            }, 1400);
        } else {
            showModalError(data.message || 'حدث خطأ. حاول مرة أخرى.');
        }
    } catch {
        showModalError('خطأ في الاتصال بالخادم.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

function showModalError(msg) {
    const el = document.getElementById('modalError');
    el.textContent = msg;
    el.style.display = 'flex';
    document.getElementById('modalSuccess').style.display = 'none';
}

function showModalSuccess(msg) {
    const el = document.getElementById('modalSuccess');
    el.textContent = msg;
    el.style.display = 'flex';
    document.getElementById('modalError').style.display = 'none';
}

// ── Search & Filter Tables ───────────────────────────────────
document.querySelectorAll('[data-search-table]').forEach(input => {
    const tableId = input.dataset.searchTable;
    const table   = document.getElementById(tableId);
    if (!table) return;

    input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

// تصدير: فتح مودال المعاملة من أي زر في الصفحة
window.openTransactionModal = openTransactionModal;

// ── Dark Mode Toggle ──────────────────────────────────────────
const themeToggleBtn = document.getElementById('themeToggleBtn');
if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('dark-theme');
        const isDark = document.body.classList.contains('dark-theme');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
}
