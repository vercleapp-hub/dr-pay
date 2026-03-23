<?php
// أعلى الصفحة
require_once 'auth_check.php';
startSecureSession();
requireLogin();

// تسجيل النشاط
logUserActivity('زيارة صفحة الملف الشخصي');
?>
<!DOCTYPE html>
<html>
<head>
    <title>الملف الشخصي</title>
</head>
<body>
    <h1>مرحباً <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    <!-- محتوى الصفحة -->
</body>
</html>