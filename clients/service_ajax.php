<?php
require_once "../config/db.php";
header("Content-Type: application/json; charset=utf-8");

$name = trim($_POST['name'] ?? '');

if (mb_strlen($name) < 3) {
    echo json_encode(["status"=>"error","msg"=>"اسم الخدمة قصير"]);
    exit;
}

/* منع التكرار */
$chk = $conn->prepare("SELECT id FROM services WHERE service_name=? LIMIT 1");
$chk->bind_param("s", $name);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    echo json_encode(["status"=>"exists"]);
    exit;
}

/* إضافة */
$stmt = $conn->prepare("INSERT INTO services (service_name,status) VALUES (?,1)");
$stmt->bind_param("s",$name);
$stmt->execute();

echo json_encode([
    "status"=>"ok",
    "id"=>$conn->insert_id
]);
