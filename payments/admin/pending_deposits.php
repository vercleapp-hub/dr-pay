<?php
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors',1);

/* حالة الإيداعات */
$setting = $conn->query("
  SELECT value FROM settings 
  WHERE name='deposits_enabled'
")->fetch_assoc();

$deposits_enabled = $setting['value'] ?? 1;

/* تغيير حالة الإيداعات */
if (isset($_GET['toggle'])) {
    $new = $deposits_enabled ? 0 : 1;
    $conn->query("
      UPDATE settings 
      SET value='$new' 
      WHERE name='deposits_enabled'
    ");
    header("Location: pending_deposits.php");
    exit;
}

/* قبول الإيداع */
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];

    $dep = $conn->query("
      SELECT * FROM deposits 
      WHERE id=$id AND status='pending'
    ")->fetch_assoc();

    if ($dep) {
        $conn->begin_transaction();

        // إضافة الرصيد
        $conn->query("
          UPDATE users 
          SET balance = balance + {$dep['amount']} 
          WHERE id={$dep['user_id']}
        ");

        // تحديث الإيداع
        $conn->query("
          UPDATE deposits 
          SET status='approved' 
          WHERE id=$id
        ");

        $conn->commit();
    }

    header("Location: pending_deposits.php");
    exit;
}

/* رفض الإيداع */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject'])) {
    $id   = (int)$_POST['id'];
    $note = trim($_POST['note']);

    $stmt = $conn->prepare("
      UPDATE deposits 
      SET status='rejected', note=? 
      WHERE id=? AND status='pending'
    ");
    $stmt->bind_param("si", $note, $id);
    $stmt->execute();

    header("Location: pending_deposits.php");
    exit;
}

/* جلب الإيداعات */
$deposits = $conn->query("
SELECT 
 d.*, u.name, u.username
FROM deposits d
JOIN users u ON u.id=d.user_id
ORDER BY d.id DESC
");
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إدارة الإيداعات</title>
<style>
body{font-family:tahoma;background:#f3f5f7}
.container{max-width:1200px;margin:auto;padding:15px}
.box{background:#fff;padding:15px;border-radius:10px;margin-bottom:15px}
table{width:100%;border-collapse:collapse}
th,td{padding:6px;border-bottom:1px solid #ddd;text-align:center}
th{background:#eee}
.btn{padding:5px 8px;border-radius:5px;color:#fff;text-decoration:none}
.green{background:#28a745}
.red{background:#dc3545}
.orange{background:#ff9800}
.small{font-size:12px;color:#555}
.disabled{background:#999}
</style>
</head>

<body>

<div class="container">

<div class="box">
<h2>💰 إدارة الإيداعات</h2>

<a 
 class="btn <?= $deposits_enabled?'red':'green' ?>" 
 href="?toggle=1"
>
<?= $deposits_enabled ? '⛔ إيقاف الإيداعات' : '▶ تفعيل الإيداعات' ?>
</a>
</div>

<div class="box">
<table>
<tr>
<th>#</th>
<th>رقم الإيداع</th>
<th>التاجر</th>
<th>المبلغ</th>
<th>الإيصال</th>
<th>الحالة</th>
<th>تاريخ</th>
<th>تحكم</th>
</tr>

<?php while($d=$deposits->fetch_assoc()): ?>
<tr>
<td><?= $d['id'] ?></td>
<td><?= $d['reference'] ?: 'DEP-'.$d['id'] ?></td>
<td><?= htmlspecialchars($d['name']) ?><br><span class="small"><?= $d['username'] ?></span></td>
<td><?= $d['amount'] ?> EGP</td>
<td>
<a href="../uploads/receipts/<?= $d['receipt'] ?>" target="_blank">📷</a>
</td>
<td class="<?= $d['status'] ?>">
<?= $d['status']=='pending'?'قيد المراجعة':($d['status']=='approved'?'مقبول':'مرفوض') ?>
</td>
<td><?= date('Y-m-d H:i', strtotime($d['created_at'])) ?></td>
<td>
<?php if($d['status']=='pending'): ?>
<a class="btn green" href="?approve=<?= $d['id'] ?>">✔ قبول</a>

<form method="post" style="margin-top:5px">
<input type="hidden" name="id" value="<?= $d['id'] ?>">
<input name="note" placeholder="سبب الرفض" required>
<button class="btn red">✖ رفض</button>
<input type="hidden" name="reject">
</form>
<?php else: ?>
—
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

</div>

</body>
</html>
