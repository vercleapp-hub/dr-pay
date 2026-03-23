<?php
// نظام تسجيل الدخول المتقدم مع حماية كاملة
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'sid_length' => 128,
    'sid_bits_per_character' => 6
]);

require_once __DIR__ . "/config/login.php";

// حماية CSRF متقدمة
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(64));
    $_SESSION['csrf_expire'] = time() + 1800; // 30 دقيقة
}

// نظام التحديث التلقائي للجلسة
function updateSessionProtection() {
    // تجديد معرف الجلسة دورياً
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 300)) {
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(64));
        $_SESSION['csrf_expire'] = time() + 1800;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // حماية من التثبيت
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

// التحقق من CSRF
function verifyCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_expire'])) {
        return false;
    }
    if (time() > $_SESSION['csrf_expire']) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_expire']);
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// جمع شامل للبيانات مع الحماية
function collectAdvancedData() {
    $data = [
        // معلومات الشبكة والأمن
        'real_ip' => filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: 'غير معروف',
        'forwarded_for' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? 
            filter_var(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0], FILTER_VALIDATE_IP) : null,
        'client_ip' => isset($_SERVER['HTTP_CLIENT_IP']) ? 
            filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP) : null,
        'is_tor' => checkTorExitNode($_SERVER['REMOTE_ADDR'] ?? ''),
        
        // معلومات المتصفح والجهاز
        'user_agent' => htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '', ENT_QUOTES, 'UTF-8'),
        'device_fingerprint' => generateDeviceFingerprint(),
        'plugins' => getBrowserPlugins(),
        
        // معلومات النظام واللغة
        'accept_lang' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'غير معروف',
        'timezone_offset' => $_POST['tz'] ?? '0',
        'daylight_saving' => $_POST['dst'] ?? '0',
        
        // معلومات الشاشة والعرض
        'resolution' => $_POST['res'] ?? '0x0',
        'viewport' => $_POST['vp'] ?? '0x0',
        'orientation' => $_POST['orient'] ?? 'unknown',
        'touch_support' => $_POST['touch'] ?? '0',
        
        // معلومات إضافية
        'webgl_renderer' => $_POST['webgl'] ?? '',
        'canvas_hash' => $_POST['canvas'] ?? '',
        'fonts_hash' => $_POST['fonts'] ?? '',
        'audio_hash' => $_POST['audio'] ?? '',
        
        // معلومات الوقت والتاريخ
        'client_time' => $_POST['ctime'] ?? date('Y-m-d H:i:s'),
        'timezone' => $_POST['tzname'] ?? 'UTC',
        
        // معلومات الاتصال
        'connection_type' => $_POST['conntype'] ?? 'unknown',
        'isp_info' => $_POST['isp'] ?? 'unknown'
    ];
    
    // جمع المعلومات من الـ Headers
    $headers = [
        'HTTP_ACCEPT', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_CHARSET',
        'HTTP_ACCEPT_DATETIME', 'HTTP_CACHE_CONTROL', 'HTTP_CONNECTION',
        'HTTP_COOKIE', 'HTTP_CONTENT_LENGTH', 'HTTP_CONTENT_TYPE',
        'HTTP_DATE', 'HTTP_EXPECT', 'HTTP_FROM', 'HTTP_HOST',
        'HTTP_IF_MATCH', 'HTTP_IF_MODIFIED_SINCE', 'HTTP_IF_NONE_MATCH',
        'HTTP_IF_RANGE', 'HTTP_IF_UNMODIFIED_SINCE', 'HTTP_MAX_FORWARDS',
        'HTTP_PRAGMA', 'HTTP_PROXY_AUTHORIZATION', 'HTTP_RANGE',
        'HTTP_TE', 'HTTP_UPGRADE', 'HTTP_VIA', 'HTTP_WARNING'
    ];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header])) {
            $key = strtolower(str_replace('HTTP_', '', $header));
            $data['header_' . $key] = htmlspecialchars($_SERVER[$header], ENT_QUOTES, 'UTF-8');
        }
    }
    
    return $data;
}

// وظائف المساعدة
function checkTorExitNode($ip) {
    $tor_nodes = @file_get_contents("https://check.torproject.org/torbulkexitlist");
    if ($tor_nodes && in_array($ip, explode("\n", $tor_nodes))) {
        return true;
    }
    return false;
}

function generateDeviceFingerprint() {
    $components = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : '',
        (isset($_POST['sw']) && isset($_POST['sh'])) ? $_POST['sw'] . 'x' . $_POST['sh'] : ''
    ];
    
    return hash('sha256', implode('|', array_filter($components)));
}

function getBrowserPlugins() {
    $plugins = [];
    if (isset($_POST['plugins']) && is_array($_POST['plugins'])) {
        $plugins = array_map('htmlspecialchars', $_POST['plugins']);
    }
    return json_encode($plugins);
}

// حفظ البيانات في قاعدة البيانات مع التحسين
function saveEnhancedData($conn, $username, $data) {
    $stmt = $conn->prepare("
        INSERT INTO login_data_enhanced (
            username, ip_address, device_fingerprint, user_agent, 
            screen_data, location_data, hardware_data, network_data,
            browser_data, system_data, security_flags, session_id,
            login_time, data_hash
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $screen_data = json_encode([
        'resolution' => $data['resolution'],
        'viewport' => $data['viewport'],
        'orientation' => $data['orientation'],
        'color_depth' => $_POST['cd'] ?? 0
    ], JSON_UNESCAPED_UNICODE);
    
    $location_data = json_encode([
        'latitude' => $_POST['lat'] ?? null,
        'longitude' => $_POST['lng'] ?? null,
        'accuracy' => $_POST['acc'] ?? null,
        'timezone' => $data['timezone']
    ], JSON_UNESCAPED_UNICODE);
    
    $hardware_data = json_encode([
        'cores' => $_POST['cores'] ?? null,
        'memory' => $_POST['mem'] ?? null,
        'storage' => $_POST['storage'] ?? null,
        'battery' => $_POST['battery'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    
    $network_data = json_encode([
        'downlink' => $_POST['downlink'] ?? null,
        'effective_type' => $_POST['efftype'] ?? null,
        'rtt' => $_POST['rtt'] ?? null,
        'connection_type' => $data['connection_type']
    ], JSON_UNESCAPED_UNICODE);
    
    $browser_data = json_encode([
        'plugins' => $data['plugins'],
        'webgl' => $data['webgl_renderer'],
        'canvas_hash' => $data['canvas_hash'],
        'fonts_hash' => $data['fonts_hash']
    ], JSON_UNESCAPED_UNICODE);
    
    $system_data = json_encode([
        'os' => detectOS($data['user_agent']),
        'browser' => detectBrowser($data['user_agent']),
        'device' => detectDevice($data['user_agent']),
        'language' => $data['accept_lang']
    ], JSON_UNESCAPED_UNICODE);
    
    $security_flags = json_encode([
        'is_tor' => $data['is_tor'] ? 1 : 0,
        'https' => isset($_SERVER['HTTPS']) ? 1 : 0,
        'proxy_detected' => $data['forwarded_for'] ? 1 : 0,
        'cookie_enabled' => isset($_COOKIE[session_name()]) ? 1 : 0
    ], JSON_UNESCAPED_UNICODE);
    
    $data_hash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    
    $stmt->bind_param("sssssssssssss", 
        $username, $data['real_ip'], $data['device_fingerprint'], $data['user_agent'],
        $screen_data, $location_data, $hardware_data, $network_data,
        $browser_data, $system_data, $security_flags, session_id(),
        $data_hash
    );
    
    return $stmt->execute();
}

// كشف الجهاز والنظام
function detectDevice($ua) {
    $mobile_patterns = '/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i';
    $tablet_patterns = '/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i';
    
    if (preg_match($tablet_patterns, $ua)) return 'tablet';
    if (preg_match($mobile_patterns, $ua)) return 'mobile';
    return 'desktop';
}

function detectOS($ua) {
    $oses = [
        '/windows nt 10/i' => 'Windows 10',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/windows nt 6.2/i' => 'Windows 8',
        '/windows nt 6.1/i' => 'Windows 7',
        '/mac os x/i' => 'macOS',
        '/linux/i' => 'Linux',
        '/android/i' => 'Android',
        '/iphone|ipad|ipod/i' => 'iOS',
        '/ubuntu/i' => 'Ubuntu'
    ];
    
    foreach ($oses as $regex => $os) {
        if (preg_match($regex, $ua)) return $os;
    }
    return 'Unknown';
}

function detectBrowser($ua) {
    $browsers = [
        '/chrome/i' => 'Chrome',
        '/firefox/i' => 'Firefox',
        '/safari/i' => 'Safari',
        '/edge/i' => 'Edge',
        '/opera|opr/i' => 'Opera',
        '/msie|trident/i' => 'Internet Explorer',
        '/brave/i' => 'Brave'
    ];
    
    foreach ($browsers as $regex => $browser) {
        if (preg_match($regex, $ua)) return $browser;
    }
    return 'Unknown';
}

// معالج تسجيل الدخول المحسن
function handleEnhancedLogin($conn, $username, $password, &$error) {
    $username = trim(htmlspecialchars($username, ENT_QUOTES, 'UTF-8'));
    $password = trim($password);
    
    if (empty($username) || empty($password)) {
        $error = "يرجى إدخال اسم المستخدم وكلمة المرور";
        return false;
    }
    
    // التحقق من معدل المحاولات
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['attempts'] >= 5) {
        $error = "تم تجاوز عدد المحاولات المسموح بها. حاول مرة أخرى لاحقاً.";
        return false;
    }
    
    // التحقق من المستخدم
    $stmt = $conn->prepare("
        SELECT id, username, password, role, last_login 
        FROM users 
        WHERE username = ? AND status = 1 
        LIMIT 1
    ");
    
    if (!$stmt) {
        $error = "خطأ في النظام";
        return false;
    }
    
    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        $error = "خطأ في تنفيذ العملية";
        return false;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        // تسجيل محاولة فاشلة
        $fail_stmt = $conn->prepare("
            INSERT INTO login_attempts (ip_address, username, successful) 
            VALUES (?, ?, 0)
        ");
        $fail_stmt->bind_param("ss", $ip, $username);
        $fail_stmt->execute();
        
        $error = "بيانات الدخول غير صحيحة";
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password'])) {
        // تسجيل محاولة فاشلة
        $fail_stmt = $conn->prepare("
            INSERT INTO login_attempts (ip_address, username, successful) 
            VALUES (?, ?, 0)
        ");
        $fail_stmt->bind_param("ss", $ip, $username);
        $fail_stmt->execute();
        
        $error = "بيانات الدخول غير صحيحة";
        return false;
    }
    
    // جمع وحفظ البيانات المتقدمة
    $fullData = collectAdvancedData();
    saveEnhancedData($conn, $username, $fullData);
    
    // تحديث آخر دخول
    $update_stmt = $conn->prepare("
        UPDATE users SET last_login = NOW() WHERE id = ?
    ");
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
    
    // تسجيل محاولة ناجحة
    $success_stmt = $conn->prepare("
        INSERT INTO login_attempts (ip_address, username, successful) 
        VALUES (?, ?, 1)
    ");
    $success_stmt->bind_param("ss", $ip, $username);
    $success_stmt->execute();
    
    // إعداد الجلسة
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['session_fingerprint'] = generateSessionFingerprint();
    $_SESSION['user_data'] = $fullData;
    
    // توليد CSRF جديد
    $_SESSION['csrf_token'] = bin2hex(random_bytes(64));
    $_SESSION['csrf_expire'] = time() + 1800;
    
    return true;
}

function generateSessionFingerprint() {
    $components = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        session_id()
    ];
    return hash('sha256', implode('|', $components));
}

// تحديث الجلسة
updateSessionProtection();

// إنشاء الجداول إذا لم تكن موجودة
$conn->query("
    CREATE TABLE IF NOT EXISTS login_data_enhanced (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        device_fingerprint VARCHAR(64),
        user_agent TEXT,
        screen_data TEXT,
        location_data TEXT,
        hardware_data TEXT,
        network_data TEXT,
        browser_data TEXT,
        system_data TEXT,
        security_flags TEXT,
        session_id VARCHAR(255),
        login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_hash VARCHAR(64),
        INDEX idx_fingerprint (device_fingerprint),
        INDEX idx_ip_time (ip_address, login_time),
        INDEX idx_user_time (username, login_time),
        INDEX idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(100),
        successful TINYINT(1) DEFAULT 0,
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time),
        INDEX idx_success_time (successful, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// معالجة طلب POST
$error = "";
$otp_mode = false;
$otp_phone = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRF($csrf_token)) {
        $error = "طلب غير صالح أو منتهي الصلاحية";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (handleEnhancedLogin($conn, $username, $password, $error)) {
            $candidate = preg_replace('/\s+/', '', (string)$username);
            if (preg_match('/^\+?\d{10,15}$/', $candidate)) {
                $_SESSION['otp_phone'] = $candidate;
                $otp_phone = $candidate;
            }
            $otp_mode = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>نظام الدخول الآمن المتقدم</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding: 20px;
            color: #e2e8f0;
        }
        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        .login-card {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 35px;
        }
        .header h1 {
            color: #60a5fa;
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header p {
            color: #94a3b8;
            font-size: 15px;
        }
        .alert {
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fca5a5;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14.5px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .alert.hidden { display: none; }
        .form-group { margin-bottom: 22px; }
        .form-group label {
            display: block;
            color: #cbd5e1;
            margin-bottom: 8px;
            font-size: 14.5px;
            font-weight: 500;
        }
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-with-icon i {
            position: absolute;
            right: 18px;
            color: #64748b;
            font-size: 18px;
        }
        .input-with-icon input {
            width: 100%;
            padding: 16px 52px 16px 20px;
            background: rgba(15, 23, 42, 0.7);
            border: 2px solid rgba(100, 116, 139, 0.3);
            border-radius: 12px;
            color: #f1f5f9;
            font-size: 15.5px;
            transition: all 0.3s;
        }
        .input-with-icon input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .input-with-icon input::placeholder {
            color: #64748b;
        }
        .btn-submit {
            width: 100%;
            padding: 17px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
        .security-info {
            margin-top: 25px;
            padding: 15px;
            background: rgba(30, 64, 175, 0.1);
            border-radius: 12px;
            text-align: center;
            font-size: 13.5px;
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="header">
                <h1><i class="fas fa-shield-alt"></i> نظام الدخول الآمن</h1>
                <p>أنظمة حماية متقدمة وجمع بيانات ذكي</p>
            </div>
            
            <div id="errorAlert" class="alert <?= empty($error) ? 'hidden' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="errorText"><?= htmlspecialchars($error) ?></span>
            </div>
            
            <form method="post" id="loginForm" onsubmit="return handleSubmit()">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <!-- حقول بيانات JavaScript -->
                <input type="hidden" name="sw" id="sw">
                <input type="hidden" name="sh" id="sh">
                <input type="hidden" name="cd" id="cd">
                <input type="hidden" name="pr" id="pr">
                <input type="hidden" name="aw" id="aw">
                <input type="hidden" name="ah" id="ah">
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
                <input type="hidden" name="acc" id="acc">
                <input type="hidden" name="alt" id="alt">
                <input type="hidden" name="spd" id="spd">
                <input type="hidden" name="hdg" id="hdg">
                <input type="hidden" name="ts" id="ts">
                <input type="hidden" name="cores" id="cores">
                <input type="hidden" name="mem" id="mem">
                <input type="hidden" name="storage" id="storage">
                <input type="hidden" name="battery" id="battery">
                <input type="hidden" name="downlink" id="downlink">
                <input type="hidden" name="efftype" id="efftype">
                <input type="hidden" name="rtt" id="rtt">
                <input type="hidden" name="savedata" id="savedata">
                <input type="hidden" name="res" id="res">
                <input type="hidden" name="vp" id="vp">
                <input type="hidden" name="orient" id="orient">
                <input type="hidden" name="touch" id="touch">
                <input type="hidden" name="tz" id="tz">
                <input type="hidden" name="dst" id="dst">
                <input type="hidden" name="tzname" id="tzname">
                <input type="hidden" name="ctime" id="ctime">
                <input type="hidden" name="conntype" id="conntype">
                <input type="hidden" name="isp" id="isp">
                <input type="hidden" name="webgl" id="webgl">
                <input type="hidden" name="canvas" id="canvas">
                <input type="hidden" name="fonts" id="fonts">
                <input type="hidden" name="audio" id="audio">
                <input type="hidden" name="plugins" id="plugins">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> اسم المستخدم</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" id="username" name="username" 
                               placeholder="أدخل اسم المستخدم" required 
                               autocomplete="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> كلمة المرور</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="أدخل كلمة المرور" required 
                               autocomplete="current-password">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" id="submitBtn">
                    <span id="btnText">تسجيل الدخول</span>
                    <div class="loader" id="btnLoader"></div>
                </button>
            </form>
            <?php if ($otp_mode): ?>
            <div style="margin-top:20px;padding:15px;border:1px solid rgba(59,130,246,.3);border-radius:12px;background:rgba(30,64,175,.1)">
                <div style="margin-bottom:10px;color:#93c5fd;font-weight:600">التحقق عبر واتساب</div>
                <div class="form-group">
                    <label for="otpPhone"><i class="fas fa-phone"></i> رقم الواتساب</label>
                    <div class="input-with-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="otpPhone" placeholder="مثال: +201234567890" value="<?= htmlspecialchars($otp_phone) ?>">
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:10px">
                    <button class="btn-submit" type="button" onclick="sendOtp()"><span>إرسال رمز</span></button>
                </div>
                <div class="form-group">
                    <label for="otpCode"><i class="fas fa-key"></i> الرمز</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="text" id="otpCode" placeholder="أدخل الرمز">
                    </div>
                </div>
                <div style="display:flex;gap:10px">
                    <button class="btn-submit" type="button" onclick="verifyOtp()"><span>تأكيد</span></button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="security-info">
                <i class="fas fa-info-circle"></i>
                <span>نظام الحماية يجمع بيانات الجهاز والشبكة لتأمين دخولك</span>
            </div>
            
            <div class="footer">
                <p>© <?= date('Y') ?> نظام الحماية المتقدم. جميع الحقوق محفوظة.</p>
                <p style="margin-top: 5px; font-size: 12px; color: #475569;">
                    الإصدار 2.1 | تشفير SSL 256-bit
                </p>
            </div>
        </div>
    </div>

    <script>
        function sendOtp(){
            const phone = document.getElementById('otpPhone').value.trim();
            if(!/^\+?\d{10,15}$/.test(phone)){ alert('رقم غير صالح'); return; }
            fetch('otp_send.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({phone})})
                .then(r=>r.json()).then(d=>{
                    if(d.status==='sent'){ alert('تم إرسال الرمز'); } else { alert('فشل الإرسال'); }
                }).catch(()=>alert('خطأ بالإرسال'));
        }
        function verifyOtp(){
            const phone = document.getElementById('otpPhone').value.trim();
            const code  = document.getElementById('otpCode').value.trim();
            fetch('otp_verify.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({phone,code})})
                .then(r=>r.json()).then(d=>{
                    if(d.valid){ window.location.href='dashboard.php?auth='+Math.random().toString(16).slice(2); }
                    else{ alert('رمز غير صحيح أو منتهي'); }
                }).catch(()=>alert('خطأ بالتحقق'));
        }
        // جمع البيانات الأساسية
        document.addEventListener('DOMContentLoaded', function() {
            // بيانات الشاشة
            document.getElementById('sw').value = screen.width;
            document.getElementById('sh').value = screen.height;
            document.getElementById('cd').value = screen.colorDepth;
            document.getElementById('pr').value = window.devicePixelRatio;
            document.getElementById('aw').value = screen.availWidth;
            document.getElementById('ah').value = screen.availHeight;
            document.getElementById('res').value = screen.width + 'x' + screen.height;
            document.getElementById('vp').value = window.innerWidth + 'x' + window.innerHeight;
            
            // اتجاه الشاشة
            document.getElementById('orient').value = 
                screen.orientation ? screen.orientation.type : 
                (window.innerWidth > window.innerHeight ? 'landscape' : 'portrait');
            
            // دعم اللمس
            document.getElementById('touch').value = 
                ('ontouchstart' in window) || navigator.maxTouchPoints ? '1' : '0';
            
            // الوقت والمنطقة الزمنية
            const now = new Date();
            document.getElementById('ctime').value = now.toISOString();
            document.getElementById('tz').value = now.getTimezoneOffset();
            document.getElementById('tzname').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
            document.getElementById('dst').value = now.getTimezoneOffset() < 
                new Date(now.getFullYear(), 0, 1).getTimezoneOffset() ? '1' : '0';
            
            // بيانات الموقع الجغرافي
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    document.getElementById('lat').value = pos.coords.latitude;
                    document.getElementById('lng').value = pos.coords.longitude;
                    document.getElementById('acc').value = pos.coords.accuracy;
                    document.getElementById('alt').value = pos.coords.altitude || '';
                    document.getElementById('spd').value = pos.coords.speed || '';
                    document.getElementById('hdg').value = pos.coords.heading || '';
                    document.getElementById('ts').value = pos.timestamp;
                }, null, {enableHighAccuracy: true, timeout: 10000, maximumAge: 0});
            }
            
            // بيانات العتاد
            document.getElementById('cores').value = navigator.hardwareConcurrency || '';
            if (navigator.deviceMemory) {
                document.getElementById('mem').value = navigator.deviceMemory;
            }
            
            // بيانات الشبكة
            if (navigator.connection) {
                const conn = navigator.connection;
                document.getElementById('downlink').value = conn.downlink || '';
                document.getElementById('efftype').value = conn.effectiveType || '';
                document.getElementById('rtt').value = conn.rtt || '';
                document.getElementById('savedata').value = conn.saveData ? '1' : '0';
                document.getElementById('conntype').value = conn.type || 'unknown';
            }
            
            // بيانات البطارية
            if (navigator.getBattery) {
                navigator.getBattery().then(battery => {
                    document.getElementById('battery').value = 
                        Math.round(battery.level * 100) + '%';
                });
            }
            
            // بيانات التخزين
            if (navigator.storage && navigator.storage.estimate) {
                navigator.storage.estimate().then(estimate => {
                    document.getElementById('storage').value = 
                        Math.round(estimate.quota / (1024*1024)) + 'MB';
                });
            }
            
            // WebGL Renderer
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (gl) {
                    const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                    if (debugInfo) {
                        document.getElementById('webgl').value = 
                            gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                    }
                    
                    // Canvas Fingerprinting
                    gl.clearColor(0, 0, 0, 1);
                    gl.clear(gl.COLOR_BUFFER_BIT);
                    gl.viewport(0, 0, 1, 1);
                    gl.scissor(0, 0, 1, 1);
                    
                    const texture = gl.createTexture();
                    gl.bindTexture(gl.TEXTURE_2D, texture);
                    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, 
                        new Uint8Array([255, 0, 0, 255]));
                    
                    const data = new Uint8Array(4);
                    gl.readPixels(0, 0, 1, 1, gl.RGBA, gl.UNSIGNED_BYTE, data);
                    document.getElementById('canvas').value = data.join(',');
                }
            } catch(e) {}
            
            // جمع الخطوط
            const fonts = [
                'Arial', 'Verdana', 'Helvetica', 'Tahoma', 'Trebuchet MS', 
                'Times New Roman', 'Georgia', 'Garamond', 'Courier New', 
                'Brush Script MT'
            ];
            const availableFonts = [];
            
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            const text = "abcdefghijklmnopqrstuvwxyz0123456789";
            
            fonts.forEach(font => {
                context.font = '72px ' + font + ', monospace';
                const width1 = context.measureText(text).width;
                context.font = '72px monospace';
                const width2 = context.measureText(text).width;
                if (width1 !== width2) {
                    availableFonts.push(font);
                }
            });
            
            document.getElementById('fonts').value = JSON.stringify(availableFonts);
            
            // جمع الإضافات
            const plugins = [];
            for (let i = 0; i < navigator.plugins.length; i++) {
                plugins.push(navigator.plugins[i].name);
            }
            document.getElementById('plugins').value = JSON.stringify(plugins);
        });
        
        // معالجة الإرسال
        function handleSubmit() {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const loader = document.getElementById('btnLoader');
            
            btn.disabled = true;
            btnText.style.opacity = '0.5';
            loader.style.display = 'block';
            
            return true;
        }
        
        // إظهار/إخفاء كلمة المرور (اختياري)
        document.getElementById('password').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
    <script src="device_collector.js"></script>
</body>
</html>
<?php $conn->close(); ?>
