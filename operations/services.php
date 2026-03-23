<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/operations.php";

// معالجة الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = $conn_operations->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $_POST['delete_id']);
    if ($stmt->execute()) {
        header("Location: services.php?deleted=1");
        exit;
    }
}

// معالجة التحديث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $name = trim($_POST['service_name']);
    $price = (float)$_POST['price'];
    $fees = (float)$_POST['fees'];
    $profit = $price - $fees;
    $desc = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] === 'active') ? 'active' : 'inactive';
    
    if ($price > 0) {
        $stmt = $conn_operations->prepare("UPDATE services SET service_name=?, price=?, fees=?, profit=?, description=?, status=? WHERE id=?");
        $stmt->bind_param("sdddssi", $name, $price, $fees, $profit, $desc, $status, $id);
        if ($stmt->execute()) {
            header("Location: services.php?updated=1");
            exit;
        }
    }
}

// البحث والفلترة
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// بناء الاستعلام
$where = "1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND service_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($status_filter !== 'all') {
    $where .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// جلب الخدمات
$sql = "SELECT * FROM services WHERE $where ORDER BY id DESC";
$stmt = $conn_operations->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$services = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🛠️ إدارة الخدمات</title>
<style>
:root{--pri:#2563eb;--suc:#16a34a;--dan:#dc2626;--gr:#64748b;--warn:#f59e0b;--bg:#f1f5f9;}
body{font-family:Tahoma;background:var(--bg);padding:10px;}
.container{max-width:1400px;margin:auto;background:#fff;padding:15px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.08);}
.header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:15px;}
.header h2{margin:0;color:#1e293b;}
.top-actions{display:flex;gap:8px;flex-wrap:wrap;}
.btn{padding:8px 14px;border-radius:6px;border:none;cursor:pointer;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:600;}
.btn-add{background:var(--suc);}
.btn-back{background:var(--gr);}
.btn-edit{background:var(--pri);padding:5px 10px;font-size:12px;}
.btn-del{background:var(--dan);padding:5px 10px;font-size:12px;}
.btn-view{background:var(--warn);padding:5px 10px;font-size:12px;}
.btn-save{background:var(--suc);}
.btn-can{background:var(--gr);}
.search-bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:15px;background:#f8fafc;padding:12px;border-radius:8px;}
.search-box{padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;flex:1;min-width:200px;font-size:13px;}
.filter-select{padding:8px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px;min-width:120px;}
.notice{padding:10px;border-radius:6px;margin-bottom:15px;text-align:center;font-size:13px;}
.suc{background:#dcfce7;color:var(--suc);}
.dan{background:#fee2e2;color:var(--dan);}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px;}
th{background:#f1f5f9;padding:10px;border:1px solid #e2e8f0;font-weight:600;}
td{padding:8px;border:1px solid #e2e8f0;text-align:center;}
tr:hover{background:#f8fafc;}
.status-active{color:var(--suc);font-weight:bold;background:#dcfce7;padding:3px 8px;border-radius:10px;font-size:11px;}
.status-inactive{color:var(--dan);font-weight:bold;background:#fee2e2;padding:3px 8px;border-radius:10px;font-size:11px;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000;}
.modal-content{background:#fff;width:90%;max-width:400px;padding:20px;border-radius:10px;}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px;}
.close{font-size:22px;cursor:pointer;color:var(--dan);}
.form-group{margin-bottom:12px;}
.form-control{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px;}
.form-actions{display:flex;gap:8px;margin-top:20px;justify-content:flex-end;}
.no-data{text-align:center;padding:30px;color:var(--gr);font-size:14px;}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:15px 0;}
.sum-card{background:#f8fafc;padding:12px;border-radius:8px;text-align:center;border:1px solid #e2e8f0;}
.sum-value{font-size:16px;font-weight:700;color:#1e293b;}
@media (max-width:768px){
    .header{flex-direction:column;text-align:center;}
    .search-bar{flex-direction:column;}
    .search-box,.filter-select{width:100%;}
    table{font-size:11px;}
    th,td{padding:6px 4px;}
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>🛠️ إدارة الخدمات</h2>
        <div class="top-actions">
            <a href="add_service.php" class="btn btn-add">➕ إضافة خدمة</a>
            <a href="../dashboard.php" class="btn btn-back">🏠 الرئيسية</a>
            <a href="reports.php" class="btn" style="background:var(--pri);">📊 التقارير</a>
        </div>
    </div>
    
    <?php if(isset($_GET['updated'])): ?>
        <div class="notice suc">✅ تم تحديث الخدمة</div>
    <?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?>
        <div class="notice suc">✅ تم حذف الخدمة</div>
    <?php endif; ?>
    
    <form method="GET" class="search-bar">
        <input type="text" name="search" class="search-box" placeholder="🔍 بحث باسم الخدمة..." 
               value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="filter-select">
            <option value="all" <?= $status_filter=='all'?'selected':'' ?>>جميع الحالات</option>
            <option value="active" <?= $status_filter=='active'?'selected':'' ?>>مفعلة</option>
            <option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>موقوفة</option>
        </select>
        <button type="submit" class="btn" style="background:var(--pri);">🔍 بحث</button>
        <?php if(!empty($search) || $status_filter !== 'all'): ?>
            <a href="services.php" class="btn btn-back">↻ عرض الكل</a>
        <?php endif; ?>
    </form>
    
    <?php 
    // حساب الإحصائيات
    $active_count = 0;
    $total_price = 0;
    $services_data = [];
    while($s = $services->fetch_assoc()) {
        $services_data[] = $s;
        if ($s['status'] == 'active') $active_count++;
        $total_price += $s['price'];
    }
    $total_services = count($services_data);
    ?>
    
    <div class="summary">
        <div class="sum-card"><div>الخدمات</div><div class="sum-value"><?= $total_services ?></div></div>
        <div class="sum-card"><div>المفعلة</div><div class="sum-value"><?= $active_count ?></div></div>
        <div class="sum-card"><div>إجمالي السعر</div><div class="sum-value"><?= number_format($total_price, 0) ?></div></div>
    </div>
    
    <?php if($total_services == 0): ?>
        <div class="no-data">📭 لا توجد خدمات</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>الاسم</th><th>السعر</th><th>الرسوم</th><th>الربح</th>
                    <th>الحقول</th><th>الحالة</th><th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($services_data as $s): 
                    $fields = json_decode($s['input_fields'] ?? '[]', true);
                    $field_count = is_array($fields) ? count($fields) : 0;
                ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><strong><?= htmlspecialchars($s['service_name']) ?></strong></td>
                    <td><?= number_format($s['price'],0) ?> ج.م</td>
                    <td><?= number_format($s['fees'],0) ?> ج.م</td>
                    <td><strong style="color:var(--suc);"><?= number_format($s['profit'],0) ?> ج.م</strong></td>
                    <td><?= $field_count ?> حقول</td>
                    <td>
                        <span class="status-<?= $s['status'] ?>">
                            <?= $s['status'] == 'active' ? 'مفعلة' : 'موقوفة' ?>
                        </span>
                    </td>
                    <td style="display:flex;gap:4px;justify-content:center;">
                        <button class="btn-edit btn" onclick="openEdit(
                            <?= $s['id'] ?>,
                            '<?= addslashes($s['service_name']) ?>',
                            <?= $s['price'] ?>,
                            <?= $s['fees'] ?>,
                            '<?= addslashes($s['description'] ?? '') ?>',
                            '<?= $s['status'] ?>'
                        )">✏️</button>
                        <button class="btn-view btn" onclick="viewService(<?= $s['id'] ?>)">👁️</button>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف الخدمة \'<?= addslashes($s['service_name']) ?>\'؟')" style="display:inline">
                            <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-del btn">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="text-align:center;padding:10px;color:#64748b;font-size:12px;margin-top:10px;">
        عدد الخدمات: <?= $total_services ?> | الخدمات المفعلة: <?= $active_count ?>
    </div>
    <?php endif; ?>
</div>

<!-- نافذة التعديل -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;">✏️ تعديل الخدمة</h3>
            <span class="close" onclick="closeEdit()">×</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label>اسم الخدمة</label>
                <input type="text" class="form-control" name="service_name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>السعر (ج.م)</label>
                <input type="number" step="0.01" class="form-control" name="price" id="edit_price" required oninput="calcProfit()">
            </div>
            <div class="form-group">
                <label>الرسوم (ج.م)</label>
                <input type="number" step="0.01" class="form-control" name="fees" id="edit_fees" required oninput="calcProfit()">
            </div>
            <div class="form-group">
                <label>الربح (ج.م)</label>
                <input type="text" class="form-control" name="profit" id="edit_profit" readonly style="background:#f8fafc;">
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea class="form-control" name="description" id="edit_desc" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <select class="form-control" name="status" id="edit_status">
                    <option value="active">مفعلة</option>
                    <option value="inactive">موقوفة</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save btn">💾 حفظ</button>
                <button type="button" class="btn-can btn" onclick="closeEdit()">❌ إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, price, fees, desc, status) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_fees').value = fees;
    document.getElementById('edit_desc').value = desc;
    document.getElementById('edit_status').value = status;
    calcProfit();
    document.getElementById('editModal').style.display = 'flex';
}

function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}

function calcProfit() {
    const price = parseFloat(document.getElementById('edit_price').value) || 0;
    const fees = parseFloat(document.getElementById('edit_fees').value) || 0;
    const profit = price - fees;
    document.getElementById('edit_profit').value = profit.toFixed(2) + ' ج.م';
}

function viewService(id) {
    window.location.href = 'view_service.php?id=' + id;
}

window.onclick = function(e) {
    if (e.target.id === 'editModal') closeEdit();
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeEdit();
});

document.getElementById('editForm').addEventListener('submit', function(e) {
    const price = parseFloat(document.getElementById('edit_price').value);
    if (price <= 0) {
        e.preventDefault();
        alert('السعر يجب أن يكون أكبر من صفر');
        document.getElementById('edit_price').focus();
    }
});
</script>
</body>
</html>