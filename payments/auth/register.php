<?php
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/* منع الدخول لو مسجل */
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $national_id = trim($_POST['national_id']);
    $address     = trim($_POST['address']);
    $password    = $_POST['password'];
    $confirm     = $_POST['confirm_password'];

    /* تحقق */
    if (!ctype_digit($phone)) {
        $msg = "❌ رقم الهاتف يجب أن يكون أرقام فقط";
    }
    elseif (!ctype_digit($national_id) || strlen($national_id) != 14) {
        $msg = "❌ الرقم القومي يجب أن يكون 14 رقم";
    }
    elseif ($password !== $confirm) {
        $msg = "❌ كلمة المرور غير متطابقة";
    }
    else {

        /* التأكد من عدم التكرار */
        $check = $conn->prepare("SELECT id FROM users WHERE username=?");
        $check->bind_param("s", $phone);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg = "❌ رقم الهاتف مسجل بالفعل";
        } else {

            /* رفع الصور */
            $upload_dir = __DIR__ . '/../uploads/ids/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            function upload($file, $dir) {
                if ($file['error'] !== 0) return null;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = uniqid().'.'.$ext;
                move_uploaded_file($file['tmp_name'], $dir.$name);
                return $name;
            }

            $id_front = upload($_FILES['id_front'], $upload_dir);
            $id_back  = upload($_FILES['id_back'],  $upload_dir);

            if (!$id_front || !$id_back) {
                $msg = "❌ يجب رفع صور البطاقة";
            } else {

                /* إنشاء الحساب */
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                  INSERT INTO users
                  (name, email, username, password, role, phone,
                   national_id, address, id_front, id_back, status)
                  VALUES
                  (?, ?, ?, ?, 'user', ?, ?, ?, ?, ?, 'pending')
                ");

                $stmt->bind_param(
                  "sssssssss",
                  $full_name,
                  $email,
                  $phone,
                  $hash,
                  $phone,
                  $national_id,
                  $address,
                  $id_front,
                  $id_back
                );

                $stmt->execute();

                $msg = "✅ تم إنشاء الحساب بنجاح – سيتم مراجعة البيانات";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>تسجيل تاجر جديد</title>
<style>
body{font-family:tahoma;background:#f4f6f8}
.box{max-width:500px;margin:auto;background:#fff;padding:20px;border-radius:10px}
input,textarea{width:100%;padding:10px;margin:6px 0}
button{width:100%;padding:10px;background:#28a745;color:#fff;border:0}
h3{margin-top:15px}
.msg{text-align:center;color:#c00}
.ok{color:green}
</style>
</head>

<body>

<div class="box">
<h2>📝 تسجيل تاجر جديد</h2>

<?php if($msg): ?>
<p class="<?= str_contains($msg,'✅')?'ok':'msg' ?>">
<?= $msg ?>
</p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

<h3>📌 بيانات التاجر</h3>
<input name="full_name" placeholder="الاسم الكامل" required>
<input name="email" type="email" placeholder="admin@drpay.com" required>
<input name="phone" placeholder="رقم الهاتف (اسم المستخدم)" pattern="[0-9]+" required>
<input type="password" name="password" placeholder="كلمة المرور" required>
<input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور" required>

<h3>🪪 بيانات الهوية</h3>
<input name="national_id" placeholder="الرقم القومي (14 رقم)" pattern="[0-9]{14}" required>
<textarea name="address" placeholder="العنوان بالتفصيل" required></textarea>

<h3>📷 صور البطاقة</h3>
<label>صورة البطاقة (الوجه)</label>
<input type="file" name="id_front" accept="image/*" required>

<label>صورة البطاقة (الظهر)</label>
<input type="file" name="id_back" accept="image/*" required>

<button>✔ إنشاء الحساب</button>
</form>

</div>

</body>
</html>
