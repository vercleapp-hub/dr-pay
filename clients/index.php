

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";
if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$theme = $_SESSION['theme'] ?? 'light';

function clean($data) {
    return trim(htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8'));
}

$client = null;
$edit_mode = false;
$client_id = null;

if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $edit_mode = true;
    $client_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$client) {
        header("Location: list.php");
        exit;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['toggle_theme'])) {
        $_SESSION['theme'] = $theme === 'light' ? 'dark' : 'light';
        echo json_encode(['status'=>'success','theme'=>$_SESSION['theme']]);
        exit;
    }
    
    header("Content-Type: application/json");
    if(!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        echo json_encode(["status"=>"error","msg"=>"طلب غير مصرح"]);
        exit;
    }
    
    if(isset($_POST['add_service'])) {
        $n = clean($_POST['service_name'] ?? '');
        if(mb_strlen($n) < 2) {
            echo json_encode(["status"=>"error","msg"=>"اسم الخدمة قصير"]);
            exit;
        }
        $s = $conn->prepare("INSERT INTO services(service_name,status)VALUES(?,1)");
        $s->bind_param("s", $n);
        echo $s->execute() ? 
            json_encode(["status"=>"success","id"=>$s->insert_id,"name"=>$n]) : 
            json_encode(["status"=>"error","msg"=>"فشل الإضافة"]);
        exit;
    }
    
    if(isset($_POST['save'])) {
        $d = [
            clean($_POST['full_name'] ?? ''),
            clean($_POST['national_id'] ?? ''),
            !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
            clean($_POST['phone_main'] ?? ''),
            clean($_POST['phone_extra'] ?? ''),
            clean($_POST['governorate'] ?? ''),
            clean($_POST['landline'] ?? ''),
            clean($_POST['service_number'] ?? ''),
            clean($_POST['service_details'] ?? ''),
            !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null,
            clean($_POST['address'] ?? ''),
            clean($_POST['notes'] ?? '')
        ];
        
        if(mb_strlen($d[0]) < 3) {
            echo json_encode(["status"=>"error","msg"=>"الاسم قصير"]);
            exit;
        }
        if(!preg_match('/^[0-9]{14}$/', $d[1])) {
            echo json_encode(["status"=>"error","msg"=>"الرقم القومي 14 رقم"]);
            exit;
        }
        if(!preg_match('/^01[0-2,5][0-9]{8}$/', $d[3])) {
            echo json_encode(["status"=>"error","msg"=>"رقم الهاتف غير صحيح"]);
            exit;
        }
        
        $check = $conn->prepare("SELECT id, full_name FROM clients WHERE national_id=? LIMIT 1");
        $check->bind_param("s", $d[1]);
        $check->execute();
        $check_result = $check->get_result();
        if($check_result->num_rows > 0) {
            $existing = $check_result->fetch_assoc();
            if(!$edit_mode || ($edit_mode && $existing['id'] != $client_id)) {
                echo json_encode([
                    "status" => "error", 
                    "msg" => "الرقم القومي مسجل للعميل: " . $existing['full_name'],
                    "type" => "national_id_duplicate"
                ]);
                exit;
            }
        }
        $check->close();
        
        $files = [
            'id_front' => $client['id_front'] ?? null,
            'id_back' => $client['id_back'] ?? null,
            'service_image' => $client['service_image'] ?? null
        ];
        
        foreach($files as $k => $v) {
            if(!empty($_FILES[$k]['name'])) {
                $f = $_FILES[$k];
                if($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3*1024*1024) continue;
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if(!in_array($ext,['jpg','jpeg','png','webp','gif'])) continue;
                $dir = __DIR__ . "/../uploads/";
                if(!is_dir($dir)) mkdir($dir,0755,true);
                $new = time() . '_' . uniqid() . '.' . $ext;
                if(move_uploaded_file($f['tmp_name'], $dir.$new)) {
                    if($v && file_exists($dir.$v)) unlink($dir.$v);
                    $files[$k] = $new;
                }
            } else {
                $files[$k] = $v;
            }
        }
        
        if($edit_mode) {
            $s = $conn->prepare("UPDATE clients SET full_name=?, national_id=?, birthdate=?, phone_main=?, phone_extra=?, governorate=?, landline=?, service_number=?, service_details=?, service_id=?, address=?, notes=?, id_front=?, id_back=?, service_image=? WHERE id=?");
            array_push($d, $files['id_front'], $files['id_back'], $files['service_image'], $client_id);
            $s->bind_param("sssssssssisssssi", ...$d);
            $m = "✅ تم التحديث";
        } else {
            $s = $conn->prepare("INSERT INTO clients(full_name,national_id,birthdate,phone_main,phone_extra,governorate,landline,service_number,service_details,service_id,address,notes,id_front,id_back,service_image)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            array_push($d, $files['id_front'], $files['id_back'], $files['service_image']);
            $s->bind_param("sssssssssisssss", ...$d);
            $m = "✅ تم الإضافة";
        }
        
        echo $s->execute() ? 
            json_encode(["status"=>"success","msg"=>$m,"redirect"=>!$edit_mode?"list.php":null]) : 
            json_encode(["status"=>"error","msg"=>"❌ خطأ في الحفظ"]);
        exit;
    }
}

$services = $conn->query("SELECT id, service_name FROM services WHERE status=1 ORDER BY service_name")->fetch_all(MYSQLI_ASSOC);
$govs = ["القاهرة","الجيزة","الإسكندرية","الدقهلية","البحيرة","المنوفية","الشرقية","الغربية","القليوبية","كفر الشيخ","الفيوم","بني سويف","المنيا","أسيوط","سوهاج","قنا","أسوان","الأقصر","البحر الأحمر","الوادي الجديد","مطروح","شمال سيناء","جنوب سيناء","دمياط","بورسعيد","الإسماعيلية","السويس"];
sort($govs);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="<?=$theme?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title><?=$e?'تعديل':'إضافة'?> عميل</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
:root{--p:#4361ee;--pd:#3a0ca3;--s:#10b981;--w:#f59e0b;--d:#ef4444;--l:#f8f9fa;--dk:#1f2937;--b:#e5e7eb;--r:12px;--sh:0 4px 20px rgba(0,0,0,0.08);}
[data-theme="dark"]{--p:#60a5fa;--pd:#3b82f6;--l:#111827;--dk:#f9fafb;--b:#374151;--sh:0 4px 20px rgba(0,0,0,0.3);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Tahoma,Arial;background:var(--l);color:var(--dk);min-height:100vh;padding:10px;}
.container{max-width:1400px;margin:0 auto;}

/* مودال */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.modal-content{background:var(--l);border-radius:var(--r);box-shadow:0 20px 40px rgba(0,0,0,0.3);width:100%;max-width:500px;animation:modalIn 0.3s;}
@keyframes modalIn{from{opacity:0;transform:scale(0.9);}to{opacity:1;transform:scale(1);}}
.modal-header{padding:20px;border-bottom:1px solid var(--b);display:flex;align-items:center;justify-content:space-between;}
.modal-body{padding:20px;max-height:70vh;overflow-y:auto;text-align:center;}
.modal-footer{padding:20px;border-top:1px solid var(--b);display:flex;gap:10px;justify-content:center;}
.modal-icon{font-size:60px;margin-bottom:20px;}
.modal-success{color:var(--s);}
.modal-error{color:var(--d);}
.modal-warning{color:var(--w);}

/* تنبيهات علوية */
.notification{position:fixed;top:20px;right:20px;z-index:1000;background:var(--l);border-radius:var(--r);padding:15px 20px;box-shadow:var(--sh);display:flex;align-items:center;gap:10px;animation:slideIn 0.3s;transform:translateX(100%);animation:slideIn 0.3s forwards;}
@keyframes slideIn{to{transform:translateX(0);}}
.notification.success{border-right:4px solid var(--s);background:rgba(16,185,129,0.1);}
.notification.error{border-right:4px solid var(--d);background:rgba(239,68,68,0.1);}
.notification.warning{border-right:4px solid var(--w);background:rgba(245,158,11,0.1);}

/* تصميم */
.navbar{background:linear-gradient(135deg,var(--p)0%,var(--pd)100%);color:#fff;padding:15px 25px;border-radius:var(--r);margin-bottom:25px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:15px;}
.nav-title{display:flex;align-items:center;gap:10px;font-size:clamp(18px,2vw,22px);font-weight:700;}
.nav-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:var(--r);border:none;font:inherit;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;white-space:nowrap;font-size:clamp(14px,1vw,16px);}
.btn-primary{background:linear-gradient(135deg,var(--p)0%,var(--pd)100%);color:#fff;}
.btn-secondary{background:var(--b);color:var(--dk);}
.btn:hover{transform:translateY(-2px);box-shadow:var(--sh);}
.theme-toggle{width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.1);border:2px solid rgba(255,255,255,0.2);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;}

.card{background:var(--l);border-radius:var(--r);box-shadow:var(--sh);padding:clamp(15px,2vw,25px);margin-bottom:20px;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,350px),1fr));gap:clamp(15px,2vw,20px);margin:20px 0;}
.form-group{margin-bottom:clamp(15px,2vw,20px);}
.form-label{display:block;margin-bottom:8px;font-weight:600;font-size:clamp(14px,1vw,16px);}
.form-control{width:100%;padding:12px 15px;border:2px solid var(--b);border-radius:var(--r);font:inherit;font-size:clamp(14px,1vw,16px);background:var(--l);color:var(--dk);}
.form-control:focus{outline:none;border-color:var(--p);}
.file-upload{position:relative;margin-top:8px;}
.file-input{position:absolute;width:100%;height:100%;opacity:0;cursor:pointer;}
.file-label{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:20px;border:2px dashed var(--b);border-radius:var(--r);cursor:pointer;}
.file-label:hover{border-color:var(--p);background:rgba(67,97,238,0.05);}
.image-preview{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:15px;margin-top:15px;}
.preview-item{background:var(--l);border-radius:var(--r);overflow:hidden;border:1px solid var(--b);}
.preview-image{width:100%;height:150px;object-fit:cover;display:block;}
.form-actions{display:flex;gap:clamp(15px,2vw,20px);justify-content:center;margin-top:clamp(25px,3vw,40px);flex-wrap:wrap;}
@media(max-width:768px){.navbar{flex-direction:column;text-align:center;}.nav-actions{justify-content:center;width:100%;}.form-actions{flex-direction:column;}.btn{width:100%;justify-content:center;}}
</style>
</head>
<body>
<div class="container">
    <nav class="navbar">
        <div class="nav-title">
            <span class="material-icons"><?=$edit_mode?'edit':'person_add'?></span>
            <?=$edit_mode?'تعديل عميل':'إضافة عميل جديد'?>
        </div>
        <div class="nav-actions">
            <a href="list.php" class="btn btn-primary">
                <span class="material-icons">groups</span>قائمة العملاء
            </a>
            <a href="../dashboard.php" class="btn btn-secondary">
                <span class="material-icons">dashboard</span>لوحة التحكم
            </a>
            <button class="theme-toggle" id="themeToggle">
                <span class="material-icons" id="themeIcon"><?=$theme==='light'?'dark_mode':'light_mode'?></span>
            </button>
        </div>
    </nav>
    
    <div class="card">
        <form id="clientForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
            <input type="hidden" name="save" value="1">
            <?php if($edit_mode):?><input type="hidden" name="id" value="<?=$client_id?>"><?php endif;?>
            
            <div class="form-grid">
                <div class="form-group"><label class="form-label">الاسم الكامل *</label><input type="text" name="full_name" class="form-control" value="<?=$edit_mode?htmlspecialchars($client['full_name']):''?>" required autofocus></div>
                <div class="form-group"><label class="form-label">الرقم القومي *</label><input type="text" name="national_id" class="form-control" maxlength="14" value="<?=$edit_mode?htmlspecialchars($client['national_id']):''?>" required></div>
                <div class="form-group"><label class="form-label">تاريخ الميلاد</label><input type="text" name="birthdate" class="form-control" readonly value="<?=$edit_mode?htmlspecialchars($client['birthdate']):''?>"></div>
                <div class="form-group"><label class="form-label">الهاتف الرئيسي *</label><input type="tel" name="phone_main" class="form-control" value="<?=$edit_mode?htmlspecialchars($client['phone_main']):''?>" required></div>
                <div class="form-group"><label class="form-label">هاتف إضافي</label><input type="tel" name="phone_extra" class="form-control" value="<?=$edit_mode?htmlspecialchars($client['phone_extra']):''?>"></div>
                <div class="form-group"><label class="form-label">المحافظة</label><select name="governorate" class="form-control"><option value="">اختر المحافظة</option><?php foreach($govs as $g):?><option value="<?=$g?>"<?=$edit_mode&&$client['governorate']==$g?' selected':''?>><?=$g?></option><?php endforeach;?></select></div>
                <div class="form-group"><label class="form-label">رقم الخدمة</label><input type="text" name="service_number" class="form-control" value="<?=$edit_mode?htmlspecialchars($client['service_number']):''?>"></div>
                <div class="form-group"><label class="form-label">نوع الخدمة</label><div style="display:flex;gap:10px;"><select name="service_id" class="form-control" style="flex:1;"><option value="">اختر الخدمة</option><?php foreach($services as $s):?><option value="<?=$s['id']?>"<?=$edit_mode&&$client['service_id']==$s['id']?' selected':''?>><?=htmlspecialchars($s['service_name'])?></option><?php endforeach;?></select><button type="button" id="addServiceBtn" class="btn btn-primary">+</button></div></div>
                <div class="form-group"><label class="form-label">تفاصيل الخدمة</label><textarea name="service_details" class="form-control" style="min-height:100px;"><?=$edit_mode?htmlspecialchars($client['service_details']):''?></textarea></div>
                <div class="form-group"><label class="form-label">العنوان التفصيلي</label><textarea name="address" class="form-control" style="min-height:100px;"><?=$edit_mode?htmlspecialchars($client['address']):''?></textarea></div>
                <div class="form-group"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" style="min-height:100px;"><?=$edit_mode?htmlspecialchars($client['notes']):''?></textarea></div>
            </div>
            
            <div class="form-grid">
                <?php $file_fields=['id_front'=>'البطاقة (الوجه)','id_back'=>'البطاقة (الظهر)','service_image'=>'صورة الخدمة'];foreach($file_fields as $f=>$l):?>
                <div class="form-group">
                    <label class="form-label"><?=$l?></label>
                    <div class="file-upload"><input type="file" name="<?=$f?>" id="<?=$f?>" class="file-input" accept="image/*"><label for="<?=$f?>" class="file-label"><span class="material-icons">cloud_upload</span><span>رفع ملف</span><span style="font-size:14px;">3MB كحد أقصى</span></label></div>
                    <?php if($edit_mode&&$client[$f]):?><div class="image-preview"><div class="preview-item"><img src="../uploads/<?=htmlspecialchars($client[$f])?>" class="preview-image"><div style="padding:12px;text-align:center;"><a href="../uploads/<?=htmlspecialchars($client[$f])?>" target="_blank" class="btn btn-primary" style="padding:5px 10px;font-size:14px;">عرض</a></div></div></div><?php endif;?>
                </div>
                <?php endforeach;?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><span class="material-icons">save</span><?=$edit_mode?'تحديث':'حفظ'?></button>
                <a href="<?=$edit_mode?'view.php?id='.$client_id:'list.php'?>" class="btn btn-secondary"><span class="material-icons"><?=$edit_mode?'visibility':'arrow_back'?></span><?=$edit_mode?'عرض':'رجوع'?></a>
            </div>
        </form>
    </div>
</div>

<!-- المودال -->
<div class="modal" id="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"></h3>
            <button onclick="closeModal()" class="btn btn-secondary" style="padding:5px 10px;">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-icon" id="modalIcon"></div>
            <p id="modalMessage" style="font-size:18px;margin-bottom:20px;"></p>
            <div id="modalDetails" style="background:var(--b);padding:15px;border-radius:var(--r);margin:15px 0;"></div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn btn-primary">موافق</button>
        </div>
    </div>
</div>

<script>
const govMap={'01':'القاهرة','02':'الإسكندرية','03':'بورسعيد','04':'السويس','11':'دمياط','12':'الدقهلية','13':'الشرقية','14':'القليوبية','15':'كفر الشيخ','16':'الغربية','17':'المنوفية','18':'البحيرة','19':'الإسماعيلية','21':'الجيزة','22':'بني سويف','23':'الفيوم','24':'المنيا','25':'أسيوط','26':'سوهاج','27':'قنا','28':'أسوان','29':'الأقصر','31':'البحر الأحمر','32':'الوادي الجديد','33':'مطروح','34':'شمال سيناء','35':'جنوب سيناء'};

function showNotification(msg,type='success',duration=5000){
    const n=document.createElement('div');
    n.className=`notification ${type}`;
    n.innerHTML=`<span class="material-icons">${type==='success'?'check_circle':type==='error'?'error':'warning'}</span><span>${msg}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;"><span class="material-icons">close</span></button>`;
    document.body.appendChild(n);
    setTimeout(()=>{if(n.parentElement)n.remove();},duration);
}

function showModal(title,message,type='error',details='',duration=0){
    const m=document.getElementById('modal');
    document.getElementById('modalTitle').textContent=title;
    document.getElementById('modalMessage').textContent=message;
    const icon=document.getElementById('modalIcon');
    icon.className=`modal-icon ${type}`;
    icon.innerHTML=type==='success'?'✅':type==='warning'?'⚠️':'❌';
    const d=document.getElementById('modalDetails');
    d.innerHTML=details?`<strong>تفاصيل:</strong><br>${details}`:'';
    m.style.display='flex';
    document.body.style.overflow='hidden';
    if(duration>0){
        setTimeout(closeModal,duration);
    }
}

function closeModal(){
    document.getElementById('modal').style.display='none';
    document.body.style.overflow='auto';
}

// المودال خارج المحتوى
document.getElementById('modal').addEventListener('click',function(e){
    if(e.target===this)closeModal();
});

// تبديل السمة
document.getElementById('themeToggle').onclick=function(){
    const t=document.documentElement.getAttribute('data-theme'),n=t==='light'?'dark':'light';
    document.documentElement.setAttribute('data-theme',n);
    document.getElementById('themeIcon').textContent=n==='light'?'dark_mode':'light_mode';
    fetch('index.php',{method:'POST',body:'toggle_theme=1&csrf='+encodeURIComponent('<?=$_SESSION['csrf']?>')});
};

// معالجة الرقم القومي
document.querySelector('[name="national_id"]').addEventListener('input',function(e){
    const v=e.target.value.replace(/\D/g,'');
    if(v.length===14){
        const c=v[0]==='2'?'19':'20';
        document.querySelector('[name="birthdate"]').value=c+v.substr(1,2)+'-'+v.substr(3,2)+'-'+v.substr(5,2);
        const g=govMap[v.substr(7,2)];
        if(g)document.querySelector('[name="governorate"]').value=g;
    }
});

// إضافة خدمة
document.getElementById('addServiceBtn').onclick=function(){
    const n=prompt('اسم الخدمة الجديدة:','');
    if(!n||n.trim().length<2){
        showModal('تنبيه','اسم الخدمة يجب أن يكون أكثر من حرفين','warning','',3000);
        return;
    }
    const f=new FormData();
    f.append('add_service','1');f.append('service_name',n.trim());f.append('csrf','<?=$_SESSION['csrf']?>');
    fetch('index.php',{method:'POST',body:f}).then(r=>r.json()).then(d=>{
        if(d.status==='success'){
            const s=document.querySelector('[name="service_id"]');
            const o=document.createElement('option');o.value=d.id;o.textContent=d.name;
            s.appendChild(o);s.value=d.id;
            showNotification('تمت إضافة الخدمة');
        }else{
            showModal('خطأ',d.msg||'حدث خطأ','error','',3000);
        }
    });
};

// إرسال النموذج
document.getElementById('clientForm').onsubmit=function(e){
    e.preventDefault();
    const b=this.querySelector('button[type="submit"]');
    const ot=b.innerHTML;
    b.innerHTML='<span class="material-icons">hourglass_empty</span> جاري...';
    b.disabled=true;
    
    const f=new FormData(this);
    fetch('index.php<?=$edit_mode?'?id='.$client_id:''?>',{method:'POST',body:f})
    .then(r=>r.json())
    .then(d=>{
        if(d.status==='success'){
            showNotification(d.msg,'success');
            if(d.redirect){
                setTimeout(()=>window.location.href=d.redirect,2000);
            }else if(!<?=$edit_mode?'true':'false'?>){
                setTimeout(()=>{
                    this.reset();
                    document.querySelector('[name="birthdate"]').value='';
                    document.querySelector('[name="governorate"]').value='';
                    showNotification('تم إعادة تعيين النموذج','success');
                },1500);
            }
        }else{
            if(d.type==='national_id_duplicate'){
                showModal('⚠️ رقم قومي مكرر',d.msg,'warning','يرجى التحقق من الرقم المدخل',5000);
            }else if(d.msg.includes('رقم الهاتف')){
                showModal('⚠️ رقم هاتف غير صحيح',d.msg,'warning','تأكد من صحة رقم الهاتف',5000);
            }else if(d.msg.includes('الرقم القومي')){
                showModal('⚠️ رقم قومي غير صحيح',d.msg,'warning','يجب أن يكون 14 رقماً',5000);
            }else{
                showModal('❌ خطأ',d.msg,'error','',4000);
            }
        }
    })
    .catch(err=>{
        showModal('❌ خطأ في الاتصال','حدث خطأ في الاتصال بالخادم','error','حاول مرة أخرى',4000);
    })
    .finally(()=>{
        b.innerHTML=ot;
        b.disabled=false;
    });
};

// معاينة الملفات
document.querySelectorAll('.file-input').forEach(i=>{
    i.addEventListener('change',function(e){
        const f=this.files[0];
        if(!f||f.size>3*1024*1024)return;
        const r=new FileReader();
        r.onload=function(e){
            const p=this.parentElement.parentElement.querySelector('.image-preview')||
            (()=>{const d=document.createElement('div');d.className='image-preview';
                this.parentElement.parentElement.appendChild(d);return d;})();
            p.innerHTML=`<div class="preview-item"><img src="${e.target.result}" class="preview-image">
                <div style="padding:12px;text-align:center;"><button onclick="this.closest('.preview-item').remove()" class="btn btn-primary" style="padding:5px 10px;font-size:14px;">إزالة</button></div></div>`;
        };
        r.readAsDataURL(f);
    });
});

// إغلاق بالـESC
document.addEventListener('keydown',function(e){
    if(e.key==='Escape')closeModal();
});
</script>
</body>
</html>