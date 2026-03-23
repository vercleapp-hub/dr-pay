<?php
require_once __DIR__ . "/../config/operations.php";
$conn_operations->set_charset("utf8mb4");

// ========== المعالجات الأساسية ==========
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn_operations->prepare("DELETE FROM operations WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: reports.php"); 
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notes'])) {
    $id = intval($_POST['id']);
    $notes = trim($_POST['notes'] ?? '');
    $stmt = $conn_operations->prepare("UPDATE operations SET notes=? WHERE id=?");
    $stmt->bind_param("si", $notes, $id);
    $stmt->execute();
    header("Location: reports.php?updated=" . $id); 
    exit;
}

// ========== إعدادات العرض ==========
$limit = isset($_GET['per_page']) && in_array($_GET['per_page'], [10,20,50,100,200,500,700,1000]) ? intval($_GET['per_page']) : 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// ========== بناء البحث ==========
$where = "1"; $params = []; $types = "";

// الفلاتر الأساسية
$filters = ['from'=>"DATE(created_at) >= ?", 'to'=>"DATE(created_at) <= ?", 'service'=>"service_name = ?", 
            'status'=>"status = ?", 'payment_company'=>"payment_company = ?", 'payment_type'=>"payment_type = ?"];

foreach ($filters as $key => $condition) {
    if (!empty($_GET[$key])) {
        $where .= " AND $condition";
        $params[] = $_GET[$key];
        $types .= "s";
    }
}

// البحث المتقدم (الفاتورة، الخدمة، التفاصيل، الملاحظات، الرقم)
if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where .= " AND (invoice_no LIKE ? OR service_name LIKE ? OR details LIKE ? OR notes LIKE ? OR service_number LIKE ?)";
    array_push($params, $search, $search, $search, $search, $search);
    $types .= "sssss";
}

// ========== حساب الإجمالي ==========
$count_stmt = $conn_operations->prepare("SELECT COUNT(*) as total FROM operations WHERE $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// ========== جلب البيانات ==========
$sql = "SELECT * FROM operations WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn_operations->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$limit, $offset]));
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$ops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========== حساب الإجماليات ==========
$totals = array_reduce($ops, function($carry, $op) {
    return ['amount' => $carry['amount'] + $op['amount'], 
            'fees' => $carry['fees'] + $op['fees'], 
            'total' => $carry['total'] + $op['total']];
}, ['amount'=>0, 'fees'=>0, 'total'=>0]);

// ========== القوائم والمعلومات ==========
$services = $conn_operations->query("SELECT DISTINCT service_name FROM operations WHERE service_name != '' ORDER BY service_name");
$companies = $conn_operations->query("SELECT DISTINCT payment_company FROM operations WHERE payment_company != '' ORDER BY payment_company");
$payment_types = $conn_operations->query("SELECT DISTINCT payment_type FROM operations WHERE payment_type != '' ORDER BY payment_type");
$pending_count = $conn_operations->query("SELECT COUNT(*) as count FROM operations WHERE status='pending'")->fetch_assoc()['count'];

// ========== رابط base مع الحفاظ على الفلاتر ==========
$query_params = $_GET;
unset($query_params['page']);
$base_url = '?' . http_build_query($query_params);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 التقارير</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, Tahoma, sans-serif; }
        :root { --primary: #2563eb; --success: #16a34a; --danger: #dc2626; --purple: #8b5cf6; --orange: #f97316; --bg: #f1f5f9; --card: #fff; --text: #1e293b; --border: #e2e8f0; }
        .dark-mode { --bg: #0f172a; --card: #1e293b; --text: #e2e8f0; --border: #334155; }
        
        body { background: var(--bg); color: var(--text); padding: 10px; }
        .container { background: var(--card); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        
        /* الهيدر */
        .header { background: linear-gradient(135deg, var(--primary), #1d4ed8); color: #fff; padding: 15px; border-radius: 12px 12px 0 0; }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }
        
        /* الأزرار */
        .btn { padding: 8px 15px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-purple { background: var(--purple); color: #fff; }
        .btn-orange { background: var(--orange); color: #fff; position: relative; }
        .btn-gray { background: #475569; color: #fff; }
        
        .badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: #fff; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; }
        
        /* الفلاتر */
        .filters { padding: 15px; background: var(--bg); border-bottom: 1px solid var(--border); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 10px; }
        input, select { padding: 8px; border: 1px solid var(--border); border-radius: 6px; width: 100%; font-size: 13px; background: var(--card); color: var(--text); }
        
        /* الملخص */
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; padding: 15px; }
        .sum-card { background: var(--bg); padding: 10px; border-radius: 8px; text-align: center; border: 1px solid var(--border); }
        .sum-value { font-size: 18px; font-weight: 700; color: var(--primary); }
        
        /* الجدول - متجاوب بالكامل */
        .table-container { padding: 0 10px 10px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: var(--bg); padding: 10px 5px; font-weight: 600; border-bottom: 2px solid var(--border); white-space: nowrap; }
        td { padding: 8px 5px; border-bottom: 1px solid var(--border); text-align: center; }
        
        /* تحسين عرض الخلايا */
        .cell-content { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cell-content:hover { overflow: visible; white-space: normal; background: var(--card); position: relative; z-index: 1; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        /* حالات العملية */
        .status { padding: 3px 8px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-paid { background: #dcfce7; color: var(--success); }
        .status-pending { background: #fef3c7; color: var(--orange); }
        .status-rejected { background: #fee2e2; color: var(--danger); }
        
        /* أزرار الإجراءات */
        .actions { display: flex; gap: 3px; justify-content: center; flex-wrap: wrap; }
        .btn-icon { width: 26px; height: 26px; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; font-size: 11px; }
        
        /* التصفح */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 5px; padding: 20px; flex-wrap: wrap; }
        .page-link { display: flex; align-items: center; justify-content: center; min-width: 35px; height: 35px; padding: 0 5px; background: var(--bg); color: var(--text); text-decoration: none; border-radius: 5px; font-size: 13px; }
        .page-link.active { background: var(--primary); color: #fff; }
        
        /* النوافذ */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: var(--card); color: var(--text); border-radius: 10px; padding: 20px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; }
        .close-btn { position: absolute; top: 10px; left: 10px; background: var(--danger); color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; }
        
        @media (max-width: 768px) {
            .header-content { flex-direction: column; text-align: center; }
            .filter-grid { grid-template-columns: 1fr; }
            table { font-size: 11px; }
            th, td { padding: 5px 3px; }
            .cell-content { max-width: 100px; }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- ========== الهيدر ========== -->
    <div class="header">
        <div class="header-content">
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-gray"><i class="fas fa-home"></i></a>
                <a href="manual.php" class="btn btn-purple"><i class="fas fa-plus"></i> إنشاء</a>
                <a href="pending.php" class="btn btn-orange">
                    <i class="fas fa-clock"></i> المعلقة
                    <?php if($pending_count > 0): ?><span class="badge"><?= $pending_count ?></span><?php endif; ?>
                </a>
            </div>
            <h2><i class="fas fa-chart-bar"></i> التقارير</h2>
            <button class="btn btn-gray" onclick="toggleDarkMode()" id="darkModeBtn">
                <i class="fas <?= isset($_COOKIE['darkMode']) && $_COOKIE['darkMode']=='enabled' ? 'fa-sun' : 'fa-moon' ?>"></i>
            </button>
        </div>
    </div>
    
    <?php if(isset($_GET['updated'])): ?>
        <div style="background:#dcfce7; color:#16a34a; padding:10px; margin:10px; border-radius:6px; text-align:center;">
            <i class="fas fa-check-circle"></i> تم التحديث
        </div>
    <?php endif; ?>
    
    <!-- ========== الفلاتر ========== -->
    <form method="GET" class="filters">
        <div class="filter-grid">
            <input type="date" name="from" value="<?= htmlspecialchars($_GET['from']??'') ?>" placeholder="من">
            <input type="date" name="to" value="<?= htmlspecialchars($_GET['to']??'') ?>" placeholder="إلى">
            
            <select name="service">
                <option value="">الخدمة</option>
                <?php while($s=$services->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($s['service_name']) ?>" <?= ($_GET['service']??'')==$s['service_name']?'selected':'' ?>><?= htmlspecialchars($s['service_name']) ?></option>
                <?php endwhile; ?>
            </select>
            
            <select name="status">
                <option value="">الحالة</option>
                <option value="paid" <?= ($_GET['status']??'')=='paid'?'selected':'' ?>>مدفوعة</option>
                <option value="pending" <?= ($_GET['status']??'')=='pending'?'selected':'' ?>>معلقة</option>
                <option value="rejected" <?= ($_GET['status']??'')=='rejected'?'selected':'' ?>>مرفوضة</option>
            </select>
            
            <select name="payment_company">
                <option value="">شركة الدفع</option>
                <?php while($c=$companies->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($c['payment_company']) ?>" <?= ($_GET['payment_company']??'')==$c['payment_company']?'selected':'' ?>><?= htmlspecialchars($c['payment_company']) ?></option>
                <?php endwhile; ?>
            </select>
            
            <select name="payment_type">
                <option value="">طريقة الدفع</option>
                <?php while($pt=$payment_types->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($pt['payment_type']) ?>" <?= ($_GET['payment_type']??'')==$pt['payment_type']?'selected':'' ?>><?= htmlspecialchars($pt['payment_type']) ?></option>
                <?php endwhile; ?>
            </select>
            
            <input type="text" name="search" placeholder="بحث شامل (رقم، فاتورة، خدمة...)" value="<?= htmlspecialchars($_GET['search']??'') ?>">
        </div>
        
        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> بحث</button>
            <a href="reports.php" class="btn btn-gray"><i class="fas fa-redo"></i> إعادة</a>
            <select name="per_page" onchange="this.form.submit()" style="width: auto;">
                <option value="10" <?= $limit==10?'selected':'' ?>>10</option>
                <option value="20" <?= $limit==20?'selected':'' ?>>20</option>
                <option value="50" <?= $limit==50?'selected':'' ?>>50</option>
                <option value="100" <?= $limit==100?'selected':'' ?>>100</option>
                <option value="200" <?= $limit==200?'selected':'' ?>>200</option>
                <option value="500" <?= $limit==500?'selected':'' ?>>500</option>
                <option value="700" <?= $limit==700?'selected':'' ?>>700</option>
                <option value="1000" <?= $limit==1000?'selected':'' ?>>1000</option>
            </select>
            <button type="button" class="btn btn-success" onclick="exportExcel()"><i class="fas fa-file-excel"></i></button>
            <button type="button" class="btn btn-danger" onclick="exportPDF()"><i class="fas fa-file-pdf"></i></button>
        </div>
    </form>
    
    <!-- ========== الملخص ========== -->
    <div class="summary">
        <div class="sum-card"><i class="fas fa-money-bill"></i><div>المبلغ</div><div class="sum-value"><?= number_format($totals['amount']) ?></div></div>
        <div class="sum-card"><i class="fas fa-percent"></i><div>الرسوم</div><div class="sum-value"><?= number_format($totals['fees']) ?></div></div>
        <div class="sum-card"><i class="fas fa-calculator"></i><div>الإجمالي</div><div class="sum-value"><?= number_format($totals['total']) ?></div></div>
        <div class="sum-card"><i class="fas fa-list"></i><div>العمليات</div><div class="sum-value"><?= count($ops) ?>/<?= $total_rows ?></div></div>
    </div>
    
    <!-- ========== الجدول (بدون سحب أفقي) ========== -->
    <div class="table-container">
        <?php if(empty($ops)): ?>
            <div style="text-align:center; padding:50px; color:#64748b"><i class="fas fa-inbox fa-3x"></i><h3>لا توجد عمليات</h3></div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>الفاتورة</th><th>التاريخ</th><th>الاعتماد</th><th>الخدمة</th>
                    <th>الرقم</th><th>المبلغ</th><th>الإجمالي</th><th>الحالة</th><th>شركة</th>
                    <th>طريقة</th><th>التفاصيل</th><th>ملاحظات</th><th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($ops as $o): 
                    $num = $o['service_number'] ?: '';
                    if (!$num && $o['service_data']) {
                        $data = json_decode($o['service_data'], true) ?: [];
                        foreach(['field_0','field0','phone','الرقم','number','id'] as $k) {
                            if(!empty($data[$k])) { $num = htmlspecialchars($data[$k]); break; }
                        }
                    }
                    $approved = $o['approval_date'] ?: $o['paid_at'] ?: '';
                ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td><div class="cell-content"><?= htmlspecialchars($o['invoice_no']?:'-') ?></div></td>
                    <td><?= date('m/d H:i', strtotime($o['created_at'])) ?></td>
                    <td><?= $approved ? date('m/d H:i', strtotime($approved)) : '-' ?></td>
                    <td><div class="cell-content"><?= htmlspecialchars($o['service_name']) ?></div></td>
                    <td><div class="cell-content"><?= $num?:'-' ?></div></td>
                    <td><?= number_format($o['amount']) ?></td>
                    <td><strong><?= number_format($o['total']) ?></strong></td>
                    <td><span class="status status-<?= $o['status'] ?>"><?= ['paid'=>'مدفوعة','pending'=>'معلقة','rejected'=>'مرفوضة'][$o['status']] ?></span></td>
                    <td><div class="cell-content"><?= htmlspecialchars($o['payment_company']?:'-') ?></div></td>
                    <td><div class="cell-content"><?= htmlspecialchars($o['payment_type']?:'-') ?></div></td>
                    
                    <!-- التفاصيل مع عرض مختصر -->
                    <td>
                        <?php if($o['details']): ?>
                            <div class="cell-content"><?= htmlspecialchars(mb_substr($o['details'],0,30)) ?>...</div>
                            <button class="btn-icon" style="background:var(--primary); width:20px; height:20px;" onclick="showModal('تفاصيل','<?= addslashes(str_replace(["\r\n","\r","\n"],'\\n',$o['details'])) ?>')"><i class="fas fa-expand"></i></button>
                        <?php else: echo '-'; endif; ?>
                    </td>
                    
                    <!-- الملاحظات مع تعديل -->
                    <td>
                        <?php if($o['notes']): ?>
                            <div class="cell-content"><?= htmlspecialchars(mb_substr($o['notes'],0,30)) ?>...</div>
                            <button class="btn-icon" style="background:var(--primary); width:20px; height:20px;" onclick="showModal('ملاحظات','<?= addslashes(str_replace(["\r\n","\r","\n"],'\\n',$o['notes'])) ?>')"><i class="fas fa-expand"></i></button>
                        <?php endif; ?>
                        <button class="btn-icon" style="background:var(--purple); width:20px; height:20px;" onclick="editNotes(<?= $o['id'] ?>,'<?= addslashes(str_replace(["\r\n","\r","\n"],'\\n',$o['notes']??'')) ?>')"><i class="fas fa-edit"></i></button>
                    </td>
                    
                    <td class="actions">
                        <a href="print.php?id=<?= $o['id'] ?>" target="_blank" class="btn-icon" style="background:var(--success)" title="طباعة"><i class="fas fa-print"></i></a>
                        <a href="?delete=<?= $o['id'] ?>" class="btn-icon" style="background:var(--danger)" title="حذف" onclick="return confirm('حذف #<?= $o['id'] ?>؟')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- ========== التصفح ========== -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?><a href="<?= $base_url ?>&page=<?= $page-1 ?>&per_page=<?= $limit ?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <?php if($i==1 || $i==$total_pages || ($i>=$page-2 && $i<=$page+2)): ?>
                    <a href="<?= $base_url ?>&page=<?= $i ?>&per_page=<?= $limit ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
                <?php elseif($i==2 || $i==$total_pages-1): echo '<span class="page-link">...</span>'; endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?><a href="<?= $base_url ?>&page=<?= $page+1 ?>&per_page=<?= $limit ?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ========== النوافذ المنبثقة ========== -->
<div id="modal" class="modal" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('modal').style.display='none'">✕</button>
        <div id="modalTitle" style="color:var(--primary); margin-bottom:10px; font-size:16px; font-weight:bold"></div>
        <div id="modalContent" style="background:var(--bg); padding:15px; border-radius:6px; line-height:1.6; white-space:pre-wrap;"></div>
    </div>
</div>

<div id="editModal" class="modal" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content">
        <button class="close-btn" onclick="document.getElementById('editModal').style.display='none'">✕</button>
        <div style="color:var(--purple); margin-bottom:10px; font-size:16px; font-weight:bold"><i class="fas fa-edit"></i> تعديل الملاحظات</div>
        <form method="post">
            <input type="hidden" name="update_notes" value="1">
            <input type="hidden" id="editId" name="id">
            <textarea id="editNotes" name="notes" rows="6" style="width:100%; background:var(--card); color:var(--text); border:1px solid var(--border); border-radius:4px; padding:8px;"></textarea>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;"><i class="fas fa-save"></i> حفظ</button>
        </form>
    </div>
</div>

<script>
// ========== الوضع الليلي ==========
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const btn = document.getElementById('darkModeBtn');
    const isDark = document.body.classList.contains('dark-mode');
    btn.innerHTML = `<i class="fas fa-${isDark ? 'sun' : 'moon'}"></i>`;
    document.cookie = `darkMode=${isDark ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}
if (document.cookie.includes('darkMode=enabled')) document.body.classList.add('dark-mode');

// ========== النوافذ ==========
function showModal(title, content) {
    document.getElementById('modalTitle').innerHTML = title;
    document.getElementById('modalContent').innerHTML = content.replace(/\\n/g, '<br>');
    document.getElementById('modal').style.display = 'flex';
}

function editNotes(id, notes) {
    document.getElementById('editId').value = id;
    document.getElementById('editNotes').value = notes.replace(/\\n/g, '\n');
    document.getElementById('editModal').style.display = 'flex';
}

// ========== تصدير Excel محسن ==========
function exportExcel() {
    let csv = "ID,الفاتورة,التاريخ,الخدمة,الرقم,المبلغ,الرسوم,الإجمالي,الحالة,شركة الدفع,طريقة الدفع,ملاحظات\n";
    <?php foreach($ops as $op): ?>
    csv += `<?= $op['id'] ?>,"<?= addslashes($op['invoice_no']?:'-') ?>","<?= $op['created_at'] ?>","<?= addslashes($op['service_name']) ?>","<?= addslashes($num?:'-') ?>",<?= $op['amount'] ?>,<?= $op['fees'] ?>,<?= $op['total'] ?>,"<?= ['paid'=>'مدفوعة','pending'=>'معلقة','rejected'=>'مرفوضة'][$op['status']] ?>","<?= addslashes($op['payment_company']?:'-') ?>","<?= addslashes($op['payment_type']?:'-') ?>","<?= addslashes(str_replace(["\r\n","\r","\n"],' ',$op['notes']?:'')) ?>"\n`;
    <?php endforeach; ?>
    
    const blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8;'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `عمليات_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
}

// ========== تصدير PDF ==========
function exportPDF() {
    const w = window.open('', '_blank');
    w.document.write(`
        <html dir="rtl"><head><meta charset="UTF-8"><title>تقرير العمليات</title>
        <style>body{font-family:Tahoma;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px;text-align:center}th{background:#f1f5f9}</style></head>
        <body><h2 style="color:#2563eb;text-align:center">📊 تقرير العمليات</h2>
        <table><tr><th>#</th><th>الفاتورة</th><th>التاريخ</th><th>الخدمة</th><th>المبلغ</th><th>الإجمالي</th><th>الحالة</th></tr>
    `);
    <?php foreach($ops as $op): ?>
    w.document.write(`<tr><td><?= $op['id'] ?></td><td><?= addslashes($op['invoice_no']?:'-') ?></td><td><?= $op['created_at'] ?></td><td><?= addslashes($op['service_name']) ?></td><td><?= $op['amount'] ?></td><td><?= $op['total'] ?></td><td><?= ['paid'=>'مدفوعة','pending'=>'معلقة','rejected'=>'مرفوضة'][$op['status']] ?></td></tr>`);
    <?php endforeach; ?>
    w.document.write('</table></body></html>');
    w.document.close();
    w.print();
}
</script>
</body>
</html>