<?php
/************************************
 * Pending Deposits – Admin
 ************************************/

require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   Pagination
================================ */
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$start = ($page - 1) * $limit;

/* ===============================
   Filters
================================ */
$search = trim($_GET['search'] ?? '');
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = "WHERE d.status='pending'";
$params = [];

if ($search !== '') {
    $search_escaped = $conn->real_escape_string($search);
    $where .= " AND (u.name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%' OR u.phone LIKE '%$search_escaped%')";
}

if ($min_amount !== '') {
    $min_amount = floatval($min_amount);
    $where .= " AND d.amount >= $min_amount";
}

if ($max_amount !== '') {
    $max_amount = floatval($max_amount);
    $where .= " AND d.amount <= $max_amount";
}

if ($date_from !== '') {
    $date_from_escaped = $conn->real_escape_string($date_from);
    $where .= " AND DATE(d.created_at) >= '$date_from_escaped'";
}

if ($date_to !== '') {
    $date_to_escaped = $conn->real_escape_string($date_to);
    $where .= " AND DATE(d.created_at) <= '$date_to_escaped'";
}

/* ===============================
   Count
================================ */
$total = $conn->query("
    SELECT COUNT(*) c
    FROM deposits d
    JOIN users u ON u.id=d.user_id
    $where
")->fetch_assoc()['c'];

$pages = ceil($total / $limit);

/* ===============================
   Fetch Data
================================ */
$q = $conn->query("
    SELECT d.*, u.name, u.email, u.phone, u.balance as user_balance
    FROM deposits d
    JOIN users u ON u.id=d.user_id
    $where
    ORDER BY d.id DESC
    LIMIT $start, $limit
");

// Get statistics
$stats_q = $conn->query("
    SELECT 
        COUNT(*) as total_pending,
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount,
        MIN(amount) as min_amount,
        MAX(amount) as max_amount
    FROM deposits 
    WHERE status='pending'
");
$stats = $stats_q->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>الإيداعات المعلقة | Dr Pay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    background: linear-gradient(135deg, #ff9800, #ff5722);
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
    display: flex;
    align-items: center;
    gap: 10px;
}
.back-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background 0.3s;
}
.back-btn:hover {
    background: rgba(255,255,255,0.3);
}
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    text-align: center;
}
.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}
.stat-card .value {
    font-size: 24px;
    font-weight: bold;
    color: #ff9800;
}
.filters {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}
.filter-group {
    display: flex;
    flex-direction: column;
}
.filter-group label {
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
}
.filter-group input, .filter-group select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}
.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 10px;
}
.btn {
    padding: 8px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}
.btn-primary {
    background: #007bff;
    color: white;
}
.btn-primary:hover {
    background: #0056b3;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background: #545b62;
}
.table-container {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}
th {
    background: #f8f9fa;
    padding: 15px;
    text-align: right;
    font-weight: bold;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
}
td {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    text-align: right;
}
tr:hover {
    background: #f8f9fa;
}
.receipt-img {
    max-width: 80px;
    max-height: 50px;
    border-radius: 5px;
    cursor: pointer;
    transition: transform 0.3s;
}
.receipt-img:hover {
    transform: scale(1.5);
}
.status-badge {
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
.action-btns {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}
.action-btn {
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 12px;
    text-decoration: none;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}
.action-approve {
    background: #28a745;
    color: white;
}
.action-approve:hover {
    background: #218838;
    color: white;
}
.action-reject {
    background: #dc3545;
    color: white;
}
.action-reject:hover {
    background: #c82333;
    color: white;
}
.action-view {
    background: #17a2b8;
    color: white;
}
.action-view:hover {
    background: #138496;
    color: white;
}
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
    padding: 15px;
    background: white;
    border-radius: 10px;
}
.page-link {
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    text-decoration: none;
    color: #007bff;
    font-weight: bold;
}
.page-link:hover {
    background: #e9ecef;
}
.page-link.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}
.page-link.disabled {
    color: #6c757d;
    cursor: not-allowed;
}
.empty-state {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}
.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #ffc107;
}
.bulk-actions {
    background: #fff8e1;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}
.bulk-select {
    display: flex;
    align-items: center;
    gap: 10px;
}
.bulk-select input[type="checkbox"] {
    width: 18px;
    height: 18px;
}
.amount-input {
    display: flex;
    align-items: center;
    gap: 5px;
}
.amount-input input {
    width: 100px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 3px;
}
.user-info {
    display: flex;
    flex-direction: column;
}
.user-name {
    font-weight: bold;
    color: #333;
}
.user-email {
    font-size: 12px;
    color: #666;
}
.user-phone {
    font-size: 11px;
    color: #888;
}
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    .header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    .filter-row {
        grid-template-columns: 1fr;
    }
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .table-container {
        overflow-x: auto;
    }
}
</style>
</head>

<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-clock"></i> الإيداعات المعلقة</h1>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-right"></i> رجوع للوحة التحكم
        </a>
    </div>

    <!-- إحصائيات -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3>عدد الإيداعات المعلقة</h3>
            <div class="value"><?= $stats['total_pending'] ?></div>
        </div>
        <div class="stat-card">
            <h3>إجمالي المبلغ المعلق</h3>
            <div class="value"><?= number_format($stats['total_amount'] ?? 0, 2) ?> EGP</div>
        </div>
        <div class="stat-card">
            <h3>متوسط المبلغ</h3>
            <div class="value"><?= number_format($stats['avg_amount'] ?? 0, 2) ?> EGP</div>
        </div>
        <div class="stat-card">
            <h3>أعلى مبلغ</h3>
            <div class="value"><?= number_format($stats['max_amount'] ?? 0, 2) ?> EGP</div>
        </div>
    </div>

    <!-- فلترة متقدمة -->
    <div class="filters">
        <form method="get" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label>بحث (اسم، إيميل، هاتف)</label>
                    <input type="text" name="search" placeholder="أدخل للبحث..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <label>الحد الأدنى للمبلغ</label>
                    <input type="number" name="min_amount" placeholder="المبلغ الأدنى" step="0.01"
                           value="<?= htmlspecialchars($min_amount) ?>">
                </div>
                <div class="filter-group">
                    <label>الحد الأقصى للمبلغ</label>
                    <input type="number" name="max_amount" placeholder="المبلغ الأقصى" step="0.01"
                           value="<?= htmlspecialchars($max_amount) ?>">
                </div>
                <div class="filter-group">
                    <label>ترتيب حسب</label>
                    <select name="sort">
                        <option value="id_desc">الأحدث أولاً</option>
                        <option value="id_asc">الأقدم أولاً</option>
                        <option value="amount_desc">المبلغ من الأعلى</option>
                        <option value="amount_asc">المبلغ من الأقل</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> بحث وتطبيق
                </button>
                <a href="pending.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> إعادة تعيين
                </a>
            </div>
        </form>
    </div>

    <!-- إجراءات جماعية -->
    <div class="bulk-actions">
        <div class="bulk-select">
            <input type="checkbox" id="selectAll">
            <label for="selectAll">تحديد الكل</label>
            <span id="selectedCount">0 محدد</span>
        </div>
        <button class="btn" onclick="bulkApprove()" style="background: #28a745; color: white;">
            <i class="fas fa-check"></i> قبول المحدد
        </button>
        <button class="btn" onclick="bulkReject()" style="background: #dc3545; color: white;">
            <i class="fas fa-times"></i> رفض المحدد
        </button>
        <div class="amount-input">
            <input type="number" id="bulkAmount" placeholder="مبلغ محدد" step="0.01">
            <button class="btn" onclick="filterByAmount()" style="background: #ffc107; color: #333;">
                <i class="fas fa-filter"></i> تصفية بالمبلغ
            </button>
        </div>
    </div>

    <!-- جدول الإيداعات -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllHeader"></th>
                    <th>#</th>
                    <th>المستخدم</th>
                    <th>المبلغ</th>
                    <th>رصيد المستخدم</th>
                    <th>الإيصال</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($q->num_rows === 0): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>لا توجد إيداعات معلقة</h3>
                            <p>جميع الإيداعات تمت معالجتها</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($d = $q->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" class="deposit-checkbox" value="<?= $d['id'] ?>"></td>
                    <td><?= $d['id'] ?></td>
                    <td>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($d['name']) ?></span>
                            <span class="user-email"><?= htmlspecialchars($d['email']) ?></span>
                            <span class="user-phone"><?= htmlspecialchars($d['phone']) ?></span>
                        </div>
                    </td>
                    <td>
                        <strong style="color: #ff9800;"><?= number_format($d['amount'], 2) ?> EGP</strong>
                    </td>
                    <td><?= number_format($d['user_balance'], 2) ?> EGP</td>
                    <td>
                        <?php if ($d['receipt'] && file_exists('../uploads/receipts/' . $d['receipt'])): ?>
                            <a href="../uploads/receipts/<?= urlencode($d['receipt']) ?>" target="_blank" 
                               data-lightbox="receipt-<?= $d['id'] ?>" data-title="إيصال #<?= $d['id'] ?>">
                                <img class="receipt-img" src="../uploads/receipts/<?= urlencode($d['receipt']) ?>"
                                     alt="إيصال">
                            </a>
                            <br>
                            <small><a href="../uploads/receipts/<?= urlencode($d['receipt']) ?>" 
                                     download="receipt_<?= $d['id'] ?>.jpg">
                                <i class="fas fa-download"></i> تحميل
                            </a></small>
                        <?php else: ?>
                            <span style="color: #6c757d;">لا يوجد</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-pending">
                            <i class="fas fa-clock"></i> معلق
                        </span>
                    </td>
                    <td>
                        <?= date('Y-m-d', strtotime($d['created_at'])) ?><br>
                        <small><?= date('H:i', strtotime($d['created_at'])) ?></small>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="approve.php?id=<?= $d['id'] ?>" 
                               class="action-btn action-approve"
                               onclick="return confirm('هل تريد قبول الإيداع بمبلغ <?= number_format($d['amount'], 2) ?> EGP؟')">
                                <i class="fas fa-check"></i> قبول
                            </a>
                            <a href="reject.php?id=<?= $d['id'] ?>" 
                               class="action-btn action-reject"
                               onclick="return confirm('هل تريد رفض الإيداع #<?= $d['id'] ?>؟')">
                                <i class="fas fa-times"></i> رفض
                            </a>
                            <a href="deposit_details.php?id=<?= $d['id'] ?>" 
                               class="action-btn action-view">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- الترقيم -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&min_amount=<?= $min_amount ?>&max_amount=<?= $max_amount ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
               class="page-link">
                <i class="fas fa-chevron-right"></i> السابق
            </a>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<a href="?page=1&search=' . urlencode($search) . '&min_amount=' . $min_amount . '&max_amount=' . $max_amount . '&date_from=' . $date_from . '&date_to=' . $date_to . '" class="page-link">1</a>';
            if ($start_page > 2) echo '<span class="page-link disabled">...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&min_amount=<?= $min_amount ?>&max_amount=<?= $max_amount ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
               class="page-link <?= $i==$page?'active':'' ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>
        
        if ($end_page < $pages) {
            if ($end_page < $pages - 1) echo '<span class="page-link disabled">...</span>';
            echo '<a href="?page=' . $pages . '&search=' . urlencode($search) . '&min_amount=' . $min_amount . '&max_amount=' . $max_amount . '&date_from=' . $date_from . '&date_to=' . $date_to . '" class="page-link">' . $pages . '</a>';
        }
        ?>
        
        <?php if ($page < $pages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&min_amount=<?= $min_amount ?>&max_amount=<?= $max_amount ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
               class="page-link">
                التالي <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Lightbox للصور -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<script>
// تكوين Lightbox
lightbox.option({
    'resizeDuration': 200,
    'wrapAround': true,
    'albumLabel': "صورة %1 من %2",
    'disableScrolling': true
});

// تحديد/إلغاء تحديد الكل
document.getElementById('selectAllHeader').addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.deposit-checkbox');
    checkboxes.forEach(cb => cb.checked = e.target.checked);
    updateSelectedCount();
});

document.getElementById('selectAll').addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.deposit-checkbox');
    checkboxes.forEach(cb => cb.checked = e.target.checked);
    updateSelectedCount();
});

// تحديث العداد
function updateSelectedCount() {
    const selected = document.querySelectorAll('.deposit-checkbox:checked');
    document.getElementById('selectedCount').textContent = selected.length + ' محدد';
}

// إضافة حدث لكل checkbox
document.querySelectorAll('.deposit-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

// إجراءات جماعية
function bulkApprove() {
    const selected = getSelectedDeposits();
    if (selected.length === 0) {
        alert('يرجى تحديد إيداع واحد على الأقل');
        return;
    }
    
    if (confirm(`هل تريد قبول ${selected.length} إيداع(ات)؟`)) {
        // يمكنك إضافة AJAX هنا للتنفيذ الفوري
        window.location.href = `bulk_approve.php?ids=${selected.join(',')}`;
    }
}

function bulkReject() {
    const selected = getSelectedDeposits();
    if (selected.length === 0) {
        alert('يرجى تحديد إيداع واحد على الأقل');
        return;
    }
    
    if (confirm(`هل تريد رفض ${selected.length} إيداع(ات)؟`)) {
        // يمكنك إضافة AJAX هنا للتنفيذ الفوري
        window.location.href = `bulk_reject.php?ids=${selected.join(',')}`;
    }
}

function getSelectedDeposits() {
    const checkboxes = document.querySelectorAll('.deposit-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// تصفية بالمبلغ المحدد
function filterByAmount() {
    const amount = document.getElementById('bulkAmount').value;
    if (amount) {
        const url = new URL(window.location.href);
        url.searchParams.set('min_amount', amount);
        url.searchParams.set('max_amount', amount);
        window.location.href = url.toString();
    }
}

// فلترة بالتاريخ (اليوم، الأسبوع، الشهر)
function filterByPeriod(period) {
    const url = new URL(window.location.href);
    const today = new Date().toISOString().split('T')[0];
    
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    
    if (period === 'today') {
        url.searchParams.set('date_from', today);
        url.searchParams.set('date_to', today);
    } else if (period === 'week') {
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        url.searchParams.set('date_from', weekAgo.toISOString().split('T')[0]);
        url.searchParams.set('date_to', today);
    } else if (period === 'month') {
        const monthAgo = new Date();
        monthAgo.setMonth(monthAgo.getMonth() - 1);
        url.searchParams.set('date_from', monthAgo.toISOString().split('T')[0]);
        url.searchParams.set('date_to', today);
    }
    
    window.location.href = url.toString();
}

// تصدير البيانات
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.location.href = `export.php?${params.toString()}`;
}

// تحديث تلقائي كل دقيقة
setTimeout(() => {
    window.location.reload();
}, 60000);
</script>

</body>
</html>