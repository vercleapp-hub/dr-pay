<?php
error_reporting(E_ALL);
require_once "../config/operations.php";

session_start();

// إعادة التوجيه
function redirect($url) {
    header("Location: $url");
    exit();
}

// التحقق من الجلسة
if (isset($_GET['logout'])) {
    session_destroy();
    redirect("https://dr.free.nf/login.php");
}

if (!isset($_SESSION['user_id'])) {
    redirect("https://dr.free.nf/login.php");
}

// إنشاء رمز CSRF إذا لم يكن موجودًا
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دالة لجلب الخدمات مع التخزين المؤقت
function getServices($conn_operations) {
    $cacheFile = 'services_cache.json';
    $cacheTime = 300; // 5 دقائق
    
    // التحقق من وجود cache صالح
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    
    // جلب البيانات من قاعدة البيانات
    $services = [];
    try {
        $stmt = $conn_operations->prepare("SELECT id, service_name, price, fees, description, input_fields FROM services WHERE status='active' ORDER BY service_name");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $local_services = file_exists('local_services.json') ? 
            json_decode(file_get_contents('local_services.json'), true) ?? [] : [];
        
        while($row = $result->fetch_assoc()){
            $row['fields'] = json_decode($row['input_fields'] ?? '[]', true) ?? [];
            $id = $row['id'];
            
            if (isset($local_services[$id])) {
                $local = $local_services[$id];
                $row = array_merge($row, array_intersect_key($local, 
                    array_flip(['service_name','price','fees','description','fields'])));
            }
            
            $services[] = $row;
        }
        $stmt->close();
        
        // حفظ في cache
        file_put_contents($cacheFile, json_encode($services));
    } catch (Exception $e) {
        error_log("Error fetching services: " . $e->getMessage());
        return [];
    }
    
    return $services;
}

$services = getServices($conn_operations);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>➕ إنشاء عملية يدوية</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#f8fafc;--card:#fff;--text:#1e293b;--primary:#3b82f6;--success:#10b981;--danger:#ef4444;--warning:#f59e0b;--border:#e2e8f0;--shadow:0 4px 6px -1px rgba(0,0,0,0.1)}
[data-theme="dark"]{--bg:#0f172a;--card:#1e293b;--text:#f1f5f9;--primary:#60a5fa;--success:#34d399;--warning:#fbbf24;--border:#475569}
*{margin:0;box-sizing:border-box;font-family:system-ui,sans-serif}
body{background:var(--bg);color:var(--text);min-height:100vh;padding:15px;overflow:hidden}

.container{max-width:1400px;margin:0 auto;background:var(--card);border-radius:12px;box-shadow:var(--shadow);padding:20px;border:1px solid var(--border);height:calc(100vh - 30px);display:flex;flex-direction:column}
.top-bar{display:flex;gap:12px;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap}
.search-box{flex:1;min-width:250px;position:relative}
.search-box input{width:100%;padding:10px 35px 10px 12px;border:2px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:14px}
.search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--border);pointer-events:none}
.buttons{display:flex;gap:8px}
.btn{padding:8px 15px;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:0.3s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit;font-size:14px}
.btn-primary{background:var(--primary);color:white}.btn-success{background:var(--success);color:white}.btn-danger{background:var(--danger);color:white}.btn-warning{background:var(--warning);color:white}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 12px rgba(0,0,0,0.1)}
.btn:disabled{opacity:0.6;cursor:not-allowed;transform:none}

.user-info{background:linear-gradient(135deg, var(--primary), #2563eb);color:white;padding:8px 15px;border-radius:8px;margin-bottom:15px;font-size:13px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
.services-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;overflow-y:auto;padding:5px 2px;flex:1;align-content:start}
.service-card{background:var(--bg);border:2px solid var(--border);border-radius:10px;padding:10px;cursor:pointer;transition:0.2s;position:relative}
.service-card:hover{transform:translateY(-2px);border-color:var(--primary);box-shadow:0 4px 8px rgba(0,0,0,0.1)}
.service-card.active{background:var(--primary);color:white;border-color:var(--primary)}
.service-card.active .service-price{color:white !important}
.service-name{font-weight:600;font-size:14px;margin-bottom:5px;display:flex;align-items:center;gap:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.service-price{font-size:15px;font-weight:700;margin:3px 0}
.service-fees{font-size:11px;opacity:0.8;background:rgba(0,0,0,0.05);padding:2px 6px;border-radius:12px;display:inline-block}
.service-desc{font-size:11px;opacity:0.7;line-height:1.3;height:0;overflow:hidden;transition:height 0.2s;margin-top:2px}
.service-card:hover .service-desc{height:32px}
.service-badge{position:absolute;top:5px;left:5px;background:var(--primary);color:white;padding:2px 6px;border-radius:12px;font-size:10px}

/* تحسينات المودال */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;padding:15px;backdrop-filter:blur(4px)}
.modal-content{background:var(--card);border-radius:12px;max-width:800px;width:100%;margin:20px auto;animation:slideIn 0.3s;max-height:calc(100vh - 40px);display:flex;flex-direction:column}
@keyframes slideIn{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
.modal-header{background:linear-gradient(135deg, var(--primary), #2563eb);color:white;padding:15px 20px;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
.modal-header button{background:rgba(255,255,255,0.2);border:none;color:white;width:34px;height:34px;border-radius:50%;cursor:pointer;transition:0.3s;font-size:16px}
.modal-header button:hover{background:rgba(255,255,255,0.3);transform:rotate(90deg)}
.modal-body{padding:20px;overflow-y:auto;flex:1}
/* شبكة الحقول */
.fields-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:10px}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:15px}
.input-group{margin-bottom:8px}
.input-group label{display:block;margin-bottom:4px;font-weight:600;font-size:12px;color:var(--text)}
.input-control{width:100%;padding:8px 10px;border:2px solid var(--border);border-radius:6px;background:var(--card);color:var(--text);font-size:13px;transition:0.2s}
.input-control:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(59,130,246,0.2)}
.amount-controls{display:flex;gap:5px;margin-top:5px}
.amount-btn{padding:5px 8px;background:var(--primary);color:white;border:none;border-radius:6px;cursor:pointer;flex:1;transition:0.2s;font-size:12px}
.amount-btn:hover{background:#2563eb}
.total-section{background:linear-gradient(135deg, #1e40af, var(--primary));color:white;border-radius:8px;padding:12px;margin:10px 0}
.total-row{display:flex;justify-content:space-between;margin-bottom:5px;font-size:13px}
.total-final{font-size:16px;font-weight:700}
.modal-actions{display:flex;gap:10px;margin-top:15px;padding-top:15px;border-top:2px solid var(--border);justify-content:flex-start}
.loader{display:inline-block;width:14px;height:14px;border:2px solid #fff;border-radius:50%;border-top-color:transparent;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.alert{position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 20px;border-radius:8px;color:white;z-index:2000;animation:slideDown 0.3s;font-size:14px}
.alert.success{background:var(--success)}.alert.error{background:var(--danger)}
@keyframes slideDown{from{opacity:0;transform:translate(-50%,-20px)}to{opacity:1;transform:translate(-50%,0)}}
.no-services{text-align:center;padding:30px;color:#64748b;grid-column:1/-1}
.no-services i{font-size:48px;margin-bottom:10px;opacity:0.5}
.required{color:var(--danger);margin-right:2px}

@media(max-width:768px){
    .top-bar{flex-direction:column}
    .search-box,.buttons{width:100%}
    .buttons{flex-wrap:wrap}
    .modal-content{margin:10px}
    .modal-body{padding:15px}
    .services-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}
    .service-name{font-size:13px}
    .service-price{font-size:14px}
    .fields-grid{grid-template-columns:1fr} /* عمود واحد على الشاشات الصغيرة */
}
</style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <div class="search-box">
            <input type="text" id="search" placeholder="ابحث عن خدمة..." autocomplete="off">
            <i class="fas fa-search"></i>
        </div>
        <div class="buttons">
            <button class="btn btn-primary" onclick="toggleTheme()" title="تغيير الثيم"><i id="themeIcon"></i></button>
            <a href="/dashboard.php" class="btn" title="الرئيسية"><i class="fas fa-home"></i></a>
            <a href="services.php" class="btn btn-success" title="إدارة الخدمات"><i class="fas fa-cog"></i> الخدمات</a>
            <a href="pending.php" class="btn btn-warning" title="الفواتير المعلقة"><i class="fas fa-clock"></i> المعلقة</a>
            <a href="?logout=1" class="btn btn-danger" title="تسجيل الخروج"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    
    <div class="user-info">
        <span><i class="fas fa-user"></i> <?=htmlspecialchars($_SESSION['username']??'المستخدم')?></span>
        <span><i class="fas fa-cubes"></i> <span id="servicesCount"><?=count($services)?></span> خدمة</span>
    </div>
    
    <div class="services-grid" id="servicesGrid">
        <?php if(empty($services)): ?>
            <div class="no-services">
                <i class="fas fa-inbox"></i>
                <div>لا توجد خدمات متاحة</div>
            </div>
        <?php else: foreach($services as $s): 
            $price = $s['price'];
            if ($price > 500) $priceColor = '#f59e0b';
            elseif ($price > 100) $priceColor = '#10b981';
            else $priceColor = '#ef4444';
        ?>
            <div class="service-card" data-name="<?=htmlspecialchars($s['service_name'])?>" 
                 data-service='<?=htmlspecialchars(json_encode($s, JSON_UNESCAPED_UNICODE))?>'>
                <div class="service-name" title="<?=htmlspecialchars($s['service_name'])?>">
                    <i class="fas fa-tag" style="font-size:12px"></i>
                    <?=htmlspecialchars($s['service_name'])?>
                </div>
                <div class="service-price" style="color:<?=$priceColor?>"><?=number_format($price,2)?> ج.م</div>
                <?php if(!empty($s['fees'])): ?>
                    <div class="service-fees">+<?=number_format($s['fees'],2)?> ج.م</div>
                <?php endif; ?>
                <?php if(!empty($s['description'])): ?>
                    <div class="service-desc"><?=htmlspecialchars(mb_substr($s['description'],0,40,'UTF-8'))?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="modal" id="dataModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> إدخال البيانات</h3>
            <button onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="save_manual.php" id="orderForm" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                <input type="hidden" name="service_id" id="service_id">
                <input type="hidden" name="service_name" id="service_name">
                <input type="hidden" name="fields_structure" id="fields_structure">
                
                <div style="background:rgba(59,130,246,0.1);padding:10px;border-radius:8px;margin-bottom:12px;border-right:4px solid var(--primary)">
                    <div style="font-weight:700;font-size:15px;color:var(--primary)" id="selectedServiceName"></div>
                    <div style="font-size:12px;margin-top:3px" id="selectedServiceDesc"></div>
                </div>
                
                <!-- حقلي السعر والرسوم في شبكة -->
                <div class="form-grid">
                    <div class="input-group">
                        <label><i class="fas fa-money-bill"></i> قيمة الخدمة <span class="required">*</span></label>
                        <input type="number" name="amount" id="amount" class="input-control" step="0.01" min="0" required oninput="updateTotal()">
                        <div class="amount-controls">
                            <button type="button" class="amount-btn" onclick="adjustAmount(1)">+1</button>
                            <button type="button" class="amount-btn" onclick="adjustAmount(-1)">-1</button>
                        </div>
                    </div>
                    <div class="input-group">
                        <label><i class="fas fa-coins"></i> الرسوم <span class="required">*</span></label>
                        <input type="number" name="fees" id="fees" class="input-control" step="0.01" min="0" required oninput="updateTotal()">
                        <div class="amount-controls">
                            <button type="button" class="amount-btn" onclick="adjustFees(1)">+1</button>
                            <button type="button" class="amount-btn" onclick="adjustFees(-1)">-1</button>
                        </div>
                    </div>
                </div>
                
                <div class="total-section">
                    <div class="total-row"><span>الخدمة:</span><span id="amountDisplay">0.00 ج.م</span></div>
                    <div class="total-row"><span>الرسوم:</span><span id="feesDisplay">0.00 ج.م</span></div>
                    <div class="total-row"><span>الإجمالي:</span><span class="total-final" id="totalDisplay">0.00 ج.م</span></div>
                </div>
                
                <!-- الحقول الديناميكية في شبكة متعددة الأعمدة -->
                <div id="dynamicFields" class="fields-grid"></div>
                
                <!-- حقل التفاصيل -->
                <div class="input-group" style="margin-top:5px;">
                    <label><i class="fas fa-sticky-note"></i> تفاصيل إضافية</label>
                    <textarea name="details" id="details" class="input-control" rows="2" placeholder="ملاحظات..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-success" id="submitBtn"><i class="fas fa-save"></i> حفظ</button>
                    <button type="button" class="btn" onclick="closeModal()" style="background:#94a3b8">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// تهيئة المتغيرات
let currentService = null;
let searchTimer;
const csrfToken = '<?=$_SESSION['csrf_token']?>';
const servicesGrid = document.getElementById('servicesGrid');
const modal = document.getElementById('dataModal');

// تفويض الأحداث لبطاقات الخدمات (أداء أفضل)
servicesGrid.addEventListener('click', (e) => {
    const card = e.target.closest('.service-card');
    if (!card) return;
    const serviceData = card.dataset.service;
    if (serviceData) {
        selectService(JSON.parse(serviceData), card);
    }
});

// البحث المحسن
document.getElementById('search').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const term = e.target.value.trim().toLowerCase();
        let visibleCount = 0;
        document.querySelectorAll('.service-card').forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const matches = term === '' || name.includes(term);
            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });
        document.getElementById('servicesCount').textContent = visibleCount;
    }, 200); // debounce 200ms
});

// اختيار خدمة
function selectService(service, element) {
    document.querySelectorAll('.service-card').forEach(c => c.classList.remove('active'));
    element.classList.add('active');
    currentService = service;
    
    document.getElementById('selectedServiceName').textContent = service.service_name;
    document.getElementById('selectedServiceDesc').textContent = service.description || 'لا يوجد وصف';
    document.getElementById('service_id').value = service.id;
    document.getElementById('service_name').value = service.service_name;
    document.getElementById('amount').value = service.price || 0;
    document.getElementById('fees').value = service.fees || 0;
    document.getElementById('details').value = service.description || '';
    updateTotal();
    
    // إنشاء الحقول الديناميكية في شبكة
    const fields = service.fields || [];
    let html = '';
    const fieldsStructure = [];
    fields.forEach((field, i) => {
        const name = field.name || `field_${i}`;
        const label = field.label || `حقل ${i+1}`;
        const required = field.required ? 'required' : '';
        fieldsStructure.push({name, label, required: field.required});
        html += `<div class="input-group">
            <label>${label} ${field.required ? '<span class="required">*</span>' : ''}</label>
            <input type="text" name="data[${name}]" class="input-control" placeholder="${field.placeholder || label}" ${required}>
        </div>`;
    });
    document.getElementById('dynamicFields').innerHTML = html;
    document.getElementById('fields_structure').value = JSON.stringify(fieldsStructure);
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// تحديث الإجمالي
function updateTotal() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const fees = parseFloat(document.getElementById('fees').value) || 0;
    const total = amount + fees;
    document.getElementById('amountDisplay').textContent = amount.toFixed(2) + ' ج.م';
    document.getElementById('feesDisplay').textContent = fees.toFixed(2) + ' ج.م';
    document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' ج.م';
}

function adjustAmount(change) {
    const input = document.getElementById('amount');
    input.value = Math.max(0, (parseFloat(input.value) || 0) + change).toFixed(2);
    updateTotal();
}
function adjustFees(change) {
    const input = document.getElementById('fees');
    input.value = Math.max(0, (parseFloat(input.value) || 0) + change).toFixed(2);
    updateTotal();
}

// التحقق من النموذج
function validateForm() {
    const amount = parseFloat(document.getElementById('amount').value);
    const fees = parseFloat(document.getElementById('fees').value);
    if (amount < 0 || fees < 0) {
        showAlert('القيم لا يمكن أن تكون سالبة', 'error');
        return false;
    }
    const requiredFields = document.querySelectorAll('#orderForm [required]');
    for (let field of requiredFields) {
        if (!field.value.trim()) {
            showAlert('الرجاء ملء جميع الحقول المطلوبة', 'error');
            field.focus();
            return false;
        }
    }
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loader"></span> جاري الحفظ...';
    return true;
}

function showAlert(message, type = 'success') {
    const alert = document.createElement('div');
    alert.className = `alert ${type}`;
    alert.textContent = message;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 3000);
}

function closeModal() {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    document.querySelectorAll('.service-card').forEach(c => c.classList.remove('active'));
    currentService = null;
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> حفظ';
}

// تبديل الثيم
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    document.getElementById('themeIcon').className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    localStorage.setItem('theme', newTheme);
}

// التهيئة
document.addEventListener('DOMContentLoaded', function() {
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
    document.getElementById('themeIcon').className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    
    // إغلاق المودال بالضغط على ESC
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closeModal());
    modal.addEventListener('click', (e) => e.target === modal && closeModal());
    
    // منع إعادة الإرسال
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});
</script>
</body>
</html>