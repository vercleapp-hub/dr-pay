<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

/* ===============================
   CSRF
================================ */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ===============================
   إعدادات الفرز
================================ */
$allowed_sort = ['id','full_name','national_id','phone_main','governorate','created_at'];
$sort  = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';

$sort  = in_array($sort,$allowed_sort) ? $sort : 'id';
$order = $order === 'ASC' ? 'ASC' : 'DESC';

/* ===============================
   Pagination
================================ */
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
$per_page = in_array($per_page,[10,15,25,50,100]) ? $per_page : 15;

$page   = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$offset = ($page-1)*$per_page;

/* ===============================
   البحث المتقدم
================================ */
$filters = [
    'full_name'   => $_GET['full_name'] ?? '',
    'national_id' => $_GET['national_id'] ?? '',
    'phone'       => $_GET['phone'] ?? '',
    'governorate' => $_GET['governorate'] ?? '',
    'from'        => $_GET['from'] ?? '',
    'to'          => $_GET['to'] ?? ''
];

$whereParts=[];
$params=[];
$types='';

if($filters['full_name']!==''){
    $whereParts[]="full_name LIKE ?";
    $params[]="%{$filters['full_name']}%";
    $types.="s";
}
if($filters['national_id']!==''){
    $whereParts[]="national_id LIKE ?";
    $params[]="%{$filters['national_id']}%";
    $types.="s";
}
if($filters['phone']!==''){
    $whereParts[]="phone_main LIKE ?";
    $params[]="%{$filters['phone']}%";
    $types.="s";
}
if($filters['governorate']!==''){
    $whereParts[]="governorate=?";
    $params[]=$filters['governorate'];
    $types.="s";
}
if($filters['from']!==''){
    $whereParts[]="DATE(created_at)>=?";
    $params[]=$filters['from'];
    $types.="s";
}
if($filters['to']!==''){
    $whereParts[]="DATE(created_at)<=?";
    $params[]=$filters['to'];
    $types.="s";
}

$where = $whereParts ? "WHERE ".implode(" AND ",$whereParts) : '';

/* ===============================
   عدد النتائج
================================ */
$count_sql="SELECT COUNT(*) FROM clients $where";
$stmt=$conn->prepare($count_sql);
if($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$total_pages=max(1,ceil($total/$per_page));

/* ===============================
   جلب البيانات
================================ */
$data_sql="SELECT * FROM clients $where ORDER BY $sort $order LIMIT ?,?";
$stmt=$conn->prepare($data_sql);

if($params){
    $stmt->bind_param($types.'ii',...array_merge($params,[$offset,$per_page]));
}else{
    $stmt->bind_param('ii',$offset,$per_page);
}

$stmt->execute();
$result=$stmt->get_result();

/* المحافظات */
$govs=$conn->query("SELECT DISTINCT governorate FROM clients ORDER BY governorate ASC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة العملاء</title>
<style>
body{font-family:Cairo;background:#f4f6f9;padding:20px}
.container{max-width:1700px;margin:auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.btn{padding:6px 10px;background:#4361ee;color:#fff;text-decoration:none;border-radius:4px;font-size:13px}
.btn-secondary{background:#6c757d}
.btn-danger{background:#dc3545}
.filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:20px}
input,select{padding:7px;border:1px solid #ccc;border-radius:5px}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border:1px solid #eee;text-align:right}
th{background:#4361ee;color:#fff}
th a{color:#fff;text-decoration:none}
.pagination{text-align:center;margin-top:15px}
.page-btn{padding:6px 10px;border:1px solid #ccc;margin:2px;text-decoration:none}
.page-btn.active{background:#4361ee;color:#fff}
</style>
</head>

<body>
<div class="container">

<div class="header">
    <h2>إدارة العملاء</h2>
    <div>
        <a href="../dashboard.php" class="btn btn-secondary">⬅ الرجوع للرئيسية</a>
        <a href="index.php" class="btn">➕ إضافة عميل</a>
    </div>
</div>

<form method="get" class="filters">

<input name="full_name" placeholder="الاسم" value="<?=htmlspecialchars($filters['full_name'])?>">
<input name="national_id" placeholder="الرقم القومي" value="<?=htmlspecialchars($filters['national_id'])?>">
<input name="phone" placeholder="الهاتف" value="<?=htmlspecialchars($filters['phone'])?>">

<select name="governorate">
<option value="">كل المحافظات</option>
<?php while($g=$govs->fetch_assoc()): ?>
<option value="<?=$g['governorate']?>" <?=$filters['governorate']==$g['governorate']?'selected':''?>>
<?=$g['governorate']?>
</option>
<?php endwhile;?>
</select>

<input type="date" name="from" value="<?=$filters['from']?>">
<input type="date" name="to" value="<?=$filters['to']?>">

<select name="per_page">
<?php foreach([10,15,25,50,100] as $num): ?>
<option value="<?=$num?>" <?=$per_page==$num?'selected':''?>><?=$num?>/صفحة</option>
<?php endforeach;?>
</select>

<button class="btn">بحث</button>

<a class="btn" style="background:#28a745"
href="export_excel.php?<?=http_build_query($_GET)?>">
⬇ Export Excel
</a>

</form>

<table>
<thead>
<tr>
<?php
function sortLink($column,$label,$sort,$order){
    $newOrder = ($sort==$column && $order=='ASC')?'DESC':'ASC';
    $query=$_GET;
    $query['sort']=$column;
    $query['order']=$newOrder;
    return '<a href="?'.http_build_query($query).'">'.$label.'</a>';
}
?>
<th><?=sortLink('id','#',$sort,$order)?></th>
<th><?=sortLink('full_name','الاسم',$sort,$order)?></th>
<th><?=sortLink('national_id','الرقم القومي',$sort,$order)?></th>
<th><?=sortLink('phone_main','الهاتف',$sort,$order)?></th>
<th><?=sortLink('governorate','المحافظة',$sort,$order)?></th>
<th><?=sortLink('created_at','التاريخ',$sort,$order)?></th>
<th>الإجراءات</th>
</tr>
</thead>

<tbody>
<?php if($result->num_rows>0): ?>
<?php while($row=$result->fetch_assoc()): ?>
<tr>
<td><?=$row['id']?></td>
<td><?=htmlspecialchars($row['full_name'])?></td>
<td><?=htmlspecialchars($row['national_id'])?></td>
<td><?=htmlspecialchars($row['phone_main'])?></td>
<td><?=htmlspecialchars($row['governorate'])?></td>
<td><?=date('Y-m-d',strtotime($row['created_at']))?></td>
<td>
<a href="view.php?id=<?=$row['id']?>" class="btn">عرض</a>
<a href="edit.php?id=<?=$row['id']?>" class="btn">تعديل</a>
<button onclick="confirmDelete(<?=$row['id']?>)" class="btn btn-danger">حذف</button>
</td>
</tr>
<?php endwhile;?>
<?php else: ?>
<tr><td colspan="7" style="text-align:center">لا توجد نتائج</td></tr>
<?php endif;?>
</tbody>
</table>

<div class="pagination">
<?php for($i=1;$i<=$total_pages;$i++): ?>
<a class="page-btn <?=$i==$page?'active':''?>"
href="?<?=http_build_query(array_merge($_GET,['page'=>$i]))?>">
<?=$i?>
</a>
<?php endfor;?>
</div>

</div>

<script>
function confirmDelete(id){
    if(!confirm("هل أنت متأكد من الحذف؟")) return;
    fetch('delete.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+id+'&csrf=<?=$_SESSION['csrf']?>'
    })
    .then(res=>res.json())
    .then(data=>{
        if(data.status==='success') location.reload();
        else alert(data.msg);
    });
}
</script>

</body>
</html>
