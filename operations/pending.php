<?php
session_start();
require_once "../config/operations.php";

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!$conn_operations) die("خطأ في الاتصال");

// جلب أسماء الحقول من جدول services
$field_labels = [];
try {
    $result = $conn_operations->query("SHOW TABLES LIKE 'services'");
    if ($result && $result->num_rows > 0) {
        $query = $conn_operations->query("
            SELECT service_name, input_fields 
            FROM services 
            WHERE input_fields IS NOT NULL AND input_fields != ''
        ");
        if ($query) {
            while($service = $query->fetch_assoc()) {
                $input_fields = json_decode($service['input_fields'], true);
                if (is_array($input_fields)) {
                    foreach($input_fields as $field_name => $field_label) {
                        // استخدام اسم الخدمة كبادئة للتمييز
                        $key = $service['service_name'] . '_' . $field_name;
                        $field_labels[$key] = $field_label;
                    }
                }
            }
            $query->free();
        }
    }
} catch (Exception $e) {
    error_log("خطأ في جلب بيانات services: " . $e->getMessage());
}

// معالجة الإجراءات
processActions($conn_operations);

// جلب خدمات وأسماء لاستخدامها
$services = [];
$services_result = $conn_operations->query("SELECT DISTINCT service_name FROM operations ORDER BY service_name");
if ($services_result) {
    $services = $services_result;
}

$companies = ['الاقصي','ضامن', 'كاش مصر', 'بدايتي','بساطه','الاهلي ممكن', 'كاش ماني','اتصلات كاش', 'فودافون كاش','وي باي 015','كاش', 'ماي فوري', 'غيرهم'];
$payment_types = ['حسن', 'هناء', 'بسمه', 'اوتومتك'];

// بناء شروط البحث
list($where, $params, $types) = buildSearchConditions();

// جلب البيانات
list($operations, $total_amount, $count) = fetchOperations($conn_operations, $where, $params, $types);

// الإحصائيات
$stats = calculateStatistics($operations);

/**
 * معالجة الإجراءات (حذف، اعتماد)
 */
function processActions($conn) {
    // حذف عملية
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM operations WHERE id=? AND status='pending'");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: pending.php?deleted=1"); 
        exit;
    }
    
    // اعتماد عملية
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_id'])) {
        $id = intval($_POST['approve_id']);
        $company = trim($_POST['payment_company']);
        $type = trim($_POST['payment_type']);
        $paid = floatval($_POST['paid_amount']);
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("
            UPDATE operations SET 
                status='paid',
                payment_company=?,
                payment_type=?,
                total=?,
                notes=CONCAT(IFNULL(notes,''), '\n--- تم الاعتماد ---\nشركة: ', ?, '\nطريقة: ', ?, '\nالمبلغ: ', ?),
                paid_at=NOW(),
                approval_date=NOW()
            WHERE id=? AND status='pending'
        ");
        
        if ($stmt) {
            $stmt->bind_param("ssdsssi", $company, $type, $paid, $company, $type, $paid, $id);
            $stmt->execute();
            $stmt->close();
        }
        
        header("Location: pending.php?success=1"); 
        exit;
    }
}

/**
 * بناء شروط البحث
 */
function buildSearchConditions() {
    $where = "status='pending'";
    $params = []; 
    $types = "";
    
    if (!empty($_GET['q'])) {
        $search_query = trim($_GET['q']);
        $search = "%" . $search_query . "%";
        $where .= " AND (invoice_no LIKE ? OR service_name LIKE ? OR details LIKE ? OR service_number LIKE ?)";
        $params = [$search, $search, $search, $search];
        $types .= "ssss";
    }
    
    if (!empty($_GET['service'])) {
        $where .= " AND service_name = ?";
        $params[] = $_GET['service'];
        $types .= "s";
    }
    
    if (!empty($_GET['from_date'])) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $_GET['from_date'];
        $types .= "s";
    }
    
    if (!empty($_GET['to_date'])) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $_GET['to_date'];
        $types .= "s";
    }
    
    return [$where, $params, $types];
}

/**
 * جلب العمليات
 */
function fetchOperations($conn, $where, $params, $types) {
    $sql = "SELECT * FROM operations WHERE $where ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    
    $operations = [];
    $total_amount = 0;
    $count = 0;
    
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while($op = $result->fetch_assoc()) {
                $operations[] = $op;
                $total_amount += $op['amount'];
                $count++;
            }
            $result->free();
        }
        $stmt->close();
    }
    
    return [$operations, $total_amount, $count];
}

/**
 * حساب الإحصائيات
 */
function calculateStatistics($operations) {
    $stats = [
        'total_invoices' => count($operations),
        'total_amount' => 0,
        'total_fees' => 0,
        'total_grand' => 0
    ];
    
    foreach($operations as $op) {
        $stats['total_amount'] += $op['amount'] ?? 0;
        $stats['total_fees'] += ($op['total'] - $op['amount']) ?? 0;
        $stats['total_grand'] += $op['total'] ?? 0;
    }
    
    return $stats;
}

/**
 * الحصول على اسم العرض للحقل
 */
function getFieldDisplayName($field_name, $service_name, $field_labels, $input_fields) {
    // أولاً: جرب البحث في field_labels باستخدام خدمة+حقل
    $key = $service_name . '_' . $field_name;
    if (isset($field_labels[$key])) {
        return $field_labels[$key];
    }
    
    // ثانياً: جرب البحث في input_fields الخاص بالعملية
    if (isset($input_fields[$field_name])) {
        return $input_fields[$field_name];
    }
    
    // ثالثاً: ترجمة الحقول الشائعة
    $common_translations = [
        'service_number' => 'رقم الخدمة',
        'customer_name' => 'اسم العميل',
        'phone' => 'الهاتف',
        'national_id' => 'الرقم القومي',
        'amount' => 'المبلغ',
        'details' => 'التفاصيل',
        'invoice_no' => 'رقم الفاتورة',
        'created_at' => 'التاريخ',
        'status' => 'الحالة'
    ];
    
    if (isset($common_translations[$field_name])) {
        return $common_translations[$field_name];
    }
    
    // رابعاً: إذا كان الحقل يحتوي على underscore، قم بتنسيقه
    if (strpos($field_name, '_') !== false) {
        return ucwords(str_replace('_', ' ', $field_name));
    }
    
    // أخيراً: استخدم اسم الحقل كما هو
    return $field_name;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>⏳ الفواتير المعلقة</title>
<style>
:root{--primary:#2563eb;--success:#16a34a;--danger:#dc2626;--orange:#f97316;--bg:#f1f5f9}
body{background:var(--bg);font-family:Tahoma;margin:0;padding:10px}
.container{background:#fff;border-radius:12px;padding:15px;box-shadow:0 4px 12px rgba(0,0,0,0.08)}
h2{text-align:center;margin:0 0 15px 0;color:#1e293b}
.header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:15px}
.btn{padding:8px 14px;border-radius:8px;border:none;cursor:pointer;color:#fff;text-decoration:none;font-size:13px;font-weight:600}
.btn-primary{background:var(--primary)}
.btn-danger{background:var(--danger)}
.btn-success{background:var(--success)}
.btn-orange{background:var(--orange)}
.search-bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:15px;background:#f8fafc;padding:12px;border-radius:8px}
.search-box{padding:8px 12px;border-radius:6px;border:1px solid #cbd5e1;flex:1;min-width:200px;font-size:13px}
.filter-select{padding:8px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px;min-width:120px}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px}
th,td{padding:8px;border-bottom:1px solid #e2e8f0;text-align:center}
th{background:#f1f5f9;font-weight:600}
textarea{width:100%;min-height:60px;border-radius:6px;padding:6px;border:1px solid #cbd5e1;font-size:12px;resize:vertical}
.form-row{margin:4px 0}
.form-row select,.form-row input{width:100%;padding:6px;border-radius:5px;border:1px solid #cbd5e1;font-size:12px}
.amount{font-weight:bold;color:var(--primary)}
.no-data{text-align:center;padding:30px;color:#64748b;font-size:14px}
.msg{padding:10px;border-radius:6px;margin-bottom:10px;text-align:center;font-size:13px}
.msg-success{background:#dcfce7;color:var(--success)}
.msg-danger{background:#fee2e2;color:var(--danger)}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:15px 0}
.sum-card{background:#f8fafc;padding:12px;border-radius:8px;text-align:center;border:1px solid #e2e8f0}
.sum-value{font-size:16px;font-weight:700;color:#1e293b}
.actions-cell{min-width:280px}
.user-info{background:#f1f5f9;padding:8px 15px;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.user-info span{color:#334155;font-weight:600}
.logout-btn{background:#ef4444;color:white;border:none;padding:5px 10px;border-radius:5px;cursor:pointer;text-decoration:none;font-size:12px}
.auto-refresh-info{background:#dbeafe;padding:6px 10px;border-radius:6px;margin-top:10px;text-align:center;color:#1d4ed8;font-size:12px;display:flex;justify-content:center;align-items:center;gap:5px}
.field-label{color:#475569;font-size:11px;margin-bottom:2px}
.data-row{display:flex;flex-direction:column;max-width:200px;margin:0 auto}
.refresh-btn,.pause-btn{background:#3b82f6;color:white;border:none;padding:2px 8px;border-radius:4px;cursor:pointer;font-size:11px;margin:0 3px}
.pause-btn{background:#6b7280}
@media (max-width:768px){
    .header{flex-direction:column;text-align:center}
    .search-bar{flex-direction:column}
    .search-box,.filter-select{width:100%}
    table{font-size:11px}
    th,td{padding:6px 4px}
    .user-info{flex-direction:column;gap:8px;text-align:center}
    .actions-cell{min-width:250px}
}
</style>
</head>
<body>
<div class="container">
    <div class="user-info">
        <div>
            👤 المستخدم: <span><?= htmlspecialchars($_SESSION['username'] ?? 'مستخدم') ?></span>
            | 🕐 آخر تحديث: <span id="last-update"><?= date('H:i:s') ?></span>
        </div>
        <a href="../logout.php" class="logout-btn" onclick="return confirm('هل تريد تسجيل الخروج؟')">🚪 تسجيل الخروج</a>
    </div>
    
    <div class="header">
        <h2>⏳ الفواتير المعلقة</h2>
        <div style="display:flex;gap:8px">
            <a href="../dashboard.php" class="btn btn-primary">🏠 الرئيسية</a>
            <a href="manual.php" class="btn btn-success">➕ إنشاء</a>
            <a href="reports.php" class="btn btn-orange">📊 التقارير</a>
        </div>
    </div>
    
    <?php if(isset($_GET['success'])): ?>
    <div class="msg msg-success">✅ تم اعتماد العملية بنجاح</div>
    <?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?>
    <div class="msg msg-danger">🗑️ تم حذف العملية</div>
    <?php endif; ?>
    
    <form method="GET" class="search-bar">
        <input type="text" name="q" class="search-box" placeholder="🔍 بحث (رقم فاتورة، خدمة، تفاصيل...)" 
               value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <select name="service" class="filter-select">
            <option value="">جميع الخدمات</option>
            <?php 
            if ($services && $services->num_rows > 0):
                $services->data_seek(0); // إعادة ضبط المؤشر
                while($s = $services->fetch_assoc()): 
            ?>
            <option value="<?= htmlspecialchars($s['service_name']) ?>" <?= ($_GET['service']??'')==$s['service_name']?'selected':'' ?>>
                <?= htmlspecialchars($s['service_name']) ?>
            </option>
            <?php 
                endwhile;
            endif; 
            ?>
        </select>
        <input type="date" name="from_date" class="filter-select" value="<?= htmlspecialchars($_GET['from_date']??'') ?>" placeholder="من تاريخ">
        <input type="date" name="to_date" class="filter-select" value="<?= htmlspecialchars($_GET['to_date']??'') ?>" placeholder="إلى تاريخ">
        <button type="submit" class="btn btn-primary">🔍 بحث</button>
        <?php if(!empty($_GET['q']) || !empty($_GET['service']) || !empty($_GET['from_date']) || !empty($_GET['to_date'])): ?>
            <a href="pending.php" class="btn btn-danger">❌ إعادة</a>
        <?php endif; ?>
    </form>
    
    <!-- شاشة الإحصائيات -->
    <div class="summary">
        <div class="sum-card">
            <div>الفواتير</div>
            <div class="sum-value"><?= $stats['total_invoices'] ?></div>
        </div>
        <div class="sum-card">
            <div>القيمة الأساسية</div>
            <div class="sum-value"><?= number_format($stats['total_amount'], 0) ?> ج.م</div>
        </div>
        <div class="sum-card">
            <div>الرسوم</div>
            <div class="sum-value"><?= number_format($stats['total_fees'], 0) ?> ج.م</div>
        </div>
        <div class="sum-card">
            <div>الإجمالي</div>
            <div class="sum-value"><?= number_format($stats['total_grand'], 0) ?> ج.م</div>
        </div>
    </div>
    
    <?php if(empty($operations)): ?>
        <div class="no-data">⏳ لا توجد فواتير معلقة حالياً</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الفاتورة</th>
                    <th>التاريخ</th>
                    <th>الخدمة</th>
                    <th>البيانات</th>
                    <th>التفاصيل</th>
                    <th>المبلغ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($operations as $o): 
                    $data = json_decode($o['service_data'] ?? '{}', true);
                    $input_fields = json_decode($o['input_fields'] ?? '{}', true);
                ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td style="font-weight:bold"><?= htmlspecialchars($o['invoice_no'] ?? '') ?></td>
                    <td><?= date('m/d H:i', strtotime($o['created_at'])) ?></td>
                    <td><?= htmlspecialchars($o['service_name']) ?></td>
                    <td style="text-align:right;max-width:200px">
                        <?php if(!empty($data)): ?>
                            <div class="data-row">
                            <?php foreach($data as $k => $v): 
                                if(empty($v)) continue;
                                
                                // الحصول على اسم العرض للحقل
                                $display_key = getFieldDisplayName($k, $o['service_name'], $field_labels, $input_fields);
                            ?>
                                <div class="field-label"><?= htmlspecialchars($display_key) ?>:</div>
                                <div style="margin-bottom:5px"><?= htmlspecialchars($v) ?></div>
                            <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color:#94a3b8">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <textarea readonly style="background:#f8fafc"><?= htmlspecialchars($o['details'] ?? '') ?></textarea>
                    </td>
                    <td>
                        <div class="amount"><?= number_format($o['amount'], 0) ?> ج.م</div>
                        <div>الرسوم: <?= number_format(($o['total'] - $o['amount']), 0) ?> ج.م</div>
                        <div><strong>الإجمالي: <?= number_format($o['total'], 0) ?> ج.م</strong></div>
                    </td>
                    <td class="actions-cell">
                        <form method="post" onsubmit="return confirm('تأكيد اعتماد الفاتورة #<?= $o['id'] ?>؟')">
                            <input type="hidden" name="approve_id" value="<?= $o['id'] ?>">
                            
                            <div class="form-row">
                                <select name="payment_company" required>
                                    <option value="">اختر الشركة</option>
                                    <?php foreach($companies as $company): ?>
                                    <option value="<?= $company ?>"><?= $company ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <select name="payment_type" required>
                                    <option value="">طريقة الدفع</option>
                                    <?php foreach($payment_types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <input type="number" step="0.01" name="paid_amount" 
                                       value="<?= $o['total'] ?>" required placeholder="المبلغ المدفوع">
                            </div>
                            
                            <div class="form-row">
                                <input type="text" name="notes" placeholder="ملاحظات إضافية">
                            </div>
                            
                            <button type="submit" class="btn btn-success" style="width:100%;margin-top:5px">
                                💾 اعتماد الفاتورة
                            </button>
                        </form>
                        
                        <div style="display:flex;gap:5px;margin-top:5px">
                            <a href="?delete=<?= $o['id'] ?>" class="btn btn-danger" 
                               style="flex:1;text-align:center"
                               onclick="return confirm('هل أنت متأكد من حذف الفاتورة #<?= $o['id'] ?>؟')">
                                🗑️ حذف
                            </a>
                            
                            <a href="print.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-primary" 
                               style="flex:1;text-align:center">
                                🖨️ طباعة
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="text-align:center;padding:10px;color:#64748b;font-size:12px">
        عدد الفواتير المعلقة: <?= $stats['total_invoices'] ?> | 
        القيمة الأساسية: <?= number_format($stats['total_amount'], 0) ?> ج.م | 
        الرسوم: <?= number_format($stats['total_fees'], 0) ?> ج.م | 
        الإجمالي: <?= number_format($stats['total_grand'], 0) ?> ج.م
    </div>
    <?php endif; ?>
    
    <div class="auto-refresh-info" id="refresh-info">
        ⏰ التحديث التلقائي كل 10 ثواني - التحديث خلال <span id="countdown">10</span> ثانية
        <button onclick="refreshNow()" class="refresh-btn">🔄 تحديث الآن</button>
        <button onclick="toggleRefresh()" class="pause-btn">⏸️ إيقاف مؤقت</button>
    </div>
</div>

<script>
// التحديث كل 10 ثواني
let refreshInterval = 10000;
let countdownInterval;
let countdown = 10;
let autoRefreshEnabled = true;
let isFormActive = false;

// تحديث وقت آخر تحديث
function updateLastUpdateTime() {
    document.getElementById('last-update').textContent = new Date().toLocaleTimeString('ar-EG');
}

// بدء العد التنازلي
function startCountdown() {
    countdown = 10;
    document.getElementById('countdown').textContent = countdown;
    
    clearInterval(countdownInterval);
    
    countdownInterval = setInterval(() => {
        if (!isFormActive) {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                if (autoRefreshEnabled) {
                    refreshPage();
                }
            }
        }
    }, 1000);
}

// تحديث الصفحة
function refreshPage() {
    saveFormData();
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('_t', Date.now());
    window.location.href = `${window.location.pathname}?${urlParams}`;
}

// تحديث فوري
function refreshNow() {
    refreshPage();
}

// تبديل التحديث التلقائي
function toggleRefresh() {
    autoRefreshEnabled = !autoRefreshEnabled;
    const btn = document.querySelector('.pause-btn');
    const info = document.getElementById('refresh-info');
    
    if (autoRefreshEnabled) {
        btn.textContent = '⏸️ إيقاف مؤقت';
        info.style.background = '#dbeafe';
        info.style.color = '#1d4ed8';
        startCountdown();
    } else {
        btn.textContent = '▶️ تشغيل';
        info.style.background = '#f3f4f6';
        info.style.color = '#6b7280';
        clearInterval(countdownInterval);
        document.getElementById('countdown').textContent = 'مُوقَّف';
    }
}

// حفظ بيانات النماذج
function saveFormData() {
    document.querySelectorAll('form').forEach(form => {
        const approveId = form.querySelector('[name="approve_id"]')?.value;
        if (approveId) {
            const formData = {};
            form.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.name && input.type !== 'hidden') {
                    formData[input.name] = input.value;
                }
            });
            localStorage.setItem(`form_${approveId}`, JSON.stringify(formData));
        }
    });
}

// استعادة بيانات النماذج
function restoreFormData() {
    document.querySelectorAll('form').forEach(form => {
        const approveId = form.querySelector('[name="approve_id"]')?.value;
        if (approveId) {
            const savedData = localStorage.getItem(`form_${approveId}`);
            if (savedData) {
                const formData = JSON.parse(savedData);
                form.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.name && input.type !== 'hidden' && formData[input.name] !== undefined) {
                        input.value = formData[input.name];
                    }
                });
                localStorage.removeItem(`form_${approveId}`);
            }
        }
    });
}

// كشف نشاط النماذج
function setupFormActivityDetection() {
    document.querySelectorAll('form').forEach(form => {
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('focus', () => {
                isFormActive = true;
                const info = document.getElementById('refresh-info');
                info.style.background = '#fef3c7';
                info.style.color = '#92400e';
                info.innerHTML = `⏸️ التحديث موقف - <span id="countdown">${countdown}</span> ثانية
                    <button onclick="refreshNow()" class="refresh-btn">🔄 تحديث الآن</button>
                    <button onclick="toggleRefresh()" class="pause-btn">▶️ تشغيل</button>`;
            });
            
            input.addEventListener('blur', () => {
                setTimeout(() => {
                    const activeElement = document.activeElement;
                    const isActive = Array.from(form.querySelectorAll('input, select, textarea'))
                        .some(el => el === activeElement);
                    
                    if (!isActive) {
                        isFormActive = false;
                        const info = document.getElementById('refresh-info');
                        info.style.background = '#dbeafe';
                        info.style.color = '#1d4ed8';
                        info.innerHTML = `⏰ التحديث التلقائي كل 10 ثواني - التحديث خلال <span id="countdown">${countdown}</span> ثانية
                            <button onclick="refreshNow()" class="refresh-btn">🔄 تحديث الآن</button>
                            <button onclick="toggleRefresh()" class="pause-btn">⏸️ إيقاف مؤقت</button>`;
                    }
                }, 100);
            });
        });
    });
}

// التهيئة عند تحميل الصفحة
window.onload = function() {
    updateLastUpdateTime();
    startCountdown();
    restoreFormData();
    setupFormActivityDetection();
    
    // تحديث تلقائي
    setInterval(() => {
        if (autoRefreshEnabled && countdown <= 0 && !isFormActive) {
            refreshPage();
        }
    }, refreshInterval);
    
    // تحديث الوقت
    setInterval(updateLastUpdateTime, 1000);
    
    // تنظيف التخزين المحلي
    window.addEventListener('beforeunload', () => {
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith('form_')) localStorage.removeItem(key);
        });
    });
};

// تنظيف التخزين عند إرسال النموذج
document.addEventListener('submit', function(e) {
    if (e.target.matches('form')) {
        const approveId = e.target.querySelector('[name="approve_id"]')?.value;
        if (approveId) localStorage.removeItem(`form_${approveId}`);
    }
});
</script>
</body>
</html>