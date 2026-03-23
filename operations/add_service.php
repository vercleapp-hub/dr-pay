<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/operations.php";

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name = trim($_POST['service_name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    
    if (empty($service_name) || $price <= 0) {
        $result = ['success' => false];
    } else {
        $description = trim($_POST['description'] ?? '');
        $fees = (float)($_POST['fees'] ?? 0);
        $status = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
        $profit = $price - $fees;
        
        // معالجة الحقول
        $fields = [];
        if (!empty($_POST['field_name'])) {
            foreach ($_POST['field_name'] as $i => $name) {
                if (empty(trim($name))) continue;
                $fields[] = [
                    'key' => preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($name))),
                    'label' => trim($_POST['field_label'][$i] ?? $name),
                    'placeholder' => trim($_POST['field_placeholder'][$i] ?? ''),
                    'required' => isset($_POST['field_required'][$i]),
                    'in_details' => isset($_POST['field_in_details'][$i])
                ];
            }
        }
        $input_fields = json_encode($fields, JSON_UNESCAPED_UNICODE);
        
        $stmt = $conn_operations->prepare("INSERT INTO services (service_name, description, price, fees, profit, input_fields, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdddss", $service_name, $description, $price, $fees, $profit, $input_fields, $status);
        
        if ($stmt->execute()) {
            header("Location: services.php?success=1");
            exit;
        } else {
            $result = ['success' => false];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>➕ إضافة خدمة</title>
<style>
:root{--pri:#2563eb;--suc:#16a34a;--dan:#dc2626;--gr:#64748b;--warn:#f59e0b;}
body{font-family:Tahoma;background:#f1f5f9;padding:10px;}
.container{max-width:800px;margin:auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.08);}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
h2{margin:0;color:#1e293b;}
.btn{padding:8px 14px;border-radius:6px;border:none;cursor:pointer;color:#fff;text-decoration:none;font-size:13px;font-weight:600;}
.btn-primary{background:var(--pri);}
.btn-success{background:var(--suc);}
.btn-secondary{background:var(--gr);}
.btn-warning{background:var(--warn);}
.btn-danger{background:var(--dan);padding:5px 10px;}
.notice{padding:10px;border-radius:6px;margin-bottom:15px;text-align:center;font-size:13px;}
.success{background:#dcfce7;color:var(--suc);}
.error{background:#fee2e2;color:var(--dan);}
.form-group{margin-bottom:12px;}
label{display:block;margin-bottom:4px;font-weight:600;font-size:13px;}
.form-control{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.profit-display{background:#dcfce7;padding:10px;border-radius:6px;font-weight:bold;color:var(--suc);}
.field-container{margin:15px 0;}
.field-row{display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:8px;margin-bottom:8px;padding:10px;background:#f8fafc;border-radius:6px;align-items:center;}
.field-options{display:flex;gap:10px;font-size:12px;}
.actions{display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;}
@media (max-width:768px){
    .header{flex-direction:column;gap:10px;}
    .grid-2,.field-row{grid-template-columns:1fr;}
    .field-options{flex-direction:column;}
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>➕ إضافة خدمة جديدة</h2>
        <div style="display:flex;gap:8px;">
            <a href="services.php" class="btn btn-secondary">📋 الخدمات</a>
            <a href="../dashboard.php" class="btn btn-secondary">🏠 الرئيسية</a>
        </div>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="notice success">✅ تم حفظ الخدمة بنجاح</div>
    <?php endif; ?>
    
    <?php if ($result && !$result['success']): ?>
        <div class="notice error">❌ حدث خطأ في الحفظ</div>
    <?php endif; ?>
    
    <form method="POST" id="serviceForm">
        <div class="form-group">
            <label>اسم الخدمة *</label>
            <input type="text" class="form-control" name="service_name" required placeholder="أدخل اسم الخدمة">
        </div>
        
        <div class="form-group">
            <label>وصف الخدمة</label>
            <textarea class="form-control" name="description" rows="2" placeholder="وصف مختصر للخدمة"></textarea>
        </div>
        
        <div class="grid-2">
            <div class="form-group">
                <label>سعر الخدمة *</label>
                <input type="number" step="0.01" class="form-control" name="price" required oninput="calculateProfit()" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>رسوم الخدمة</label>
                <input type="number" step="0.01" class="form-control" name="fees" value="0" oninput="calculateProfit()" placeholder="0.00">
            </div>
        </div>
        
        <div class="profit-display">
            الربح المتوقع: <span id="profitValue">0.00</span> ج.م
        </div>
        
        <div class="form-group">
            <label>حالة الخدمة</label>
            <select class="form-control" name="status">
                <option value="active">مفعلة</option>
                <option value="inactive">موقوفة</option>
            </select>
        </div>
        
        <div class="field-container">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h3 style="margin:0;font-size:16px;">حقول إدخال الخدمة</h3>
                <button type="button" class="btn btn-warning" onclick="addField()">+ إضافة حقل</button>
            </div>
            <div id="fieldsContainer">
                <div class="field-row">
                    <input type="text" class="form-control" name="field_label[]" placeholder="اسم الحقل (العربي)" required>
                    <input type="text" class="form-control" name="field_name[]" placeholder="المفتاح (انجليزي)" required>
                    <input type="text" class="form-control" name="field_placeholder[]" placeholder="نص مساعد">
                    <div class="field-options">
                        <label><input type="checkbox" name="field_required[]"> إجباري</label>
                        <label><input type="checkbox" name="field_in_details[]"> في التفاصيل</label>
                    </div>
                    <button type="button" class="btn btn-danger" onclick="removeField(this)">✕</button>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <button type="submit" class="btn btn-success">💾 حفظ الخدمة</button>
            <button type="button" class="btn btn-primary" onclick="addField()">➕ إضافة حقل</button>
            <a href="services.php" class="btn btn-secondary">← رجوع للقائمة</a>
        </div>
    </form>
</div>

<script>
function calculateProfit() {
    const price = parseFloat(document.querySelector('[name="price"]').value) || 0;
    const fees = parseFloat(document.querySelector('[name="fees"]').value) || 0;
    document.getElementById('profitValue').textContent = (price - fees).toFixed(2);
}

function addField() {
    const container = document.getElementById('fieldsContainer');
    const div = document.createElement('div');
    div.className = 'field-row';
    div.innerHTML = `
        <input type="text" class="form-control" name="field_label[]" placeholder="اسم الحقل (العربي)" required>
        <input type="text" class="form-control" name="field_name[]" placeholder="المفتاح (انجليزي)" required>
        <input type="text" class="form-control" name="field_placeholder[]" placeholder="نص مساعد">
        <div class="field-options">
            <label><input type="checkbox" name="field_required[]"> إجباري</label>
            <label><input type="checkbox" name="field_in_details[]"> في التفاصيل</label>
        </div>
        <button type="button" class="btn btn-danger" onclick="removeField(this)">✕</button>
    `;
    container.appendChild(div);
}

function removeField(btn) {
    if (document.querySelectorAll('.field-row').length > 1) {
        btn.closest('.field-row').remove();
    } else {
        alert('يجب أن تحتوي الخدمة على حقل واحد على الأقل');
    }
}

document.getElementById('serviceForm').addEventListener('submit', function(e) {
    const price = parseFloat(document.querySelector('[name="price"]').value);
    if (price <= 0) {
        e.preventDefault();
        alert('سعر الخدمة يجب أن يكون أكبر من صفر');
        return false;
    }
    
    const serviceName = document.querySelector('[name="service_name"]').value.trim();
    if (!serviceName) {
        e.preventDefault();
        alert('اسم الخدمة مطلوب');
        return false;
    }
    
    // تحقق من وجود حقول مكررة
    const fieldNames = [];
    document.querySelectorAll('[name="field_name[]"]').forEach(input => {
        const name = input.value.trim().toLowerCase();
        if (name && fieldNames.includes(name)) {
            e.preventDefault();
            alert('يوجد مفتاح حقل مكرر: ' + name);
            input.focus();
        }
        fieldNames.push(name);
    });
});

// حساب الربح عند التحميل
window.onload = calculateProfit;
</script>
</body>
</html>