<?php
session_start();
if(!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
require_once __DIR__."/../config/db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0){ $_SESSION['error'] = 'رقم غير صالح'; header('Location: list.php'); exit; }

$sql = "SELECT c.*, s.service_name FROM clients c 
        LEFT JOIN services s ON c.service_id = s.id 
        WHERE c.id = ? LIMIT 1";
$stmt = $conn->prepare($sql); 
$stmt->bind_param("i", $id); 
$stmt->execute();
$res = $stmt->get_result(); 

if($res->num_rows === 0){ 
    $_SESSION['error'] = 'العميل غير موجود'; 
    header('Location: list.php'); 
    exit; 
}

$client = $res->fetch_assoc(); 
$stmt->close();

// معالجة حذف العميل
if(isset($_POST['delete']) && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'])){
    // حذف الملفات المرفوعة أولاً
    $upload_dir = __DIR__."/../uploads/";
    $files = ['id_front', 'id_back', 'service_image'];
    foreach($files as $file){
        if(!empty($client[$file]) && file_exists($upload_dir . $client[$file])){
            unlink($upload_dir . $client[$file]);
        }
    }
    
    $delete_stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $delete_stmt->bind_param("i", $id);
    if($delete_stmt->execute()){
        $_SESSION['success'] = '✅ تم حذف العميل بنجاح';
        header('Location: list.php');
        exit;
    }
    $delete_stmt->close();
}

// معالجة الطباعة
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" <?php if($print_mode) echo 'class="print-mode"'; ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>👁️ عرض العميل #<?= $id ?></title>
<style>
:root{
    --primary: #4361ee;
    --secondary: #3a0ca3;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --light: #f8f9fa;
    --dark: #1f2937;
    --border: #e5e7eb;
    --radius: 12px;
    --shadow: 0 4px 20px rgba(0,0,0,0.08);
}

*{ box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
body{ background: #f5f7fa; padding: 20px; min-height: 100vh; }
.container{ max-width: 1200px; margin: auto; }

.header{ 
    background: linear-gradient(135deg, var(--primary), var(--secondary)); 
    color: white; padding: 20px; border-radius: var(--radius); 
    margin-bottom: 20px; box-shadow: var(--shadow);
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
}

.header h1{ font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
.actions{ display: flex; gap: 10px; flex-wrap: wrap; }
.btn{ padding: 10px 20px; border-radius: var(--radius); text-decoration: none; 
       font-weight: 600; display: inline-flex; align-items: center; gap: 8px; 
       transition: all 0.3s; border: 2px solid transparent; cursor: pointer; }
.btn-primary{ background: white; color: var(--primary); border-color: var(--primary); }
.btn-secondary{ background: var(--success); color: white; }
.btn-danger{ background: var(--danger); color: white; }
.btn:hover{ transform: translateY(-2px); box-shadow: var(--shadow); }

.card{ background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 25px; margin-bottom: 20px; }
.card-title{ color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border); 
             display: flex; align-items: center; gap: 10px; font-size: 1.2rem; }

.info-grid{ display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px; }
.info-item{ margin-bottom: 15px; }
.info-label{ font-weight: 600; color: var(--dark); margin-bottom: 5px; font-size: 0.9rem; }
.info-value{ background: var(--light); padding: 12px; border-radius: var(--radius); border: 1px solid var(--border); 
             color: var(--dark); min-height: 44px; display: flex; align-items: center; }

.files-grid{ display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
.file-item{ background: var(--light); border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border); }
.file-img{ width: 100%; height: 180px; object-fit: cover; border-bottom: 1px solid var(--border); }
.file-label{ padding: 12px; text-align: center; font-weight: 600; color: var(--primary); }

.modal{ display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-content{ background: white; border-radius: var(--radius); padding: 30px; max-width: 500px; width: 90%; }
.modal-actions{ display: flex; gap: 10px; margin-top: 20px; justify-content: center; }

@media print{
    .no-print{ display: none !important; }
    body{ background: white; padding: 0; }
    .card{ box-shadow: none; border: 1px solid #ddd; }
    .btn{ display: none !important; }
}

@media (max-width: 768px){
    .header{ flex-direction: column; text-align: center; }
    .actions{ justify-content: center; }
    .info-grid{ grid-template-columns: 1fr; }
    .files-grid{ grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="container">
    <div class="header no-print">
        <h1>👁️ بيانات العميل #<?= $id ?></h1>
        <div class="actions">
            <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">✏️ تعديل</a>
            <a href="list.php" class="btn btn-primary">👥 القائمة</a>
            <button onclick="window.print()" class="btn btn-secondary no-print">🖨️ طباعة</button>
            <button onclick="showDeleteModal()" class="btn btn-danger no-print">🗑️ حذف</button>
        </div>
    </div>
    
    <div class="card">
        <h2 class="card-title">📋 المعلومات الأساسية</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">الاسم الكامل</div>
                <div class="info-value"><?= htmlspecialchars($client['full_name'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">الرقم القومي</div>
                <div class="info-value"><?= htmlspecialchars($client['national_id'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">تاريخ الميلاد</div>
                <div class="info-value"><?= $client['birthdate'] ? date('Y-m-d', strtotime($client['birthdate'])) : 'غير محدد' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">الهاتف الأساسي</div>
                <div class="info-value"><?= htmlspecialchars($client['phone_main'] ?? 'غير محدد') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">الهاتف الإضافي</div>
                <div class="info-value"><?= htmlspecialchars($client['phone_extra'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">المحافظة</div>
                <div class="info-value"><?= htmlspecialchars($client['governorate'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">رقم أرضي</div>
                <div class="info-value"><?= htmlspecialchars($client['landline'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">تاريخ التسجيل</div>
                <div class="info-value"><?= date('Y-m-d H:i', strtotime($client['created_at'] ?? '')) ?></div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2 class="card-title">🛠️ معلومات الخدمة</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">نوع الخدمة</div>
                <div class="info-value"><?= htmlspecialchars($client['service_name'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">رقم الخدمة</div>
                <div class="info-value"><?= htmlspecialchars($client['service_number'] ?? 'غير محدد') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">تفاصيل الخدمة</div>
                <div class="info-value" style="min-height: 80px;"><?= nl2br(htmlspecialchars($client['service_details'] ?? 'لا توجد تفاصيل')) ?></div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2 class="card-title">📍 معلومات إضافية</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">العنوان التفصيلي</div>
                <div class="info-value" style="min-height: 80px;"><?= nl2br(htmlspecialchars($client['address'] ?? 'لا يوجد عنوان')) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">ملاحظات</div>
                <div class="info-value" style="min-height: 80px;"><?= nl2br(htmlspecialchars($client['notes'] ?? 'لا توجد ملاحظات')) ?></div>
            </div>
        </div>
    </div>
    
    <?php if(!empty($client['id_front']) || !empty($client['id_back']) || !empty($client['service_image'])): ?>
    <div class="card">
        <h2 class="card-title">📁 المرفقات</h2>
        <div class="files-grid">
            <?php 
            $files = [
                'id_front' => 'البطاقة الشخصية (الوجه)',
                'id_back' => 'البطاقة الشخصية (الظهر)',
                'service_image' => 'صورة الخدمة / الإيصال'
            ];
            
            foreach($files as $field => $label): 
                if(!empty($client[$field])):
            ?>
                <div class="file-item">
                    <?php if(in_array(pathinfo($client[$field], PATHINFO_EXTENSION), ['jpg','jpeg','png','gif','webp'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($client[$field]) ?>" 
                             alt="<?= $label ?>" 
                             class="file-img" 
                             onclick="openImage('../uploads/<?= htmlspecialchars($client[$field]) ?>')">
                    <?php else: ?>
                        <div style="padding: 30px; text-align: center; background: #f0f0f0;">
                            📄 ملف PDF
                        </div>
                    <?php endif; ?>
                    <div class="file-label"><?= $label ?></div>
                    <div style="padding: 10px; text-align: center;">
                        <a href="../uploads/<?= htmlspecialchars($client[$field]) ?>" 
                           target="_blank" 
                           class="btn btn-primary" 
                           style="padding: 5px 15px; font-size: 14px;">
                            📥 تحميل
                        </a>
                    </div>
                </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal حذف -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h2 style="color: var(--danger); margin-bottom: 20px; text-align: center;">⚠️ تأكيد الحذف</h2>
        <p style="text-align: center; margin-bottom: 20px; color: var(--dark);">
            هل أنت متأكد من حذف العميل <strong><?= htmlspecialchars($client['full_name']) ?></strong>؟<br>
            <small style="color: var(--danger);">هذا الإجراء لا يمكن التراجع عنه</small>
        </p>
        <form method="post" style="display: none;" id="deleteForm">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="delete" value="1">
        </form>
        <div class="modal-actions">
            <button onclick="confirmDelete()" class="btn btn-danger">نعم، احذف</button>
            <button onclick="hideDeleteModal()" class="btn btn-primary">إلغاء</button>
        </div>
    </div>
</div>

<script>
function showDeleteModal() {
    document.getElementById('deleteModal').style.display = 'flex';
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function confirmDelete() {
    document.getElementById('deleteForm').submit();
}

function openImage(src) {
    window.open(src, '_blank', 'width=800,height=600,scrollbars=yes');
}

// إغلاق المودال عند الضغط على ESC
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') hideDeleteModal();
});

// إغلاق المودال عند النقر خارج المحتوى
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if(e.target === this) hideDeleteModal();
});

// عرض رسائل الجلسة
<?php if(isset($_SESSION['success'])): ?>
    alert('<?= $_SESSION['success'] ?>');
    <?php unset($_SESSION['success']); ?>
<?php elseif(isset($_SESSION['error'])): ?>
    alert('<?= $_SESSION['error'] ?>');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>
</body>
</html>