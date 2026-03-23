<?php
require_once '../config/payments.php';
require_login();

if ($_SESSION['role'] != 'مسؤول') {
    header("Location: ../dashboard.php");
    exit();
}

$result = $conn->query("SELECT d.*, u.username FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.status != 'قيد المراجعة' ORDER BY d.created_at DESC");
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"><title>العمليات المالية</title></head>
<body style="font-family: Arial; padding: 30px;">
    <h2>العمليات المالية</h2>
    <a href="../dashboard.php" style="color: #666;">← رجوع</a><br><br>
    
    <?php while($row = $result->fetch_assoc()): ?>
    <div style="border-left: 4px solid <?php echo $row['status'] == 'مقبول' ? '#28a745' : '#dc3545'; ?>; padding: 10px; margin: 10px 0; background: white;">
        <?php echo $row['username']; ?> - <?php echo جنيه($row['amount']); ?><br>
        <small style="color: #666;"><?php echo date('Y/m/d h:i A', strtotime($row['created_at'])); ?> | <?php echo $row['status']; ?></small>
    </div>
    <?php endwhile; ?>
</body>
</html>