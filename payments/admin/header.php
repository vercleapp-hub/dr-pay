<?php
require_once "../config/auth.php";
requireLogin();
requireAdmin();

/* منع كاش الصفحات بعد تسجيل الخروج */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>لوحة الإدارة</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body{font-family:Tahoma,Arial;background:#f7f7f7;margin:0}
        .container{max-width:1100px;margin:0 auto;padding:14px}
        .top-admin-bar{
            display:flex;justify-content:space-between;align-items:center;
            gap:10px;flex-wrap:wrap;
            background:#fff;border:1px solid #eee;border-radius:14px;
            padding:12px 14px;margin-bottom:12px;
            box-shadow:0 6px 16px rgba(0,0,0,.05);
        }
        .top-admin-bar h2{margin:0;font-size:18px}
        .admin-name{color:#444;font-size:13px}
        nav{
            background:#fff;border:1px solid #eee;border-radius:14px;
            padding:10px 12px;margin-bottom:12px;
            box-shadow:0 6px 16px rgba(0,0,0,.05);
            display:flex;gap:10px;flex-wrap:wrap;
        }
        nav a{
            text-decoration:none;
            background:#111;color:#fff;
            padding:8px 12px;border-radius:12px;
            font-weight:700;font-size:13px;
        }
        nav a:hover{opacity:.9}
        .btn-logout{
            background:#b00020 !important;
        }
        hr{border:0;border-top:1px solid #eee;margin:12px 0}
    </style>
</head>
<body>

<div class="container">

    <div class="top-admin-bar">
        <div>
            <h2>لوحة تحكم الإدارة</h2>
            <div class="admin-name">
                مرحبًا 👋 <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <div>
            <a class="btn-logout" href="../auth/logout.php"
               style="text-decoration:none;background:#b00020;color:#fff;padding:8px 12px;border-radius:12px;font-weight:700;">
                خروج
            </a>
        </div>
    </div>

    <nav>
        <a href="dashboard.php">الرئيسية</a>
        <a href="users.php">المستخدمين</a>
        <a href="deposits.php">الإيداعات</a>
        <a href="deposit_methods.php">🏦 طرق الإيداع</a>

        <a href="services.php">الخدمات</a>
        <a href="transactions.php">العمليات</a>
        <a href="recharge_cards.php">كروت الشحن</a>
    </nav>

    <hr>
