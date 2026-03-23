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

function generateCode($length = 6) {
    $min = (int)str_pad('1', $length, '0');
    $max = (int)str_pad('', $length, '9');
    return (string)random_int($min, $max);
}

function fingerprint() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ua . '|' . $ip);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method_not_allowed'], 405);
}

$phone   = sanitizePhone($_POST['phone'] ?? '');
$purpose = $_POST['purpose'] ?? 'login';

if (!$phone) {
    jsonResponse(['error' => 'phone_required'], 422);
}

$code = generateCode(6);
$expires_at = date('Y-m-d H:i:s', time() + 10 * 60);
$session_id = session_id();
$fp = fingerprint();
$user_id = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$stmt = $conn->prepare("
    INSERT INTO otp_codes (user_id, phone, code, purpose, attempts, max_attempts, expires_at, verified, session_id, device_fingerprint)
    VALUES (?, ?, ?, ?, 0, 5, ?, 0, ?, ?)
");
$stmt->bind_param("issssss", $user_id, $phone, $code, $purpose, $expires_at, $session_id, $fp);
$stmt->execute();
$otp_id = $stmt->insert_id;
$stmt->close();

$ultra_instance = getenv('ULTRAMSG_INSTANCE_ID') ?: 'instance166591';
$ultra_token    = getenv('ULTRAMSG_TOKEN') ?: 'merz7515ky1dxc2v';
$ultra_url      = "https://api.ultramsg.com/{$ultra_instance}/messages/chat";

$body = "رمز التحقق: {$code} (صالح لمدة 10 دقائق)";

$ch = curl_init($ultra_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
        'token' => $ultra_token,
        'to' => $phone,
        'body' => $body,
        'priority' => 0,
        'referenceId' => 'OTP'
    ])
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    jsonResponse(['error' => 'send_failed', 'detail' => $err], 500);
}

if ($httpcode < 200 || $httpcode >= 300) {
    jsonResponse(['error' => 'send_failed', 'status' => $httpcode, 'response' => $response], 502);
}

jsonResponse(['status' => 'sent', 'otp_id' => $otp_id]);
