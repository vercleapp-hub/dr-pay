<?php
require_once __DIR__.'/../config/auth.php';
requireAdmin();
require_once __DIR__.'/../config/db.php';

$service_id = (int)$_GET['service'];

if ($_POST) {
    $name = trim($_POST['field_name']);
    $label = trim($_POST['field_label']);
    $type = $_POST['field_type'];
    $req = isset($_POST['required'])?1:0;

    $st = $conn->prepare("
      INSERT INTO service_fields
      (service_id,field_name,field_label,field_type,required)
      VALUES (?,?,?,?,?)
    ");
    $st->bind_param("isssi",$service_id,$name,$label,$type,$req);
    $st->execute();
}

$fields = $conn->query(
  "SELECT * FROM service_fields WHERE service_id=$service_id"
);
?>
<h2>⚙ الحقول المخصصة</h2>

<form method="post">
اسم الحقل (DB)
<input name="field_name" required>

عنوان الحقل
<input name="field_label" required>

النوع
<select name="field_type">
<option value="text">نص</option>
<option value="number">رقم</option>
<option value="email">بريد</option>
</select>

<label><input type="checkbox" name="required" checked> مطلوب</label>
<button>إضافة</button>
</form>

<hr>

<?php while($f=$fields->fetch_assoc()): ?>
<p>
<?= $f['field_label'] ?> (<?= $f['field_type'] ?>)
</p>
<?php endwhile; ?>
