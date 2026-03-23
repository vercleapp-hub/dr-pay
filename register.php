<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . "/config/login.php";

// التحقق من وجود الجدول وإنشائه إذا لم يكن موجوداً
$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(15) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user','driver','admin') DEFAULT 'user',
        status ENUM('active','inactive','suspended') DEFAULT 'active',
        is_active TINYINT(1) DEFAULT 1,
        failed_attempts INT DEFAULT 0,
        lock_until DATETIME NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        email VARCHAR(100) NULL,
        last_login DATETIME NULL,
        last_ip VARCHAR(45) NULL,
        session_token VARCHAR(255) NULL,
        last_latitude DECIMAL(10,8) NULL,
        last_longitude DECIMAL(11,8) NULL,
        last_location_name VARCHAR(255) NULL,
        last_user_agent TEXT NULL,
        login_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone (phone),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim(preg_replace('/[^0-9]/', '', $_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    // التحقق من المدخلات
    if (empty($full_name) || empty($phone) || empty($password)) {
        $error = "❌ جميع الحقول المطلوبة يجب ملؤها";
    } elseif (!preg_match('/^\d{10,15}$/', $phone)) {
        $error = "❌ رقم الهاتف غير صالح (يجب أن يكون 10-15 رقم)";
    } elseif (strlen($password) < 6) {
        $error = "❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } elseif ($password !== $confirm_password) {
        $error = "❌ كلمة المرور غير متطابقة";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ البريد الإلكتروني غير صالح";
    } else {
        // التحقق من عدم وجود الرقم مسجل مسبقاً
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "❌ رقم الهاتف مسجل مسبقاً";
        } else {
            // إنشاء حساب جديد
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            
            $stmt = $conn->prepare("
                INSERT INTO users (full_name, phone, password, email, last_ip, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssss", $full_name, $phone, $hashed_password, $email, $ip);
            
            if ($stmt->execute()) {
                $success = "✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن";
                
                // تسجيل عملية التسجيل
                $log_stmt = $conn->prepare("
                    INSERT INTO login_attempts (ip_address, username, user_agent, status, reason, attempt_time) 
                    VALUES (?, ?, ?, 'success', 'account_created', NOW())
                ");
                $log_stmt->bind_param("sss", $ip, $phone, $_SERVER['HTTP_USER_AGENT'] ?? '');
                $log_stmt->execute();
                
                // إعادة توجيه بعد 3 ثواني
                header("refresh:3;url=index.php");
            } else {
                $error = "❌ حدث خطأ أثناء إنشاء الحساب";
            }
            $stmt->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل جديد - Dr Pay</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1d2671, #c33764);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            width: 100%;
            max-width: 500px;
            padding: 40px 35px;
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo img {
            max-width: 150px;
        }

        h2 {
            text-align: center;
            color: #1d2671;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .alert-error {
            background: #f8d7da;
            color: #b71c1c;
            border-right: 5px solid #c33764;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-right: 5px solid #28a745;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 18px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 50px 15px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .input-group input:focus {
            border-color: #1d2671;
            background: white;
            box-shadow: 0 0 0 4px rgba(29, 38, 113, 0.1);
            outline: none;
        }

        .password-toggle {
            left: 15px !important;
            right: auto !important;
            cursor: pointer;
        }

        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #1d2671, #c33764);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 15px rgba(29, 38, 113, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(29, 38, 113, 0.4);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #1d2671;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
            padding-right: 15px;
        }

        .hint i {
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <img src="https://firebasestorage.googleapis.com/v0/b/drpay-fdc61.appspot.com/o/%D8%B4%D8%B1%D9%83%D8%A9%20%D8%A7%D9%84%D8%AF%D9%83%D8%AA%D9%88%D8%B1%20%D8%A8%D8%A7%D9%8A%2001063151472.png?alt=media&token=93234ae8-344f-4e5a-ab9b-616300010f3a" alt="Dr Pay">
        </div>

        <h2>إنشاء حساب جديد</h2>

        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="full_name" placeholder="الاسم الكامل" required value="<?=htmlspecialchars($_POST['full_name'] ?? '')?>">
            </div>

            <div class="input-group">
                <i class="fas fa-phone"></i>
                <input type="tel" name="phone" id="phone" placeholder="رقم المحمول" required value="<?=htmlspecialchars($_POST['phone'] ?? '')?>" pattern="\d{10,15}" maxlength="15">
            </div>
            <div class="hint">
                <i class="fas fa-info-circle"></i> أدخل رقم الهاتف بدون صفر أو رمز دولي (10-15 رقم)
            </div>

            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="البريد الإلكتروني (اختياري)" value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="كلمة المرور" required minlength="6">
                <i class="fas fa-eye password-toggle" onclick="togglePassword('password', this)"></i>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="تأكيد كلمة المرور" required minlength="6">
                <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
            </div>
            <div class="hint">
                <i class="fas fa-info-circle"></i> كلمة المرور يجب أن تكون 6 أحرف على الأقل
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> إنشاء حساب
            </button>
        </form>

        <div class="login-link">
            <a href="index.php">
                <i class="fas fa-sign-in-alt"></i> لديك حساب بالفعل؟ سجل دخولك
            </a>
        </div>

        <div class="footer">
            <i class="fas fa-copyright"></i> الدكتور باي <?=date("Y")?> | جميع الحقوق محفوظة
        </div>
    </div>

    <script>
        // تبديل إظهار كلمة المرور
        function togglePassword(fieldId, element) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                element.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                element.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // منع الأحرف غير الرقمية في حقل الهاتف
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/[^\d]/g, '').slice(0, 15);
        });

        // التحقق من تطابق كلمة المرور عند الإرسال
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('❌ كلمة المرور غير متطابقة');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل');
                return false;
            }
        });
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>