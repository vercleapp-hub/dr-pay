<?php
require_once "../config/auth.php";
requireLogin();
requireAgent();

header("Location: dashboard.php");
exit;
