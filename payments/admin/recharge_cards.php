<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "header.php"; // admin header (auth + admin)

if (!isset($conn)) {
    die("❌ DB Connection not found. تأكد إن الاتصال موجود في config/auth.php");
}

/* ================= Helpers ================= */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k, $d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

function tableExists($conn, $table){
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return ($res && $res->num_rows > 0);
}

function columnExists($conn, $table, $col){
    $table = $conn->real_escape_string($table);
    $col   = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($res && $res->num_rows > 0);
}

/* ================= Create Tables (Auto) ================= */
if (!tableExists($conn, 'recharge_companies')) {
    $conn->query("
        CREATE TABLE recharge_companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(100) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

if (!tableExists($conn, 'recharge_packages')) {
    $conn->query("
        CREATE TABLE recharge_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            package_name VARCHAR(100) NOT NULL,
            face_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

if (!tableExists($conn, 'recharge_cards')) {
    $conn->query("
        CREATE TABLE recharge_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            package_id INT NOT NULL,
            card_code VARCHAR(255) NOT NULL,
            status ENUM('available','sold') NOT NULL DEFAULT 'available',
            sold_transaction_id INT NULL,
            sold_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_card_code (card_code),
            INDEX(company_id),
            INDEX(package_id),
            INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/* ================= Fix Old Column Names (Compatibility) ================= */
/*
  لو الجدول اتعمل قبل كده وكان العمود اسمه "name" بدل "company_name"
  هنظبطه تلقائيًا.
*/
if (tableExists($conn, 'recharge_companies')) {
    $hasName = columnExists($conn, 'recharge_companies', 'name');
    $hasCompanyName = columnExists($conn, 'recharge_companies', 'company_name');

    if ($hasName && !$hasCompanyName) {
        $conn->query("ALTER TABLE recharge_companies CHANGE `name` `company_name` VARCHAR(100) NOT NULL");
    }
}

/* ================= Detect Company Column ================= */
$companyCol = 'company_name';
if (columnExists($conn, 'recharge_companies', 'name')) {
    $companyCol = 'name';
}
if (columnExists($conn, 'recharge_companies', 'company_name')) {
    $companyCol = 'company_name';
}

/* ================= Messages ================= */
$msg = '';
$ok  = '';

/* ================= Actions ================= */

// 1) Add Company
if (isset($_POST['add_company'])) {
    $name = post('company_name');
    if ($name === '') {
        $msg = "❌ اكتب اسم الشركة";
    } else {
        $stmt = $conn->prepare("INSERT INTO recharge_companies ($companyCol, active) VALUES (?, 1)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $ok = "✅ تم إضافة الشركة بنجاح";
    }
}

// 2) Add Package
if (isset($_POST['add_package'])) {
    $company_id   = (int)post('company_id');
    $package_name = post('package_name');
    $face_value   = (float)post('face_value');

    if ($company_id <= 0 || $package_name === '') {
        $msg = "❌ اختر الشركة واكتب اسم الفئة";
    } else {
        $stmt = $conn->prepare("INSERT INTO recharge_packages (company_id, package_name, face_value, active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("isd", $company_id, $package_name, $face_value);
        $stmt->execute();
        $ok = "✅ تم إضافة الفئة بنجاح";
    }
}

// 3) Add Cards Bulk
if (isset($_POST['add_cards'])) {
    $company_id = (int)post('cards_company_id');
    $package_id = (int)post('cards_package_id');
    $cards_text = post('cards_text');

    if ($company_id <= 0 || $package_id <= 0 || $cards_text === '') {
        $msg = "❌ اختر الشركة والفئة + اكتب الكروت";
    } else {
        $lines = preg_split("/\r\n|\n|\r/", $cards_text);
        $inserted = 0;
        $skipped  = 0;

        $stmt = $conn->prepare("INSERT INTO recharge_cards (company_id, package_id, card_code, status) VALUES (?, ?, ?, 'available')");

        foreach ($lines as $line) {
            $code = trim($line);
            if ($code === '') continue;

            $code = preg_replace('/\s+/', ' ', $code);

            $stmt->bind_param("iis", $company_id, $package_id, $code);
            try {
                $stmt->execute();
                $inserted++;
            } catch (Throwable $ex) {
                $skipped++;
            }
        }

        $ok = "✅ تم إضافة: $inserted كارت | تم تخطي: $skipped (مكرر/خطأ)";
    }
}

// 4) Toggle Company Active
if (isset($_GET['toggle_company'])) {
    $id = (int)$_GET['toggle_company'];
    $conn->query("UPDATE recharge_companies SET active = IF(active=1,0,1) WHERE id=$id");
    header("Location: recharge_cards.php");
    exit;
}

// 5) Toggle Package Active
if (isset($_GET['toggle_package'])) {
    $id = (int)$_GET['toggle_package'];
    $conn->query("UPDATE recharge_packages SET active = IF(active=1,0,1) WHERE id=$id");
    header("Location: recharge_cards.php");
    exit;
}

/* ================= Fetch Data ================= */
$companies = [];
$res = $conn->query("SELECT id, $companyCol AS company_name, active FROM recharge_companies ORDER BY $companyCol ASC");
while ($r = $res->fetch_assoc()) $companies[] = $r;

$packages = [];
$res = $conn->query("
    SELECT p.id, p.company_id, p.package_name, p.face_value, p.active,
           c.$companyCol AS company_name
    FROM recharge_packages p
    JOIN recharge_companies c ON c.id = p.company_id
    ORDER BY c.$companyCol ASC, p.package_name ASC
");
while ($r = $res->fetch_assoc()) $packages[] = $r;

/* ================= Filters ================= */
$f_company = (int)($_GET['f_company'] ?? 0);
$f_package = (int)($_GET['f_package'] ?? 0);
$f_status  = trim($_GET['f_status'] ?? '');

$where = "1=1";
if ($f_company > 0) $where .= " AND rc.company_id = $f_company";
if ($f_package > 0) $where .= " AND rc.package_id = $f_package";
if ($f_status === 'available' || $f_status === 'sold') $where .= " AND rc.status = '$f_status'";

/* ================= Stats + List Cards ================= */
$stats = [
    'available' => 0,
    'sold'      => 0,
    'total'     => 0,
];

$st = $conn->query("SELECT status, COUNT(*) c FROM recharge_cards GROUP BY status");
while ($row = $st->fetch_assoc()) {
    if ($row['status'] === 'available') $stats['available'] = (int)$row['c'];
    if ($row['status'] === 'sold')      $stats['sold'] = (int)$row['c'];
}
$stats['total'] = $stats['available'] + $stats['sold'];

$cards = [];
$res = $conn->query("
    SELECT rc.id, rc.card_code, rc.status, rc.created_at, rc.sold_at,
           c.$companyCol AS company_name,
           p.package_name, p.face_value
    FROM recharge_cards rc
    JOIN recharge_companies c ON c.id = rc.company_id
    JOIN recharge_packages p ON p.id = rc.package_id
    WHERE $where
    ORDER BY rc.id DESC
    LIMIT 200
");
while ($r = $res->fetch_assoc()) $cards[] = $r;
?>

<style>
.container{max-width:1100px;margin:0 auto}
.box{background:#fff;border:1px solid #eee;border-radius:14px;padding:14px;margin:12px 0;box-shadow:0 6px 18px rgba(0,0,0,.04)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
label{display:block;margin:8px 0 6px;font-weight:700;font-size:13px}
input, select, textarea{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:12px;outline:none}
textarea{min-height:140px;resize:vertical}
button{padding:10px 14px;border:0;border-radius:12px;font-weight:700;cursor:pointer}
.btn{background:#111;color:#fff}
.btn2{background:#0a7;color:#fff}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px}
.badge.ok{background:#eafff2;color:#067a3d;border:1px solid #baf2cf}
.badge.err{background:#ffecec;color:#b00020;border:1px solid #ffc5c5}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{border-bottom:1px solid #eee;padding:10px;font-size:13px;text-align:right}
.small{color:#666;font-size:12px}
.actions a{padding:6px 10px;border-radius:10px;background:#f4f4f4;text-decoration:none;color:#111;font-weight:700}
.filters{display:flex;gap:10px;flex-wrap:wrap}
.filters > div{flex:1;min-width:200px}
</style>

<div class="container">

    <h3>🟦 إدارة كروت الشحن</h3>

    <div class="box">
        <b>إحصائيات:</b>
        <span class="badge ok">متاح: <?= (int)$stats['available'] ?></span>
        <span class="badge err">مباع: <?= (int)$stats['sold'] ?></span>
        <span class="badge">الإجمالي: <?= (int)$stats['total'] ?></span>
        <div class="small" style="margin-top:6px">يعرض آخر 200 كارت في الجدول</div>
    </div>

    <?php if ($msg): ?>
        <div class="box"><span class="badge err"><?= e($msg) ?></span></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="box"><span class="badge ok"><?= e($ok) ?></span></div>
    <?php endif; ?>

    <div class="grid">

        <div class="box">
            <h4 style="margin:0 0 8px">➕ إضافة شركة</h4>
            <form method="post">
                <label>اسم الشركة</label>
                <input type="text" name="company_name" placeholder="Vodafone / Orange / WE ..." required>
                <div style="margin-top:10px">
                    <button class="btn" name="add_company">حفظ</button>
                </div>
            </form>
        </div>

        <div class="box">
            <h4 style="margin:0 0 8px">➕ إضافة فئة</h4>
            <form method="post">
                <label>الشركة</label>
                <select name="company_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach($companies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['company_name']) ?> <?= ((int)$c['active']===1?'':'(مقفولة)') ?></option>
                    <?php endforeach; ?>
                </select>

                <label>اسم الفئة</label>
                <input type="text" name="package_name" placeholder="مثال: كارت 10 / كارت 50" required>

                <label>القيمة (اختياري)</label>
                <input type="number" step="0.01" name="face_value" value="0">

                <div style="margin-top:10px">
                    <button class="btn2" name="add_package">حفظ</button>
                </div>
            </form>
        </div>

        <div class="box">
            <h4 style="margin:0 0 8px">📦 إضافة كروت (بالجملة)</h4>
            <form method="post">
                <label>الشركة</label>
                <select name="cards_company_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach($companies as $c): ?>
                        <?php if ((int)$c['active']===1): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['company_name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <label>الفئة</label>
                <select name="cards_package_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach($packages as $p): ?>
                        <?php if ((int)$p['active']===1): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= e($p['company_name']) ?> - <?= e($p['package_name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <label>أكواد الكروت (كل كارت في سطر)</label>
                <textarea name="cards_text" placeholder="0123-4567-8901&#10;1111-2222-3333&#10;..."></textarea>

                <div style="margin-top:10px">
                    <button class="btn" name="add_cards">إضافة</button>
                </div>
            </form>
        </div>

    </div>

    <div class="box">
        <h4 style="margin:0 0 10px">🧾 الكروت</h4>

        <form method="get" class="filters">
            <div>
                <label>الشركة</label>
                <select name="f_company">
                    <option value="0">كل الشركات</option>
                    <?php foreach($companies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($f_company==(int)$c['id']?'selected':'') ?>>
                            <?= e($c['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>الفئة</label>
                <select name="f_package">
                    <option value="0">كل الفئات</option>
                    <?php foreach($packages as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ($f_package==(int)$p['id']?'selected':'') ?>>
                            <?= e($p['company_name']) ?> - <?= e($p['package_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>الحالة</label>
                <select name="f_status">
                    <option value="">الكل</option>
                    <option value="available" <?= ($f_status==='available'?'selected':'') ?>>متاح</option>
                    <option value="sold" <?= ($f_status==='sold'?'selected':'') ?>>مباع</option>
                </select>
            </div>

            <div style="display:flex;align-items:flex-end;gap:10px">
                <button class="btn" type="submit">فلترة</button>
                <a class="btn" style="background:#666;color:#fff;text-decoration:none;display:inline-block" href="recharge_cards.php">إعادة</a>
            </div>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الشركة</th>
                    <th>الفئة</th>
                    <th>الكود</th>
                    <th>الحالة</th>
                    <th>تاريخ الإضافة</th>
                    <th>تاريخ البيع</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($cards as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= e($c['company_name']) ?></td>
                        <td><?= e($c['package_name']) ?> (<?= number_format((float)$c['face_value'],2) ?>)</td>
                        <td style="font-family:monospace"><?= e($c['card_code']) ?></td>
                        <td><?= ($c['status']==='available' ? '<span class="badge ok">متاح</span>' : '<span class="badge err">مباع</span>') ?></td>
                        <td class="small"><?= e($c['created_at']) ?></td>
                        <td class="small"><?= e($c['sold_at'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($cards)): ?>
                    <tr><td colspan="7" class="small">لا توجد نتائج</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include "../user/footer.php"; ?>
