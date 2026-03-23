<?php
/*********************************
 * عرض الأخطاء (للتطوير فقط)
 *********************************/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*********************************
 * إعدادات MySQLi
 *********************************/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*********************************
 * الاتصال بقاعدة بيانات العمليات
 *********************************/
$conn_operations = new mysqli(
    "sql302.infinityfree.com",
    "if0_40974310",
    "1p7F4ABr5utA4",
    "if0_40974310_operations"
);

/*********************************
 * ترميز عربي صحيح
 *********************************/
$conn_operations->set_charset("utf8mb4");

/*********************************
 * ضبط التوقيت (مصر)
 *********************************/
$conn_operations->query("SET time_zone = '+02:00'");
