<?php
session_start();

/* =======================
   Security Headers
======================= */
header('X-Frame-Options: DENY');
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
 header("Permissions-Policy: geolocation=(self), bluetooth=(self), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:;");

/* منع الكاش عشان بعد تسجيل الخروج مايرجعش بالباك */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* =======================
   Auth Check
======================= */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

/* حماية ضد Session Fixation */
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = true;
}

/* =======================
   Logout
======================= */
if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header('Location: login.php');
    exit();
}

/* =======================
   User Data
======================= */
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'مستخدم', ENT_QUOTES, 'UTF-8');

/* تنظيف كوكيز الثيم */
$allowedThemes = ['light', 'dark'];
$userTheme = $_COOKIE['theme'] ?? 'light';
$userTheme = in_array($userTheme, $allowedThemes, true) ? $userTheme : 'light';

/* تنظيف كوكيز حجم الخط */
$allowedFontSizes = ['small', 'medium', 'large'];
$userFontSize = $_COOKIE['fontSize'] ?? 'medium';
$userFontSize = in_array($userFontSize, $allowedFontSizes, true) ? $userFontSize : 'medium';

/* =======================
   Dashboard Cards
======================= */
$cards = [
    ['👥', 'العملاء', 'clients/index.php'],
    ['🧾', 'العمليات', 'operations/index.php'],  
    ['💳', 'سيستم الدفع للتجار', 'payments/index.php'],

];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة التحكم</title>

<style>
:root{--p:#2563eb;--bg:#f8fafc;--c:#fff;--t:#1e293b;--b:#e2e8f0;--s:rgba(0,0,0,.1)}
[data-theme="dark"]{--bg:#0f172a;--c:#1e293b;--t:#f1f5f9;--b:#334155;--s:rgba(0,0,0,.3)}
[data-fontsize="small"]{font-size:14px}
[data-fontsize="medium"]{font-size:16px}
[data-fontsize="large"]{font-size:18px}

body{font-family:system-ui,sans-serif;margin:0;padding:15px;background:var(--bg);color:var(--t);min-height:100vh;transition:.2s}
.container{max-width:1000px;margin:auto;background:var(--c);border-radius:12px;box-shadow:0 8px 25px var(--s)}

.header{padding:20px;border-bottom:1px solid var(--b);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.welcome{font-size:1.2em;font-weight:600;color:var(--p)}
.controls{display:flex;gap:5px;flex-wrap:wrap}
.btn{padding:8px 12px;background:var(--p);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9em;transition:.2s}
.btn:hover{opacity:.9}

.content{padding:20px}
h1{text-align:center;margin-bottom:25px;color:var(--p)}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:15px;margin:20px 0}
.card{padding:20px;background:var(--bg);border:1px solid var(--b);border-radius:10px;text-align:center;cursor:pointer;transition:.2s;position:relative}
.card:hover{transform:translateY(-3px);box-shadow:0 5px 15px var(--s);border-color:var(--p)}
.icon{font-size:35px;margin-bottom:10px}
.card h3{margin:10px 0 15px}
.card a{padding:8px 16px;background:var(--p);color:#fff;text-decoration:none;border-radius:6px;display:inline-block}

.footer{padding:15px;border-top:1px solid var(--b);text-align:center;font-size:.9em;opacity:.8}
.footer a{color:var(--p);text-decoration:none;margin:0 10px}

@media(max-width:768px){.grid{grid-template-columns:repeat(2,1fr)}.header{flex-direction:column;text-align:center}}
@media(max-width:480px){.grid{grid-template-columns:1fr}}
</style>
</head>

<body data-theme="<?=$userTheme?>" data-fontsize="<?=$userFontSize?>">
<div class="container">

    <div class="header">
        <div class="welcome">مرحباً <?=$userName?></div>

        <div class="controls">
            <button class="btn" id="themeBtn" onclick="toggleTheme()">🌙</button>
            <button class="btn" onclick="location='dashboard1.php'">📊 سجلات</button>
            <button class="btn" onclick="if(confirm('تسجيل خروج؟'))location='?logout=1'">🚪 خروج</button>
        </div>
    </div>

    <div class="content">
        <h1>لوحة التحكم</h1>

        <div class="grid">
            <?php foreach($cards as $card): 
                $icon = $card[0];
                $title = htmlspecialchars($card[1], ENT_QUOTES, 'UTF-8');
                $link = htmlspecialchars($card[2], ENT_QUOTES, 'UTF-8');
            ?>
            <div class="card" onclick="location='<?=$link?>'">
                <div class="icon"><?=$icon?></div>
                <h3><?=$title?></h3>
                <a href="<?=$link?>" onclick="event.stopPropagation();">فتح</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="footer">
        © <?=date('Y')?> |
        <a href="DeviceInfoCollector.php">📱 سجلات الدخول</a>
        <a href="?logout=1" onclick="return confirm('تسجيل خروج؟')">🚪 تسجيل خروج</a>
    </div>

</div>

<script src="device_collector.js"></script>
<script>
/* زر الثيم يتغير شكله */
function updateThemeIcon() {
    const btn = document.getElementById('themeBtn');
    btn.textContent = document.body.dataset.theme === 'dark' ? '☀️' : '🌙';
}

/* تبديل الثيم */
function toggleTheme() {
    const theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
    document.body.dataset.theme = theme;
    document.cookie = `theme=${theme};path=/;max-age=31536000;samesite=Lax`;
    updateThemeIcon();
}

/* تشغيل الايقونة حسب الثيم الحالي */
updateThemeIcon();

/* مهلة الجلسة (30 دقيقة) */
let idleTimer;

function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
        if (confirm('انتهت الجلسة. إعادة تحميل؟')) location.reload();
    }, 1800000);
}

['click','mousemove','keypress','scroll','touchstart'].forEach(e => {
    document.addEventListener(e, resetIdleTimer, { passive: true });
});

resetIdleTimer();

/* اختصارات لوحة المفاتيح */
document.addEventListener('keydown', e => {
    if (e.ctrlKey) {
        if (e.key === 't') toggleTheme();
        if (e.key === 'd') location.href='dashboard1.php';
        if (e.key === 'l' && confirm('تسجيل خروج؟')) location.href='?logout=1';
    }
});
</script>

</body>
</html>
