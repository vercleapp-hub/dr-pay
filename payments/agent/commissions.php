<?php
require_once '../config/payments.php';

if ($_SESSION['role'] != 'وكيل') {
    header("Location: ../dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM deposits WHERE user_id = $user_id AND status = 'مقبول'");
$total_commission = 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>العمولات</title></head>
<body style="font-family: Arial; padding: 30px;">
    <h2>💰 حسابات العمولات</h2>
    <a href="../dashboard.php" style="color:#666">← رجوع</a><br><br>
    
    <?php while($row = $result->fetch_assoc()):
        $commission = $row['amount'] * 0.05; // 5% عمولة
        $total_commission += $commission;
    ?>
    <div style="border:1px solid #f39c12; padding:15px; margin:10px 0; border-radius:5px; background:#fff3cd">
        <strong>عملية #<?php echo $row['id']; ?></strong><br>
        المبلغ: <?php echo جنيه($row['amount']); ?><br>
        العمولة (5%): <?php echo جنيه($commission); ?><br>
        التاريخ: <?php echo date('Y/m/d', strtotime($row['created_at'])); ?>
    </div>
    <?php endwhile; ?>
    
    <div style="background:#2c3e50; color:white; padding:20px; margin-top:20px; border-radius:5px; text-align:center">
        <h3>إجمالي العمولات المستحقة: <?php echo جنيه($total_commission); ?></h3>
        <small>يتم صرف العمولات أول كل شهر</small>
    </div>
</body>
</html>