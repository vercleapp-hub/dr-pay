<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


date_default_timezone_set('Africa/Cairo');


$DB_HOST = 'sql302.infinityfree.com';
$DB_USER = 'if0_40974310';
$DB_PASS = '1p7F4ABr5utA4';
$DB_NAME = 'if0_40974310_payments';


$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
die('DB Error: ' . $conn->connect_error);
}