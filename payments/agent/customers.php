<?php include "header.php"; ?>

<h3>قائمة العملاء</h3>

<?php
$users = $conn->query("
SELECT id, account_number, username, balance 
FROM users 
WHERE role='user' AND status='active'
");
?>

<table>
<tr>
    <th>رقم الحساب</th>
    <th>الاسم</th>
    <th>الرصيد (ج.م)</th>
</tr>

<?php while($u=$users->fetch_assoc()): ?>
<tr>
    <td><?= $u['account_number'] ?></td>
    <td><?= $u['username'] ?></td>
    <td><?= $u['balance'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<?php include "footer.php"; ?>
