<?php
require_once "../config/auth.php";
requireLogin();
requireAgent();

$agent = $conn->query("SELECT * FROM users WHERE id=".$_SESSION['user_id'])->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>منطقة الوكيل</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container">
<h2>منطقة الوكيل</h2>

<p>
👤 <?= $agent['username'] ?> |
💳 <?= $agent['account_number'] ?> |
💰 الرصيد: <b><?= $agent['balance'] ?> ج.م</b>
</p>

<nav style="margin-bottom:15px">
    <a href="dashboard.php">الرئيسية</a> |
    <a href="customers.php">العملاء</a> |
    <a href="deposit.php">إيداع لعميل</a> |
    <a href="services.php">تنفيذ خدمة</a> |
    <a href="transactions.php">عملياتي</a> |
    <a href="../auth/logout.php">خروج</a>
</nav>

<hr>
