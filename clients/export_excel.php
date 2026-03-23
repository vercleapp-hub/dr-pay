<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) exit;

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=clients_export.xls");

echo "ID\tالاسم\tالرقم القومي\tالهاتف\tالمحافظة\tالتاريخ\n";

/* نفس فلترة البحث */
$whereParts = [];
$params = [];
$types = '';

if (!empty($_GET['full_name'])) {
    $whereParts[]="full_name LIKE ?";
    $params[]="%".$_GET['full_name']."%";
    $types.="s";
}
if (!empty($_GET['national_id'])) {
    $whereParts[]="national_id LIKE ?";
    $params[]="%".$_GET['national_id']."%";
    $types.="s";
}
if (!empty($_GET['phone'])) {
    $whereParts[]="phone_main LIKE ?";
    $params[]="%".$_GET['phone']."%";
    $types.="s";
}
if (!empty($_GET['governorate'])) {
    $whereParts[]="governorate=?";
    $params[]=$_GET['governorate'];
    $types.="s";
}
if (!empty($_GET['from'])) {
    $whereParts[]="DATE(created_at)>=?";
    $params[]=$_GET['from'];
    $types.="s";
}
if (!empty($_GET['to'])) {
    $whereParts[]="DATE(created_at)<=?";
    $params[]=$_GET['to'];
    $types.="s";
}

$where=$whereParts?"WHERE ".implode(" AND ",$whereParts):"";

$sql="SELECT * FROM clients $where ORDER BY id DESC";
$stmt=$conn->prepare($sql);
if($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$result=$stmt->get_result();

while($row=$result->fetch_assoc()){
    echo $row['id']."\t".
         $row['full_name']."\t".
         $row['national_id']."\t".
         $row['phone_main']."\t".
         $row['governorate']."\t".
         $row['created_at']."\n";
}
exit;
