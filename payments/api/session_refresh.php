<?php
/* ===============================
   API: Session Refresh
   تحديث الرصيد + الإشعارات
================================ */

header('Content-Type: application/json; charset=utf-8');

require_once "../../config/db.php";
require_once "../../config/auth.php";

/* ===============================
   التحقق من تسجيل الدخول
================================ */
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'unauthorized'
    ]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/* ===============================
   جلب الرصيد الحالي
================================ */
$stmt = $conn->prepare("
    SELECT balance 
    FROM users 
    WHERE id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

if ($balance === null) {
    http_response_code(401);
    echo json_encode([
        'error' => 'session_expired'
    ]);
    exit;
}

/* ===============================
   عدد الإشعارات غير المقروءة
================================ */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($notifications);
$stmt->fetch();
$stmt->close();

/* ===============================
   Response
================================ */
echo json_encode([
    'balance'       => number_format((float)$balance, 2),
    'notifications' => (int)$notifications
]);
