<?php
session_start();
require_once __DIR__."/../config/db.php";

$id = (int)($_GET['id'] ?? 0);
if($id <= 0){ 
    $_SESSION['error']='رقم غير صالح'; 
    header('Location:list.php'); 
    exit; 
}

// جلب بيانات العميل
$stmt = $conn->prepare("SELECT * FROM clients WHERE id=? LIMIT 1"); 
$stmt->bind_param("i", $id); 
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$client){ 
    $_SESSION['error']='العميل غير موجود'; 
    header('Location:list.php'); 
    exit; 
}

// جلب البيانات الأساسية
$services = $conn->query("SELECT id,service_name FROM services WHERE status=1 ORDER BY service_name")->fetch_all(MYSQLI_ASSOC);
$governorates = ["القاهرة","الجيزة","الإسكندرية","الدقهلية","البحيرة","المنوفية","الشرقية","الغربية","القليوبية","كفر الشيخ","الفيوم","بني سويف","المنيا","أسيوط","سوهاج","قنا","أسوان","الأقصر","البحر الأحمر","الوادي الجديد","مطروح","شمال سيناء","جنوب سيناء","دمياط","بورسعيد","الإسماعيلية","السويس"];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])){
    // التحقق من التكرار قبل التحديث
    $new_national_id = trim($_POST['national_id'] ?? '');
    if($new_national_id !== $client['national_id']){
        $check = $conn->prepare("SELECT id FROM clients WHERE national_id=? AND id!=?");
        $check->bind_param("si", $new_national_id, $id);
        $check->execute();
        if($check->get_result()->num_rows > 0){
            $_SESSION['error'] = 'رقم قومي مُسجل مسبقاً لعميل آخر';
            header("Location: edit.php?id=".$id);
            exit;
        }
        $check->close();
    }
    
    // جمع البيانات
    $full_name = trim($_POST['full_name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $phone_main = trim($_POST['phone_main'] ?? '');
    $phone_extra = trim($_POST['phone_extra'] ?? '');
    $landline = trim($_POST['landline'] ?? '');
    $governorate = trim($_POST['governorate'] ?? '');
    $service_number = trim($_POST['service_number'] ?? '');
    $service_details = trim($_POST['service_details'] ?? '');
    $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : NULL;
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // معالجة الملفات
    $upload_dir = __DIR__."/../uploads/";
    if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $file_fields = ['id_front', 'id_back', 'service_image'];
    $file_values = [];
    
    foreach($file_fields as $field){
        $file_values[$field] = $client[$field];
        if(!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK){
            $file = $_FILES[$field];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg','jpeg','png','webp','gif']) && $file['size'] <= 5*1024*1024){
                $new_name = "client_{$id}_" . time() . "_$field.$ext";
                if(move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)){
                    if(!empty($client[$field]) && file_exists($upload_dir . $client[$field])){
                        unlink($upload_dir . $client[$field]);
                    }
                    $file_values[$field] = $new_name;
                }
            }
        }
    }
    
    // تحديث البيانات
    $stmt = $conn->prepare("UPDATE clients SET full_name=?, national_id=?, birthdate=?, phone_main=?, phone_extra=?, landline=?, governorate=?, service_number=?, service_details=?, service_id=?, address=?, notes=?, id_front=?, id_back=?, service_image=? WHERE id=?");
    
    $stmt->bind_param("sssssssssisssssi", 
        $full_name, $new_national_id, $birthdate, $phone_main, $phone_extra, 
        $landline, $governorate, $service_number, $service_details, $service_id,
        $address, $notes, $file_values['id_front'], $file_values['id_back'], 
        $file_values['service_image'], $id
    );
    
    if($stmt->execute()){
        $_SESSION['success'] = '✅ تم تحديث بيانات العميل بنجاح';
        header("Location: view.php?id=".$id);
        exit;
    } else {
        $_SESSION['error'] = "حدث خطأ: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>✏️ تعديل عميل #<?=$id?></title>
<style>
body{background:#f5f7fa;padding:20px;font-family:'Cairo',sans-serif;}
.container{max-width:1200px;margin:auto;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);overflow:hidden;}
.header{background:linear-gradient(135deg,#4361ee,#3a0ca3);color:#fff;padding:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.content{padding:25px;}
.alert{padding:15px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-bottom:25px;}
.form-group{margin-bottom:20px;}
.form-label{display:block;margin-bottom:8px;font-weight:600;color:#1f2937;}
.form-control{width:100%;padding:12px;border:2px solid #e5e7eb;border-radius:12px;font-size:1rem;transition:0.3s;}
.form-control:focus{outline:none;border-color:#4361ee;box-shadow:0 0 0 3px rgba(67,97,238,0.2);}
textarea.form-control{min-height:100px;resize:vertical;}
.file-preview{display:flex;gap:15px;margin-top:10px;flex-wrap:wrap;}
.preview-item{position:relative;border:2px solid #e5e7eb;border-radius:12px;overflow:hidden;width:150px;height:150px;}
.preview-item img{width:100%;height:100%;object-fit:cover;}
.submit-btn{background:linear-gradient(135deg,#4361ee,#3a0ca3);color:#fff;border:none;padding:15px 30px;border-radius:12px;font-size:1.1rem;cursor:pointer;width:100%;margin-top:20px;}
.btn{padding:10px 20px;border-radius:12px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px;}
.btn-primary{background:#fff;color:#4361ee;}
.btn-secondary{background:#10b981;color:#fff;}
@media(max-width:768px){.form-grid{grid-template-columns:1fr;}.header{flex-direction:column;}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✏️ تعديل العميل #<?=$id?></h1>
        <div>
            <a href="list.php" class="btn btn-primary">👥 القائمة</a>
            <a href="view.php?id=<?=$id?>" class="btn btn-secondary">👁️ عرض</a>
        </div>
    </div>
    <div class="content">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?=$_SESSION['success']?></div>
            <?php unset($_SESSION['success']); ?>
        <?php elseif(isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?=$_SESSION['error']?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="update" value="1">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الاسم الكامل *</label>
                    <input type="text" name="full_name" class="form-control" value="<?=htmlspecialchars($client['full_name'])?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الرقم القومي *</label>
                    <input type="text" name="national_id" class="form-control" value="<?=htmlspecialchars($client['national_id'])?>" maxlength="14" required>
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ الميلاد</label>
                    <input type="date" name="birthdate" class="form-control" value="<?=htmlspecialchars($client['birthdate'])?>">
                </div>
                <div class="form-group">
                    <label class="form-label">الهاتف الأساسي *</label>
                    <input type="tel" name="phone_main" class="form-control" value="<?=htmlspecialchars($client['phone_main'])?>" pattern="01[0-2,5]\d{8}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">هاتف إضافي</label>
                    <input type="tel" name="phone_extra" class="form-control" value="<?=htmlspecialchars($client['phone_extra'])?>" pattern="01[0-2,5]\d{8}">
                </div>
                <div class="form-group">
                    <label class="form-label">المحافظة *</label>
                    <select name="governorate" class="form-control" required>
                        <option value="">اختر المحافظة</option>
                        <?php foreach($governorates as $gov): ?>
                            <option value="<?=$gov?>" <?=$client['governorate']===$gov?'selected':''?>><?=$gov?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">رقم أرضي</label>
                    <input type="tel" name="landline" class="form-control" value="<?=htmlspecialchars($client['landline'])?>">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الخدمة</label>
                    <input type="text" name="service_number" class="form-control" value="<?=htmlspecialchars($client['service_number'])?>">
                </div>
                <div class="form-group">
                    <label class="form-label">نوع الخدمة</label>
                    <select name="service_id" class="form-control">
                        <option value="">اختر الخدمة</option>
                        <?php foreach($services as $service): ?>
                            <option value="<?=$service['id']?>" <?=$client['service_id']==$service['id']?'selected':''?>><?=htmlspecialchars($service['service_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">تفاصيل الخدمة</label>
                <textarea name="service_details" class="form-control"><?=htmlspecialchars($client['service_details'])?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">العنوان</label>
                <textarea name="address" class="form-control"><?=htmlspecialchars($client['address'])?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control"><?=htmlspecialchars($client['notes'])?></textarea>
            </div>
            
            <h3>📁 المرفقات</h3>
            <div class="form-grid">
                <?php foreach(['id_front'=>'البطاقة (الوجه)','id_back'=>'البطاقة (الظهر)','service_image'=>'صورة الخدمة'] as $field=>$label): ?>
                <div class="form-group">
                    <label class="form-label"><?=$label?></label>
                    <?php if(!empty($client[$field])): ?>
                        <div class="file-preview">
                            <div class="preview-item">
                                <img src="../uploads/<?=htmlspecialchars($client[$field])?>" alt="<?=$label?>">
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="<?=$field?>" class="form-control" accept="image/*">
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" class="submit-btn">💾 حفظ التعديلات</button>
        </form>
    </div>
</div>
</body>
</html>