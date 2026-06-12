<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/helpers.php';
startSecureSession();
requireAgent(); // Must be logged in as agent or admin


// Only admin or agents with can_chat permission can access this page
if (!hasPermission('can_chat')) {
    http_response_code(403);
    echo '<h2>ليس لديك صلاحية الوصول لهذه الصفحة</h2>';
    exit;
}

$pageTitle = 'المحادثات المباشرة';
$activePage = 'chat';
$isAdmin = ($_SESSION['role'] === 'admin');

require_once dirname(__DIR__) . '/components/header.php';
if ($isAdmin) {
    require_once dirname(__DIR__) . '/components/sidebar-admin.php';
}
else {
    require_once dirname(__DIR__) . '/components/sidebar-agent.php';
}
require_once dirname(__DIR__) . '/components/topbar.php';
?>

<style>
.cc{display:flex;height:calc(100vh - 160px);background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;overflow:hidden}
.cc-sb{width:310px;border-left:1px solid var(--color-border);display:flex;flex-direction:column;background:var(--color-bg);flex-shrink:0}
.cc-tabs{display:flex;border-bottom:1px solid var(--color-border);background:var(--color-bg)}
.cc-tab{flex:1;padding:13px 2px;text-align:center;background:transparent;border:none;border-bottom:2px solid transparent;cursor:pointer;font-family:inherit;font-weight:600;color:var(--color-text-muted);font-size:12px;transition:color .2s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cc-tab.active{color:var(--color-primary);border-bottom-color:var(--color-primary)}
.cc-list{flex:1;overflow-y:auto;padding:8px}
.ci{padding:14px;border-radius:12px;cursor:pointer;margin-bottom:10px;border:1px solid #e2e8f0;border-right:3px solid transparent;background:#ffffff;box-shadow:0 2px 6px rgba(0,0,0,.03);transition:all .2s;position:relative;overflow:hidden}
.dark-theme .ci{background:#1e293b;border-color:#334155;box-shadow:0 3px 8px rgba(0,0,0,.2)}
.ci:hover{border-color:var(--color-primary-light);background:var(--color-gray-50);transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.dark-theme .ci:hover{background:#0f172a;border-color:var(--color-primary);box-shadow:0 4px 14px rgba(0,0,0,.4)}
.ci.sel{border-color:var(--color-primary);border-right-color:var(--color-primary);background:#f0f9ff}
.dark-theme .ci.sel{background:#0f172a;border-right-color:var(--color-primary);border-color:var(--color-primary)}

/* Transferred badge */
.badge-transferred {background:#fdf6e3;color:#b45309;font-size:10.5px;padding:3px 8px;border-radius:6px;font-weight:700;border:1px solid #fde68a;margin-right:10px;vertical-align:middle;line-height:1;display:inline-block}
.dark-theme .badge-transferred {background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.4);color:#fcd34d}
.ci-nm{font-weight:700;font-size:14px;color:var(--color-text);display:flex;align-items:center}
.ci-sub{font-size:12px;color:var(--color-text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ci-time{font-size:11px;color:var(--color-text-muted);font-weight:600}

/* Main */
.cc-main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.cc-hd{padding:12px 18px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:12px;flex-shrink:0}
.cc-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#89CFF0,#5ab8f0);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#fff;flex-shrink:0}
.cc-info{flex:1;min-width:0}
.cc-name{font-weight:700;font-size:15px}
.cc-meta{font-size:12px;color:var(--color-text-muted);display:flex;flex-wrap:wrap;gap:8px;margin-top:2px}
.cc-actions{display:flex;gap:6px;flex-shrink:0}
.cc-desc{padding:9px 18px;font-size:13px;color:#78350f;background:linear-gradient(135deg,#fef9c3,#fef3c7);border-bottom:1px solid #fde68a;display:none;line-height:1.4}
.dark-theme .cc-desc{background:rgba(245,158,11,.1);color:#fbbf24;border-color:rgba(245,158,11,.3)}
.cc-desc strong{margin-left:6px}

/* Messages */
.cc-msgs{flex:1;padding:18px 16px;overflow-y:auto;display:flex;flex-direction:column;gap:12px;background:var(--color-gray-50)}
.dark-theme .cc-msgs{background:var(--color-bg)}
.m-wrap{display:flex;gap:8px;align-items:flex-end}
.m-wrap.out{flex-direction:row-reverse}
.m-av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;text-transform:uppercase}
.m-av.ag{background:linear-gradient(135deg,#89CFF0,#5ab8f0)}
.m-av.cu{background:linear-gradient(135deg,#94a3b8,#64748b)}
.m-av.sy{background:#e2e8f0;color:#64748b}
.m-blk{display:flex;flex-direction:column;max-width:70%}
.m-nm{font-size:11px;color:var(--color-text-muted);font-weight:600;margin-bottom:3px}
.m-wrap.out .m-nm{text-align:left}
.m-bub{padding:9px 14px;border-radius:14px;font-size:14px;line-height:1.55;word-break:break-word}
.m-bub.in{background:var(--color-surface);border:1px solid var(--color-border);border-bottom-right-radius:3px;color:var(--color-text);box-shadow:0 1px 3px rgba(0,0,0,.06)}
.m-bub.out{background:linear-gradient(135deg,#5ab8f0,#89CFF0);color:#fff;border-bottom-left-radius:3px}
.m-bub.sys{background:linear-gradient(135deg,#fef9c3,#fde047);border:1px dashed #eab308;color:#854d0e;border-radius:12px;font-size:12px;font-weight:700;text-align:center;padding:7px 16px;margin:8px auto;display:inline-block;box-shadow:0 3px 6px rgba(234,179,8,.15)}
.dark-theme .m-bub.sys{background:rgba(234,179,8,.15);border-color:rgba(234,179,8,.3);color:#fde047;box-shadow:none}
.m-bub img{max-width:240px;min-width:60px;min-height:40px;border-radius:9px;display:block;cursor:zoom-in;background:rgba(0,0,0,0.05);object-fit:cover}
.m-time{font-size:10.5px;color:var(--color-text-muted);margin-top:3px}
.m-wrap.out .m-time{text-align:right}
.sys-row{text-align:center}

/* Typing */
.typing-ind{display:none;align-items:center;gap:8px;padding:2px 0}
.typing-dots{display:flex;gap:4px}
.typing-dots span{width:7px;height:7px;border-radius:50%;background:var(--color-primary);animation:tdot .8s infinite}
.typing-dots span:nth-child(2){animation-delay:.15s}
.typing-dots span:nth-child(3){animation-delay:.3s}
@keyframes tdot{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-5px)}}

/* Input */
.cc-inp-area{padding:10px 14px;border-top:1px solid var(--color-border);background:var(--color-surface);display:flex;flex-direction:column;gap:7px;flex-shrink:0}
.cc-inp-row{display:flex;gap:8px;align-items:center}
.cc-inp{flex:1;padding:9px 16px;border:1.5px solid var(--color-border);border-radius:22px;background:var(--color-bg);color:var(--color-text);outline:none;font-family:inherit;font-size:14px;transition:border .2s}
.cc-inp:focus{border-color:var(--color-primary)}
.cc-btn{width:40px;height:40px;border-radius:50%;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .15s;flex-shrink:0}
.cc-btn:hover{transform:scale(1.06)}
.cc-btn.send{background:var(--color-primary);color:#fff}
.cc-btn.img{background:var(--color-gray-200);color:var(--color-text-muted)}
.dark-theme .cc-btn.img{background:var(--color-gray-700)}
#agImgPrev{display:none;align-items:center;gap:8px;background:var(--color-gray-100);border-radius:8px;padding:7px 12px;font-size:13px;color:var(--color-text-muted)}
.dark-theme #agImgPrev{background:var(--color-gray-800)}
#agImgPrev img{height:42px;border-radius:6px}
#agImgRm{background:none;border:none;color:#ef4444;cursor:pointer;font-size:18px;padding:0 3px}
#agFileIn{display:none}
.tpl-btn{padding:8px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text);font-size:12px;cursor:pointer;text-align:center;transition:all .15s}
.tpl-btn:hover{background:var(--color-primary-light);border-color:var(--color-primary)}
.dark-theme .tpl-btn:hover{background:rgba(89,184,240,.1)}
.quick-tx-box{width:320px !important}

/* Emojis */
#agEmojiPop{position:absolute;bottom:calc(100% + 10px);right:14px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.15);padding:10px;width:280px;max-height:260px;overflow-y:auto;display:none;grid-template-columns:repeat(7,1fr);gap:4px;z-index:100}
#agEmojiPop::-webkit-scrollbar{width:4px}
#agEmojiPop::-webkit-scrollbar-thumb{background:var(--color-border);border-radius:4px}
.ag-emj{cursor:pointer;text-align:center;font-size:22px;padding:4px;border-radius:6px;transition:background .15s;user-select:none}
.ag-emj:hover{background:var(--color-gray-100)}
.dark-theme .ag-emj:hover{background:var(--color-gray-800)}

/* Empty */
.cc-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--color-text-muted);gap:12px}
.cc-empty svg{opacity:.2}
.cc-empty p{font-size:14px;margin:0}

/* Notif */
#chatNotif{position:fixed;top:18px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:13px 20px;border-radius:13px;box-shadow:0 8px 30px rgba(0,0,0,.3);z-index:9999;display:none;align-items:center;gap:12px;min-width:300px;max-width:420px;animation:notifIn .35s ease}
@keyframes notifIn{from{opacity:0;top:0}to{opacity:1;top:18px}}
#chatNotif svg{stroke:#89CFF0;flex-shrink:0;fill:none;stroke-width:2}
.notif-txt strong{display:block;color:#89CFF0;font-size:14px;margin-bottom:1px}
.notif-txt span{font-size:12.5px;color:#cbd5e1}

/* Transfer Modal */
#transferModal{position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:99999;display:none;align-items:center;justify-content:center}
#transferModal.show{display:flex}
.transfer-box{background:#ffffff;border-radius:18px;padding:24px;width:380px;max-width:92vw;box-shadow:0 25px 60px rgba(0,0,0,0.25);text-align:right}
.dark-theme .transfer-box{background:#1e293b;box-shadow:0 25px 60px rgba(0,0,0,0.5)}
.transfer-box h4{margin:0 0 16px;font-size:17px;font-weight:700;color:var(--color-text);border-bottom:1px solid var(--color-border);padding-bottom:12px}
#agentsList{display:flex;flex-direction:column;gap:10px;max-height:300px;overflow-y:auto;margin-bottom:18px;padding-left:4px}
#agentsList::-webkit-scrollbar{width:4px}
#agentsList::-webkit-scrollbar-thumb{background:var(--color-border);border-radius:4px}
.agent-item{padding:12px 16px;border-radius:12px;border:1.5px solid var(--color-border);cursor:pointer;display:flex;align-items:center;gap:14px;transition:all .2s;background:var(--color-surface);text-align:right}
.agent-item:hover{border-color:var(--color-primary);background:var(--color-primary-light);transform:translateY(-1px)}
.dark-theme .agent-item:hover{background:rgba(89,184,240,.08)}
.agent-av{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#89CFF0,#5ab8f0);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:16px;flex-shrink:0}
.agent-info{flex:1}
.agent-name{font-weight:700;font-size:15px;color:var(--color-text);margin-bottom:3px}
.agent-role{font-size:12.5px;color:var(--color-text-muted)}
.transfer-cancel{width:100%;padding:13px;background:var(--color-gray-100);border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;color:var(--color-text-muted);transition:background .2s,color .2s}
.transfer-cancel:hover{background:var(--color-gray-200);color:var(--color-text)}
.dark-theme .transfer-cancel{background:var(--color-gray-800);color:#cbd5e1}
.dark-theme .transfer-cancel:hover{background:var(--color-gray-700);color:#fff}
</style>

<!-- Notif popup -->
<div id="chatNotif">
    <svg width="22" height="22" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <div class="notif-txt"><strong id="notifTitle">طلب محادثة جديد</strong><span id="notifBody"></span></div>
</div>

<!-- Transfer Modal -->
<div id="transferModal">
    <div class="transfer-box">
        <h4>تحويل المحادثة لوكيل آخر</h4>
        <div id="agentsList">
            <div class="text-center text-muted py-20">جاري تحميل الوكلاء...</div>
        </div>
        <button class="transfer-cancel" onclick="closeTransfer()">إلغاء</button>
    </div>
</div>

<!-- Quick Transaction Modal -->
<div id="quickTxModal" class="modal-overlay" style="position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:99999;display:none;align-items:center;justify-content:center">
    <div class="transfer-box quick-tx-box">
        <h4 id="txTitle">إضافة رصيد للعميل</h4>
        <form id="quickTxForm" onsubmit="submitQuickTx(event)">
            <input type="hidden" name="user_id" id="txUserId">
            <div class="form-group mb-12">
                <label class="text-xs fw-bold">العميل:</label>
                <div id="txCustName" class="text-sm text-muted"></div>
            </div>
            <div class="form-group mb-12">
                <label>نوع العملية</label>
                <select name="type" class="form-control" style="padding:8px;font-size:14px">
                    <option value="deposit">إيداع (+)</option>
                    <option value="deduct">خصم (-)</option>
                </select>
            </div>
            <div class="form-group mb-12">
                <label>المبلغ</label>
                <input type="number" name="amount" step="0.01" class="form-control" required placeholder="0.00">
            </div>
            <div class="form-group mb-16">
                <label>ملاحظة (اختياري)</label>
                <input type="text" name="notes" class="form-control" placeholder="مثلاً: شحن عبر الشات">
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary btn-sm" style="flex:1">تنفيذ</button>
                <button type="button" class="btn btn-ghost btn-sm" style="flex:1" onclick="closeQuickTx()">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<div class="flex-between mb-24">
    <div>
        <h3 class="mb-8 mt-0 fw-bold">المحادثات المباشرة</h3>
        <p class="text-muted text-sm mt-0">تواصل مع العملاء في الوقت الفعلي <?php echo $isAdmin ? '— <strong>مدير النظام</strong>: لديك صلاحيات كاملة' : ''; ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <span class="badge badge-warning" style="font-size:13px;padding:5px 12px">انتظار: <strong id="waitTopCnt">0</strong></span>
    </div>
</div>

<div class="cc">
    <div class="cc-sb">
        <div class="cc-tabs">
            <button class="cc-tab active" id="tab_waiting" onclick="switchTab('waiting')">انتظار <span id="waitCnt" class="badge badge-warning" style="font-size:11px">0</span></button>
            <button class="cc-tab" id="tab_transferred" onclick="switchTab('transferred')">محوّلة <span id="transferredCnt" class="badge badge-info" style="font-size:11px">0</span></button>
            <button class="cc-tab" id="tab_active" onclick="switchTab('active')"><?php echo $isAdmin ? 'كل النشطة' : 'محادثاتي'; ?> <span id="activeCnt" class="badge badge-primary" style="font-size:11px">0</span></button>
            <button class="cc-tab" id="tab_history" onclick="switchTab('history')">الأرشيف</button>
        </div>
        <div class="cc-list" id="chatList"></div>
    </div>

    <div class="cc-main" id="chatMain" style="display:none;flex-direction:column">
        <div class="cc-hd">
            <div class="cc-av" id="custAv">؟</div>
            <div class="cc-info">
                <div class="cc-name" id="custName">-</div>
                <div class="cc-meta" id="custMeta"></div>
            </div>
            <div class="cc-actions">
                <button class="btn btn-primary btn-sm" id="btnAccept" onclick="acceptSess()" style="display:none">قبول</button>
                <?php if ($isAdmin): ?>
                <button class="btn btn-secondary btn-sm" id="btnTakeover" onclick="takeoverSess()" style="display:none">تدخل</button>
                <?php
endif; ?>
                <button class="btn btn-secondary btn-sm" id="btnTransfer" onclick="openTransfer()" style="display:none">تحويل</button>
                <button class="btn btn-ghost btn-sm text-danger" id="btnClose" onclick="closeSess()" style="display:none">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    انهاء
                </button>
            </div>
        </div>
        <div class="cc-desc" id="descBar"><strong>المشكلة:</strong><span id="descTxt"></span></div>
        <div class="cc-msgs" id="chatMsgs">
            <div class="typing-ind" id="typingInd">
                <div class="m-av cu" style="width:26px;height:26px;font-size:10px">ع</div>
                <div class="typing-dots"><span></span><span></span><span></span></div>
            </div>
        </div>

        <div id="tplBox" style="display:none;position:absolute;bottom:75px;left:15px;right:15px;background:var(--color-surface);border:1.5px solid var(--color-border);border-radius:14px;padding:15px;box-shadow:0 10px 40px rgba(0,0,0,0.15);z-index:110;">
            <div style="font-weight:700;margin-bottom:12px;font-size:13px;display:flex;justify-content:space-between;align-items:center">
                <span>ردود جاهزة السريعة ⚡</span>
                <button onclick="toggleTpls()" style="background:none;border:none;cursor:pointer;padding:5px;">✕</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <button class="tpl-btn" onclick="useTpl('أهلاً بك، كيف يمكنني مساعدتك؟')">تحية</button>
                <button class="tpl-btn" onclick="useTpl('يرجى تزويدي برقم العملية أو لقطة شاشة للتحويل.')">طلب إثبات</button>
                <button class="tpl-btn" onclick="useTpl('تم شحن الرصيد بنجاح، يرجى تحديث الصفحة.')">تم الشحن</button>
                <button class="tpl-btn" onclick="useTpl('تم استلام طلبك وهو قيد التنفيذ الآن، يرجى الانتظار.')">جاري العمل</button>
                <button class="tpl-btn" onclick="useTpl('طلبك مكتمل الآن، هل هناك أي شيء آخر؟')">اكتمال الطلب</button>
                <button class="tpl-btn" onclick="useTpl('أعتذر، ولكن يجب عليك تسجيل الدخول أولاً.')">تسجيل دخول</button>
            </div>
        </div>

        <div class="cc-inp-area" id="inputArea" style="display:none;position:relative;">
            <div id="agEmojiPop"></div>
            <div id="agImgPrev"><img id="agPrevImg" src="" alt=""/><span id="agPrevNm"></span><button id="agImgRm">&#x2715;</button></div>
            <div class="cc-inp-row">
                <input type="text" id="msgInp" class="cc-inp" placeholder="اكتب رسالتك هنا..." autocomplete="off" />
                <button class="cc-btn img" id="agEmjBtn" title="إدراج إيموجي">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                </button>
                <button class="cc-btn img" id="agTplBtn" title="ردود جاهزة" onclick="toggleTpls()">
                    ⚡
                </button>
                <label class="cc-btn img" title="إرفاق صورة" style="cursor:pointer">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <input type="file" id="agFileIn" accept="image/png, image/jpeg, image/gif, image/webp" />
                </label>
                <button class="cc-btn send" onclick="sendMsg()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="cc-empty" id="emptyState">
        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <p>اختر محادثة من القائمة</p>
    </div>
</div>

<script>
var curTab = 'waiting';
var activeSessId = null;
var sessions = [];
var lastMsgId = 0;
var msgTimer = null;
var listTimer = null;
var notifLastId = null;
var agFile = null;
var isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
var myName = <?php echo json_encode($_SESSION['full_name'] ?? 'وكيل'); ?>;
var csrfToken = <?php echo json_encode(csrfToken()); ?>; // CSRF
var allAgents = [];
var emojisList = ['😀','😃','😄','😁','😆','😅','😂','🤣','🥲','☺️','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾','👋','🤚','🖐','✋','🖖','👌','🤌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦵','🦿','🦶','👣','👂','🦻','👃','🫀','🫁','🧠','🦷','🦴','👀','👁','👅','👄','💋','🩸','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','✅','❌','💯','✨','🔥','🌟','🎉','🎊','🎈','👑','💍','💎','💡','💸','💰','💵'];
window.sentClientIds = {};

function genId(){ return 'c_'+Date.now()+'_'+Math.random().toString(36).slice(2,6); }

// Secure fetch helper: automatically appends CSRF token to every POST
function postAgent(fd) {
    fd.append('csrf_token', csrfToken);
    return fetch('../api/chat-agent.php', {
        method: 'POST', 
        body: fd,
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    });
}
// Read-only fetch helper (no CSRF needed)
function getAgent(fd) {
    return fetch('../api/chat-agent.php', {method:'POST', body:fd});
}

// === Beep ===
function beep(){
    try{var c=new(window.AudioContext||window.webkitAudioContext)();[0,.2].forEach(function(t){var o=c.createOscillator(),g=c.createGain();o.connect(g);g.connect(c.destination);o.type='sine';o.frequency.value=880;g.gain.setValueAtTime(0,c.currentTime+t);g.gain.linearRampToValueAtTime(.45,c.currentTime+t+.05);g.gain.linearRampToValueAtTime(0,c.currentTime+t+.3);o.start(c.currentTime+t);o.stop(c.currentTime+t+.35);});}catch(e){}
}
function showNotif(title,body){
    document.getElementById('notifTitle').textContent=title;
    document.getElementById('notifBody').textContent=body;
    var p=document.getElementById('chatNotif');
    p.style.display='flex';clearTimeout(window._nTimer);
    window._nTimer=setTimeout(function(){p.style.display='none';},5000);
}

// === Tabs ===
function switchTab(tab){
    curTab=tab;
    ['waiting','transferred','active','history'].forEach(function(t){document.getElementById('tab_'+t)&&document.getElementById('tab_'+t).classList.toggle('active',t===tab);});
    document.getElementById('chatMain').style.display='none';
    document.getElementById('emptyState').style.display='flex';
    activeSessId=null;
    if(msgTimer){clearInterval(msgTimer);msgTimer=null;}
    loadSessions();
}

// === Load sessions list ===
async function loadSessions(){
    try {
        var action = (curTab==='waiting') ? 'get_waiting' : (curTab==='transferred') ? 'get_transferred' : (curTab==='history') ? 'get_history' : 'get_active';
        var fd=new FormData();fd.append('action',action);
        var res=await getAgent(fd);
        if (!res.ok) throw new Error('Network response was not ok: ' + res.status);
        var text = await res.text();
        try {
            var data=JSON.parse(text);
            if(data.success){ sessions=data.data.sessions; renderList(); }
            else { window.showToast&&window.showToast('تنبيه', data.message || 'فشل جلب البيانات', 'warning'); }
        } catch(e) {
            window.showToast&&window.showToast('خطأ', 'حدث خطأ في قراءة رد الخادم', 'error');
        }
    } catch(err) {
        // Silently fail or log to a real logger if available
    }
}

function renderList(){
    var list=document.getElementById('chatList');list.innerHTML='';
    var cnt=sessions.length;
    if(curTab==='waiting') { document.getElementById('waitCnt').textContent=cnt; if(document.getElementById('waitTopCnt')) document.getElementById('waitTopCnt').textContent=cnt; }
    else if(curTab==='transferred') { if(document.getElementById('transferredCnt')) document.getElementById('transferredCnt').textContent=cnt; }
    else if(curTab==='active') { if(document.getElementById('activeCnt')) document.getElementById('activeCnt').textContent=cnt; }
    if(!cnt){list.innerHTML='<div class="text-center text-muted text-sm mt-16">لا توجد محادثات</div>';return;}
    sessions.forEach(function(s){
        var d=document.createElement('div');d.className='ci'+(s.id==activeSessId?' sel':'');
        d.onclick=function(){openSess(s);};

        // Header row: name + time
        var hdr=document.createElement('div');hdr.style.cssText='display:flex;justify-content:space-between;align-items:center;margin-bottom:5px';
        var nm=document.createElement('div');nm.className='ci-nm';
        var nmSpan=document.createElement('span');nmSpan.textContent=s.customer_name||'زائر';
        nm.appendChild(nmSpan);

        // Dynamic Badge: In 'waiting' tab -> Transferred to, In 'transferred' tab -> Transferred from
        if(s.agent_id && curTab !== 'active') {
            var b=document.createElement('span');
            nm.appendChild(b);
        }

        // Department Badge
        if (s.department) {
            var db = document.createElement('span');
            db.style.cssText = 'font-size:10px;padding:2px 6px;border-radius:4px;margin-right:5px;font-weight:700;text-transform:uppercase;border:1px solid';
            if (s.department === 'wallet') {
                db.textContent = 'شحن محفظة';
                db.style.backgroundColor = 'rgba(16,185,129,0.1)';
                db.style.color = '#10b981';
                db.style.borderColor = '#10b981';
            } else {
                db.textContent = 'خدمة العملاء';
                db.style.backgroundColor = 'rgba(59,130,246,0.1)';
                db.style.color = '#3b82f6';
                db.style.borderColor = '#3b82f6';
            }
            nm.appendChild(db);
        }

        var tm=document.createElement('span');tm.className='ci-time';tm.textContent=fmtT(s.created_at);
        hdr.appendChild(nm);hdr.appendChild(tm);d.appendChild(hdr);

        // Agent info (safe)
        if(s.agent_name){
            var ag=document.createElement('div');ag.className='ci-sub';
            ag.style.color=(curTab==='transferred'?'var(--color-info)':'var(--color-primary)');
            ag.style.fontWeight='600';
            ag.textContent='العميل مع: '+s.agent_name;d.appendChild(ag);
            if(curTab==='transferred') d.style.borderRightColor='var(--color-info)'; // Visual distinguisher
        }

        // Description (safe)
        var desc=document.createElement('div');desc.className='ci-sub';
        desc.textContent=s.problem_description?s.problem_description.substr(0,55)+'...':'ينتظر الرد...';
        d.appendChild(desc);

        list.appendChild(d);
    });
}

function fmtT(d){if(!d)return'';var dt=new Date(d);return dt.getHours()+':'+String(dt.getMinutes()).padStart(2,'0');}

// === Open session ===
function openSess(s){
    // TASK 7: Block opening a 2nd active chat while one is already open
    var inputVisible = document.getElementById('inputArea').style.display !== 'none';
    if(activeSessId && activeSessId !== s.id && inputVisible){
        window.showToast&&window.showToast('تنبيه','أغلق المحادثة الحالية أولاً قبل فتح أخرى','warning');
        return;
    }
    activeSessId=s.id;renderList();
    document.getElementById('emptyState').style.display='none';
    document.getElementById('chatMain').style.display='flex';
    // Avatar
    document.getElementById('custAv').textContent=(s.customer_name||'ز').charAt(0).toUpperCase();
    document.getElementById('custName').textContent=s.customer_name||'زائر';
    // Meta info
    var mItems=[];
    if(s.department) {
        var dpt = s.department === 'wallet' ? 'شحن محفظة' : 'خدمة العملاء';
        mItems.push('<span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-weight:700;">'+dpt+'</span>');
    }
    if(s.customer_username) mItems.push('<span>👤 <b>'+s.customer_username+'</b></span>');
    if(s.wallet_balance !== undefined && s.wallet_balance !== null) {
        mItems.push('<span style="color:#10b981;font-weight:700;">💰 '+s.wallet_balance+'</span>');
    }
    if(s.customer_email)mItems.push('<span>&#9993; '+s.customer_email+'</span>');
    if(s.customer_phone)mItems.push('<span>&#9742; '+s.customer_phone+'</span>');
    if(s.agent_name)mItems.push('<span style="color:var(--color-primary)">الوكيل: '+s.agent_name+'</span>');
    
    // Quick Wallet Action
    if (s.customer_username) {
        mItems.push('<button class="btn btn-sm" style="background:#10b981;color:white;border:none;border-radius:6px;padding:3px 8px;font-size:11px;margin-top:5px;cursor:pointer" onclick="openQuickTx('+(s.wp_user_id || 0)+',\''+(s.customer_name||'').replace(/'/g,"\\'")+'\',\''+(s.customer_email||'')+'\')">+ إضافة رصيد</button>');
    }
    
    if(s.rating){
        var stars = '⭐'.repeat(s.rating);
        mItems.push('<div style="background:rgba(245,158,11,0.1);padding:8px;border-radius:10px;margin-top:10px;border:1px solid rgba(245,158,11,0.2)">' +
            '<div style="font-weight:700;font-size:12px;color:#d97706;margin-bottom:4px">تقييم العميل: '+stars+'</div>' +
            (s.rating_comment ? '<div style="font-size:11px;font-style:italic">"'+s.rating_comment+'"</div>' : '') +
            '</div>');
    }
    
    document.getElementById('custMeta').innerHTML=mItems.join('')||'<span>لا توجد معلومات إضافية</span>';
    // Description bar
    if(s.problem_description){
        document.getElementById('descTxt').textContent=' '+s.problem_description;
        document.getElementById('descBar').style.display='block';
    } else document.getElementById('descBar').style.display='none';
    // Buttons
    var isW = (curTab==='waiting' || curTab==='transferred');
    document.getElementById('btnAccept').style.display=isW?'inline-flex':'none';
    document.getElementById('btnClose').style.display=isW?'none':'flex';
    document.getElementById('btnTransfer').style.display=isW?'none':'flex';
    if(isAdmin){
        var tkbtn=document.getElementById('btnTakeover');
        if(tkbtn) tkbtn.style.display=(!isW&&s.agent_id&&s.status==='active')?'inline-flex':'none';
    }
    document.getElementById('inputArea').style.display=(isW || s.status==='closed')?'none':'flex';
    lastMsgId=0;
    document.getElementById('chatMsgs').innerHTML='<div class="typing-ind" id="typingInd"><div class="m-av cu" style="width:26px;height:26px;font-size:10px">ع</div><div class="typing-dots"><span></span><span></span><span></span></div></div>';
    if(msgTimer)clearInterval(msgTimer);
    pollMsgs();msgTimer=setInterval(pollMsgs,3000);
}

// === Accept ===
async function acceptSess(){
    if(!activeSessId)return;
    var fd=new FormData();fd.append('action','accept');fd.append('session_id',activeSessId);
    var res=await postAgent(fd);
    var data=await res.json();
    if(data.success){
        window.showToast&&window.showToast('تم','تم قبول المحادثة بنجاح','success');
        activeSessId=null;
        document.getElementById('chatMain').style.display='none';
        document.getElementById('emptyState').style.display='flex';
        switchTab('active');
        
        // Auto-open the accepted session shortly after loading the active tab
        setTimeout(function(){
            var s = sessions.find(function(x) { return x.id == data.data?.session_id || x.id == data.session_id || true; });
            // The cleanest way is just to trigger the API to reload sessions, then open the first one if we can't find it directly, 
            // but since loadSessions inside switchTab is async, we'll wait 400ms and try to find the newly accepted session (it will be at the end or top depending on order)
            var acceptedId = fd.get('session_id');
            var acceptedSession = sessions.find(function(x) { return x.id == acceptedId; });
            if (acceptedSession) {
                openSess(acceptedSession);
            } else if (sessions.length > 0) {
                // Fallback to opening the most recent one if exact ID match fails
                openSess(sessions[0]);
            }
        }, 500);

    } else window.showToast&&window.showToast('خطأ',data.message||'فشل القبول','error');
}

// === Takeover (Admin only) ===
async function takeoverSess(){
    if(!activeSessId||!isAdmin)return;
    if(!confirm('هل تريد الاستيلاء على هذه المحادثة؟'))return;
    var fd=new FormData();fd.append('action','takeover');fd.append('session_id',activeSessId);
    await postAgent(fd);
    window.showToast&&window.showToast('تم','تدخلت في المحادثة','success');
    loadSessions();
}

// === Close ===
async function closeSess(){
    if(!activeSessId)return;
    if(!confirm('انهاء المحادثة نهائياً؟'))return;
    var fd=new FormData();fd.append('action','close');fd.append('session_id',activeSessId);
    await postAgent(fd);
    switchTab('active');
}

// === Transfer ===
function openTransfer(){
    loadAgents();
    document.getElementById('transferModal').classList.add('show');
}
function closeTransfer(){ document.getElementById('transferModal').classList.remove('show'); }
async function loadAgents(){
    var fd=new FormData();fd.append('action','get_agents');
    var res=await getAgent(fd);
    var data=await res.json();
    if(!data.success)return;
    allAgents=data.data.agents;
    var el=document.getElementById('agentsList');el.innerHTML='';
    allAgents.forEach(function(a){
        var d=document.createElement('div');d.className='agent-item';
        d.onclick=function(){doTransfer(a.id,a.full_name);};
        // Avatar (first letter only — safe)
        var av=document.createElement('div');av.className='agent-av';
        av.textContent=(a.full_name||'و').charAt(0).toUpperCase();
        // Info block
        var info=document.createElement('div');info.className='agent-info';
        var agNm=document.createElement('div');agNm.className='agent-name';agNm.textContent=a.full_name;
        var agRl=document.createElement('div');agRl.className='agent-role';agRl.textContent=(a.role==='admin'?'مدير النظام':'وكيل');
        info.appendChild(agNm);info.appendChild(agRl);
        d.appendChild(av);d.appendChild(info);
        el.appendChild(d);
    });
}
async function doTransfer(agId,agName){
    if(!activeSessId)return;
    closeTransfer();
    var fd=new FormData();fd.append('action','transfer');fd.append('session_id',activeSessId);fd.append('target_agent_id',agId);
    var res=await postAgent(fd);
    var data=await res.json();
    if(data.success){window.showToast&&window.showToast('تم','تم التحويل إلى '+agName,'success');switchTab('active');}
    else window.showToast&&window.showToast('خطأ',data.message,'error');
}

// === Poll messages ===
async function pollMsgs(){
    if(!activeSessId)return;
    var fd=new FormData();fd.append('action','poll_messages');fd.append('session_id',activeSessId);fd.append('last_id',lastMsgId);
    try{
        var res=await getAgent(fd);
        var data=await res.json();
        if(!data.success)return;
        var d=data.data;
        // typing indicator
        var ti=document.getElementById('typingInd');
        if(ti) ti.style.display=(d.session&&d.session.typing_customer)?'flex':'none';

        if(d.status==='closed'){
            window.showToast&&window.showToast('انتباه','تم اغلاق المحادثة','info');
            document.getElementById('inputArea').style.display='none';
            clearInterval(msgTimer);msgTimer=null;
        }
        if(d.messages&&d.messages.length){
            d.messages.forEach(function(m){
                if(parseInt(m.id)>lastMsgId){
                    lastMsgId=parseInt(m.id);
                    if (!m.client_id || !window.sentClientIds[m.client_id]) {
                        appendMsg(m);
                    }
                }
            });
        }
    }catch(e){}
}

function appendMsg(m){
    var isAg=(m.sender_type==='agent');
    var isSys=(m.sender_type==='system');
    if(isSys){
        var sd=document.createElement('div');sd.className='sys-row';
        var ss=document.createElement('div');ss.className='m-bub sys';ss.textContent=m.message;
        sd.appendChild(ss);
        var cont=document.getElementById('chatMsgs');
        var ti=document.getElementById('typingInd');
        if(ti)cont.insertBefore(sd,ti);else cont.appendChild(sd);
        cont.scrollTop=cont.scrollHeight;return;
    }
    var wrap=document.createElement('div');wrap.className='m-wrap'+(isAg?' out':'');
    var av=document.createElement('div');av.className='m-av '+(isAg?'ag':'cu');
    av.textContent=(m.sender_name||'و').charAt(0).toUpperCase();
    wrap.appendChild(av);
    var blk=document.createElement('div');blk.className='m-blk';
    var nm=document.createElement('div');nm.className='m-nm';
    nm.textContent=m.sender_name||(isAg?'وكيل الدعم':'العميل');
    blk.appendChild(nm);
    var bub=document.createElement('div');bub.className='m-bub '+(isAg?'out':'in');
    if(m.message_type==='image'){
        var src=m.message;
        if(src.indexOf('http')!==0 && src.indexOf('blob:')!==0) src=window.location.origin+'/dashboard/'+src;
        var img=document.createElement('img');img.src=src;img.style.cursor='zoom-in';img.onclick=function(){window.open(src,'_blank');};
        bub.appendChild(img);
    } else if(m.message_type==='document'){
        var src=m.message;
        if(src.indexOf('http')!==0 && src.indexOf('blob:')!==0) src=window.location.origin+'/dashboard/'+src;
        var lnk=document.createElement('a');lnk.href=src;lnk.target='_blank';lnk.className='m-file-lnk';
        lnk.innerHTML='<div style="display:flex;align-items:center;gap:8px;padding:8px;background:rgba(0,0,0,0.05);border-radius:10px;"><span style="font-size:24px">📄</span><div style="text-align:right"><div style="font-weight:700;font-size:13px">مستند / ملف PDF</div><div style="font-size:11px;opacity:0.7">اضغط للمعاينة</div></div></div>';
        bub.appendChild(lnk);
    } else {
        bub.textContent=m.message;
    }
    var tm=document.createElement('div');tm.className='m-time';tm.textContent=fmtT(m.created_at);
    blk.appendChild(bub);blk.appendChild(tm);
    wrap.appendChild(blk);
    var cont=document.getElementById('chatMsgs');
    var ti=document.getElementById('typingInd');
    if(ti)cont.insertBefore(wrap,ti);else cont.appendChild(wrap);
    cont.scrollTop=cont.scrollHeight;
}

// === Send message ===
async function sendMsg(){
    var inp=document.getElementById('msgInp');
    var msg=inp.value.trim();
    if(!activeSessId)return;
    if(!msg&&!agFile)return;
    inp.value='';
    var cid=genId();
    var fd=new FormData();fd.append('action','send');fd.append('session_id',activeSessId);fd.append('client_id',cid);
    
    // Optimistic render
    var opm = {
        id: '9999999'+Date.now(),
        client_id: cid,
        sender_type: 'agent',
        sender_name: myName,
        message: agFile ? URL.createObjectURL(agFile) : msg,
        message_type: agFile ? 'image' : 'text',
        created_at: new Date().toISOString()
    };
    appendMsg(opm);
    window.sentClientIds[cid] = true;

    if(agFile){
        fd.append('image',agFile);
        var mType = agFile.type.indexOf('image') === 0 ? 'image' : 'document';
        fd.append('message_type', mType);
        clearAgImg();
    } else {
        fd.append('message',msg);
        fd.append('message_type', 'text');
    }
    // Reset typing
    var tfd=new FormData();tfd.append('action','typing');tfd.append('session_id',activeSessId);tfd.append('is_typing',0);
    postAgent(tfd).catch(function(){});
    try{await postAgent(fd);}catch(e){}
}

document.getElementById('msgInp').onkeypress=function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}};

// Typing signal
var agTypTimer=null;
document.getElementById('msgInp').oninput=function(){
    if(!activeSessId)return;
    var fd=new FormData();fd.append('action','typing');fd.append('session_id',activeSessId);fd.append('is_typing',1);
    postAgent(fd).catch(function(){});
    clearTimeout(agTypTimer);
    agTypTimer=setTimeout(function(){
        var fd2=new FormData();fd2.append('action','typing');fd2.append('session_id',activeSessId);fd2.append('is_typing',0);
        postAgent(fd2).catch(function(){});
    },2500);
};

// Image attach
document.getElementById('agFileIn').onchange=function(){
    if(!this.files||!this.files[0])return;
    var f=this.files[0];
    var ext=f.name.split('.').pop().toLowerCase();
    var allowed=['jpg','jpeg','png','gif','webp','pdf'];
    if(allowed.indexOf(ext)===-1){alert('الامتداد غير مسموح به، يرجى اختيار صورة أو PDF');this.value='';return;}
    if(f.size>8*1024*1024){alert('الحد الاقصى 8 ميجابايت');return;}
    agFile=f;document.getElementById('agPrevImg').src=URL.createObjectURL(f);document.getElementById('agPrevNm').textContent=f.name;document.getElementById('agImgPrev').style.display='flex';
};
document.getElementById('agImgRm').onclick=clearAgImg;
function clearAgImg(){agFile=null;document.getElementById('agFileIn').value='';document.getElementById('agImgPrev').style.display='none';document.getElementById('agPrevImg').src='';}

// Emojis
(function(){
    var ePop = document.getElementById('agEmojiPop');
    var eBtn = document.getElementById('agEmjBtn');
    var inp = document.getElementById('msgInp');
    emojisList.forEach(function(e){
        var spn=document.createElement('span');spn.className='ag-emj';spn.textContent=e;
        spn.onclick=function(){
            var start=inp.selectionStart, end=inp.selectionEnd;
            inp.value = inp.value.substring(0,start) + e + inp.value.substring(end);
            inp.selectionStart=inp.selectionEnd=start+e.length;
            inp.focus();
            ePop.style.display='none';
        };
        ePop.appendChild(spn);
    });
    eBtn.onclick=function(ev){ev.stopPropagation();ePop.style.display=(ePop.style.display==='grid'?'none':'grid');};
    document.addEventListener('click',function(ev){if(!ePop.contains(ev.target)&&ev.target!==eBtn)ePop.style.display='none';});
})();

// === Stats polling for new chat notifications ===
async function pollStats(){
    var fd=new FormData();fd.append('action','get_stats');
    try{
        var res=await getAgent(fd);
        var data=await res.json();
        if(data.success){
            var newest=data.data.newest_waiting;
            document.getElementById('waitTopCnt').textContent=data.data.waiting_count;
            document.getElementById('waitCnt').textContent=data.data.waiting_count;
            document.getElementById('transferredCnt').textContent=data.data.transferred_count;
            document.getElementById('activeCnt').textContent=data.data.active_count;
            
            if(newest&&newest.id!=notifLastId&&notifLastId!==null){
                beep();showNotif('طلب محادثة جديد','العميل: '+(newest.customer_name||'زائر'));
                if(window.Notification && Notification.permission==='granted'){
                    new Notification('طلب محادثة جديد', {body:'العميل: '+(newest.customer_name||'زائر'), icon:'../assets/img/icon.png'});
                }
                if(curTab==='waiting')loadSessions();
            }
            if(newest)notifLastId=newest.id;
            else if(notifLastId===null)notifLastId=0;
        }
    }catch(e){console.error('[chat] pollStats error:',e);}
}

// === Canned Responses ===
function toggleTpls(){
    var b=document.getElementById('tplBox');
    b.style.display=(b.style.display==='none'?'block':'none');
}
function useTpl(txt){
    var inp=document.getElementById('msgInp');
    inp.value=txt;
    inp.focus();
    toggleTpls();
}

// === Quick Wallet Tx ===
function openQuickTx(uid, name, email){
    if(!uid){ alert('هوية المستخدم غير متوفرة'); return; }
    document.getElementById('txUserId').value = uid;
    document.getElementById('txCustName').textContent = name + ' (' + email + ')';
    document.getElementById('quickTxModal').style.display = 'flex';
}
function closeQuickTx(){
    document.getElementById('quickTxModal').style.display = 'none';
    document.getElementById('quickTxForm').reset();
}
// Request notification permission
if(window.Notification && Notification.permission!=='granted' && Notification.permission!=='denied'){
    Notification.requestPermission();
}
async function submitQuickTx(e){
    e.preventDefault();
    var fd = new FormData(e.target);
    fd.append('csrf_token', csrfToken);
    
    try {
        var res = await fetch('../api/add-transaction.php', {method:'POST', body:fd});
        var data = await res.json();
        if(data.success){
            window.showToast&&window.showToast('تم','تمت العملية بنجاح وتحديث الرصيد','success');
            closeQuickTx();
            loadSessions(); // Refresh to show new balance
        } else {
            alert(data.message || 'فشل تنفيذ العملية');
        }
    } catch(err) {
        alert('خطأ في الاتصال بالسيرفر');
    }
}

listTimer=setInterval(function(){pollStats();if(curTab!=='waiting'||!activeSessId)loadSessions();},4000);
pollStats();
loadSessions();
</script>

<?php require_once dirname(__DIR__) . '/components/footer.php'; ?>
