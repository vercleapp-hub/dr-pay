<?php include "header.php"; ?>

<h3>تنفيذ خدمة لعميل</h3>

<?php
if (!isset($_SESSION['pay_token'])) {
    $_SESSION['pay_token'] = uniqid();
}

if ($_SERVER['REQUEST_METHOD']=='POST') {

    if ($_POST['token'] !== $_SESSION['pay_token']) {
        die("عملية غير صالحة");
    }
    unset($_SESSION['pay_token']);

    $uid = (int)$_POST['user_id'];
    $sid = (int)$_POST['service_id'];

    $srv = $conn->query("SELECT * FROM services WHERE id=$sid AND active=1")->fetch_assoc();
    $total = $srv['price'] + $srv['fee'];

    $conn->query("START TRANSACTION");

    $bal = $conn->query("
        SELECT balance FROM users 
        WHERE id=$uid FOR UPDATE
    ")->fetch_assoc();

    if ($bal['balance'] < $total) {
        $conn->query("ROLLBACK");
        die("❌ رصيد العميل غير كافي");
    }

    $receipt = "RC".time();

    $conn->query("UPDATE users SET balance=balance-$total WHERE id=$uid");
    $conn->query("
        INSERT INTO transactions
        (user_id, service_id, price, fee, total, receipt_no)
        VALUES
        ($uid, $sid, {$srv['price']}, {$srv['fee']}, $total, '$receipt')
    ");

    $conn->query("COMMIT");

    echo "✅ تم تنفيذ الخدمة بنجاح";
}

$users = $conn->query("
SELECT id, account_number, username 
FROM users 
WHERE role='user' AND status='active'
");

$services = $conn->query("SELECT * FROM services WHERE active=1");
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

    <select name="service_id" required>
        <?php while($s=$services->fetch_assoc()): ?>
            <option value="<?= $s['id'] ?>">
                <?= $s['service_name'] ?> (<?= $s['price']+$s['fee'] ?> ج.م)
            </option>
        <?php endwhile; ?>
    </select>
    <br><br>

    <input type="hidden" name="token" value="<?= $_SESSION['pay_token'] ?>">
    <button>تنفيذ الخدمة</button>
</form>

<?php include "footer.php"; ?>
