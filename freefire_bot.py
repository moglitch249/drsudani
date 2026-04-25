# -*- coding: utf-8 -*-
"""
freefire_bot.py — Multi-Instance Bot Worker
تشغيل: python freefire_bot.py <BOT_ID> <PORT>
مثال:  python freefire_bot.py bot_1 5000
       python freefire_bot.py bot_2 5001
"""
import sys, io, time, requests, pyotp, os, base64, threading, signal, queue, hashlib, json, re
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

from playwright.sync_api import sync_playwright

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

# ─── هوية البوت من سطر الأوامر أو المتغيرات البيئية ───────────────────────
BOT_ID   = sys.argv[1] if len(sys.argv) > 1 else os.environ.get('BOT_ID', 'bot_1')
BOT_PORT = int(sys.argv[2] if len(sys.argv) > 2 else os.environ.get('BOT_PORT', '5000'))

# ─── إعدادات الـ API ────────────────────────────────────────────────────────
API_BASE_URL     = "https://bot.drsudani.com"
API_SECRET_TOKEN = "super_secret_token_123"
TOPUP_URL        = "https://gold.razer.com/global/en/gold/catalog/freefire-direct-top-up"

# ─── AES Encryption Helpers ────────────────────────────────────────────────
def encrypt_payload(data):
    key = hashlib.sha256(API_SECRET_TOKEN.encode()).digest()
    iv = os.urandom(16)
    cipher = AES.new(key, AES.MODE_CBC, iv)
    ct_bytes = cipher.encrypt(pad(json.dumps(data).encode(), AES.block_size))
    return base64.b64encode(iv + ct_bytes).decode('utf-8')

def decrypt_payload(payload):
    try:
        raw = base64.b64decode(payload)
        iv = raw[:16]
        ct = raw[16:]
        key = hashlib.sha256(API_SECRET_TOKEN.encode()).digest()
        cipher = AES.new(key, AES.MODE_CBC, iv)
        pt = unpad(cipher.decrypt(ct), AES.block_size)
        return json.loads(pt.decode('utf-8'))
    except Exception as e:
        print(f"Decryption error: {e}")
        return None

# ─── متغيرات الحالة العالمية (يقرأها الـ Heartbeat Thread) ─────────────────
EMAIL    = ""
PASSWORD = ""
OTP_SECRET = ""

bot_status        = "starting"
bot_balance_status = "unknown"
session_start_time = time.time()
orders_today      = 0
success_count     = 0
fail_count        = 0
consecutive_failures = 0
current_processing_order_id = None
bot_paused        = False
bot_draining      = False
processed_ids     = set()   # Layer 3: in-memory dedup

# ─── حدث الإيقاف الفوري (يُستخدم لمقاطعة الطلب الحالي) ──────────────────────
pause_event = threading.Event()   # عند تفعيله يعني "أوقف الطلب الحالي"
abort_event = threading.Event()   # عند تفعيله يعني "تم الإلغاء يدوياً"

class AbortOrderException(Exception): pass

def check_abort():
    if abort_event.is_set():
        raise AbortOrderException("تم إلغاء/إكمال الطلب من اللوحة")

# ─── جلب بيانات حساب ريزر من السيرفر ──────────────────────────────────────
def fetch_credentials():
    global EMAIL, PASSWORD, OTP_SECRET
    print(f"[{BOT_ID}] جلب بيانات الحساب من السيرفر...")
    for attempt in range(5):
        try:
            res = requests.post(
                f"{API_BASE_URL}/get_bot_credentials.php",
                json={"payload": encrypt_payload({"token": API_SECRET_TOKEN, "bot_id": BOT_ID})},
                timeout=10
            )
            if res.status_code == 200:
                resp_json = res.json()
                if 'payload' in resp_json:
                    data = decrypt_payload(resp_json['payload'])['data']
                else:
                    data = resp_json.get('data', {})
                EMAIL      = data['email']
                PASSWORD   = data['password']
                OTP_SECRET = data['otp_secret']
                print(f"[{BOT_ID}] ✓ تم تحميل بيانات الحساب: {EMAIL}")
                return True
            else:
                print(f"[{BOT_ID}] ✗ خطأ {res.status_code}: {res.text[:100]}")
        except Exception as e:
            print(f"[{BOT_ID}] ✗ محاولة {attempt+1}/5 فشلت: {e}")
        time.sleep(3)
    return False

# ─── إرسال Heartbeat كل 60 ثانية ──────────────────────────────────────────
def heartbeat_loop():
    global bot_status, bot_paused, bot_draining, consecutive_failures, orders_today, success_count, fail_count, bot_balance_status, current_processing_order_id
    while True:
        try:
            session_age = int((time.time() - session_start_time) / 60)
            success_rate = 100.0
            total = success_count + fail_count
            if total > 0:
                success_rate = round(success_count / total * 100, 1)

            payload_data = {
                "token":                API_SECRET_TOKEN,
                "bot_id":               BOT_ID,
                "port":                 BOT_PORT,
                "status":               "paused" if bot_paused else bot_status,
                "balance_status":       bot_balance_status,
                "active_orders":        1 if current_processing_order_id else 0,
                "orders_today":         orders_today,
                "success_count":        success_count,
                "fail_count":           fail_count,
                "success_rate":         success_rate,
                "consecutive_failures": consecutive_failures,
                "session_start":        time.strftime('%Y-%m-%d %H:%M:%S',
                                            time.localtime(session_start_time)),
                "current_order_id":     current_processing_order_id,
            }
            res = requests.post(f"{API_BASE_URL}/heartbeat.php", json={"payload": encrypt_payload(payload_data)}, timeout=10)

            if res.status_code == 200:
                resp_json = res.json()
                if 'payload' in resp_json:
                    resp_data = decrypt_payload(resp_json['payload'])
                else:
                    resp_data = resp_json

                cmd = resp_data.get('pending_command')
                if cmd == 'pause':
                    bot_paused   = True
                    bot_draining = False
                    pause_event.set()    # أشعر الطلب الجاري بالتوقف الفوري
                    print(f"[{BOT_ID}] ⏸  تم استقبال أمر PAUSE — إيقاف الطلب الجاري إن وُجد")
                elif cmd == 'resume':
                    bot_paused   = False
                    bot_draining = False
                    if bot_status == 'error_paused':
                        bot_status = 'online'
                        consecutive_failures = 0
                    pause_event.clear()  # امسح إشارة الإيقاف للسماح بالعمل مجدداً
                    print(f"[{BOT_ID}] ▶  تم استقبال أمر RESUME")
                elif cmd == 'restart':
                    print(f"[{BOT_ID}] 🔄 تم استقبال أمر RESTART — يتم الآن إعادة التشغيل...")
                    os.execl(sys.executable, sys.executable, *sys.argv)
                
                if resp_data.get('abort_order'):
                    abort_event.set()
                    print(f"[{BOT_ID}] 🛑 تم تغيير حالة الطلب خارجياً (إلغاء/إعادة)! جاري التخطي...")

        except Exception as e:
            print(f"[{BOT_ID}] ✗ Heartbeat Error: {e}")
        time.sleep(3)

# ─── تسجيل حدث في السيرفر ──────────────────────────────────────────────────
def log_action(order_id, action, result=""):
    try:
        payload_data = {
            "token":    API_SECRET_TOKEN,
            "bot_id":   BOT_ID,
            "order_id": order_id,
            "action":   action,
            "result":   result
        }
        requests.post(f"{API_BASE_URL}/log_action.php", json={"payload": encrypt_payload(payload_data)}, timeout=5)
    except:
        pass

# ─── دوال الـ API ───────────────────────────────────────────────────────────
def get_pending_order():
    try:
        payload = encrypt_payload({"token": API_SECRET_TOKEN, "bot_id": BOT_ID})
        res = requests.post(
            f"{API_BASE_URL}/get_pending.php",
            json={"payload": payload},
            timeout=10
        )
        if res.status_code == 200:
            resp_json = res.json()
            if 'payload' in resp_json:
                data = decrypt_payload(resp_json['payload'])
            else:
                data = resp_json
            if data and data.get('success') and data.get('data'):
                return data['data']
    except:
        pass
    return None

def update_order_status(order_id, status, fail_reason="", evidence_base64="",
                        checkout_clicked=False, financial_risk="none",
                        duration_seconds=0):
    global bot_balance_status
    url     = f"{API_BASE_URL}/update_status.php"
    payload_data = {
        "token":            API_SECRET_TOKEN,
        "id":               order_id,
        "status":           status,
        "fail_reason":      fail_reason,
        "evidence":         evidence_base64,
        "checkout_clicked": 1 if checkout_clicked else 0,
        "financial_risk":   financial_risk,
        "requires_human":   1 if (checkout_clicked and status != 'completed') else 0,
        "bot_id":           BOT_ID,
    }
    if status == 'completed' and duration_seconds > 0:
        payload_data['duration_seconds'] = duration_seconds

    # تحديث حالة الرصيد في manage_accounts أيضاً
    if financial_risk == 'critical':
        bot_balance_status = 'insufficient'
        try:
            acc_payload = encrypt_payload({
                "token":          API_SECRET_TOKEN,
                "action":         "update_balance",
                "bot_id":         BOT_ID,
                "balance_status": "insufficient"
            })
            requests.post(f"{API_BASE_URL}/manage_accounts.php", json={"payload": acc_payload}, timeout=5)
        except:
            pass

    for attempt in range(3):
        try:
            res = requests.post(url, json={"payload": encrypt_payload(payload_data)}, timeout=15)
            if res.status_code == 200:
                print(f"    [API] الطلب {order_id} → {status}")
                return True
        except Exception as e:
            print(f"    [!] محاولة {attempt+1}/3 لتحديث الطلب {order_id} فشلت: {e}")
            time.sleep(3)
    return False

# ─── OTP ────────────────────────────────────────────────────────────────────
def wait_for_otp(page, timeout_ms=15000):
    deadline = time.time() + timeout_ms / 1000
    while time.time() < deadline:
        check_abort()
        try:
            if page.is_visible('#otp-input-0'):
                return 'page'
                
            # إذا تقدمت الصفحة لصفحة النجاح، لا حاجة للانتظار
            # Checking for early success without OTP
            current_url = page.url.lower()
            if "receipt" in current_url or "success" in current_url or "thank-you" in current_url:
                print("    [!] تخطي OTP (العملية نجحت مباشرة)")
                return None
        except:
            pass
            
        for f in page.frames:
            try:
                if 'razerid' in f.url or f.url != page.url:
                    el = f.query_selector('#otp-input-0')
                    if el and el.is_visible():
                        return f
            except:
                continue
        time.sleep(0.5)
    return None

def fill_otp(container, page_ref):
    totp = pyotp.TOTP(OTP_SECRET.replace(" ", ""))
    code = totp.now()
    print(f"    [+] OTP: {code}")
    try:
        inp0 = container.query_selector('#otp-input-0')
        if not inp0:
            return False
        inp0.click(timeout=1000)
        time.sleep(0.1)
        for digit in code:
            page_ref.keyboard.type(digit)
            time.sleep(0.03)
        return True
    except:
        return False

# ─── تسجيل الدخول ───────────────────────────────────────────────────────────
def ensure_logged_in(page, context):
    global bot_status
    bot_status = "checking_login"
    print(f"[{BOT_ID}] فحص حالة الجلسة...")
    if page.is_closed():
        print(f"[{BOT_ID}] ✗ المتصفح مغلق! يرجى إعادة التشغيل.")
        return

    try:
        page.goto("https://gold.razer.com/global/en/account/profile", timeout=45000)
        page.wait_for_load_state("domcontentloaded")
    except Exception as e:
        print(f"[{BOT_ID}] ! تنبيه عند محاولة فحص الجلسة: {e}")

    time.sleep(2)
    try:
        current_url = page.url
    except:
        return

    if ("razerid.razer.com" not in current_url and
            ("account" in current_url or "gold.razer.com" in current_url)):
        if not page.is_visible('text="Log In"'):
            print(f"[{BOT_ID}] ✓ الجلسة نشطة")
            bot_status = "online"
            return

    print(f"[{BOT_ID}] تسجيل الدخول...")
    try:
        page.goto(f"https://razerid.razer.com/?client_id=63c74d17e027dc11f642146bfeeaee09c3ce23d8&redirect=https%3A%2F%2Fgold.razer.com%2Fglobal%2Fen", timeout=45000)
        page.wait_for_load_state("networkidle")
        time.sleep(2)
        page.click('[data-cky-tag="accept-button"]', timeout=1000)
    except:
        pass

    STATE_FILE = f"razer_state_{BOT_ID}.json"
    try:
        page.wait_for_selector('#input-login-email', timeout=15000)
        page.click('#input-login-email')
        page.type('#input-login-email', EMAIL, delay=50)
        time.sleep(0.5)
        page.evaluate("document.querySelector('#input-login-password').removeAttribute('readonly')")
        page.click('#input-login-password')
        page.type('#input-login-password', PASSWORD, delay=50)
        time.sleep(0.5)
        try:
            page.evaluate("document.querySelector('#btn-log-in').removeAttribute('disabled')")
        except:
            pass
        page.click('#btn-log-in')
        print(f"    [..] جاري التحقق...")
        try:
            page.wait_for_url("https://gold.razer.com/**", timeout=50000)
            print(f"    [OK] تم تسجيل الدخول!")
            context.storage_state(path=STATE_FILE)
        except:
            print(f"    [!] كابتشا! يرجى الحل في المتصفح.")
            input("    اضغط Enter بعد إكمال تسجيل الدخول... ")
            context.storage_state(path=STATE_FILE)
    except Exception as e:
        print(f"    [!] {e}")

    bot_status = "online"

# ─── العودة لصفحة الشحن ─────────────────────────────────────────────────────
def go_to_topup_page(page):
    try:
        if page.url.split('?')[0] == TOPUP_URL:
            page.reload(timeout=10000, wait_until="commit")
        else:
            page.goto(TOPUP_URL, timeout=15000, wait_until="commit")
        print(f"    [OK] صفحة الشحن جاهزة.")
        return True
    except:
        try:
            page.goto("https://gold.razer.com/global/en", timeout=10000)
            return True
        except:
            return False

# ─── الباقة ─────────────────────────────────────────────────────────────────
def get_diamond_label(amount):
    try:
        clean_val = "".join(filter(str.isdigit, str(amount)))
        if not clean_val:
            return None
        val = int(clean_val)
        return f"{'{:,}'.format(val)} Diamonds"
    except:
        return None

# ─── معالجة الطلب ───────────────────────────────────────────────────────────
def process_order(page, context, player_id, diamonds, order_id):
    global bot_status, bot_balance_status, consecutive_failures, bot_paused

    max_retries     = 2
    checkout_clicked_flag = False

    def check_paused():
        """يُرجع True إذا صدر أمر PAUSE أو تم إلغاء الطلب"""
        return pause_event.is_set() or abort_event.is_set()

    for attempt in range(max_retries):
        if checkout_clicked_flag:
            return False, "تحقق يدوي! Checkout ضُغط سابقاً.", "", True

        print(f"\n[{BOT_ID}] محاولة الشحن [{attempt+1}/{max_retries}] — {diamonds}💎 → {player_id}")
        log_action(order_id, f"Attempt {attempt+1}/{max_retries}: Navigating to top-up page")

        # فحص فوري للإيقاف قبل البدء
        if check_paused():
            log_action(order_id, "Order interrupted", "PAUSE received before attempt")
            return False, "⏸ تم الإيقاف — الطلب يحتاج مراجعة يدوية", "", False

        try:
            bot_status = "busy"
            if page.url.split('?')[0] != TOPUP_URL:
                page.goto(TOPUP_URL, timeout=15000, wait_until="commit")

            try:
                page.click('[data-cky-tag="accept-button"]', timeout=500)
            except:
                pass

            # مسح الأيدي القديم فوراً
            try:
                page.evaluate("""() => {
                    let cb = document.querySelector('input[type=checkbox]');
                    if(cb && cb.checked) cb.click();
                    let inputs = document.querySelectorAll('input[id^="gameUserId"], input[name="playerID"]');
                    inputs.forEach(el => { el.value = ''; el.dispatchEvent(new Event('input',{bubbles:true})); });
                }""")
            except:
                pass

            # ─── خطوة 1: إدخال الأيدي ───────────────────────────────────
            log_action(order_id, f"Entering player ID {player_id}")
            entered = False
            try:
                page.wait_for_selector(
                    "input[id^='gameUserId'], input[name='playerID'], "
                    "input[placeholder*='Player ID'], input[placeholder*='Game User ID']",
                    timeout=6000
                )
                loc = page.locator(
                    "input[id^='gameUserId'], input[name='playerID'], "
                    "input[placeholder*='Player ID'], input[placeholder*='Game User ID']"
                ).first
                loc.scroll_into_view_if_needed()
                loc.fill(player_id)
                entered = True
                actual = loc.input_value()
                print(f"    [OK] الأيدي: {actual}")
                log_action(order_id, f"Entered player ID", f"Verified: {actual}")
            except Exception as ex:
                print(f"    [!] فشل إدخال الأيدي: {ex}")
                log_action(order_id, "Enter player ID", f"FAILED: {ex}")

            if not entered:
                raise Exception(f"فشل إدخال الأيدي ({player_id})!")

            time.sleep(0.5)

            # ─── خطوة 2: التحقق من الأيدي ───────────────────────────────
            try:
                actual_id = loc.input_value().strip()
                if actual_id != player_id.strip():
                    log_action(order_id, "Player ID Mismatch", f"{actual_id} ≠ {player_id}")
                    return False, f"حماية! الأيدي ({actual_id}) ≠ المطلوب ({player_id})", "", False
            except:
                pass

            # التحقق الفوري من رسالة الخطأ للأيدي
            try:
                for _ in range(10):
                    check_abort()
                    outcome = page.evaluate("""() => {
                        let text = document.body.innerText.toLowerCase();
                        if (text.includes('invalid username') || text.includes('user not found') || text.includes('invalid player id') || text.includes('invalid format')) return 'invalid';
                        return 'ok';
                    }""")
                    if outcome == 'invalid':
                        log_action(order_id, "Invalid Player ID detected", "Razer showed invalid ID error before checkout")
                        print(f"    [!] الأيدي غير صحيح أو غير موجود!")
                        return False, "الأيدي غير صحيح (Invalid Username/account)", "", False
                    time.sleep(0.3)
            except:
                pass

            # ─── خطوة 3: اختيار الباقة ──────────────────────────────────
            diamond_label = get_diamond_label(diamonds)
            selected      = False
            log_action(order_id, f"Selecting package {diamond_label}")

            if diamond_label:
                try:
                    tile = page.locator(f'text="{diamond_label}"').first
                    if tile.is_visible(timeout=3000):
                        tile.click(timeout=2000)
                        selected = True
                        print(f"    [OK] الباقة: {diamond_label}")
                        log_action(order_id, f"Package selected", diamond_label)
                except:
                    pass

            if not selected and diamond_label:
                try:
                    tiles = page.locator(
                        f'div:has-text("{diamond_label}"), span:has-text("{diamond_label}")'
                    ).all()
                    for t in tiles:
                        try:
                            if t.is_visible():
                                t.click(timeout=2000)
                                selected = True
                                print(f"    [OK] الباقة (جزئي): {diamond_label}")
                                log_action(order_id, "Package selected (partial match)", diamond_label)
                                break
                        except:
                            continue
                except:
                    pass

            if not selected:
                raise Exception(f"فشل اختيار الباقة: {diamond_label}")

            time.sleep(0.5)

            # فحص الإيقاف بعد اختيار الباقة
            if check_paused():
                log_action(order_id, "Order interrupted", "PAUSE after package selection")
                return False, "⏸ تم الإيقاف قبل الدفع — الطلب يحتاج مراجعة يدوية", "", False

            # ─── خطوة 4: فحص الرصيد + الدفع ────────────────────────────
            print(f"    [*] فحص زر الدفع...")

            # قراءة الرصيد الحالي قبل الدفع لاستخدامه كاحتياط
            pre_balance = ""
            try:
                bal_el = page.locator('[data-cs-override-id="navigation-gold-amount"], .gold-amount, .balance-amount').first
                if bal_el.is_visible(timeout=2000):
                    raw_text = bal_el.inner_text().strip()
                    nums = re.findall(r'[\d\.]+', raw_text.replace(',', ''))
                    if nums:
                        pre_balance = nums[-1]
                        print(f"    [INFO] الرصيد قبل الدفع: {pre_balance} (من النص: {raw_text})")
                        log_action(order_id, "Pre-checkout balance", pre_balance)
            except:
                pass

            try:
                # فحص محتوى الزر بدقة قبل البدء في أي انتظار
                btn_el = page.locator('[data-cs-override-id="purchase-webshop-checkout-btn"]').first
                if btn_el.is_visible(timeout=5000):
                    txt = btn_el.inner_text().upper()
                    if "RELOAD" in txt or "GET GOLD" in txt or "شحن" in txt:
                        bot_balance_status = "insufficient"
                        bot_paused = True # إيقاف البوت ذاتياً لمنع تكرار الفشل
                        log_action(order_id, "Checkout check", f"INSUFFICIENT BALANCE — Button text: {txt}")
                        print(f"    [!!!] رصيد غير كافٍ! ({txt}) — تم إيقاف البوت تلقائياً")
                        return (False, "🔴 رصيد ريزر غير كافٍ! (الرصيد أقل من سعر الباقة)", "", False, "critical")
            except:
                pass

            # حلقة انتظار ذكية للزر مع فحص النص في كل مرة
            for _ in range(30):
                res = page.evaluate("""() => {
                    let text = document.body.innerText.toUpperCase();
                    // فحص شامل لكل الصفحة عن كلمات تدل على نقص الرصيد
                    if (text.includes('RELOAD TO CHECKOUT') || text.includes('GET GOLD') || text.includes('INSUFFICIENT BALANCE') || text.includes('رصيد غير كاف')) {
                        return 'insufficient';
                    }
                    
                    let btn = document.querySelector('[data-cs-override-id="purchase-webshop-checkout-btn"]');
                    if (!btn) return 'not_found';
                    let txt = btn.innerText.toUpperCase();
                    if (txt.includes('RELOAD') || txt.includes('GET GOLD') || txt.includes('شحن')) return 'insufficient';
                    
                    if (text.includes('INVALID USERNAME') || text.includes('USER NOT FOUND') || text.includes('INVALID PLAYER ID')) {
                        return 'invalid_id';
                    }
                    
                    return (btn && !btn.disabled && !btn.classList.contains('btn--disabled')) ? 'enabled' : 'disabled';
                }""")
                
                if res == 'insufficient':
                    bot_balance_status = "insufficient"
                    bot_paused = True
                    log_action(order_id, "Balance Check", "Detected INSUFFICIENT via Global Scan")
                    return (False, "🔴 رصيد غير كافٍ (تم اكتشافه بمسح الصفحة)", "", False, "critical")
                
                if res == 'invalid_id':
                    return (False, "الأيدي غير صحيح (Invalid Username/account)", "", False)

                if res == 'enabled':
                    break
                time.sleep(0.3)

            # التحقق النهائي من الأيدي قبل الدفع
            try:
                final_id = page.locator(
                    "input[id^='gameUserId'], input[name='playerID'], "
                    "input[placeholder*='Player ID'], input[placeholder*='Game User ID']"
                ).first.input_value().strip()
                if final_id != player_id.strip():
                    log_action(order_id, "Final ID check FAILED", f"{final_id} ≠ {player_id}")
                    return False, f"حماية! الأيدي تغير قبل الدفع ({final_id}) ≠ ({player_id})", "", False
                log_action(order_id, "Final ID verified", final_id)
                print(f"    [OK] تحقق نهائي: {final_id}")
            except:
                pass

            # ── نقطة اللاعودة ──
            page.evaluate("""() => {
                let btn = document.querySelector('[data-cs-override-id="purchase-webshop-checkout-btn"]');
                if(btn){ btn.removeAttribute('disabled'); btn.click(); }
            }""")
            checkout_clicked_flag = True
            log_action(order_id, "Checkout CLICKED", "POINT OF NO RETURN")
            print(f"    [!!] === تم الضغط على Checkout! ===")

            # ── إبلاغ فوري للسيرفر (Checkpoint) — الحماية من الشحن المزدوج ──
            # نخبر قاعدة البيانات فوراً أن الدفع تم، قبل أي انتظار آخر.
            # إذا مات البوت بعد هذا السطر، لن يُعاد إرسال الطلب للبوت مرة أخرى.
            try:
                update_order_status(
                    order_id,
                    status="processing",
                    fail_reason="checkout_clicked — awaiting confirmation",
                    checkout_clicked=True,
                    financial_risk="low"
                )
                print(f"    [✔] Checkpoint: سيرفر أُبلغ بضغط Checkout")
            except Exception as cp_err:
                print(f"    [!] Checkpoint فشل (لكن العملية مستمرة): {cp_err}")


            # ننتظر إما ظهور زر Confirm أو رسالة الأيدي الخاطئ
            try:
                outcome = "none"
                for _ in range(30):
                    check_abort()
                    outcome = page.evaluate("""() => {
                        let text = document.body.innerText.toLowerCase();
                        if (text.includes('invalid username') || text.includes('user not found') || text.includes('invalid player id')) {
                            return 'invalid';
                        }
                        let btns = Array.from(document.querySelectorAll('button'));
                        if (btns.some(b => b.innerText.toLowerCase().includes('confirm') || b.innerText.toLowerCase().includes('pay now'))) {
                            return 'confirm';
                        }
                        return 'none';
                    }""")
                    if outcome != 'none':
                        break
                    time.sleep(0.2)

                if outcome == 'invalid':
                    log_action(order_id, "Invalid Player ID detected", "Razer showed invalid ID error after checkout")
                    print(f"    [!] الأيدي غير صحيح أو غير موجود (بعد الضغط)!")
                    return False, "الأيدي غير صحيح (Invalid Username/account)", "", True
                elif outcome == 'confirm':
                    print(f"    [*] جاري تأكيد الدفع (Confirm)...")
                    confirm_btn = page.locator('button:has-text("Confirm"), button:has-text("Pay Now")').first
                    if confirm_btn.is_visible(timeout=1000):
                        confirm_btn.click(timeout=2000)
                    log_action(order_id, "Confirm CLICKED")
            except:
                pass

            # ─── خطوة 5: OTP ─────────────────────────────────────────────
            log_action(order_id, "Waiting for OTP")
            try:
                otp_result = wait_for_otp(page, timeout_ms=30000)
                if otp_result is not None:
                    container = otp_result if otp_result != 'page' else page
                    fill_otp(container, page)
                    log_action(order_id, "OTP entered automatically")
                    try:
                        page.wait_for_selector('.modal-one-time-password', state='hidden', timeout=15000)
                    except:
                        pass
            except:
                pass

            # ─── خطوة 6: انتظار النتيجة ──────────────────────────────────
            log_action(order_id, "Waiting for payment result")
            deadline = time.time() + 60
            while time.time() < deadline:
                check_abort()
                current_url = page.url.lower()
                if "receipt" in current_url or "success" in current_url or "confirmation" in current_url or "thank-you" in current_url:
                    break
                try:
                    inner = page.inner_text("body").lower()
                    if "transaction successful" in inner or "purchase successful" in inner or "thank you for your purchase" in inner:
                        break
                except:
                    pass
                time.sleep(0.5)

            time.sleep(1)

            # ─── خطوة 7: تحليل النتيجة ───────────────────────────────────
            current_url = page.url.lower()
            body_text   = ""
            try:
                body_text = page.inner_text("body").lower()
            except:
                pass

            b64_img = ""
            try:
                # Enable full_page to capture the complete receipt
                b64_img = base64.b64encode(page.screenshot(full_page=True, timeout=5000)).decode('utf-8')
            except:
                pass

            print(f"    [INFO] URL: {page.url}")

            if ("success" in current_url or "confirmation" in current_url or
                    "thank-you" in current_url or "order-received" in current_url or
                    "transaction successful" in body_text or "purchase successful" in body_text or
                    "thank you for your purchase" in body_text):
                log_action(order_id, "Payment result", "SUCCESS ✓")
                bot_balance_status = "sufficient"
                return True, "تم الشحن بنجاح", b64_img, True

            # ─── احتياط ريزر: فحص تغير الرصيد ────────────────────────
            # إذا لم تظهر صفحة النجاح لكن الرصيد انخفض → حالة غير معروفة!
            # قد يكون الطلب "pending" عند ريزر (الجواهر لم تصل بعد)
            # → نرسله للمراجعة اليدوية مع تنبيه واضح
            if checkout_clicked_flag and pre_balance:
                try:
                    post_bal_el = page.locator('[data-cs-override-id="navigation-gold-amount"], .gold-amount, .balance-amount').first
                    if post_bal_el.is_visible(timeout=3000):
                        post_balance = post_bal_el.inner_text().strip()
                        log_action(order_id, "Post-checkout balance", post_balance)
                        if post_balance != pre_balance:
                            log_action(order_id, "Payment result", f"BALANCE_CHANGED — needs manual verification ({pre_balance} → {post_balance})")
                            print(f"    [⚠] الرصيد تغير! {pre_balance} → {post_balance} — لكن لا تأكيد من ريزر! → مراجعة يدوية")
                            return False, f"⚠ تنبيه: الرصيد تغير ({pre_balance} → {post_balance}) لكن حالة الشحن غير مؤكدة — يرجى التحقق يدوياً من حساب اللاعب", b64_img, True, "balance_changed"
                except:
                    pass

            if "pending" in body_text or "in progress" in body_text:
                log_action(order_id, "Payment result", "PENDING — needs review")
                return False, f"تحقق يدوي! معلّق (pending). URL: {page.url[:80]}", b64_img, True

            if checkout_clicked_flag:
                log_action(order_id, "Payment result", f"UNCERTAIN — URL: {page.url[:80]}")
                return False, f"تحقق يدوي! Checkout ضُغط لكن النتيجة غير واضحة. URL: {page.url[:80]}", b64_img, True
            elif attempt < max_retries - 1:
                continue
            else:
                return False, f"توقف الشحن — URL: {page.url[:100]}", b64_img, False

        except AbortOrderException as ae:
            raise ae
        except Exception as e:
            is_timeout = 'TimeoutError' in str(type(e)) or 'Timeout' in str(e)
            if is_timeout and not checkout_clicked_flag:
                print(f"    [!] Fast-Fail: Timeout detected before checkout. Aborting retry.")
                raise e

            b64_img = ""
            try:
                b64_img = base64.b64encode(page.screenshot(full_page=True, timeout=5000)).decode('utf-8')
            except:
                pass
            error_msg = f"{type(e).__name__}: {str(e)}"[:200]
            log_action(order_id, "Exception", error_msg)

            if checkout_clicked_flag:
                print(f"    [!!] خطأ بعد Checkout — لن نعيد المحاولة!")
                return False, f"تحقق يدوي! خطأ بعد Checkout: {error_msg}", b64_img, True
            elif attempt < max_retries - 1:
                print(f"    [!] {e} — إعادة المحاولة...")
            else:
                return False, f"خطأ: {error_msg}", b64_img, False

    return False, "استنفدت المحاولات", "", False

# ─── Flask Receiver تم حذفه ────────────────────────────────────────────────

# ─── الحلقة الرئيسية ────────────────────────────────────────────────────────
def main():
    global bot_status, bot_balance_status, session_start_time
    global orders_today, success_count, fail_count, consecutive_failures
    global current_processing_order_id, bot_paused, bot_draining, processed_ids

    print(f"\n{'='*55}")
    print(f"  BOT ID: {BOT_ID}  |  PORT: {BOT_PORT}")
    print(f"  API:    {API_BASE_URL}")
    print(f"{'='*55}\n")

    # جلب بيانات الحساب
    if not fetch_credentials():
        print(f"[{BOT_ID}] ✗ فشل جلب بيانات الحساب. تأكد من تسجيل الحساب ومرتبطيته بـ {BOT_ID}")
        sys.exit(1)

    # الاعتماد على Pull-based Polling فقط (لا يوجد Flask Queue)

    # بدء Heartbeat
    threading.Thread(target=heartbeat_loop, daemon=True).start()
    print(f"[{BOT_ID}] Heartbeat نشط (كل 60 ثانية)")

    # إعداد Signal Handler
    def signal_handler(sig, frame):
        print(f"\n[{BOT_ID}] إغلاق...")
        if current_processing_order_id:
            update_order_status(current_processing_order_id, 'manual_review',
                                "تم إغلاق البوت أثناء المعالجة — يحتاج مراجعة يدوية.",
                                checkout_clicked=True,
                                financial_risk="high")
        sys.exit(0)

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    USER_DATA_DIR = os.path.join(os.getcwd(), f"chrome_bot_profile_{BOT_ID}")
    os.makedirs(USER_DATA_DIR, exist_ok=True)

    with sync_playwright() as p:
        context = p.chromium.launch_persistent_context(
            user_data_dir=USER_DATA_DIR,
            headless=False,
            channel="chrome",
            slow_mo=200,
            locale="en-US",
            viewport=None,
            ignore_default_args=["--disable-extensions", "--enable-automation"],
            args=[
                "--disable-blink-features=AutomationControlled",
                "--start-maximized",
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-dev-shm-usage",
                "--disable-gpu",
            ]
        )

        page = context.pages[0] if context.pages else context.new_page()

        print(f"\n{'='*55}")
        print(f" المتصفح مفتوح. فعّل VPN إذا لزم.")
        print(f"{'='*55}")
        input(f" [{BOT_ID}] >> اضغط Enter للبدء... ")

        if context.pages:
            page = context.pages[-1]
        else:
            page = context.new_page()

        session_start_time = time.time()
        ensure_logged_in(page, context)
        go_to_topup_page(page)

        bot_status    = "online"
        last_activity = time.time()
        pause_event.clear()   # تأكد أن البوت يبدأ في وضع غير موقوف
        print(f"\n[{BOT_ID}] ✓ جاهز للعمل — أنا أراقب الطلبات...")

        while True:
            # إذا كان البوت موقوفاً — انتظر الاستئناف
            if bot_paused:
                print(f".", end="", flush=True)
                time.sleep(5)
                continue

            # عند الخروج من حالة الإيقاف، امسح حدث الإيقاف
            if pause_event.is_set():
                pause_event.clear()
                print(f"\n[{BOT_ID}] ▶ استُؤنف البوت — استقبال الطلبات...")

            # إذا كان البوت موقوفاً بسبب كثرة الأخطاء
            if bot_status == 'error_paused':
                print(f"!", end="", flush=True)
                time.sleep(5)
                continue

            # إذا كان في وضع Drain وانتهى الطلب الحالي
            if bot_draining and not current_processing_order_id:
                print(f"[{BOT_ID}] Drain مكتمل — متوقف.")
                bot_paused   = True
                bot_draining = False
                continue

            order = None

            # الاعتماد الكلي على الـ API (Pull-based) لضمان الأمان وعدم تعارض البوتات
            # فحص حالة البوت وانتظار الطلبات
            while True:
                    if bot_paused or bot_draining:
                        if bot_paused:
                            print(f"[{BOT_ID}] ⏸ البوت متوقف مؤقتاً (PAUSED)... في انتظار الاستئناف.")
                        elif bot_draining:
                            print(f"[{BOT_ID}] 🚰 وضع DRAIN مفعل... تم إنهاء العمل. انتظر إغلاق البوت.")
                        time.sleep(3)
                        continue
                    
                    order = get_pending_order()
                    break

            if not order:
                print(".", end="", flush=True)
                if time.time() - last_activity > 3600:
                    print(f"\n[{BOT_ID}] إنعاش دوري للصفحة...")
                    go_to_topup_page(page)
                    last_activity = time.time()
                time.sleep(1) # فحص كل ثانية للحصول على استجابة فورية
                continue

            last_activity = time.time()
            order_id  = order['id']
            woo_id    = order.get('woo_order_id')
            display_id = f"#{woo_id}" if woo_id else f"#{order_id}"
            player_id = str(order['player_id'])
            diamonds  = str(order.get('diamonds', order.get('amount', '100')))

            # Layer 3 In-memory dedup removed because get_pending handles locking securely.

            print(f"\n\n[{BOT_ID}] === طلب {display_id} — {player_id} — {diamonds}💎 ===")
            log_action(order_id, "Order received", f"player:{player_id} diamonds:{diamonds}")

            current_processing_order_id = order_id
            orders_today += 1
            order_start_time = time.time()  # ← تتبع وقت بدء الطلب الفعلي

            # لا حاجة لـ update_order_status('processing') — get_pending.php يفعلها ذرياً

            result_success   = False
            result_msg       = ""
            result_img       = ""
            checkout_done    = False
            financial_risk   = "none"

            try:
                ret = process_order(page, context, player_id, diamonds, order_id)
                # ret = (success, msg, img, checkout_clicked [, financial_risk])
                result_success = ret[0]
                result_msg     = ret[1]
                result_img     = ret[2]
                checkout_done  = ret[3] if len(ret) > 3 else False
                financial_risk = ret[4] if len(ret) > 4 else "none"

                # إذا صدر أمر PAUSE أثناء المعالجة → أجبر على manual_review
                if pause_event.is_set() and result_success is False and not checkout_done:
                    result_msg = "⏸ تم الإيقاف يدوياً — يحتاج مراجعة"
                    checkout_done = False   # لم يُضغط Checkout → آمن للمراجعة

            except AbortOrderException as ae:
                result_msg = str(ae)
                print(f"    [!] {result_msg}")
                log_action(order_id, "Aborted", result_msg)
                
            except BaseException as e:
                is_kb_interrupt = isinstance(e, KeyboardInterrupt)
                is_timeout = 'TimeoutError' in str(type(e)) or 'Timeout' in str(e)
                
                result_msg = f"Crash: {type(e).__name__}: {str(e)}"[:200]
                if is_kb_interrupt:
                    result_msg = "انقطاع مفاجئ أو توقف إجباري للبوت"
                elif is_timeout:
                    result_msg = f"بطء في موقع ريزر جولد (Timeout). المحاولة: {attempt+1}"
                    
                print(f"    [ERR] {result_msg}")
                log_action(order_id, "Unhandled exception or Crash", result_msg)
                
                if checkout_done:
                    financial_risk = "high"
                
                try:
                    result_img = base64.b64encode(page.screenshot(full_page=True, timeout=5000)).decode('utf-8')
                except:
                    pass
                    
                # Fast Fail Logic (توجيه للمراجعة اليدوية بدلاً من الطابور)
                if is_timeout and not checkout_done:
                    # المستخدم طلب: لا ترجع للطابور أبداً. أي تأخير يذهب للمراجعة.
                    pass
                elif not is_kb_interrupt:
                    try:
                        ensure_logged_in(page, context)
                    except:
                        pass
                else:
                    bot_draining = True
            finally:
                current_processing_order_id = None
                bot_status = "online"   # ← إعادة الحالة لـ online بعد انتهاء الطلب

                if abort_event.is_set():
                    abort_event.clear()
                    print(f"    [!] تم تخطي تحديث الحالة لأن الطلب تم إلغاؤه من اللوحة.")
                    print(f"    [*] عودة لصفحة الشحن...")
                    go_to_topup_page(page)
                    time.sleep(3)
                    continue

                order_duration = int(time.time() - order_start_time)

                if result_success:
                    success_count        += 1
                    consecutive_failures  = 0
                    bot_balance_status    = "sufficient"
                    update_order_status(order_id, 'completed',
                                        evidence_base64=result_img,
                                        checkout_clicked=True,
                                        financial_risk="none",
                                        duration_seconds=order_duration)
                    log_action(order_id, "Order COMPLETED", f"✓ ({order_duration}s)")
                    print(f"    [OK] ✓ الطلب {order_id} اكتمل في {order_duration} ثانية!")
                elif financial_risk == "critical":
                    fail_count           += 1
                    consecutive_failures += 1
                    bot_balance_status    = "insufficient"
                    update_order_status(order_id, 'manual_review', result_msg,
                                        evidence_base64=result_img,
                                        checkout_clicked=checkout_done,
                                        financial_risk="critical")
                    log_action(order_id, "Order → MANUAL REVIEW (Insufficient Balance)", result_msg)
                    print(f"    [!!!] رصيد غير كافٍ — تم التوجيه للمراجعة!")
                elif financial_risk == "balance_changed":
                    # الرصيد تغير لكن ريزر لم تؤكد النجاح
                    # → مراجعة يدوية مع عدم إبلاغ العميل (يبقى "قيد المعالجة")
                    fail_count           += 1
                    update_order_status(order_id, 'manual_review', result_msg,
                                        evidence_base64=result_img,
                                        checkout_clicked=True,
                                        financial_risk="balance_changed")
                    log_action(order_id, "Order → MANUAL REVIEW (BALANCE CHANGED)", result_msg)
                    print(f"    [⚠] الطلب {order_id} → مراجعة يدوية (الرصيد تغير لكن لا تأكيد!)")
                elif checkout_done:
                    # checkout ضُغط لكن النتيجة غير مؤكدة → manual_review
                    fail_count           += 1
                    update_order_status(order_id, 'manual_review', result_msg,
                                        evidence_base64=result_img,
                                        checkout_clicked=True,
                                        financial_risk="high")
                    log_action(order_id, "Order → MANUAL REVIEW", result_msg)
                    print(f"    [!!] الطلب {order_id} → manual_review")
                elif pause_event.is_set():
                    # تم الإيقاف يدوياً قبل الوصول لـ Checkout → manual_review (آمن)
                    fail_count           += 1
                    update_order_status(order_id, 'manual_review', result_msg,
                                        evidence_base64=result_img,
                                        checkout_clicked=False,
                                        financial_risk="none")
                    log_action(order_id, "Order → MANUAL REVIEW (PAUSED)", result_msg)
                    print(f"    [⏸] الطلب {order_id} → manual_review (إيقاف يدوي)")
                elif 'Timeout' in result_msg or 'بطء' in result_msg:
                    # تم تحويل حالات البطء للمراجعة اليدوية بناءً على طلبك
                    fail_count           += 1
                    update_order_status(order_id, 'manual_review', result_msg,
                                        evidence_base64=result_img,
                                        checkout_clicked=False,
                                        financial_risk="none")
                    log_action(order_id, "Order → MANUAL REVIEW (TIMEOUT)", result_msg)
                    print(f"    [⏳] الطلب {order_id} → manual_review (بسبب البطء/Timeout)")
                else:
                    fail_count           += 1
                    consecutive_failures += 1
                    update_order_status(order_id, 'manual_review', result_msg,
                                        evidence_base64=result_img,
                                        checkout_clicked=False,
                                        financial_risk="none")
                    log_action(order_id, "Order → MANUAL REVIEW (UNKNOWN ERROR)", result_msg)
                    print(f"    [FAIL] الطلب {order_id} فشل وتوجه للمراجعة: {result_msg[:60]}")

                # ── إيقاف البوت إذا تكررت الأخطاء المجهولة المتتالية ──
                if consecutive_failures > 2 and not bot_paused and bot_status != 'error_paused':
                    bot_status = 'error_paused'
                    bot_paused = True
                    pause_event.set()
                    print(f"\n[!!!] تم إيقاف البوت مؤقتاً لتجاوز الأخطاء المتتالية الحد المسموح (3 أخطاء)!")
                    log_action("BOT_SYSTEM", "Auto-paused", f"3 consecutive unknown errors")


                if consecutive_failures >= 3:
                    print(f"    [!!!] {consecutive_failures} فشل متتالي — يرجى الفحص!")

                if getattr(locals().get('e'), '__class__', None) == KeyboardInterrupt:
                    print(f"    [x] تم التعامل مع الإغلاق الإجباري. سيتم إيقاف السكربت.")
                    sys.exit(0)

                print(f"    [*] عودة لصفحة الشحن...")
                go_to_topup_page(page)

            time.sleep(3)

if __name__ == "__main__":
    main()
