<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "config/auth.php";

$result = login("1000", "123456");
var_dump($result);
