import re

with open("php_system/index.php", "r", encoding="utf-8") as f:
    content = f.read()

# Replace fonts 1
content = content.replace(
    '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">',
    '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">'
)

# Replace fonts 2
content = content.replace(
    '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">',
    '<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">'
)

# Replace Login Style and structure
login_style_pattern = re.compile(r'<style>[\s\S]*?<div class="login-card">')

new_login_style = """<style>
        :root {
            --bg:     #0E1016;
            --bg2:    #14161F;
            --bg3:    #1C1F2E;
            --bg4:    #242840;
            --border: #252836;
            --border2:#2F3347;
            --text:   #DDE1F0;
            --text2:  #7B82A0;
            --text3:  #4A5070;
            --green:  #14532D; --gl: #4ADE80; --gg: rgba(74,222,128,0.12);
            --amber:  #78350F; --al: #FCD34D; --ag: rgba(252,211,77,0.12);
            --red:    #7F1D1D; --rl: #F87171; --rg: rgba(248,113,113,0.12);
            --purple: #3B0764; --pl: #C084FC; --pg: rgba(192,132,252,0.12);
            --cyan:   #1E3A5F; --cl: #60A5FA;
            --razer:  #34D399;
            --brand-color: var(--razer);
            --mono:   'JetBrains Mono', monospace;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .login-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.4);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-text {
            color: var(--text);
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .logo-accent {
            color: var(--razer);
        }

        .subtitle {
            font-size: 12px;
            color: var(--text2);
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        .input-group {
            margin-bottom: 24px;
        }

        .input-label {
            display: block;
            font-size: 10px;
            font-weight: 500;
            color: var(--text3);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-field {
            width: 100%;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 10px 14px;
            font-size: 13px;
            transition: all 0.15s;
            text-align: left;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--cl);
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.12);
        }

        .btn-submit {
            width: 100%;
            background: #166534;
            border: 1px solid #14532D;
            color: #4ADE80;
            border-radius: 8px;
            padding: 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #15803D;
        }

        .error-msg {
            background: rgba(248, 113, 113, 0.08);
            border: 1px solid rgba(248, 113, 113, 0.25);
            color: #F87171;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="login-card">"""
content = login_style_pattern.sub(new_login_style, content, count=1)

# Main app style replacement
app_style_pattern = re.compile(r'<style>\s*\*\{margin:0;padding:0;box-sizing:border-box\}[\s\S]*?</style>')
new_app_style = """<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
  --bg:     #0E1016;
  --bg2:    #14161F;
  --bg3:    #1C1F2E;
  --bg4:    #242840;
  --border: #252836;
  --border2:#2F3347;
  --text:   #DDE1F0;
  --text2:  #7B82A0;
  --text3:  #4A5070;
  --green:  #14532D; --gl: #4ADE80; --gg: rgba(74,222,128,0.12);
  --amber:  #78350F; --al: #FCD34D; --ag: rgba(252,211,77,0.12);
  --red:    #7F1D1D; --rl: #F87171; --rg: rgba(248,113,113,0.12);
  --purple: #3B0764; --pl: #C084FC; --pg: rgba(192,132,252,0.12);
  --cyan:   #1E3A5F; --cl: #60A5FA;
  --razer:  #34D399;
  --mono:   'JetBrains Mono', monospace;
}
html { font-family: 'Cairo', sans-serif; background: var(--bg); color: var(--text); font-size: 13px; line-height: 1.5; font-weight: 400; }
body { min-height: 100vh; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

/* NAV */
nav { display: flex; align-items: center; padding: 0 16px; background: var(--bg2); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; min-height: 48px; gap: 0; }
.brand { font-size: 13px; font-weight: 500; color: var(--razer); letter-spacing: 0.3px; padding-left: 16px; border-left: 1px solid var(--border); margin-left: 8px; white-space: nowrap; }
.sys-tabs { display: flex; flex: 1; overflow-x: auto; padding: 0 6px; }
.sys-tab { padding: 0 14px; height: 48px; background: transparent; border: none; border-bottom: 2px solid transparent; color: var(--text2); font-size: 13px; cursor: pointer; white-space: nowrap; transition: color 0.15s; display: flex; align-items: center; gap: 6px; font-weight: 400; }
.sys-tab:hover { color: var(--text); }
.sys-tab.active { color: var(--text); border-bottom-color: var(--cl); }
.sys-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.nav-right { display: flex; align-items: center; gap: 10px; padding-right: 4px; border-right: 1px solid var(--border); margin-right: 8px; }
.nav-clock { font-family: var(--mono); font-size: 11px; color: var(--text3); }

/* PAGE TABS */
.ptabs { display: flex; background: var(--bg2); border-bottom: 1px solid var(--border); }
.ptab { padding: 10px 18px; background: transparent; border: none; border-bottom: 2px solid transparent; color: var(--text2); font-size: 13px; font-weight: 400; cursor: pointer; transition: all 0.15s; }
.ptab:hover { color: var(--text); background: var(--bg3); }
.ptab.active { color: var(--text); border-bottom-color: var(--cl); }

/* PAGES */
.page { display: none; padding: 14px 16px; max-width: 1700px; margin: 0 auto; }
.page.active { display: block; }

/* SECTION HEADER */
.shdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
.shdr-title { font-size: 10px; font-weight: 500; color: var(--text3); text-transform: uppercase; letter-spacing: 0.7px; display: flex; align-items: center; gap: 8px; }

/* STATS BAR */
.stats-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.stat-cell { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; flex: 1; min-width: 110px; display: flex; flex-direction: column; gap: 3px; }
.stat-lbl { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.7px; font-weight: 500; }
.stat-val { font-size: 24px; font-weight: 500; font-family: var(--mono); }
.stat-val.g { color: var(--gl); } .stat-val.a { color: var(--al); } .stat-val.r { color: var(--rl); }
.stat-actions-cell { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; flex: 1; min-width: 110px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

/* BOTS GRID */
.bots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 14px; }
.bot-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; transition: all 0.15s; }
.bot-card:hover { border-color: var(--border2); transform: translateY(-1px); }
.bot-top { display: flex; align-items: center; gap: 8px; padding: 9px 12px; background: var(--bg3); border-bottom: 1px solid var(--border); }
.bot-id { font-weight: 500; font-size: 13px; font-family: var(--mono); flex: 1; text-transform: uppercase; }
.bot-port { font-size: 10px; color: var(--text3); font-family: var(--mono); }
.bot-body { padding: 8px 12px; display: flex; flex-direction: column; gap: 3px; }
.brow { display: flex; justify-content: space-between; align-items: center; padding: 2px 0; border-bottom: 1px solid var(--border); font-size: 11px; }
.brow:last-child { border: none; }
.brow-label { color: var(--text3); font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; }
.brow-val { font-family: var(--mono); font-weight: 400; }
.bot-pending { padding: 4px 12px; font-size: 10px; color: var(--al); background: var(--ag); border-bottom: 1px solid var(--border); }
.bot-foot { padding: 8px 12px; border-top: 1px solid var(--border); }

/* TWO-PANEL LAYOUT */
.panels-row { display: grid; grid-template-columns: 1fr 370px 370px; gap: 12px; align-items: start; }
@media(max-width:1400px) { .panels-row { grid-template-columns: 1fr 370px; } }
@media(max-width:1100px) { .panels-row { grid-template-columns: 1fr; } }
.panel { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; overflow: hidden; }

/* TABLE */
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 6px; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead tr { background: transparent; }
th { padding: 8px 12px; text-align: right; font-weight: 500; color: var(--text2); font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; white-space: nowrap; border-bottom: 1px solid var(--border2); }
th.sortable { cursor: pointer; user-select: none; }
th.sortable:hover { color: var(--text); background: var(--bg4); }
.sort-ind { font-size: 9px; color: var(--cl); margin-right: 2px; }
td { padding: 8px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; white-space: nowrap; transition: background 0.15s; }
tr:last-child td { border: none; }
tr:hover td { background: var(--bg3); }
tr.tr-processing td { background: rgba(252,211,77,0.04); }
tr.tr-manual_review td { background: rgba(192,132,252,0.05); }
tr.tr-completed td { background: rgba(74,222,128,0.04); }
tr.tr-failed td { background: rgba(248,113,113,0.04); }
tr.tr-stuck td { background: rgba(248,113,113,0.08); }
tr.tr-processing:hover td, tr.tr-manual_review:hover td, tr.tr-completed:hover td, tr.tr-failed:hover td, tr.tr-stuck:hover td { background: var(--bg3); }
.mono { font-family: var(--mono); }
.tdim { color: var(--text3); }
.tempty { text-align: center; padding: 20px; color: var(--text3); }

/* SEARCH INPUT */
.srch { padding: 8px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: all 0.15s; width: 220px; }
.srch:focus { border-color: var(--cl); outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }

/* REVIEW PANEL */
.review-list { display: flex; flex-direction: column; gap: 8px; max-height: 640px; overflow-y: auto; margin-top: 10px; }
.rcard { background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; padding: 10px; }
.rcard-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.rcard-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: var(--text2); margin-bottom: 6px; }
.rcard-reason { font-size: 10px; color: var(--text3); margin-bottom: 8px; line-height: 1.5; border-right: 2px solid var(--border2); padding-right: 7px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.rcard-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.rcard-btns { display: flex; gap: 4px; flex-wrap: wrap; }
.review-thumb { width: 72px; height: 46px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid var(--border); transition: opacity 0.15s; }
.review-thumb:hover { opacity: 0.8; }

/* BADGES */
.badge { border-radius: 6px; font-size: 10px; font-weight: 500; padding: 2px 8px; letter-spacing: 0.2px; display: inline-block; }
.badge-sm { font-size: 9px; padding: 2px 6px; }
.badge-num { background: var(--bg3); color: var(--cl); padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 500; }
.badge-online { background: var(--gg); color: var(--gl); }
.badge-offline { background: var(--rg); color: var(--rl); }
.badge-red { background: var(--rg); color: var(--rl); }
.badge-amber { background: var(--ag); color: var(--al); }
.badge-purple { background: var(--pg); color: var(--pl); }
.bot-st-online { background: var(--gg); color: var(--gl); }
.bot-st-busy { background: var(--ag); color: var(--al); }
.bot-st-offline { background: var(--rg); color: var(--rl); }
.bot-st-paused { background: rgba(123,130,160,0.12); color: var(--text2); }
.bot-st-draining { background: var(--ag); color: var(--al); }

/* STATUS PILLS */
.pill { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 500; letter-spacing: 0.2px; }
.p-pending { background: rgba(123,130,160,0.12); color: var(--text2); }
.p-processing { background: var(--ag); color: var(--al); }
.p-completed { background: var(--gg); color: var(--gl); }
.p-failed { background: var(--rg); color: var(--rl); }
.p-manual_review { background: var(--pg); color: var(--pl); }
.p-stuck { background: var(--rg); color: var(--rl); }

/* BUTTONS */
.btn { background: transparent; border: 1px solid var(--border2); color: var(--text2); border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 500; transition: all 0.15s; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; font-family: inherit; }
.btn:hover { background: var(--bg3); color: var(--text); border-color: var(--border2); }
.btn-sm { padding: 4px 10px; font-size: 11px; }
.btn-xs { padding: 2px 8px; font-size: 10px; }
.btn-primary { background: var(--cyan); border-color: #1D4ED8; color: #93C5FD; } .btn-primary:hover { background: #2563EB; }
.btn-green { background: #14532D; border-color: #166534; color: #4ADE80; } .btn-green:hover { background: #166534; }
.btn-amber { background: #78350F; border-color: #92400E; color: #FCD34D; } .btn-amber:hover { background: #92400E; }
.btn-red { background: #7F1D1D; border-color: #991B1B; color: #F87171; } .btn-red:hover { background: #991B1B; }
.btn-ghost { }
.btn-red-ghost { color: var(--rl); border-color: var(--border2); } .btn-red-ghost:hover { background: var(--rg); border-color: var(--red); color: var(--rl); }
.acell { display: flex; gap: 4px; flex-wrap: nowrap; }

/* ACCOUNTS / SYSTEMS GRID */
.agrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
.acard { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.acard-top { display: flex; justify-content: space-between; align-items: flex-start; }
.acard-label { font-weight: 500; font-size: 14px; }
.acard-sub { font-size: 11px; color: var(--text2); margin-top: 2px; }
.acard-meta { font-size: 12px; color: var(--text2); }
.acard-foot { display: flex; gap: 6px; margin-top: 4px; }
.add-card { background: var(--bg2); border: 1px dashed var(--border2); border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text2); font-size: 13px; transition: all 0.15s; min-height: 90px; }
.add-card:hover { border-color: var(--text3); color: var(--text); background: var(--bg3); }
.bot-cmd-code { font-size: 10px; background: var(--bg); padding: 5px 8px; border-radius: 4px; color: var(--cl); display: block; word-break: break-all; font-family: var(--mono); margin-top: 4px; }

/* LOG FEED */
.log-feed { display: flex; flex-direction: column; gap: 0; height: 480px; overflow-y: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 8px 0; font-family: var(--mono); font-size: 11px; line-height: 1.8; margin-top: 10px; }
.lrow { display: flex; gap: 8px; align-items: flex-start; padding: 2px 12px; background: transparent; }
.lrow:nth-child(even) { background: rgba(255, 255, 255, 0.015); }
.ltime { color: var(--text3); white-space: nowrap; flex-shrink: 0; }
.lbot { color: var(--cl); flex-shrink: 0; }
.lact { color: var(--text2); flex: 1; overflow: hidden; }
.lres.ok { color: var(--gl); } .lres.fail { color: var(--rl); }

/* MODALS */
.moverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.moverlay.open { display: flex; }
.modal { background: var(--bg2); border: 1px solid var(--border2); border-radius: 14px; padding: 24px; width: 90%; max-width: 480px; max-height: 88vh; overflow-y: auto; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
.modal h3 { font-size: 15px; font-weight: 500; margin-bottom: 16px; color: var(--text); }
.fg { margin-bottom: 12px; }
.fg label { display: block; font-size: 10px; color: var(--text3); margin-bottom: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.7px; }
.fg input, .fg select, .fg textarea { width: 100%; padding: 8px 12px; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: all 0.15s; }
.fg input:focus, .fg select:focus, .fg textarea:focus { border-color: var(--cl); outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }
.fg textarea { resize: vertical; height: 60px; }
.frow { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.mfooter { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 16px; }
.warnbox { background: rgba(248,113,113,0.08); border: 1px solid rgba(248,113,113,0.25); border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #F87171; margin-bottom: 16px; line-height: 1.5; font-weight: 500; }

/* TOAST */
.toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(60px); background: var(--bg3); border: 1px solid var(--border2); border-radius: 8px; padding: 12px 24px; font-size: 13px; opacity: 0; transition: all 0.25s; z-index: 9999; pointer-events: none; white-space: nowrap; box-shadow: 0 10px 30px rgba(0,0,0,0.3); font-weight: 500; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* SCREENSHOT OVERLAY */
#ss-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 2000; display: none; align-items: center; justify-content: center; cursor: zoom-out; backdrop-filter: blur(4px); }
#ss-img { max-width: 96vw; max-height: 94vh; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }

/* BALANCE BADGES */
.bal-sufficient { background: var(--gg); color: var(--gl); }
.bal-low { background: var(--ag); color: var(--al); }
.bal-insufficient { background: var(--rg); color: var(--rl); }
.bal-unknown { background: rgba(123,130,160,0.12); color: var(--text3); }

/* ALERTS */
.critical-alerts {
  display: none;
  background: rgba(248,113,113,0.08);
  border-bottom: 1px solid rgba(248,113,113,0.25);
  color: #F87171;
  padding: 0 16px;
  text-align: center;
  font-weight: 500;
  font-size: 12px;
  z-index: 999;
  position: sticky;
  top: 0;
  height: 36px;
  line-height: 36px;
}
.warning-alerts {
  display: none;
  background: rgba(252,211,77,0.08);
  border-bottom: 1px solid rgba(252,211,77,0.25);
  color: #FCD34D;
  padding: 0 16px;
  text-align: center;
  font-weight: 500;
  font-size: 12px;
  z-index: 999;
  position: sticky;
  top: 0;
  height: 36px;
  line-height: 36px;
}
.alert-icon { font-size: 14px; margin: 0 8px; vertical-align: middle; opacity: 0.8; }
</style>"""
content = app_style_pattern.sub(new_app_style, content, count=1)

with open("php_system/index.php", "w", encoding="utf-8") as f:
    f.write(content)
print("Done styling index.php")
