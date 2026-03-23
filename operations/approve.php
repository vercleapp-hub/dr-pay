<?php
declare(strict_types=1);
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once "../config/operations.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("رقم عملية غير صحيح");
}

/* تحديث الحالة */
$sql = "
    UPDATE operations
    SET status='paid',
        paid_at = NOW()
    WHERE id = ?
    AND status = 'pending'
    LIMIT 1
";

$stmt = $conn_operations->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $stmt->close();
    die("لم يتم اعتماد العملية");
}
$stmt->close();

/* بعد الدفع → الطباعة مباشرة */
header("Location: print.php?id=".$id);
exit;
