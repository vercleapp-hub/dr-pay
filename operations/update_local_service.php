<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'غير مسجل دخول']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']));
}

$input = json_decode(file_get_contents('php://input'), true);
$service_id = $input['service_id'] ?? null;
$service_data = $input['service_data'] ?? null;

if (!$service_id || !$service_data) {
    die(json_encode(['success' => false, 'error' => 'بيانات ناقصة']));
}

// تحميل البيانات الحالية
$local_services = [];
if (file_exists('local_services.json')) {
    $local_data = file_get_contents('local_services.json');
    $local_services = json_decode($local_data, true) ?? [];
}

// تحديث البيانات
$local_services[$service_id] = $service_data;

// حفظ البيانات
if (file_put_contents('local_services.json', json_encode($local_services, JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'فشل في الحفظ']);
}
?>