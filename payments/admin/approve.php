<?php require_once __DIR__.'/../config/auth.php'; requireAdmin(); require_once __DIR__.'/../config/db.php';
$id=(int)$_GET['id'];
$conn->query("UPDATE deposits SET status='approved' WHERE id=$id");
header('Location: pending.php');