<?php
require_once "../config/auth.php";
require_once "../config/db.php";

requireLogin();
requireAdmin();

// استدعاء هيدر الأدمن (داخل نفس المجلد)
include "header.php";

/* منع كاش الصفحات بعد تسجيل الخروج */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* =========================
   CSRF Token
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   تحديث بيانات المستخدم
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // حماية CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("طلب غير صالح (CSRF).");
    }

    $user_id = intval($_POST['user_id']);

    // تحديث الحالة
    if (isset($_POST['status'])) {
        $status = $_POST['status'];
        $allowedStatus = ['active', 'pending', 'blocked'];
        if (in_array($status, $allowedStatus, true)) {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $user_id);
            $stmt->execute();
        }
    }

    // تحديث الصلاحية
    if (isset($_POST['role'])) {
        $role = $_POST['role'];
        $allowedRoles = ['user', 'agent', 'admin'];
        if (in_array($role, $allowedRoles, true)) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $role, $user_id);
            $stmt->execute();
        }
    }

    // تحديث الرصيد
    if (isset($_POST['balance'])) {
        $balance = floatval($_POST['balance']);
        if ($balance < 0) $balance = 0;

        $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->bind_param("di", $balance, $user_id);
        $stmt->execute();
    }

    header("Location: users.php?updated=1");
    exit;
}

/* =========================
   جلب المستخدمين
========================= */
$users = $conn->query("
    SELECT u.*, a.name AS agent_name
    FROM users u
    LEFT JOIN agents a ON u.agent_id = a.id
    ORDER BY u.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين</title>

    <style>
        body{
            font-family:Tahoma,Arial;
            background:#f7f7f7;
            margin:0;
        }
        .container{
            max-width:1100px;
            margin:0 auto;
            padding:14px;
        }

        h2{
            margin: 0 0 12px 0;
            text-align:center;
        }

        .success{
            background:#d4edda;
            color:#155724;
            padding:12px;
            border-radius:12px;
            border:1px solid #c3e6cb;
            text-align:center;
            margin-bottom:12px;
            box-shadow:0 6px 16px rgba(0,0,0,.05);
            font-weight:700;
        }

        table{
            width:100%;
            background:#fff;
            border-collapse:collapse;
            border-radius:14px;
            overflow:hidden;
            border:1px solid #eee;
            box-shadow:0 6px 16px rgba(0,0,0,.05);
        }

        th, td{
            padding:10px;
            border:1px solid #eee;
            text-align:center;
            font-size:14px;
        }

        th{
            background:#111;
            color:#fff;
            font-weight:800;
        }

        select, input{
            padding:7px 8px;
            border-radius:12px;
            border:1px solid #ccc;
            outline:none;
            font-size:13px;
            width: 100%;
            max-width: 140px;
        }

        .btn-save{
            padding:8px 12px;
            border:0;
            border-radius:12px;
            cursor:pointer;
            background:#007bff;
            color:#fff;
            font-weight:800;
            font-size:13px;
        }

        .btn-save:hover{
            opacity:.9;
        }

        .agent-name{
            font-weight:700;
            color:#444;
        }

        @media (max-width: 768px){
            th, td{font-size:12px;padding:8px}
            select, input{max-width:110px}
        }
    </style>
</head>

<body>

<div class="container">

    <h2>👤 إدارة المستخدمين</h2>

    <?php if (isset($_GET['updated'])): ?>
        <div class="success">✅ تم حفظ التعديلات بنجاح</div>
    <?php endif; ?>

    <table>
        <tr>
            <th>ID</th>
            <th>اسم المستخدم</th>
            <th>الدور</th>
            <th>الحالة</th>
            <th>الرصيد (ج.م)</th>
            <th>الوكيل</th>
            <th>حفظ</th>
        </tr>

        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
            <form method="post">
                <td><?= (int)$u['id'] ?></td>

                <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>

                <td>
                    <select name="role">
                        <option value="user"  <?= $u['role']=='user'?'selected':'' ?>>مستخدم</option>
                        <option value="agent" <?= $u['role']=='agent'?'selected':'' ?>>وكيل</option>
                        <option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>أدمن</option>
                    </select>
                </td>

                <td>
                    <select name="status">
                        <option value="active"  <?= $u['status']=='active'?'selected':'' ?>>نشط</option>
                        <option value="pending" <?= $u['status']=='pending'?'selected':'' ?>>قيد المراجعة</option>
                        <option value="blocked" <?= $u['status']=='blocked'?'selected':'' ?>>محظور</option>
                    </select>
                </td>

                <td>
                    <input type="number" step="0.01" name="balance" value="<?= (float)$u['balance'] ?>">
                </td>

                <td class="agent-name">
                    <?= !empty($u['agent_name']) ? htmlspecialchars($u['agent_name'], ENT_QUOTES, 'UTF-8') : '—' ?>
                </td>

                <td>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button class="btn-save" type="submit">💾 حفظ</button>
                </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </table>

</div>

</body>
</html>
