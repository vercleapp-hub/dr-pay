<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once "../config/operations.php";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) die("عملية غير صحيحة");

// جلب العملية
$stmt = $conn_operations->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$op = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$op) die("العملية غير موجودة");

// جلب حقول الخدمة باستخدام اسم الخدمة من جدول services
$service_name = $op['service_name'];
$service_fields = [];

if (!empty($service_name)) {
    $stmt2 = $conn_operations->prepare("SELECT input_fields FROM services WHERE service_name = ?");
    $stmt2->bind_param("s", $service_name);
    $stmt2->execute();
    $service_result = $stmt2->get_result();
    if ($service_row = $service_result->fetch_assoc()) {
        $input_fields = json_decode($service_row['input_fields'] ?? '[]', true);
        if (is_array($input_fields)) {
            foreach ($input_fields as $field) {
                if (isset($field['key']) && isset($field['label'])) {
                    $service_fields[$field['key']] = $field['label'];
                }
            }
        }
    }
    $stmt2->close();
}

// فك بيانات الخدمة من العملية
$serviceData = json_decode($op['service_data'] ?? '[]', true);
if (!is_array($serviceData)) $serviceData = [];

// الحصول على رقم الخدمة بشكل صحيح
$service_number = $op['service_number'] ?? '';
if (empty($service_number) && !empty($serviceData)) {
    $number_keys = ['field_0', 'field0', 'phone', 'الرقم', 'الرقم_المرجعي', 'رقم_الهاتف', 'الارقام', 'number'];
    foreach ($number_keys as $key) {
        if (!empty($serviceData[$key])) {
            $service_number = htmlspecialchars($serviceData[$key]);
            break;
        }
    }
}

// استخدام الرقم المرجعي من قاعدة البيانات (invoice_no)
$invoice_no = $op['invoice_no'] ?? '';
// إذا لم يكن موجوداً، نستخدم تنسيق التاريخ والوقت
if (empty($invoice_no)) {
    $invoice_no = date("ymd-His", strtotime($op['created_at']));
}

// معالجة التفاصيل
$details = '';
if (!empty($op['details'])) {
    $details = trim($op['details']);
    $details = preg_replace('/[ \t]+/', ' ', $details);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>إيصال | عملية #<?= htmlspecialchars($invoice_no) ?></title>
<!-- html2canvas library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
@page { 
    size: 48mm auto; 
    margin: 0;
    -webkit-print-color-adjust: exact !important;
    color-adjust: exact !important;
}
html, body { 
    margin: 0; 
    padding: 0; 
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: Tahoma, Arial, sans-serif;
}
body { 
    padding: 10px;
}
.receipt { 
    width: 48mm; 
    background: #fff;
    padding: 4px 5px; 
    box-sizing: border-box;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 15px;
    border-radius: 4px;
}
.logo img { 
    max-width: 42mm; 
    display: block; 
    margin: 0 auto 4px; 
    filter: contrast(1.3);
}
.service-name { 
    text-align: center; 
    font-size: 13px; 
    font-weight: bold; 
    margin-bottom: 4px;
}
.line { 
    border-top: 1px solid #000; 
    margin: 5px 0;
}
.row { 
    display: flex; 
    justify-content: space-between; 
    margin: 3px 0;
}
.label { 
    font-weight: bold; 
    font-size: 11px;
}
.value { 
    text-align: left; 
    font-weight: bold; 
    font-size: 11px;
}
.details-container { 
    margin: 4px 0 2px 0;
}
.details-label { 
    font-weight: bold; 
    font-size: 11px;
    display: inline;
}
.details-text { 
    font-size: 11px; 
    line-height: 1.25;
    word-break: break-word;
    display: inline;
    text-align: right;
    direction: rtl;
    white-space: pre-line;
    margin: 0;
    padding: 0;
}
.amounts .row { 
    font-size: 12px; 
}
.amounts .total { 
    font-size: 13px; 
    font-weight: bold;
    margin-top: 4px;
}
.ref-row { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin: 3px 0;
}
.ref-label { 
    font-weight: bold; 
    font-size: 11px;
}
.ref-value { 
    font-size: 10px; 
    font-weight: normal; 
    color: #333;
    letter-spacing: 0.5px;
}
.center { 
    text-align: center;
}
.small { 
    font-size: 10px; 
    line-height: 1.2;
}
.success { 
    font-weight: bold; 
    font-size: 12px;
    margin: 3px 0;
}
.actions { 
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    margin: 15px 0;
    width: 100%;
    max-width: 400px;
}
.btn { 
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 10px 16px; 
    font-size: 14px; 
    background: #3b82f6; 
    color: #fff; 
    text-decoration: none; 
    border-radius: 30px;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    flex: 1 0 auto;
    min-width: 120px;
}
.btn:hover { background: #2563eb; }
.btn.print { background: #10b981; }
.btn.image { background: #8b5cf6; }
.btn.share { background: #f59e0b; }
.btn.bluetooth { background: #6366f1; }
.btn.danger { background: #ef4444; }
.print-buttons-container {
    display: flex;
    flex-direction: row;
    gap: 5px;
    width: 100%;
}
.print-buttons-container .btn {
    flex: 1;
}
@media print { 
    .actions, .btn, .no-print { display: none; }
    body { background: #fff; padding: 0; }
    .receipt { box-shadow: none; margin-bottom: 0; }
}
</style>
</head>
<body>
<div class="receipt" id="receipt">
    <div class="logo">
        <img src="/dr.free.nf/htdocs/logo/لوجو الايصال PNG" 
             alt="شعار الشركة" 
             onerror="this.src='https://firebasestorage.googleapis.com/v0/b/drpay-fdc61.appspot.com/o/%D8%AF%D9%88%D9%86%20%D8%B9%D9%86%D9%88%D8%A7%D9%86%20(%D8%B9%D8%B1%D8%B6%20%D8%AA%D9%82%D8%AF%D9%8A%D9%85%D9%8A).jpg?alt=media&token=23c53df1-02ac-401a-9a2f-9a58e82843fd'"
             style="max-width: 42mm; display: block; margin: 0 auto 4px; filter: contrast(1.3);">
    </div>
    <div class="service-name"><?= htmlspecialchars($op['service_name']) ?></div>
    <div class="line"></div>
    
    <div class="row">
        <span class="label">التاريخ</span>
        <span class="value"><?= date("Y-m-d H:i", strtotime($op['created_at'])) ?></span>
    </div>
    
    <?php if (!empty($service_number)): ?>
    <div class="row">
        <span class="label">رقم الخدمة</span>
        <span class="value"><?= $service_number ?></span>
    </div>
    <?php endif; ?>
    
    <?php 
    foreach ($serviceData as $key => $value): 
        if ($key === '_fees' || empty($value) || in_array($key, ['field_0', 'field0', 'phone', 'الرقم', 'الرقم_المرجعي', 'رقم_الهاتف', 'الارقام', 'number'])) continue;
        
        $field_label = $service_fields[$key] ?? $key;
        if (!empty($value)): 
    ?>
    <div class="row">
        <span class="label"><?= htmlspecialchars($field_label) ?></span>
        <span class="value"><?= htmlspecialchars($value) ?></span>
    </div>
    <?php 
        endif; 
    endforeach; 
    ?>
    
    <?php if (!empty($details)): ?>
    <div class="details-container">
        <span class="details-label">تفاصيل:</span>
        <span class="details-text"><?= htmlspecialchars($details) ?></span>
    </div>
    <?php endif; ?>
    
    <div class="line"></div>
    <div class="center success">✔ عملية ناجحة</div>
    <div class="line"></div>
    
    <div class="amounts">
        <div class="row"><span class="label">القيمة</span><span><?= number_format($op['amount'], 0) ?> ج.م</span></div>
        <div class="row"><span class="label">الرسوم</span><span><?= number_format($op['fees'], 0) ?> ج.م</span></div>
        <div class="row total"><span>الإجمالي</span><span><?= number_format($op['total'], 0) ?> ج.م</span></div>
    </div>
    
    <div class="line"></div>
    <div class="ref-row">
        <span class="ref-label">مرجعي</span>
        <span class="ref-value"><?= htmlspecialchars($invoice_no) ?></span>
    </div>
    <div class="line"></div>
    
    <div class="center small">عند البطء في الشبكة قد يستغرق<br>التنفيذ حتى 24 ساعة</div>
    <div class="center small" style="margin-top:3px;">تم الدفع عبر <b>ELDoctor Pay</b><br>الدعم: 01063151472</div>
</div>

<div class="actions no-print">
    <!-- مجموعة أزرار الطباعة - أكثر وضوحاً -->
    <div class="print-buttons-container">
        <button class="btn print" onclick="window.print()">
            <span>🖨️</span> طباعة عادية
        </button>
        <button class="btn bluetooth" onclick="printBluetooth()">
            <span>📱</span> طباعة بلوتوث
        </button>
    </div>
    
    <!-- أزرار إضافية -->
    <button class="btn image" onclick="downloadReceiptImage()">
        <span>⬇️</span> حفظ كصورة
    </button>
    <button class="btn share" onclick="shareReceipt()">
        <span>📤</span> مشاركة
    </button>
    <a href="pending.php" class="btn">⏳ المعلقة</a>
    <a href="manual.php" class="btn">➕ عملية جديدة</a>
    <a href="/dashboard.php" class="btn">📊 الرئيسية</a>
</div>

<!-- عرض توضيحي لزر الطباعة العادية والبلوتوث كصورة -->
<div style="display: none;" id="print-buttons-visual">
    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='80' viewBox='0 0 200 80'%3E%3Crect width='200' height='80' fill='%23f0f0f0' rx='10'/%3E%3Ctext x='20' y='30' font-family='Arial' font-size='14' fill='%23333'%3E🖨️ طباعة عادية%3C/text%3E%3Ctext x='120' y='30' font-family='Arial' font-size='14' fill='%23333'%3E📱 طباعة بلوتوث%3C/text%3E%3Ctext x='20' y='60' font-family='Arial' font-size='12' fill='%23666'%3Eأكثر وضوحاً وسهولة%3C/text%3E%3C/svg%3E" alt="طباعة عادية وبلوتوث">
</div>

<script>
// دالة لاستخراج نص الإيصال للطباعة عبر البلوتوث
function extractReceiptText() {
    var text = "";
    var receipt = document.getElementById('receipt');
    if (!receipt) return null;

    // الحصول على الشعار (نص بديل)
    var logo = receipt.querySelector('.logo img');
    if (logo && logo.alt) {
        text += "      " + logo.alt + "\n";
    }
    
    // اسم الخدمة
    var serviceName = receipt.querySelector('.service-name')?.innerText || "إيصال دفع";
    text += "      " + serviceName + "\n";
    text += "--------------------------------\n";

    // الصفوف
    var rows = receipt.querySelectorAll('.row');
    rows.forEach(function(row) {
        var label = row.querySelector('.label')?.innerText || "";
        var value = row.querySelector('.value')?.innerText || row.querySelector('span:last-child')?.innerText || "";
        if (label && value) {
            text += label + ": " + value + "\n";
        }
    });

    // التفاصيل
    var details = receipt.querySelector('.details-text')?.innerText || "";
    if (details) text += "تفاصيل: " + details + "\n";

    text += "--------------------------------\n";
    
    // المبالغ
    var amountRow = Array.from(receipt.querySelectorAll('.row')).find(el => el.querySelector('.label')?.innerText === 'القيمة');
    var feesRow = Array.from(receipt.querySelectorAll('.row')).find(el => el.querySelector('.label')?.innerText === 'الرسوم');
    var totalRow = receipt.querySelector('.total');
    
    if (amountRow) {
        var amount = amountRow.querySelector('span:last-child')?.innerText || "";
        text += "القيمة: " + amount + "\n";
    }
    if (feesRow) {
        var fees = feesRow.querySelector('span:last-child')?.innerText || "";
        text += "الرسوم: " + fees + "\n";
    }
    if (totalRow) {
        text += totalRow.innerText + "\n";
    }
    
    // الرقم المرجعي
    var ref = receipt.querySelector('.ref-value')?.innerText || "";
    if (ref) text += "مرجعي: " + ref + "\n";

    text += "--------------------------------\n";
    text += "تم الدفع عبر ELDoctor Pay\n";
    text += "دعم فني: 01063151472\n";
    text += "--------------------------------\n";
    
    return text;
}

// دالة الطباعة عبر البلوتوث (تتواصل مع تطبيق Android)
function printBluetooth() {
    var receiptText = extractReceiptText();
    if (receiptText) {
        // التحقق من وجود واجهة Android
        if (window.Android && typeof Android.printRaw === 'function') {
            Android.printRaw(receiptText);
        } else {
            // إذا كان التطبيق لا يدعم الطباعة المباشرة، نعرض نافذة منبثقة
            showBluetoothInstructions(receiptText);
        }
    } else {
        alert("لا يمكن استخراج نص الإيصال");
    }
}

// دالة عرض تعليمات الطباعة في حالة عدم وجود واجهة Android
function showBluetoothInstructions(text) {
    var instructions = "لطباعة الإيصال عبر البلوتوث:\n\n";
    instructions += "1. تأكد من تشغيل البلوتوث على جهازك\n";
    instructions += "2. افتح تطبيق ELDoctor Pay على جهازك\n";
    instructions += "3. اختر طابعة بلوتوث من التطبيق\n\n";
    instructions += "نص الإيصال للطباعة:\n";
    instructions += "--------------------------------\n";
    instructions += text;
    
    alert(instructions);
    
    // نسخ النص إلى الحافظة
    navigator.clipboard.writeText(text).then(function() {
        alert("تم نسخ نص الإيصال، يمكنك لصقه في أي تطبيق");
    }).catch(function() {
        // إذا فشل النسخ التلقائي
    });
}

// دالة لحفظ الإيصال كصورة
function downloadReceiptImage() {
    const receipt = document.getElementById('receipt');
    const originalBtn = event.target.closest('button');
    const originalText = originalBtn.innerHTML;
    originalBtn.innerHTML = '<span>⏳</span> جاري التحضير...';
    originalBtn.disabled = true;

    html2canvas(receipt, {
        scale: 2,
        backgroundColor: '#ffffff',
        logging: false,
        allowTaint: false,
        useCORS: true
    }).then(canvas => {
        const link = document.createElement('a');
        link.download = `إيصال_${<?= json_encode($invoice_no) ?>}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        
        originalBtn.innerHTML = originalText;
        originalBtn.disabled = false;
    }).catch(error => {
        alert('حدث خطأ أثناء إنشاء الصورة: ' + error);
        originalBtn.innerHTML = originalText;
        originalBtn.disabled = false;
    });
}

// دالة لمشاركة الإيصال
function shareReceipt() {
    const receipt = document.getElementById('receipt');
    const shareBtn = event.target.closest('button');
    const originalText = shareBtn.innerHTML;
    shareBtn.innerHTML = '<span>⏳</span> جاري...';
    shareBtn.disabled = true;

    html2canvas(receipt, {
        scale: 2,
        backgroundColor: '#ffffff',
        logging: false,
        allowTaint: false,
        useCORS: true
    }).then(canvas => {
        canvas.toBlob(blob => {
            const file = new File([blob], `إيصال_${<?= json_encode($invoice_no) ?>}.png`, { type: 'image/png' });
            
            if (navigator.share) {
                navigator.share({
                    title: 'إيصال عملية',
                    text: `إيصال عملية ${<?= json_encode($op['service_name']) ?>}`,
                    files: [file]
                }).catch(error => {
                    if (error.name !== 'AbortError') {
                        fallbackShare();
                    }
                }).finally(() => {
                    shareBtn.innerHTML = originalText;
                    shareBtn.disabled = false;
                });
            } else {
                fallbackShare();
            }
        });
    }).catch(error => {
        alert('حدث خطأ: ' + error);
        shareBtn.innerHTML = originalText;
        shareBtn.disabled = false;
    });
}

// دالة احتياطية للمشاركة
function fallbackShare() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        alert('تم نسخ رابط الإيصال، يمكنك لصقه في أي تطبيق');
    }).catch(() => {
        alert('يمكنك حفظ الصورة أولاً ثم مشاركتها');
    });
}

// إضافة مستمع لأوامر الطباعة من تطبيق Android
window.addEventListener('message', function(event) {
    if (event.data === 'print_receipt') {
        var text = extractReceiptText();
        if (text && window.Android && typeof Android.printRaw === 'function') {
            Android.printRaw(text);
        }
    }
});

// دالة للتحقق من وجود واجهة Android
function checkAndroidInterface() {
    if (window.Android && typeof Android.printRaw === 'function') {
        console.log('Android interface is ready');
    }
}

// عند تحميل الصفحة
window.onload = function() {
    setTimeout(() => {
        checkAndroidInterface();
    }, 500);
};
</script>
</body>
</html>