<?php include "header.php"; ?>

<h3>سجل العمليات</h3>

<?php
$t = $conn->query("
SELECT t.*, s.service_name, u.username
FROM transactions t
JOIN services s ON s.id=t.service_id
JOIN users u ON u.id=t.user_id
ORDER BY t.id DESC
LIMIT 100
");
?>

<table>
<tr>
    <th>رقم</th>
    <th>العميل</th>
    <th>الخدمة</th>
    <th>الإجمالي</th>
    <th>الحالة</th>
</tr>

<?php while($row=$t->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= $row['username'] ?></td>
    <td><?= $row['service_name'] ?></td>
    <td><?= $row['total'] ?> ج.م</td>
    <td><?= $row['status']=='pending'?'قيد التنفيذ':'ناجحة' ?></td>
</tr>
<?php endwhile; ?>
</table>

<?php include "footer.php"; ?>
