<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once "../config/operations.php";

/* =======================
   التحقق من البيانات
======================= */
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    die("طريقة غير مسموحة");
}

$service_name = trim($_POST['service_name'] ?? '');
$amount       = floatval($_POST['amount'] ?? 0);
$fees         = floatval($_POST['fees'] ?? 0);
$details      = trim($_POST['details'] ?? '');
$data         = $_POST['data'] ?? [];

if($service_name === '' || $amount <= 0){
    die("بيانات غير مكتملة");
}

/* =======================
   تجهيز البيانات
======================= */
$total   = $amount + $fees;
$currency = "EGP";
$status   = "pending";        // 👈 الفاتورة معلّقة
$payment_company = "manual";
$payment_type    = "cash";

$service_data = !empty($data)
    ? json_encode($data, JSON_UNESCAPED_UNICODE)
    : null;

/* رقم فاتورة فريد */
$invoice_no = "INV-" . date("Ymd-His") . "-" . rand(100,999);

/* =======================
   الحفظ في قاعدة البيانات
======================= */
$stmt = $conn_operations->prepare("
    INSERT INTO operations (
        invoice_no,
        service_name,
        service_data,
        details,
        amount,
        fees,
        total,
        currency,
        status,
        payment_company,
        payment_type,
        created_at
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,NOW()
    )
");

if(!$stmt){
    die("SQL Error: ".$conn_operations->error);
}

$stmt->bind_param(
    "ssssdddssss",
    $invoice_no,
    $service_name,
    $service_data,
    $details,
    $amount,
    $fees,
    $total,
    $currency,
    $status,
    $payment_company,
    $payment_type
);

$stmt->execute();

$operation_id = $stmt->insert_id;
$stmt->close();

/* =======================
   التحويل للطباعة
======================= */
header("Location: print.php?id=".$operation_id);
exit;