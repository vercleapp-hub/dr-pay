<?php
require_once "config/auth.php";
requireLogin();

switch ($_SESSION['role']) {
    case 'admin':
        header("Location: admin/");
        break;

    case 'agent':
        header("Location: agent/");
        break;

    default:
        header("Location: user/");
        break;
}
exit;
