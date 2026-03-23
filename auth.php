<?php
// auth.php - ملف التحقق من المصادقة
session_start();

/**
 * التحقق من تسجيل الدخول
 * @return bool true إذا كان المستخدم مسجل دخول
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * إعادة توجيه المستخدم غير المسجل
 * @param string $redirectUrl رابط التوجيه
 */
function requireLogin($redirectUrl = 'login.php')
{
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * تسجيل الخروج
 */
function logout()
{
    $_SESSION = array();
    session_destroy();
    header("Location: login.php");
    exit;
}
?>