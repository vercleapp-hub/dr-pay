<?php
session_start();

// تسجيل نشاط الخروج إذا كان المستخدم مسجل الدخول
if (isset($_SESSION['user_id'])) {
    // جلب معلومات المستخدم قبل تدمير الجلسة
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'غير معروف';
    
    // تسجيل نشاط الخروج في قاعدة البيانات إذا كانت متوفرة
    @include_once __DIR__ . "/config/login.php";
    
    if (isset($conn)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
                VALUES (?, 'logout', ?, ?, ?)
            ");
            $description = "تسجيل خروج المستخدم: " . $username;
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'غير معروف';
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'غير معروف';
            
            $stmt->bind_param("isss", $user_id, $description, $ip, $agent);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // تجاهل الأخطاء في تسجيل النشاط
            error_log("Logout activity error: " . $e->getMessage());
        }
    }
}

// تدمير جميع بيانات الجلسة
$_SESSION = array();

// إذا كنت تريد حذف الكوكيز
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إضافة رسالة تأكيد
session_start();
$_SESSION['logout_message'] = "تم تسجيل الخروج بنجاح";
session_write_close();

// إعادة التوجيه إلى صفحة الدخول
header("Location: https://dr.free.nf/login.php");
exit();
?>