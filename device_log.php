<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/DeviceInfoCollector.php';

$collector = new DeviceInfoCollector($conn);
$ok = $collector->logAllDeviceInfo($_POST);
echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
