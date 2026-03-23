<?php
session_start();
require_once "../config/operations.php";

// المصادقة
if (!isset($_SESSION['user_id'])) header("Location: login.php");
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// الثيم والإحصائيات
$theme = $_COOKIE['theme'] ?? 'light';
$username = htmlspecialchars($_SESSION['username'] ?? 'مستخدم');
$stats = ['pending' => 0, 'active' => 0];

if ($conn_operations) {
    // استعلام واحد للحصول على الإحصائيات
    $tables = $conn_operations->query("
        SELECT 
            (SELECT COUNT(*) FROM operations WHERE status='pending') as pending,
            (SELECT COUNT(*) FROM services WHERE status='active') as active
    ");
    if ($tables && $row = $tables->fetch_assoc()) {
        $stats['pending'] = (int)$row['pending'];
        $stats['active'] = (int)$row['active'];
    }
    $conn_operations->close();
}

// قائمة العناصر
$cards = [
    ['➕','فاتورة جديدة','إنشاء فاتورة جديدة','manual.php','s','فتح'],
    ['⏳','فواتير معلقة','عملية معلقة','pending.php',$stats['pending'],''],
    ['🛠️','الخدمات','خدمة نشطة','services.php',$stats['active'],''],
    ['📊','التقارير','عرض التقارير','reports.php','g','فتح'],
    ['✨','إضافة خدمة','إضافة خدمة جديدة','add_service.php','s','إضافة'],
    ['👥','العملاء','إدارة العملاء','clients.php','p','فتح']
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="<?= $theme ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة التحكم</title>
<style>
:root{--p:#2563eb;--s:#16a34a;--g:#64748b;--d:#dc2626;
      --bg:#f8fafc;--card:#fff;--text:#1e293b;--border:#e5e7eb;}
[data-theme="dark"]{--bg:#0f172a;--card:#1e293b;--text:#f1f5f9;--border:#374151;}

*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Tahoma,Arial;background:var(--bg);color:var(--text);padding:15px;}
.container{max-width:1200px;margin:auto;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px;}
.user-info{background:var(--p);color:#fff;padding:6px 15px;border-radius:20px;}
.controls{display:flex;gap:8px;}
.btn{padding:8px 16px;border:none;border-radius:8px;cursor:pointer;color:#fff;}
.btn:hover{opacity:.9;}
.btn-p{background:var(--p);}.btn-s{background:var(--s);}.btn-g{background:var(--g);}

h1{text-align:center;margin:20px 0 30px;color:var(--p);}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px;
      text-align:center;cursor:pointer;transition:.2s;}
.card:hover{transform:translateY(-3px);box-shadow:0 5px 15px rgba(0,0,0,.1);}
.card-icon{font-size:2.2rem;}
.card-title{font-size:1.2rem;margin:10px 0;font-weight:600;}
.card-num{font-size:2rem;font-weight:700;margin:10px 0;color:var(--p);}
.card-desc{color:var(--g);font-size:.9rem;margin-bottom:15px;}

.logout{position:fixed;bottom:20px;left:20px;background:var(--d);color:#fff;
        padding:10px 18px;border-radius:10px;text-decoration:none;}

@media (max-width:768px){
    .header{flex-direction:column;}.grid{grid-template-columns:1fr;}
    .logout{position:relative;bottom:auto;left:auto;margin:20px auto 0;width:fit-content;}
}
</style>
</head>

<body>
<a href="?logout=1" class="logout" onclick="return confirm('تأكيد الخروج؟')">🚪 خروج</a>

<div class="container">
    <div class="header">
        <div class="user-info"><?= $username ?></div>
        <div class="controls">
            <button class="btn btn-p" onclick="toggleTheme()">🌙الوضع</button>
            <button class="btn btn-s" onclick="location.reload()">🔄تحديث الصفحه</button>
            <button class="btn btn-g" onclick="location='../dashboard.php'">🏠الصفحه الرئسيه</button>
        </div>
    </div>

    <h1>📋 لوحة تحكم العمليات </h1>

    <div class="grid">
        <?php foreach($cards as $c): ?>
        <div class="card" onclick="location='<?= $c[3] ?>'">
            <div class="card-icon"><?= $c[0] ?></div>
            <div class="card-title"><?= $c[1] ?></div>
            <div class="card-desc"><?= $c[2] ?></div>
            <?php if(is_numeric($c[4])): ?>
                <div class="card-num"><?= $c[4] ?></div>
            <?php else: ?>
                <button class="btn btn-<?= $c[4] ?>"><?= $c[5] ?></button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// الثيم
function toggleTheme(){
    const t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    document.cookie = `theme=${t};path=/;max-age=31536000`;
}

// مهلة الجلسة
let t;
function resetTimer(){
    clearTimeout(t);
    t = setTimeout(()=>{
        if(confirm('انتهت المهلة. البقاء؟')) resetTimer();
        else location.href='?logout=1';
    },1800000); // 30 دقيقة
}
['click','mousemove','keypress'].forEach(e=>document.addEventListener(e,resetTimer));
resetTimer();
</script>
</body>
</html>