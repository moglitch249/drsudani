/**
 * notifications.js — نظام الإشعارات الفوري (AJAX Polling)
 * يعمل في الخلفية كل 10 ثوانٍ
 */

'use strict';

// ── صوت الإشعار (Base64 MP3 Beep) ───────────────────────────
const BEEP_SOUND = 'data:audio/mp3;base64,//uQxAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAAFAAAGhgBVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqr///////////////////////////////////////////8AAAA5TEFNRTMuOTlyBKwAAAAAAAAAABSAJAKGQgAAgAAABoYzPc9UAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//uQxAADgAABpAAAACAAADSAAAAETEFNRTMuOTmqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqTEFNRTMuOTmqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq';

let audioCtx = null;

function playBeep() {
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();

        const oscillator = audioCtx.createOscillator();
        const gainNode   = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.type      = 'sine';
        oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(440, audioCtx.currentTime + 0.1);

        gainNode.gain.setValueAtTime(0.4, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);

        oscillator.start(audioCtx.currentTime);
        oscillator.stop(audioCtx.currentTime + 0.35);
    } catch (e) {
        // صوت غير مدعوم
    }
}

// ── Toast Notifications ──────────────────────────────────────
const toastContainer = document.getElementById('toastContainer');

const TOAST_ICONS = {
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    error:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    order:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
};

function showToast(title, body = '', type = 'info', withSound = false) {
    if (!toastContainer) return;

    if (withSound) playBeep();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${TOAST_ICONS[type] || TOAST_ICONS.info}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            ${body ? `<div class="toast-body">${body}</div>` : ''}
        </div>
        <button class="toast-close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <div class="toast-progress"></div>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => dismissToast(toast));

    toastContainer.appendChild(toast);

    // إخفاء تلقائي بعد 5 ثوانٍ
    setTimeout(() => dismissToast(toast), 5000);
}

function dismissToast(toast) {
    if (toast.classList.contains('hiding')) return;
    toast.classList.add('hiding');
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
    setTimeout(() => toast.remove(), 400);
}

// ── AJAX Polling ─────────────────────────────────────────────
let lastNotifId = 0;

async function pollNotifications() {
    try {
        const res  = await fetch(`../api/poll.php?last_id=${lastNotifId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) return;

        const data = await res.json();
        if (!data.success) return;

        const isFirstPoll = (lastNotifId === 0);
        lastNotifId = data.last_id ?? lastNotifId;

        // تحديث عداد الإشعارات
        if (data.unread_count !== undefined) {
            const badge = document.getElementById('notifBadge');
            if (badge) {
                badge.textContent = data.unread_count;
                badge.classList.toggle('hidden', data.unread_count === 0);
            }
        }

        // عرض الإشعارات الجديدة
        if (!isFirstPoll && Array.isArray(data.notifications) && data.notifications.length > 0) {
            data.notifications.forEach(item => {
                const type = item.type === 'new_order' ? 'order' : 'success';
                showToast(item.title, item.body || '', type, true);
                appendNotifToDropdown(item);
            });
        }
    } catch {
        // تجاهل أخطاء الشبكة المؤقتة
    }
}

function appendNotifToDropdown(item) {
    const list = document.getElementById('notifList');
    if (!list) return;

    // إزالة رسالة "لا توجد إشعارات"
    const empty = list.querySelector('.notif-empty');
    if (empty) empty.remove();

    const iconClass = item.type === 'new_order' ? 'order' : 'transaction';
    
    // تحديد الرابط
    const isAdmin = window.location.pathname.includes('/admin/');
    let link = '#';
    if (iconClass === 'order') {
        link = isAdmin ? '../admin/orders.php' : '../agent/orders.php';
    } else {
        link = isAdmin ? '../admin/transactions.php' : '../agent/transactions.php';
    }

    const notifEl   = document.createElement('a');
    notifEl.href = link;
    notifEl.className = 'notif-item unread';
    notifEl.style.textDecoration = 'none';
    notifEl.style.color = 'inherit';
    notifEl.innerHTML = `
        <div class="notif-icon ${iconClass}">
            ${iconClass === 'order' ? TOAST_ICONS.order : TOAST_ICONS.success}
        </div>
        <div class="notif-body">
            <div class="notif-title">${item.title}</div>
            ${item.body ? `<div class="notif-text">${item.body}</div>` : ''}
            <div class="notif-time">الآن</div>
        </div>
    `;

    list.insertBefore(notifEl, list.firstChild);
}

// ── Mark All as Read ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const markBtn = document.getElementById('markAllRead');
    if (markBtn) {
        markBtn.addEventListener('click', async () => {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('../api/mark-read.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    }
                });
                const data = await res.json();
                if (data.success) {
                    const badge = document.getElementById('notifBadge');
                    if (badge) {
                        badge.textContent = '0';
                        badge.classList.add('hidden');
                    }
                    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                }
            } catch (err) {}
        });
    }
});

// بدء الـ polling بعد 3 ثوانٍ من تحميل الصفحة، ثم كل 10 ثوانٍ
setTimeout(() => {
    pollNotifications();
    setInterval(pollNotifications, 10000);
}, 3000);

// تصدير
window.showToast = showToast;
window.playBeep  = playBeep;
