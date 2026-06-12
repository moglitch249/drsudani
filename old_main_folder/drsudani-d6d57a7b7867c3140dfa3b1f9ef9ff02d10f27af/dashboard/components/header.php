<?php
/**
 * رأس الصفحة المشترك
 * @var string $pageTitle عنوان الصفحة
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <title><?= isset($pageTitle) ? clean($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components.css?v=<?= time() ?>">
    <?php if (($_SESSION['role'] ?? '') !== 'admin'): ?>
    <style>
    #ss-block{position:fixed;inset:0;z-index:2147483647;background:rgba(15,23,42,.92);display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px;backdrop-filter:blur(18px);}
    #ss-block svg{stroke:#ef4444;fill:none;stroke-width:2;}
    #ss-block p{color:#f1f5f9;font-size:16px;font-weight:700;margin:0;text-align:center;}
    #ss-block small{color:#94a3b8;font-size:13px;}
    body.ss-blur *:not(#ss-block){filter:blur(14px)!important;pointer-events:none!important;}
    </style>
    <div id="ss-block">
        <svg width="52" height="52" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p>⛔ التقاط الشاشة غير مسموح به</p>
        <small>يتم تسجيل هذا الإجراء في سجلات النظام</small>
    </div>
    <script>
    (function(){
        var bl=document.getElementById('ss-block'),t=null;
        function go(){
            document.body.classList.add('ss-blur');bl.style.display='flex';
            clearTimeout(t);t=setTimeout(function(){bl.style.display='none';document.body.classList.remove('ss-blur');},1800);
        }
        document.addEventListener('keyup',function(e){
            if(e.key==='PrintScreen'||e.keyCode===44){try{navigator.clipboard.writeText('');}catch(ex){}go();}
        });
    })();
    </script>
    <?php endif; ?>
</head>
<body>
<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-theme');
    }
</script>

<div class="app-layout">
