<?php
require_once '../config/payments.php';
require_login();

if ($_SESSION['role'] != 'مسؤول') {
    header("Location: ../dashboard.php");
    exit();
}

$stats = $conn->query("SELECT 
    COUNT(*) as total_deposits,
    SUM(CASE WHEN status='مقبول' THEN amount ELSE 0 END) as total_approved,
    SUM(CASE WHEN status='قيد المراجعة' THEN 1 ELSE 0 END) as pending_count
    FROM deposits")->fetch_assoc();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"><title>التقارير</title></head>
<body style="font-family: Arial; padding: 30px;">
    <h2>التقارير الإحصائية</h2>
    <a href="../dashboard.php" style="color: #666;">← رجوع</a><br><br>
    
    <div style="background: #f8f9fa; padding: 25px; border-radius: 10px; max-width: 500px;">
        <h3>إحصائيات النظام</h3>
        <p>✅ إجمالي الإيداعات المقبولة: <strong><?php echo جنيه($stats['total_approved']); ?></strong></p>
        <p>⏳ الطلبات المعلقة: <strong><?php echo $stats['pending_count']; ?></strong></p>
        <p>📊 إجمالي العمليات: <strong><?php echo $stats['total_deposits']; ?></strong></p>
    </div>
</body>
</html>