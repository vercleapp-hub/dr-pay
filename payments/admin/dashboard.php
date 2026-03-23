<?php
/************************************
 * Admin Dashboard – Dr Pay
 ************************************/

require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   إحصائيات عامة
================================ */

// عدد المستخدمين
$users_count = $conn->query(
    "SELECT COUNT(*) total FROM users WHERE role='user'"
)->fetch_assoc()['total'];

// عدد الأدمن
$admins_count = $conn->query(
    "SELECT COUNT(*) total FROM users WHERE role='admin'"
)->fetch_assoc()['total'];

// الرصيد الإجمالي
$total_balance = $conn->query(
    "SELECT SUM(balance) total FROM users"
)->fetch_assoc()['total'] ?? 0;

// إحصائيات الإيداعات
$stats = [];
$q = $conn->query("
    SELECT status, COUNT(*) c, SUM(amount) total_amount 
    FROM deposits 
    GROUP BY status
");
while ($row = $q->fetch_assoc()) {
    $stats[$row['status']] = $row;
}

$pending  = $stats['pending']['c'] ?? 0;
$approved = $stats['approved']['c'] ?? 0;
$rejected = $stats['rejected']['c'] ?? 0;
$pending_amount = $stats['pending']['total_amount'] ?? 0;
$approved_amount = $stats['approved']['total_amount'] ?? 0;

// إحصائيات العمليات
$transactions_count = $conn->query(
    "SELECT COUNT(*) total FROM transactions"
)->fetch_assoc()['total'];

$today_transactions = $conn->query("
    SELECT COUNT(*) total FROM transactions 
    WHERE DATE(created_at) = CURDATE()
")->fetch_assoc()['total'];

// التحقق من وجود عمود description في transactions
$check_column = $conn->query("SHOW COLUMNS FROM transactions LIKE 'description'");
$has_description = $check_column->num_rows > 0;

// آخر الإيداعات
$last_deposits = $conn->query("
    SELECT d.id, d.amount, d.status, d.created_at, u.name, u.id as user_id
    FROM deposits d
    JOIN users u ON u.id = d.user_id
    ORDER BY d.id DESC
    LIMIT 5
");

// آخر العمليات - استعلام معدل بدون type
if ($has_description) {
    $last_transactions = $conn->query("
        SELECT t.id, t.amount, t.description, t.created_at, u.name, t.status
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        ORDER BY t.id DESC
        LIMIT 5
    ");
} else {
    // إذا لم يكن هناك عمود description، نستخدم transaction_type أو نعرض بدون نوع
    $last_transactions = $conn->query("
        SELECT t.id, t.amount, t.created_at, u.name, t.status
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        ORDER BY t.id DESC
        LIMIT 5
    ");
}

// آخر المستخدمين المسجلين
$last_users = $conn->query("
    SELECT id, name, email, phone, balance, created_at 
    FROM users 
    WHERE role='user'
    ORDER BY id DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة تحكم الأدمن | Dr Pay</title>

<style>
* {
    box-sizing: border-box;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f8;
    margin: 0;
    color: #333;
}
.container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}
.header {
    background: linear-gradient(135deg, #2c3e50, #4a6491);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header h1 {
    margin: 0;
    font-size: 24px;
}
.header h1 i {
    margin-right: 10px;
}
.dashboard-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 25px;
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.nav-btn {
    padding: 10px 20px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    color: #495057;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}
.nav-btn:hover {
    background: #007bff;
    color: white;
    border-color: #007bff;
    transform: translateY(-2px);
}
.nav-btn i {
    font-size: 16px;
}
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
}
.card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.card p {
    font-size: 28px;
    margin: 0;
    font-weight: bold;
    color: #2c3e50;
}
.card .subtext {
    font-size: 14px;
    color: #28a745;
    margin-top: 5px;
}
.card-icon {
    font-size: 24px;
    margin-bottom: 15px;
    color: #007bff;
}
.section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f8f9fa;
}
.section-header h3 {
    margin: 0;
    color: #2c3e50;
}
.view-all {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
}
.view-all:hover {
    text-decoration: underline;
}
.table-container {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}
th {
    background: #f8f9fa;
    padding: 15px;
    text-align: right;
    font-weight: bold;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}
td {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    text-align: right;
}
tr:hover {
    background: #f8f9fa;
}
.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}
.status-pending {
    background: #fff3cd;
    color: #856404;
}
.status-approved {
    background: #d4edda;
    color: #155724;
}
.status-rejected {
    background: #f8d7da;
    color: #721c24;
}
.status-completed {
    background: #d1ecf1;
    color: #0c5460;
}
.btn-action {
    padding: 5px 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    margin: 0 3px;
    text-decoration: none;
    display: inline-block;
}
.btn-view {
    background: #17a2b8;
    color: white;
}
.btn-edit {
    background: #ffc107;
    color: #212529;
}
.btn-delete {
    background: #dc3545;
    color: white;
}
.btn-print {
    background: #6c757d;
    color: white;
}
.btn-action:hover {
    opacity: 0.9;
}
.action-btns {
    display: flex;
    gap: 5px;
    justify-content: center;
}
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}
.quick-action {
    background: white;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
}
.quick-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.quick-action i {
    font-size: 24px;
    color: #007bff;
    margin-bottom: 10px;
}
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}
.stat-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}
.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
}
.stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    .dashboard-nav {
        flex-direction: column;
    }
    .nav-btn {
        width: 100%;
        justify-content: center;
    }
    .cards {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-tachometer-alt"></i> لوحة تحكم الأدمن – Dr Pay</h1>
        <div>
            <span>مرحباً, <?php echo $_SESSION['user_name'] ?? 'مدير'; ?></span>
            <a href="../auth/logout.php" class="nav-btn" style="margin-left: 15px;">
                <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
            </a>
        </div>
    </div>

    <!-- قائمة التنقل الرئيسية -->
    <div class="dashboard-nav">
        <a href="pending.php" class="nav-btn">
            <i class="fas fa-clock"></i> الإيداعات المعلقة
        </a>
        <a href="deposits.php" class="nav-btn">
            <i class="fas fa-money-bill-wave"></i> جميع الإيداعات
        </a>
        <a href="users.php" class="nav-btn">
            <i class="fas fa-users"></i> إدارة المستخدمين
        </a>
        <a href="transactions.php" class="nav-btn">
            <i class="fas fa-exchange-alt"></i> سجل العمليات
        </a>
        <a href="services.php" class="nav-btn">
            <i class="fas fa-concierge-bell"></i> إدارة الخدمات
        </a>
        <a href="add_user.php" class="nav-btn">
            <i class="fas fa-user-plus"></i> إضافة مستخدم جديد
        </a>
        <a href="settings.php" class="nav-btn">
            <i class="fas fa-cog"></i> الإعدادات
        </a>
        <a href="reports.php" class="nav-btn">
            <i class="fas fa-chart-bar"></i> التقارير
        </a>
    </div>

    <!-- الإحصائيات -->
    <div class="cards">
        <div class="card">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <h3>المستخدمين</h3>
            <p><?= $users_count ?></p>
            <div class="subtext"><?= $admins_count ?> أدمن</div>
        </div>
        
        <div class="card">
            <div class="card-icon"><i class="fas fa-wallet"></i></div>
            <h3>الرصيد الإجمالي</h3>
            <p><?= number_format($total_balance, 2) ?> EGP</p>
        </div>
        
        <div class="card">
            <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
            <h3>إيداعات معلقة</h3>
            <p><?= $pending ?></p>
            <div class="subtext"><?= number_format($pending_amount, 2) ?> EGP</div>
        </div>
        
        <div class="card">
            <div class="card-icon"><i class="fas fa-check-circle"></i></div>
            <h3>إيداعات مقبولة</h3>
            <p><?= $approved ?></p>
            <div class="subtext"><?= number_format($approved_amount, 2) ?> EGP</div>
        </div>
        
        <div class="card">
            <div class="card-icon"><i class="fas fa-exchange-alt"></i></div>
            <h3>العمليات</h3>
            <p><?= $transactions_count ?></p>
            <div class="subtext"><?= $today_transactions ?> عملية اليوم</div>
        </div>
        
        <div class="card">
            <div class="card-icon"><i class="fas fa-ban"></i></div>
            <h3>إيداعات مرفوضة</h3>
            <p><?= $rejected ?></p>
        </div>
    </div>

    <!-- إجراءات سريعة -->
    <div class="section">
        <h3>إجراءات سريعة</h3>
        <div class="quick-actions">
            <a href="add_deposit.php" class="quick-action">
                <i class="fas fa-plus-circle"></i>
                <div>إضافة إيداع</div>
            </a>
            <a href="manual_transaction.php" class="quick-action">
                <i class="fas fa-hand-holding-usd"></i>
                <div>عملية يدوية</div>
            </a>
            <a href="notifications.php" class="quick-action">
                <i class="fas fa-bell"></i>
                <div>إرسال إشعار</div>
            </a>
            <a href="search.php" class="quick-action">
                <i class="fas fa-search"></i>
                <div>بحث متقدم</div>
            </a>
            <a href="backup.php" class="quick-action">
                <i class="fas fa-database"></i>
                <div>نسخ احتياطي</div>
            </a>
        </div>
    </div>

    <!-- آخر الإيداعات -->
    <div class="section">
        <div class="section-header">
            <h3><i class="fas fa-history"></i> آخر الإيداعات</h3>
            <a href="deposits.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المستخدم</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($d = $last_deposits->fetch_assoc()): ?>
                    <tr>
                        <td><?= $d['id'] ?></td>
                        <td>
                            <a href="user_profile.php?id=<?= $d['user_id'] ?>" style="color: #007bff;">
                                <?= htmlspecialchars($d['name']) ?>
                            </a>
                        </td>
                        <td><strong><?= number_format($d['amount'], 2) ?> EGP</strong></td>
                        <td>
                            <span class="status status-<?= $d['status'] ?>">
                                <?= $d['status'] ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($d['created_at'])) ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="deposit_details.php?id=<?= $d['id'] ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if($d['status'] == 'pending'): ?>
                                <a href="approve_deposit.php?id=<?= $d['id'] ?>" class="btn-action btn-edit">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="reject_deposit.php?id=<?= $d['id'] ?>" class="btn-action btn-delete">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                                <a href="print.php?id=<?= $d['id'] ?>" target="_blank" class="btn-action btn-print">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- آخر العمليات -->
    <div class="section">
        <div class="section-header">
            <h3><i class="fas fa-exchange-alt"></i> آخر العمليات</h3>
            <a href="transactions.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المستخدم</th>
                        <th>المبلغ</th>
                        <?php if ($has_description): ?>
                        <th>الوصف</th>
                        <?php endif; ?>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($last_transactions): ?>
                    <?php while ($t = $last_transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td><?= htmlspecialchars($t['name']) ?></td>
                        <td><strong><?= number_format($t['amount'], 2) ?> EGP</strong></td>
                        <?php if ($has_description): ?>
                        <td><?= htmlspecialchars($t['description'] ?? '') ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="status status-<?= $t['status'] ?>">
                                <?= $t['status'] ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                        <td>
                            <a href="transaction_details.php?id=<?= $t['id'] ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> تفاصيل
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="<?= $has_description ? '7' : '6' ?>" style="text-align: center; padding: 20px;">
                            لا توجد عمليات حالياً
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- آخر المستخدمين -->
    <div class="section">
        <div class="section-header">
            <h3><i class="fas fa-user-plus"></i> آخر المستخدمين المسجلين</h3>
            <a href="users.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>الرصيد</th>
                        <th>تاريخ التسجيل</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $last_users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['phone'] ?></td>
                        <td><strong><?= number_format($u['balance'], 2) ?> EGP</strong></td>
                        <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="user_profile.php?id=<?= $u['id'] ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn-action btn-edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="add_balance.php?id=<?= $u['id'] ?>" class="btn-action" style="background: #28a745; color: white;">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- إحصائيات إضافية -->
    <div class="section">
        <h3>إحصائيات سريعة</h3>
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value"><?= $pending ?></div>
                <div class="stat-label">إيداع معلق</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $today_transactions ?></div>
                <div class="stat-label">عملية اليوم</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $users_count ?></div>
                <div class="stat-label">مستخدم نشط</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($total_balance, 0) ?></div>
                <div class="stat-label">إجمالي الأرصدة</div>
            </div>
        </div>
    </div>

</div>

<script>
// تحديث الإحصائيات كل 60 ثانية
setTimeout(() => {
    window.location.reload();
}, 60000);

// تأكيد قبل الحذف
document.addEventListener('click', function(e) {
    if(e.target.closest('.btn-delete')) {
        if(!confirm('هل أنت متأكد من رفض هذا الإيداع؟')) {
            e.preventDefault();
        }
    }
});

// رسالة ترحيب
window.onload = function() {
    console.log('لوحة التحكم جاهزة - Dr Pay System');
};
</script>

</body>
</html>