<?php
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/* لو مسجل دخول بالفعل */
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

/* تسجيل الدخول */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']); // رقم فقط
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (!ctype_digit($username)) {
        $error = "❌ اسم المستخدم يجب أن يكون رقم فقط";
    } else {

        $stmt = $conn->prepare("
            SELECT id, username, password, role 
            FROM users 
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {

            /* Session */
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];

            /* Remember Me */
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie(
                    'remember_token',
                    $token,
                    time() + (86400 * 30),
                    "/",
                    "",
                    false,
                    true
                );

                $st = $conn->prepare("
                    UPDATE users SET remember_token=? WHERE id=?
                ");
                $st->bind_param("si", $token, $user['id']);
                $st->execute();
            }

            /* توجيه حسب الصلاحية */
            if ($user['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
            exit;

        } else {
            $error = "❌ بيانات الدخول غير صحيحة";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>تسجيل الدخول</title>
<style>
body{
  font-family:tahoma;
  background:#f2f4f7;
  display:flex;
  justify-content:center;
  align-items:center;
  height:100vh;
}
.box{
  background:#fff;
  padding:20px;
  border-radius:10px;
  width:320px;
}
input{
  width:100%;
  padding:10px;
  margin:6px 0;
}
button{
  width:100%;
  padding:10px;
  background:#007bff;
  border:0;
  color:#fff;
  border-radius:6px;
}
.error{color:red;text-align:center}
.small{font-size:13px;color:#666}
</style>
</head>

<body>

<div class="box">
<h2 style="text-align:center">🔐 Dr Pay</h2>

<?php if(!empty($error)): ?>
<p class="error"><?= $error ?></p>
<?php endif; ?>

<form method="post">
<input 
  type="text" 
  name="username" 
  placeholder="رقم المستخدم"
  pattern="[0-9]+"
  required
>

<input 
  type="password" 
  name="password" 
  placeholder="كلمة المرور"
  required
>

<label class="small">
<input type="checkbox" name="remember">
 حفظ بيانات الدخول
</label>

<button>تسجيل الدخول</button>
</form>

<p class="small" style="text-align:center;margin-top:10px">
ليس لديك حساب؟  
<a href="register.php">إنشاء حساب</a>
</p>
</div>

</body>
</html>
