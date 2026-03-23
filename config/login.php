<?php
// config/login.php - إعدادات الاتصال وقاعدة البيانات

// إيقاف عرض الأخطاء في بيئة الإنتاج
error_reporting(0);
ini_set('display_errors', 0);

// بيانات قاعدة البيانات
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_40974310');
define('DB_PASS', '1p7F4ABr5utA4');
define('DB_NAME', 'if0_40974310_login');

// إنشاء الاتصال
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// فحص الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// دعم اللغة العربية
$conn->set_charset("utf8mb4");

// تحديد المنطقة الزمنية لمصر
date_default_timezone_set('Africa/Cairo');

// إنشاء الجداول اللازمة إذا لم تكن موجودة
$tables = [
    "users" => "
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            full_name VARCHAR(255),
            role VARCHAR(50) DEFAULT 'user',
            permissions TEXT,
            is_admin TINYINT(1) DEFAULT 0,
            status TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME,
            last_activity DATETIME,
            login_count INT DEFAULT 0,
            INDEX idx_username (username),
            INDEX idx_status (status),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    "site_permissions" => "
        CREATE TABLE IF NOT EXISTS site_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            permission_name VARCHAR(100) NOT NULL,
            permission_description TEXT,
            permission_value TINYINT(1) DEFAULT 1,
            module VARCHAR(50),
            granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            granted_by INT,
            expires_at DATETIME,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_permission (user_id, permission_name),
            INDEX idx_module (module),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    "permission_modules" => "
        CREATE TABLE IF NOT EXISTS permission_modules (
            id INT PRIMARY KEY AUTO_INCREMENT,
            module_name VARCHAR(100) NOT NULL UNIQUE,
            module_description TEXT,
            module_icon VARCHAR(50),
            parent_module VARCHAR(100),
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    "login_sessions" => "
        CREATE TABLE IF NOT EXISTS login_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            device_fingerprint VARCHAR(64),
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            device_info TEXT,
            network_info TEXT,
            location_info TEXT,
            hardware_info TEXT,
            manufacturer VARCHAR(100),
            browser VARCHAR(50),
            os VARCHAR(50),
            screen_info VARCHAR(100),
            isp VARCHAR(100),
            permissions_used TEXT,
            login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            logout_time DATETIME,
            session_duration INT,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_session (user_id, session_id),
            INDEX idx_fingerprint (device_fingerprint),
            INDEX idx_ip (ip_address),
            INDEX idx_active (is_active),
            INDEX idx_login_time (login_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    "permission_logs" => "
        CREATE TABLE IF NOT EXISTS permission_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            permission_name VARCHAR(100),
            action VARCHAR(50),
            resource VARCHAR(100),
            ip_address VARCHAR(45),
            user_agent TEXT,
            success TINYINT(1) DEFAULT 1,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_action (user_id, action),
            INDEX idx_permission (permission_name),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    "login_data_enhanced" => "
        CREATE TABLE IF NOT EXISTS login_data_enhanced (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            login_time DATETIME NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            location_data TEXT,
            system_data TEXT,
            browser_data TEXT,
            network_data TEXT,
            hardware_data TEXT,
            screen_data TEXT,
            user_agent TEXT,
            device_fingerprint VARCHAR(64),
            session_id VARCHAR(255),
            success TINYINT(1) DEFAULT 1,
            INDEX idx_username (username),
            INDEX idx_login_time (login_time),
            INDEX idx_ip (ip_address),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    "login_attempts" => "
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            successful TINYINT(1) DEFAULT 0,
            user_agent TEXT,
            INDEX idx_username (username),
            INDEX idx_ip (ip_address),
            INDEX idx_attempt_time (attempt_time),
            INDEX idx_successful (successful)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
    ,
    "otp_codes" => "
        CREATE TABLE IF NOT EXISTS otp_codes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            phone VARCHAR(30) NOT NULL,
            code VARCHAR(10) NOT NULL,
            purpose VARCHAR(30) DEFAULT 'login',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 5,
            expires_at DATETIME NOT NULL,
            verified TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_id VARCHAR(255),
            device_fingerprint VARCHAR(64),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_phone (phone),
            INDEX idx_code (code),
            INDEX idx_exp (expires_at),
            INDEX idx_verified (verified)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach ($tables as $table_name => $sql) {
    $conn->query($sql);
}

// إدخال بيانات النماذج الافتراضية
$modules = [
    ['dashboard', 'لوحة التحكم', 'fas fa-tachometer-alt', null, 1],
    ['users', 'إدارة المستخدمين', 'fas fa-users', null, 2],
    ['permissions', 'إدارة الصلاحيات', 'fas fa-user-shield', null, 3],
    ['reports', 'التقارير والإحصائيات', 'fas fa-chart-bar', null, 4],
    ['settings', 'الإعدادات', 'fas fa-cog', null, 5],
    ['content', 'إدارة المحتوى', 'fas fa-file-alt', null, 6],
    ['files', 'إدارة الملفات', 'fas fa-folder', null, 7],
    ['system', 'إعدادات النظام', 'fas fa-server', null, 8],
    ['login_logs', 'سجلات الدخول', 'fas fa-sign-in-alt', null, 9]
];

foreach ($modules as $module) {
    $conn->query("
        INSERT IGNORE INTO permission_modules 
        (module_name, module_description, module_icon, parent_module, display_order) 
        VALUES (
            '{$module[0]}', 
            '{$module[1]}', 
            '{$module[2]}', 
            " . ($module[3] ? "'{$module[3]}'" : "NULL") . ", 
            {$module[4]}
        )
    ");
}

// دالة لتحويل الوقت إلى توقيت مصر بتنسيق 12 ساعة
function formatEgyptianTime12h($datetime, $include_date = true) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'غير محدد';
    }
    
    try {
        // إنشاء كائن DateTime مع افتراض أن الوقت مخزن بالتوقيت العالمي
        $date = new DateTime($datetime, new DateTimeZone('UTC'));
        
        // تحويل إلى توقيت القاهرة
        $date->setTimezone(new DateTimeZone('Africa/Cairo'));
        
        if ($include_date) {
            // تنسيق التاريخ والوقت معاً
            $date_str = $date->format('Y-m-d');
            $time_str = $date->format('h:i:s');
            $ampm = $date->format('A');
            
            // تحويل AM/PM إلى العربية
            $ampm_ar = ($ampm == 'AM') ? 'ص' : 'م';
            
            // تحويل الأرقام الإنجليزية إلى عربية
            $date_str_ar = str_replace(
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
                $date_str
            );
            
            $time_str_ar = str_replace(
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
                $time_str
            );
            
            return $date_str_ar . ' ' . $time_str_ar . ' ' . $ampm_ar;
        } else {
            // تنسيق الوقت فقط
            $time_str = $date->format('h:i:s');
            $ampm = $date->format('A');
            
            // تحويل AM/PM إلى العربية
            $ampm_ar = ($ampm == 'AM') ? 'ص' : 'م';
            
            // تحويل الأرقام الإنجليزية إلى عربية
            $time_str_ar = str_replace(
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
                $time_str
            );
            
            return $time_str_ar . ' ' . $ampm_ar;
        }
    } catch (Exception $e) {
        // في حالة الخطأ، عرض التاريخ كما هو
        return $datetime;
    }
}

// دالة لتحويل الوقت إلى توقيت مصر مع التاريخ الهجري (اختياري)
function formatEgyptianDateTime($datetime, $include_hijri = false) {
    return formatEgyptianTime12h($datetime, true);
}

// دالة تحويل التاريخ الميلادي إلى هجري
function gregorianToHijri($date) {
    try {
        if (class_exists('IntlDateFormatter')) {
            $date_obj = new DateTime($date);
            $formatter = new IntlDateFormatter(
                'ar_EG@calendar=islamic',
                IntlDateFormatter::FULL,
                IntlDateFormatter::NONE,
                'Africa/Cairo',
                IntlDateFormatter::TRADITIONAL,
                'd MMMM y'
            );
            return $formatter->format($date_obj);
        }
    } catch (Exception $e) {
        // في حالة عدم التوفر، ارجع سلسلة فارغة
    }
    return '';
}

// دالة للحصول على التاريخ والوقت الحاليين بتوقيت مصر
function getCurrentEgyptianTime($format = '12h') {
    $now = new DateTime('now', new DateTimeZone('Africa/Cairo'));
    
    if ($format === '12h') {
        $time = $now->format('h:i:s');
        $ampm = $now->format('A');
        $ampm_ar = ($ampm == 'AM') ? 'ص' : 'م';
        $date = $now->format('Y-m-d');
        
        // تحويل الأرقام إلى عربية
        $date_ar = str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            $date
        );
        
        $time_ar = str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            $time
        );
        
        return [
            'date' => $date,
            'date_ar' => $date_ar,
            'time' => $time,
            'time_ar' => $time_ar,
            'ampm' => $ampm,
            'ampm_ar' => $ampm_ar,
            'full_ar' => $date_ar . ' ' . $time_ar . ' ' . $ampm_ar,
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'timestamp_12h' => $now->format('Y-m-d h:i:s A')
        ];
    } else {
        return $now->format('Y-m-d H:i:s');
    }
}

// دالة للتحقق من صحة الوقت
function isValidDateTime($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return false;
    }
    
    try {
        $date = new DateTime($datetime);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// دالة لتحويل أي وقت إلى توقيت مصر
function convertToEgyptTime($datetime, $from_timezone = 'UTC') {
    if (!isValidDateTime($datetime)) {
        return 'غير محدد';
    }
    
    try {
        $date = new DateTime($datetime, new DateTimeZone($from_timezone));
        $date->setTimezone(new DateTimeZone('Africa/Cairo'));
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $datetime;
    }
}

// دالة التحقق من الصلاحيات
function hasPermission($conn, $user_id, $permission_name, $module = null) {
    $stmt = $conn->prepare("
        SELECT sp.*, u.is_admin 
        FROM site_permissions sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.user_id = ? 
        AND sp.permission_name = ?
        AND sp.is_active = 1
        AND (sp.expires_at IS NULL OR sp.expires_at > NOW())
        AND sp.permission_value = 1
        " . ($module ? "AND (sp.module = ? OR sp.module IS NULL)" : "") . "
        LIMIT 1
    ");
    
    if ($module) {
        $stmt->bind_param("iss", $user_id, $permission_name, $module);
    } else {
        $stmt->bind_param("is", $user_id, $permission_name);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $permission = $result->fetch_assoc();
        return $permission['is_admin'] == 1 ? true : $permission['permission_value'] == 1;
    }
    
    // التحقق إذا كان المستخدم admin
    $admin_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $admin_check->bind_param("i", $user_id);
    $admin_check->execute();
    $admin_result = $admin_check->get_result();
    $user = $admin_result->fetch_assoc();
    
    return $user['is_admin'] == 1;
}

// دالة تسجيل أنشطة الصلاحيات
function logPermissionActivity($conn, $user_id, $permission_name, $action, $resource, $success = true, $error = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("
        INSERT INTO permission_logs 
        (user_id, permission_name, action, resource, ip_address, user_agent, success, error_message) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "isssssis", 
        $user_id, $permission_name, $action, $resource, 
        $ip, $user_agent, $success, $error
    );
    
    return $stmt->execute();
}

// دالة الحصول على جميع صلاحيات المستخدم
function getUserPermissions($conn, $user_id) {
    $permissions = [];
    
    // التحقق إذا كان المستخدم admin
    $admin_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $admin_check->bind_param("i", $user_id);
    $admin_check->execute();
    $admin_result = $admin_check->get_result();
    $user = $admin_result->fetch_assoc();
    
    if ($user['is_admin'] == 1) {
        // إذا كان admin، يرجع جميع الصلاحيات
        $modules_result = $conn->query("SELECT module_name FROM permission_modules WHERE is_active = 1");
        while ($module = $modules_result->fetch_assoc()) {
            $permissions[] = [
                'module' => $module['module_name'],
                'permissions' => ['view', 'create', 'edit', 'delete', 'manage']
            ];
        }
        return $permissions;
    }
    
    // الحصول على صلاحيات المستخدم العادي
    $stmt = $conn->prepare("
        SELECT sp.permission_name, sp.module, pm.module_description, pm.module_icon
        FROM site_permissions sp
        LEFT JOIN permission_modules pm ON sp.module = pm.module_name
        WHERE sp.user_id = ? 
        AND sp.is_active = 1
        AND sp.permission_value = 1
        AND (sp.expires_at IS NULL OR sp.expires_at > NOW())
        ORDER BY pm.display_order
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $module = $row['module'] ?: 'general';
        if (!isset($permissions[$module])) {
            $permissions[$module] = [
                'name' => $row['module'] ?: 'عام',
                'description' => $row['module_description'],
                'icon' => $row['module_icon'],
                'permissions' => []
            ];
        }
        $permissions[$module]['permissions'][] = $row['permission_name'];
    }
    
    return $permissions;
}

// دالة التحقق من صلاحية الوصول للموقع
function hasSiteAccess($conn, $username) {
    $permission_name = 'site_access';
    
    $stmt = $conn->prepare("
        SELECT sp.*, u.id as user_id 
        FROM site_permissions sp
        JOIN users u ON sp.user_id = u.id
        WHERE u.username = ? 
        AND sp.permission_name = ?
        AND sp.is_active = 1
        AND sp.permission_value = 1
        AND (sp.expires_at IS NULL OR sp.expires_at > NOW())
        LIMIT 1
    ");
    
    $stmt->bind_param("ss", $username, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

// دالة لتنظيف وإعادة تنسيق بيانات JSON
function cleanJsonData($json_string) {
    if (empty($json_string)) {
        return '{}';
    }
    
    // محاولة فك الترميز
    $data = json_decode($json_string, true);
    
    // إذا كان JSON غير صالح، أرجع كائن فارغ
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '{}';
    }
    
    // إعادة الترميز بتبسيط
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

// دالة لجلب سجلات الدخول الناجحة
function getSuccessfulLogs($conn, $limit = 100) {
    $logs = [];
    
    $stmt = $conn->prepare("
        SELECT username, login_time, ip_address, location_data, system_data, 
               browser_data, network_data, hardware_data, screen_data, user_agent,
               device_fingerprint, session_id
        FROM login_data_enhanced 
        WHERE success = 1
        ORDER BY login_time DESC 
        LIMIT ?
    ");
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return $logs;
}

// دالة لجلب محاولات الدخول الفاشلة
function getFailedLogs($conn, $limit = 100) {
    $logs = [];
    
    // التحقق من وجود الجدول
    $table_check = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($table_check->num_rows > 0) {
        // التحقق من الأعمدة الموجودة
        $columns_result = $conn->query("DESCRIBE login_attempts");
        $columns = [];
        while ($col = $columns_result->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        $select_cols = [];
        foreach (['id', 'ip_address', 'username', 'attempt_time', 'successful'] as $col) {
            if (in_array($col, $columns)) $select_cols[] = $col;
        }
        if (in_array('user_agent', $columns)) $select_cols[] = 'user_agent';
        
        $column_list = implode(', ', $select_cols);
        
        $stmt = $conn->prepare("
            SELECT $column_list
            FROM login_attempts 
            WHERE successful = 0 
            ORDER BY attempt_time DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    
    return $logs;
}

// دالة لإضافة سجل دخول جديد
function addLoginLog($conn, $username, $ip_address, $success = true, $additional_data = []) {
    $current_time = date('Y-m-d H:i:s');
    
    if ($success) {
        // إدخال في جدول الدخول الناجحة
        $stmt = $conn->prepare("
            INSERT INTO login_data_enhanced 
            (username, login_time, ip_address, location_data, system_data, 
             browser_data, network_data, hardware_data, screen_data, user_agent,
             device_fingerprint, session_id, success)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $location_data = json_encode($additional_data['location'] ?? [], JSON_UNESCAPED_UNICODE);
        $system_data = json_encode($additional_data['system'] ?? [], JSON_UNESCAPED_UNICODE);
        $browser_data = json_encode($additional_data['browser'] ?? [], JSON_UNESCAPED_UNICODE);
        $network_data = json_encode($additional_data['network'] ?? [], JSON_UNESCAPED_UNICODE);
        $hardware_data = json_encode($additional_data['hardware'] ?? [], JSON_UNESCAPED_UNICODE);
        $screen_data = json_encode($additional_data['screen'] ?? [], JSON_UNESCAPED_UNICODE);
        $user_agent = $additional_data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device_fingerprint = $additional_data['device_fingerprint'] ?? '';
        $session_id = session_id();
        
        $stmt->bind_param(
            "ssssssssssss",
            $username,
            $current_time,
            $ip_address,
            $location_data,
            $system_data,
            $browser_data,
            $network_data,
            $hardware_data,
            $screen_data,
            $user_agent,
            $device_fingerprint,
            $session_id
        );
    } else {
        // إدخال في جدول المحاولات الفاشلة
        $stmt = $conn->prepare("
            INSERT INTO login_attempts 
            (username, ip_address, attempt_time, successful, user_agent)
            VALUES (?, ?, ?, 0, ?)
        ");
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param(
            "ssss",
            $username,
            $ip_address,
            $current_time,
            $user_agent
        );
    }
    
    return $stmt->execute();
}

// دالة لتحسين عرض بيانات JSON في الواجهة
function displayJsonData($json_string, $field = null) {
    if (empty($json_string)) {
        return 'غير متوفر';
    }
    
    $data = json_decode($json_string, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $json_string;
    }
    
    if ($field && isset($data[$field])) {
        return htmlspecialchars($data[$field], ENT_QUOTES, 'UTF-8');
    }
    
    // عرض بسيط للبيانات
    if (is_array($data)) {
        $output = [];
        foreach ($data as $key => $value) {
            if (!empty($value) && $value !== 'null' && $value !== 'undefined') {
                $output[] = htmlspecialchars($key . ': ' . $value, ENT_QUOTES, 'UTF-8');
            }
        }
        return implode('<br>', $output);
    }
    
    return htmlspecialchars($json_string, ENT_QUOTES, 'UTF-8');
}

// دالة لتحسين الأمان
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// إغلاق الاتصال التلقائي في نهاية التنفيذ
register_shutdown_function(function() use ($conn) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
});

// إرجاع كائن الاتصال للاستخدام في ملفات أخرى
return $conn;
?>
