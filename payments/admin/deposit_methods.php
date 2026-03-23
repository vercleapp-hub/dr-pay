<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $details = trim($_POST['details']);

    if ($name && $details) {
        $stmt = $conn->prepare("
            INSERT INTO deposit_methods (name, details)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ss", $name, $details);
        $stmt->execute();
    }
}

// جلب الطرق
$methods = $conn->query("SELECT * FROM deposit_methods ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طرق الإيداع</title>
</head>
<body>

<h2>💳 طرق الإيداع</h2>

<form method="post">
    <input type="text" name="name" placeholder="اسم الطريقة" required>
    <textarea name="details" placeholder="بيانات التحويل" required></textarea>
    <button type="submit">➕ إضافة</button>
</form>

<hr>

<table border="1" width="100%">
<tr>
    <th>الطريقة</th>
    <th>البيانات</th>
    <th>الحالة</th>
</tr>

<?php while($m = $methods->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($m['name']) ?></td>
    <td><?= nl2br(htmlspecialchars($m['details'])) ?></td>
    <td><?= $m['status'] ?></td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>
