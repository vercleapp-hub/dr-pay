<?php
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* حذف خدمة */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM services WHERE id=$id");
    $conn->query("DELETE FROM service_fields WHERE service_id=$id");
    header("Location: services.php");
    exit;
}

/* إضافة خدمة */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {

    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $merchant_profit = (float)$_POST['merchant_profit'];
    $system_profit = (float)$_POST['system_profit'];
    $fee = (float)$_POST['fee'];

    $stmt = $conn->prepare("
        INSERT INTO services
        (name, description, price, merchant_profit, system_profit, fee, active)
        VALUES (?,?,?,?,?,?,1)
    ");
    $stmt->bind_param(
        "ssdddd",
        $name,
        $desc,
        $price,
        $merchant_profit,
        $system_profit,
        $fee
    );
    $stmt->execute();

    $service_id = $conn->insert_id;

    /* إضافة الحقول المخصصة */
    if (!empty($_POST['field_name'])) {
        foreach ($_POST['field_name'] as $i => $fname) {
            if ($fname == '') continue;

            $label = $_POST['field_label'][$i];
            $type  = $_POST['field_type'][$i];
            $req   = isset($_POST['field_required'][$i]) ? 1 : 0;

            $st = $conn->prepare("
                INSERT INTO service_fields
                (service_id, field_name, field_label, field_type, required)
                VALUES (?,?,?,?,?)
            ");
            $st->bind_param(
                "isssi",
                $service_id,
                $fname,
                $label,
                $type,
                $req
            );
            $st->execute();
        }
    }

    header("Location: services.php");
    exit;
}

/* جلب الخدمات */
$services = $conn->query("SELECT * FROM services ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إدارة الخدمات</title>

<style>
body{font-family:tahoma;background:#f2f4f7}
.container{max-width:1100px;margin:auto}
.box{background:#fff;padding:15px;border-radius:10px;margin:15px 0}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
th{background:#eee}
input,select,textarea{width:100%;padding:6px;margin:4px 0}
button{padding:8px 12px;border:0;background:#007bff;color:#fff;border-radius:5px}
.danger{background:#dc3545}
.small{font-size:12px;color:#666}
</style>

<script>
function addField(){
  const box = document.getElementById('fields');
  box.insertAdjacentHTML('beforeend', `
    <div class="box">
      <input name="field_name[]" placeholder="اسم الحقل (DB)">
      <input name="field_label[]" placeholder="اسم الحقل للمستخدم">
      <select name="field_type[]">
        <option value="text">نص</option>
        <option value="number">رقم</option>
        <option value="tel">هاتف</option>
      </select>
      <label>
        <input type="checkbox" name="field_required[]"> حقل مطلوب
      </label>
    </div>
  `);
}
</script>
</head>

<body>
<div class="container">

<!-- إضافة خدمة -->
<div class="box">
<h2>➕ إضافة خدمة جديدة</h2>
<form method="post">

<input name="name" placeholder="اسم الخدمة" required>
<textarea name="description" placeholder="وصف الخدمة"></textarea>

<div style="display:flex;gap:10px">
<input type="number" step="0.01" name="price" placeholder="سعر الخدمة" required>
<input type="number" step="0.01" name="fee" placeholder="رسوم الخدمة">
</div>

<div style="display:flex;gap:10px">
<input type="number" step="0.01" name="merchant_profit" placeholder="ربح التاجر">
<input type="number" step="0.01" name="system_profit" placeholder="ربح النظام">
</div>

<h4>📥 الحقول المطلوبة من المستخدم</h4>
<div id="fields"></div>

<button type="button" onclick="addField()">➕ إضافة حقل</button>
<br><br>
<button name="add_service">✔ حفظ الخدمة</button>
</form>
</div>

<!-- عرض الخدمات -->
<div class="box">
<h2>📋 الخدمات الحالية</h2>
<table>
<tr>
<th>ID</th>
<th>الخدمة</th>
<th>السعر</th>
<th>التاجر</th>
<th>النظام</th>
<th>الرسوم</th>
<th>الحالة</th>
<th>تحكم</th>
</tr>

<?php while($s = $services->fetch_assoc()): ?>
<tr>
<td><?= $s['id'] ?></td>
<td><?= htmlspecialchars($s['name']) ?></td>
<td><?= $s['price'] ?></td>
<td><?= $s['merchant_profit'] ?></td>
<td><?= $s['system_profit'] ?></td>
<td><?= $s['fee'] ?></td>
<td><?= $s['active'] ? '✅' : '❌' ?></td>
<td>
<a href="?delete=<?= $s['id'] ?>" onclick="return confirm('حذف الخدمة؟')">
<button class="danger">حذف</button>
</a>
</td>
</tr>
<?php endwhile; ?>

</table>
</div>

</div>
</body>
</html>
