<?php
require_once "../config/auth.php";
requireLogin();
requireAdmin();

header("Location: dashboard.php");
exit;
