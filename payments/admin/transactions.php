<?php
/* =================================================
   عرض الأخطاء بالكامل (للتطوير فقط)
================================================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =================================================
   جلسة + حماية دخول
================================================= */
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

/* =================================================
   اتصال قاعدة البيانات (بدون require خارجي)
   عدّل البيانات حسب سيرفرك
================================================= */
$db_host = "sql303.infinityfree.com";
$db_user = "if0_40974310";
$db_pass = "YOUR_DB_PASSWORD";
$db_name = "if0_40974310_payments";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

/* =================================================
   الفلاتر و Search
================================================= */
$where  = "1=1";
$params = [];
$types  = "";

if (!empty($_GET['q'])) {
    $q = "%" . trim($_GET['q']) . "%";
    $where .= " AND (invoice_no LIKE ? OR service_name LIKE ? OR details LIKE ?)";
    array_push($params, $q, $q, $q);
    $types .= "sss";
}

if (!empty($_GET['status'])) {
    $where .= " AND status=?";
    $params[] = $_GET['status'];
    $types .= "s";
}

if (!empty($_GET['service'])) {
    $where .= " AND service_name=?";
    $params[] = $_GET['service'];
    $types .= "s";
}

if (!empty($_GET['from_date'])) {
    $where .= " AND DATE(created_at) >= ?";
    $params[] = $_GET['from_date'];
    $types .= "s";
}

if (!empty($_GET['to_date'])) {
    $where .= " AND DATE(created_at) <= ?";
    $params[] = $_GET['to_date'];
    $types .= "s";
}

/* =================================================
   جلب البيانات
================================================= */
$sql = "
SELECT 
    id, invoice_no, created_at, service_name,
    details, amount, total, paid_amount,
    status, payment_company, payment_type, paid_at
FROM operations
WHERE $where
ORDER BY created_at DESC
LIMIT 500
";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$sum_amount = 0;
$sum_paid   = 0;
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $sum_amount += (float)$r['amount'];
    $sum_paid   += (float)$r['paid_amount'];
}

/* =================================================
   خدمات مميزة للفلتر
================================================= */
$servicesRes = $conn->query("SELECT DISTINCT service_name FROM operations ORDER BY service_name");
$services = $servicesRes->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>📄 جميع المعاملات</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{background:#f1f5f9;font-family:tahoma;margin:0;padding:10px}
.container{background:#fff;border-radius:12px;padding:15px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
h2{text-align:center;margin-bottom:10px;color:#1e293b}
.btn{padding:7px 12px;border-radius:7px;border:none;color:#fff;font-size:12px;text-decoration:none;cursor:pointer}
.btn-primary{background:#2563eb}
.btn-success{background:#16a34a}
.btn-danger{background:#dc2626}
.btn-gray{background:#6b7280}
.search{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.search input,.search select{padding:7px;border-radius:6px;border:1px solid #cbd5e1;font-size:12px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:7px;border-bottom:1px solid #e2e8f0;text-align:center}
th{background:#f8fafc}
.badge{padding:3px 8px;border-radius:10px;font-size:11px;color:#fff}
.pending{background:#f59e0b}
.paid{background:#16a34a}
.amount{font-weight:bold;color:#2563eb}
.footer{margin-top:10px;text-align:center;color:#475569;font-size:12px}
</style>
</head>
<body>

<div class="container">
<h2>📄 جميع المعاملات</h2>

<form class="search" method="GET">
<input name="q" placeholder="بحث (فاتورة/خدمة/تفاصيل)" value="<?= htmlentities($_GET['q'] ?? '') ?>">

<select name="status">
<option value="">كل الحالات</option>
<option value="pending" <?= ($_GET['status']??'')=='pending'?'selected':'' ?>>معلقة</option>
<option value="paid" <?= ($_GET['status']??'')=='paid'?'selected':'' ?>>مدفوعة</option>
</select>

<select name="service">
<option value="">كل الخدمات</option>
<?php foreach($services as $s): ?>
<option value="<?= htmlentities($s['service_name']) ?>" <?= ($_GET['service']??'')==$s['service_name']?'selected':'' ?>>
    <?= htmlentities($s['service_name']) ?>
</option>
<?php endforeach; ?>
</select>

<input type="date" name="from_date" value="<?= htmlentities($_GET['from_date'] ?? '') ?>">
<input type="date" name="to_date" value="<?= htmlentities($_GET['to_date'] ?? '') ?>">

<button class="btn btn-primary">بحث</button>
<a href="transactions.php" class="btn btn-gray">إعادة</a>
</form>

<?php if(empty($rows)): ?>
<p style="text-align:center;color:#64748b">لا توجد معاملات</p>
<?php else: ?>

<table>
<thead>
<tr>
<th>#</th>
<th>فاتورة</th>
<th>التاريخ</th>
<th>الخدمة</th>
<th>المبلغ</th>
<th>المدفوع</th>
<th>الحالة</th>
<th>شركة الدفع</th>
<th>طريقة الدفع</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlentities($r['invoice_no']) ?></td>
<td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
<td><?= htmlentities($r['service_name']) ?></td>
<td class="amount"><?= number_format($r['amount'],0) ?></td>
<td><?= number_format($r['paid_amount'],0) ?></td>
<td>
<span class="badge <?= $r['status']=='paid'?'paid':'pending' ?>">
<?= $r['status']=='paid'?'مدفوعة':'معلقة' ?>
</span>
</td>
<td><?= htmlentities($r['payment_company'] ?? '-') ?></td>
<td><?= htmlentities($r['payment_type'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="footer">
عدد العمليات: <?= count($rows) ?> | 
إجمالي المبالغ: <?= number_format($sum_amount,0) ?> | 
إجمالي المدفوع: <?= number_format($sum_paid,0) ?>
</div>

<?php endif; ?>

<a href="../dashboard.php" class="btn btn-success" style="display:block;margin-top:12px;text-align:center">
⬅ العودة للوحة التحكم
</a>
</div>

</body>
</html>
