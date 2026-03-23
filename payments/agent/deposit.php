<?php include "header.php"; ?>

<h3>طلب إيداع لعميل</h3>

<?php
$msg = "";

if ($_SERVER['REQUEST_METHOD']=='POST') {
    $uid = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];

    if ($amount <= 0) {
        $msg = "❌ مبلغ غير صحيح";
    } else {
        $conn->query("
            INSERT INTO deposits (user_id, amount)
            VALUES ($uid, $amount)
        ");
        $msg = "✅ تم إرسال الطلب – في انتظار موافقة الإدارة";
    }
}

$users = $conn->query("
SELECT id, account_number, username 
FROM users 
WHERE role='user' AND status='active'
");
?>

<form method="post">
    <select name="user_id" required>
        <?php while($u=$users->fetch_assoc()): ?>
            <option value="<?= $u['id'] ?>">
                <?= $u['account_number'] ?> - <?= $u['username'] ?>
            </option>
        <?php endwhile; ?>
    </select>
    <br><br>

    <input type="number" name="amount" step="0.01" placeholder="المبلغ بالجنيه" required>
    <button>إرسال الطلب</button>
</form>

<p><?= $msg ?></p>

<?php include "footer.php"; ?>
