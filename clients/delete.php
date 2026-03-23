<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

// التحقق من CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// التحقق من الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "msg" => "❌ طلب غير صالح"]);
    exit;
}

if (!isset($_POST['id'], $_POST['csrf'])) {
    echo json_encode(["status" => "error", "msg" => "❌ بيانات ناقصة"]);
    exit;
}

if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    echo json_encode(["status" => "error", "msg" => "❌ رمز الحماية غير صحيح"]);
    exit;
}

// التحقق من صحة المعرف
$id = (int)$_POST['id'];
if ($id <= 0) {
    echo json_encode(["status" => "error", "msg" => "❌ معرف العميل غير صحيح"]);
    exit;
}

// التحقق من وجود العميل
$stmt = $conn->prepare("SELECT full_name, id_front, id_back, service_image FROM clients WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client) {
    echo json_encode(["status" => "error", "msg" => "❌ العميل غير موجود"]);
    exit;
}

// حذف الصور المرفقة
$upload_dir = __DIR__ . "/../uploads/";
$deleted_files = [];

$files_to_delete = [
    'id_front' => $client['id_front'],
    'id_back' => $client['id_back'],
    'service_image' => $client['service_image']
];

foreach ($files_to_delete as $file) {
    if ($file && file_exists($upload_dir . $file)) {
        if (unlink($upload_dir . $file)) {
            $deleted_files[] = $file;
        }
    }
}

// حذف العميل من قاعدة البيانات
$stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();
    
    // تسجيل عملية الحذف (اختياري)
    if (isset($_SESSION['username'])) {
        $log_message = sprintf(
            "قام %s بحذف العميل %s (ID: %d) - %s",
            $_SESSION['username'],
            $client['full_name'],
            $id,
            date('Y-m-d H:i:s')
        );
        // يمكنك حفظ هذا في جدول logs إذا كان موجوداً
    }
    
    echo json_encode([
        "status" => "success", 
        "msg" => "✅ تم حذف العميل بنجاح",
        "deleted_files" => count($deleted_files)
    ]);
} else {
    echo json_encode(["status" => "error", "msg" => "❌ فشل في حذف العميل من قاعدة البيانات"]);
    $stmt->close();
}
?>