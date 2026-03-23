<?php
// تفعيل عرض جميع الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// تسجيل الأخطاء في ملف
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// بدء الجلسة مع معالجة الأخطاء
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// اختبار الاتصال بقاعدة البيانات
function testDatabaseConnection() {
    try {
        require_once __DIR__ . "/config/login.php";
        
        if (!isset($conn)) {
            throw new Exception("متغير الاتصال بقاعدة البيانات غير موجود");
        }
        
        if ($conn->connect_error) {
            throw new Exception("فشل الاتصال: " . $conn->connect_error);
        }
        
        // اختبار وجود الجدول
        $result = $conn->query("SHOW TABLES LIKE 'comprehensive_login_logs'");
        if ($result->num_rows == 0) {
            echo "<div style='background:#fff3cd;color:#856404;padding:10px;margin:10px;border:1px solid #ffeeba;border-radius:5px;'>";
            echo "⚠️ الجدول غير موجود. سيتم إنشاؤه تلقائياً...";
            echo "</div>";
            
            // إنشاء الجدول
            $sql = "CREATE TABLE IF NOT EXISTS comprehensive_login_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                attempt_type ENUM('failed', 'successful', 'blocked') NOT NULL,
                username VARCHAR(100),
                password_attempt TEXT,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                device_type VARCHAR(50),
                browser_name VARCHAR(50),
                browser_version VARCHAR(50),
                os_name VARCHAR(50),
                os_version VARCHAR(50),
                device_fingerprint VARCHAR(64),
                forwarded_ip VARCHAR(45),
                real_ip VARCHAR(45),
                proxy_detected BOOLEAN DEFAULT FALSE,
                vpn_detected BOOLEAN DEFAULT FALSE,
                tor_detected BOOLEAN DEFAULT FALSE,
                country VARCHAR(100),
                city VARCHAR(100),
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                timezone VARCHAR(50),
                screen_resolution VARCHAR(20),
                color_depth INT,
                pixel_ratio FLOAT,
                language VARCHAR(50),
                languages TEXT,
                cookies_enabled BOOLEAN,
                javascript_enabled BOOLEAN,
                request_method VARCHAR(10),
                request_uri TEXT,
                http_referer TEXT,
                http_protocol VARCHAR(20),
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                server_time DATETIME,
                client_time DATETIME,
                csrf_token_valid BOOLEAN,
                session_id VARCHAR(255),
                attempt_count INT DEFAULT 1,
                INDEX idx_ip_time (ip_address, attempt_time),
                INDEX idx_username (username),
                INDEX idx_fingerprint (device_fingerprint),
                INDEX idx_attempt_type (attempt_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                echo "<div style='background:#d4edda;color:#155724;padding:10px;margin:10px;border:1px solid #c3e6cb;border-radius:5px;'>";
                echo "✅ تم إنشاء الجدول بنجاح!";
                echo "</div>";
            } else {
                throw new Exception("فشل إنشاء الجدول: " . $conn->error);
            }
        }
        
        return $conn;
        
    } catch (Exception $e) {
        echo "<div style='background:#f8d7da;color:#721c24;padding:15px;margin:10px;border:1px solid #f5c6cb;border-radius:5px;'>";
        echo "<strong>❌ خطأ في قاعدة البيانات:</strong><br>";
        echo $e->getMessage() . "<br>";
        echo "<br><strong>معلومات إضافية:</strong><br>";
        echo "الملف: " . $e->getFile() . "<br>";
        echo "السطر: " . $e->getLine();
        echo "</div>";
        return null;
    }
}

// اختبار الـ POST data
function debugPostData() {
    echo "<div style='background:#e2e3e5;color:#383d41;padding:15px;margin:10px;border:1px solid #d6d8db;border-radius:5px;'>";
    echo "<strong>📦 بيانات POST المرسلة:</strong><br>";
    if (empty($_POST)) {
        echo "لا توجد بيانات POST";
    } else {
        echo "<pre>";
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['password'])) {
                echo htmlspecialchars($key) . " = [مخفية]\n";
            } else {
                echo htmlspecialchars($key) . " = " . htmlspecialchars(print_r($value, true)) . "\n";
            }
        }
        echo "</pre>";
    }
    echo "</div>";
}

// اختبار الجلسة
function debugSession() {
    echo "<div style='background:#d1ecf1;color:#0c5460;padding:15px;margin:10px;border:1px solid #bee5eb;border-radius:5px;'>";
    echo "<strong>🔑 معلومات الجلسة:</strong><br>";
    echo "معرف الجلسة: " . (session_id() ?: 'لا توجد جلسة') . "<br>";
    echo "حالة الجلسة: " . (session_status() == PHP_SESSION_ACTIVE ? 'نشطة' : 'غير نشطة') . "<br>";
    
    if (!empty($_SESSION)) {
        echo "<pre>";
        foreach ($_SESSION as $key => $value) {
            if ($key === 'csrf_token') {
                echo htmlspecialchars($key) . " = " . substr($value, 0, 20) . "...\n";
            } else {
                echo htmlspecialchars($key) . " = " . htmlspecialchars(print_r($value, true)) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "لا توجد بيانات في الجلسة";
    }
    echo "</div>";
}

// اختبار CSRF
function debugCsrf() {
    echo "<div style='background:#fff3cd;color:#856404;padding:15px;margin:10px;border:1px solid #ffeeba;border-radius:5px;'>";
    echo "<strong>🛡️ معلومات CSRF:</strong><br>";
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo "تم إنشاء CSRF token جديد<br>";
    }
    
    echo "Token في الجلسة: " . substr($_SESSION['csrf_token'], 0, 20) . "...<br>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_token = $_POST['csrf_token'] ?? 'غير موجود';
        echo "Token في POST: " . substr($post_token, 0, 20) . "...<br>";
        
        if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            echo "✅ CSRF token صحيح";
        } else {
            echo "❌ CSRF token غير صحيح";
        }
    } else {
        echo "لم يتم إرسال طلب POST بعد";
    }
    echo "</div>";
}

// تنفيذ الاختبارات
$conn = testDatabaseConnection();
debugSession();
debugCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugPostData();
}
?>