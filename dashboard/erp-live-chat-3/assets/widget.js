document.addEventListener('DOMContentLoaded', () => {
    const config = window.ErpChatConfig || { apiUrl: '', title: 'تحدث معنا' };
    
    const widget = document.getElementById('erp-chat-widget');
    const toggleBtn = document.getElementById('erpChatToggle');
    const titleText = document.getElementById('erpChatTitleText');
    const badge = document.getElementById('erpChatQueueStatus');
    
    const initForm = document.getElementById('erpChatInitForm');
    const msgArea = document.getElementById('erpChatMessagesArea');
    const messages = document.getElementById('erpChatMessages');
    const footer = document.getElementById('erpChatFooter');
    
    const nameInput = document.getElementById('erpChatName');
    const emailInput = document.getElementById('erpChatEmail');
    const startBtn = document.getElementById('erpChatStartBtn');
    
    const msgInput = document.getElementById('erpChatMessageInput');
    const sendBtn = document.getElementById('erpChatSendBtn');

    if(titleText) titleText.textContent = config.title;

    let sessionToken = localStorage.getItem('erp_chat_token');
    let pollInterval = null;
    let lastMsgId = 0;
    
    // Toggle Window
    toggleBtn.addEventListener('click', () => {
        widget.classList.toggle('erp-chat-open');
        if (widget.classList.contains('erp-chat-open')) {
            if (sessionToken) {
                // Restore session view
                initForm.style.display = 'none';
                msgArea.style.display = 'block';
                startPolling();
                scrollToBottom();
            } else {
                initForm.style.display = 'flex';
                msgArea.style.display = 'none';
                footer.style.display = 'none';
            }
        }
    });

    // Start Chat
    startBtn.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        
        if(!name) {
            alert('يرجى إدخال اسمك'); return;
        }

        startBtn.textContent = 'جاري الاتصال...';
        startBtn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'start_session');
            fd.append('name', name);
            fd.append('email', email);

            const res = await fetch(config.apiUrl, { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                sessionToken = data.token;
                localStorage.setItem('erp_chat_token', sessionToken);
                
                initForm.style.display = 'none';
                msgArea.style.display = 'block';
                messages.innerHTML = '';
                
                startPolling();
            } else {
                alert(data.message || 'حدث خطأ');
            }
        } catch(e) {
            console.error(e);
            alert('تعذر الاتصال بالخادم');
        } finally {
            startBtn.textContent = 'بدء المحادثة';
            startBtn.disabled = false;
        }
    });

    // Send Message
    async function sendMessage() {
        const msg = msgInput.value.trim();
        if(!msg || !sessionToken) return;

        msgInput.value = '';
        appendMessage({ sender_type: 'customer', message: msg, created_at: new Date().toISOString() });
        
        try {
            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('token', sessionToken);
            fd.append('message', msg);
            await fetch(config.apiUrl, { method: 'POST', body: fd });
        } catch(e) {
            console.error(e);
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    msgInput.addEventListener('keypress', (e) => {
        if(e.key === 'Enter') sendMessage();
    });

    function startPolling() {
        if(pollInterval) clearInterval(pollInterval);
        pollMessages(); // Init
        pollInterval = setInterval(pollMessages, 3000);
    }

    async function pollMessages() {
        if(!sessionToken) return;

        try {
            const fd = new FormData();
            fd.append('action', 'poll');
            fd.append('token', sessionToken);
            fd.append('last_id', lastMsgId);

            const res = await fetch(config.apiUrl, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                const status = data.status;
                
                if (status === 'waiting') {
                    footer.style.display = 'none';
                    let qpos = data.queue_position;
                    badge.style.display = 'inline-block';
                    badge.textContent = `دورك: ${qpos}`;
                    
                    if (!document.getElementById('sysWaitMsg')) {
                        const div = document.createElement('div');
                        div.id = 'sysWaitMsg';
                        div.className = 'erp-chat-sys-msg';
                        div.textContent = `جاري انتظار الرد من وكيل... أنت رقم ${qpos} في الطابور`;
                        messages.appendChild(div);
                    } else {
                        document.getElementById('sysWaitMsg').textContent = `جاري انتظار الرد من وكيل... أنت رقم ${qpos} في الطابور`;
                    }
                } else if (status === 'active') {
                    footer.style.display = 'flex';
                    badge.style.display = 'none';
                    const waitMsg = document.getElementById('sysWaitMsg');
                    if(waitMsg) waitMsg.remove();
                } else if (status === 'closed') {
                    footer.style.display = 'none';
                    badge.style.display = 'inline-block';
                    badge.textContent = 'مغلقة';
                    badge.style.background = '#94a3b8';
                    clearInterval(pollInterval);
                }

                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => {
                        if (m.id > lastMsgId) {
                            lastMsgId = m.id;
                            appendMessage(m);
                        }
                    });
                }
            } else if (data.message === 'Session not found') {
                // Reset
                localStorage.removeItem('erp_chat_token');
                sessionToken = null;
                clearInterval(pollInterval);
                initForm.style.display = 'flex';
                msgArea.style.display = 'none';
                footer.style.display = 'none';
            }
        } catch(e) {
            console.error(e);
        }
    }

    function appendMessage(msg) {
        const div = document.createElement('div');
        div.className = `erp-chat-msg ${msg.sender_type}`;
        
        let tStr = '';
        if(msg.created_at) {
            const d = new Date(msg.created_at);
            tStr = `${d.getHours()}:${String(d.getMinutes()).padStart(2, '0')}`;
        }

        div.innerHTML = `
            ${msg.message.replace(/\n/g, '<br>')}
            <span class="erp-chat-msg-time">${tStr}</span>
        `;
        messages.appendChild(div);
        scrollToBottom();
    }

    function scrollToBottom() {
        msgArea.scrollTop = msgArea.scrollHeight;
    }
});
