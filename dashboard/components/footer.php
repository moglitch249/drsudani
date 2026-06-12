    </div><!-- /.page-content -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- Toast Notifications Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Transaction Modal -->
<?php require_once BASE_PATH . '/components/modal-transaction.php'; ?>

<!-- Main JavaScript -->
<script src="<?= SITE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
<script src="<?= SITE_URL ?>/assets/js/notifications.js?v=<?= time() ?>"></script>

<?php if (hasPermission('can_chat') && basename($_SERVER['PHP_SELF']) !== 'chat.php'): ?>
<script>
(function() {
    let globalNotifLastId = null;
    function pollGlobalChatActivity() {
        var fd = new FormData(); fd.append('action', 'get_stats');
        fetch('<?= SITE_URL ?>/api/chat-agent.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                let badge = document.getElementById('globalChatBadge');
                if (badge) {
                    badge.textContent = d.data.waiting_count;
                    if (d.data.waiting_count > 0) badge.classList.remove('hidden');
                    else badge.classList.add('hidden');
                }
                let newest = d.data.newest_waiting;
                if (newest && newest.id != globalNotifLastId && globalNotifLastId !== null) {
                    if (window.showToast) {
                        window.showToast('طلب محادثة جديد', 'العميل: ' + (newest.customer_name || 'زائر'), 'info');
                    }
                    try {
                        let c = new(window.AudioContext||window.webkitAudioContext)();
                        [0,.2].forEach(function(t){let o=c.createOscillator(),g=c.createGain();o.connect(g);g.connect(c.destination);o.type='sine';o.frequency.value=880;g.gain.setValueAtTime(0,c.currentTime+t);g.gain.linearRampToValueAtTime(.45,c.currentTime+t+.05);g.gain.linearRampToValueAtTime(0,c.currentTime+t+.3);o.start(c.currentTime+t);o.stop(c.currentTime+t+.35);});
                    } catch(e) {}
                }
                if (newest) globalNotifLastId = newest.id;
                else if (globalNotifLastId === null) globalNotifLastId = 0;
            }
        }).catch(e => {});
    }
    setInterval(pollGlobalChatActivity, 5000);
    pollGlobalChatActivity();
})();
</script>
<?php endif; ?>
</body>
</html>
