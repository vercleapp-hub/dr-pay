<?php
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();
requireRole(['admin']);

$deposits = $conn->query("
    SELECT 
        d.*, 
        u.username,
        m.name AS method_name,
        m.details
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN deposit_methods m ON d.method = m.id
    ORDER BY d.created_at DESC
");

include __DIR__ . '/header.php';
?>

<h2 style="text-align:center;margin:10px 0 18px;">📥 طلبات الإيداع</h2>

<table style="width:100%;background:#fff;border-collapse:collapse;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <tr style="background:#333;color:#fff;">
        <th style="padding:10px;border:1px solid #eee;">المستخدم</th>
        <th style="padding:10px;border:1px solid #eee;">المبلغ</th>
        <th style="padding:10px;border:1px solid #eee;">الطريقة</th>
        <th style="padding:10px;border:1px solid #eee;">بيانات التحويل</th>
        <th style="padding:10px;border:1px solid #eee;">الصورة</th>
        <th style="padding:10px;border:1px solid #eee;">الحالة</th>
        <th style="padding:10px;border:1px solid #eee;">التاريخ</th>
    </tr>

    <?php while($d = $deposits->fetch_assoc()): ?>
    <tr>
        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?= htmlspecialchars($d['username'], ENT_QUOTES, 'UTF-8') ?>
        </td>

        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?= number_format((float)$d['amount'], 2) ?> ج.م
        </td>

        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?= htmlspecialchars($d['method_name'], ENT_QUOTES, 'UTF-8') ?>
        </td>

        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?= nl2br(htmlspecialchars($d['details'], ENT_QUOTES, 'UTF-8')) ?>
        </td>

        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?php if (!empty($d['image'])): ?>
                <a href="../uploads/deposits/<?= rawurlencode($d['image']) ?>" target="_blank">عرض</a>
            <?php else: ?>
                —
            <?php endif; ?>
        </td>

        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?= htmlspecialchars($d['status'], ENT_QUOTES, 'UTF-8') ?>
        </td>

        <td style="padding:10px;border:1px solid #eee;text-align:center;">
            <?= htmlspecialchars($d['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<?php include __DIR__ . '/footer.php'; ?>
