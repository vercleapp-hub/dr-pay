<?php
if (session_status() === PHP_SESSION_NONE) session_start();


function requireLogin() {
if (empty($_SESSION['user_id'])) {
header('Location: /payments/auth/login.php'); exit;
}
}
function requireAdmin() {
requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
http_response_code(403); exit('Forbidden');
}
}