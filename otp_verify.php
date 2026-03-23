<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/login.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizePhone($phone) {
    $p = preg_replace('/\s+/', '', (string)$phone);
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method_not_allowed'], 405);
}

$phone = sanitizePhone($_POST['phone'] ?? '');
$code  = (string)($_POST['code'] ?? '');

if (!$phone || !$code) {
    jsonResponse(['error' => 'missing_params'], 422);
}

$stmt = $conn->prepare("
    SELECT id, attempts, max_attempts, expires_at, verified 
    FROM otp_codes 
    WHERE phone = ? AND code = ? 
    ORDER BY id DESC 
    LIMIT 1
");
$stmt->bind_param("ss", $phone, $code);
$stmt->execute();
$stmt->bind_result($id, $attempts, $max_attempts, $expires_at, $verified);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    jsonResponse(['valid' => false, 'reason' => 'not_found'], 404);
}

if ((int)$verified === 1) {
    jsonResponse(['valid' => false, 'reason' => 'already_verified'], 409);
}

if ($attempts >= $max_attempts) {
    jsonResponse(['valid' => false, 'reason' => 'max_attempts'], 429);
}

if (strtotime($expires_at) < time()) {
    jsonResponse(['valid' => false, 'reason' => 'expired'], 410);
}

$stmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE otp_codes SET verified = 1 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

jsonResponse(['valid' => true]);
