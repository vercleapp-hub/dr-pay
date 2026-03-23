<?php
require_once "../config/db.php";

$id = intval($_GET['id']);
$sql = "
SELECT clients.*, services.service_name
FROM clients
LEFT JOIN services ON services.id = clients.service_id
WHERE clients.id=$id
";
$r = $conn->query($sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طباعة إيصال</title>
</head>
<body onload="window.print()">

<h3>🧾 إيصال خدمة</h3>
<hr>
<p>العميل: <?= $r['client_name'] ?></p>
<p>الموبايل: <?= $r['phone'] ?></p>
<p>الخدمة: <?= $r['service_name'] ?></p>
<p>المبلغ: <?= $r['amount'] ?> ج.م</p>
<p>التاريخ: <?= $r['created_at'] ?></p>
<p><?= $r['notes'] ?></p>

</body>
</html>
