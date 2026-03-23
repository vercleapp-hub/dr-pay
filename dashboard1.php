<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect('sql302.infinityfree.com', 'if0_40974310', '1p7F4ABr5utA4', 'if0_40974310_login');
if (!$conn) die("فشل الاتصال بقاعدة البيانات");

$user_id = $_SESSION['user_id'];
$user_result = mysqli_query($conn, "SELECT username, email, role, last_login FROM users WHERE id = '$user_id'");
if (!$user_result || mysqli_num_rows($user_result) == 0) die("المستخدم غير موجود");
$user = mysqli_fetch_assoc($user_result);

// 1. جلب الدخول الناجحة
$success_logs_result = mysqli_query($conn, "
    SELECT username, login_time, ip_address, location_data, system_data, 
           browser_data, network_data, hardware_data, screen_data, user_agent,
           device_fingerprint, session_id
    FROM login_data_enhanced 
    ORDER BY login_time DESC 
    LIMIT 100
");

// تخزين البيانات في مصفوفة لعرضها لاحقاً
$success_logs = [];
if ($success_logs_result) {
    while($row = mysqli_fetch_assoc($success_logs_result)) {
        $success_logs[] = $row;
    }
}

// 2. جلب المحاولات الفاشلة
$table_check = mysqli_query($conn, "DESCRIBE login_attempts");
$columns = [];
if ($table_check) while ($row = mysqli_fetch_assoc($table_check)) $columns[] = $row['Field'];

$select_cols = [];
foreach (['id', 'ip_address', 'username', 'attempt_time', 'successful'] as $col) {
    if (in_array($col, $columns)) $select_cols[] = $col;
}
if (in_array('user_agent', $columns)) $select_cols[] = 'user_agent';

$column_list = implode(', ', $select_cols);
$failed_logs_result = mysqli_query($conn, "
    SELECT $column_list
    FROM login_attempts 
    WHERE successful = 0 
    ORDER BY attempt_time DESC 
    LIMIT 100
");

$failed_logs = [];
if ($failed_logs_result) {
    while($row = mysqli_fetch_assoc($failed_logs_result)) {
        $failed_logs[] = $row;
    }
}

// دوال المساعدة
function formatTime($time) {
    if (empty($time) || $time == '0000-00-00 00:00:00') return 'غير محدد';
    try {
        $date = new DateTime($time, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Africa/Cairo'));
        return $date->format('Y-m-d h:i:s A');
    } catch (Exception $e) {
        return $time;
    }
}

function formatTimeJS($timeStr) {
    if (empty($timeStr) || $timeStr == '0000-00-00 00:00:00') return 'غير محدد';
    try {
        $date = new DateTime($timeStr, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Africa/Cairo'));
        return $date->format('Y-m-d h:i:s A');
    } catch (Exception $e) {
        return $timeStr;
    }
}

function clean($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - الدكتور باي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif}
        body{background:#f5f7fa;color:#333}
        .header{background:linear-gradient(135deg,#1e3c72,#2a5298);color:white;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.1)}
        .header h1{font-size:26px;margin-bottom:10px;display:flex;align-items:center;gap:10px}
        .header-info{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-top:10px}
        .user-info{background:rgba(255,255,255,0.1);padding:10px 15px;border-radius:8px;border-right:4px solid #4CAF50}
        .controls{display:flex;gap:8px;flex-wrap:wrap}
        .btn{padding:8px 16px;border:none;border-radius:5px;cursor:pointer;font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;transition:all 0.3s}
        .btn-success{background:#4CAF50;color:white}
        .btn-success:hover{background:#45a049}
        .btn-warning{background:#ff9800;color:white}
        .btn-warning:hover{background:#e68a00}
        .btn-danger{background:#f44336;color:white}
        .btn-danger:hover{background:#d32f2f}
        .container{max-width:1400px;margin:20px auto;padding:0 15px}
        .tabs{display:flex;gap:8px;margin-bottom:20px;background:white;padding:12px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05)}
        .tab{padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-weight:600;background:#e9ecef;color:#495057;transition:all 0.3s;font-size:14px}
        .tab.active{background:#1e3c72;color:white}
        .table-container{background:white;border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.08);margin-bottom:20px}
        table{width:100%;border-collapse:collapse}
        thead{background:linear-gradient(135deg,#1e3c72,#2a5298)}
        th{color:white;padding:12px 10px;text-align:right;font-weight:600;font-size:13px;border-bottom:3px solid #162a50}
        td{padding:10px 12px;border-bottom:1px solid #e9ecef;color:#495057;vertical-align:top;font-size:13px}
        tbody tr:hover{background:#f8f9fa}
        .badge{display:inline-block;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;margin:1px}
        .badge-ip{background:#e3f2fd;color:#1565c0}
        .badge-failed{background:#ffebee;color:#c62828}
        .badge-success{background:#e8f5e9;color:#2e7d32}
        .badge-info{background:#e8eaf6;color:#3949ab}
        .details-btn{background:#2196F3;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:12px;transition:all 0.3s;display:inline-flex;align-items:center;gap:4px}
        .details-btn:hover{background:#1976D2}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal-content{background:white;padding:20px;border-radius:8px;width:90%;max-width:700px;max-height:80vh;overflow-y:auto;box-shadow:0 8px 25px rgba(0,0,0,0.2)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #e9ecef}
        .modal-title{font-size:18px;color:#1e3c72;font-weight:600;display:flex;align-items:center;gap:8px}
        .close-btn{background:none;border:none;font-size:24px;cursor:pointer;color:#6c757d}
        .close-btn:hover{color:#dc3545}
        .details-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:15px}
        .detail-card{background:#f8f9fa;padding:15px;border-radius:6px;border-left:3px solid #2196F3}
        .detail-card h3{color:#1e3c72;margin-bottom:10px;font-size:14px;display:flex;align-items:center;gap:6px}
        .detail-item{margin-bottom:8px;padding-bottom:8px;border-bottom:1px dashed #dee2e6}
        .detail-label{font-weight:600;color:#495057;display:inline-block;width:100px}
        .detail-value{color:#212529;word-break:break-word}
        .no-data{text-align:center;padding:40px;color:#6c757d}
        .warning-box{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:12px;border-radius:6px;margin-bottom:15px;display:flex;align-items:center;gap:8px;font-size:14px}
        .time-12h{font-family:monospace;font-size:12px}
        @media (max-width:768px){
            .header-info,.controls,.tabs{flex-direction:column}
            .tab{width:100%;text-align:center}
            table{display:block;overflow-x:auto}
            .details-grid{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chart-bar"></i> لوحة التحكم - نظام الدكتور باي</h1>
        <div class="header-info">
            <div class="user-info">
                <div><i class="fas fa-user"></i> <strong>المستخدم:</strong> <?= clean($user['username']) ?> (<?= clean($user['role']) ?>)</div>
                <div><i class="fas fa-envelope"></i> <strong>البريد:</strong> <?= clean($user['email']) ?></div>
                <div><i class="fas fa-clock"></i> <strong>آخر دخول:</strong> <span class="time-12h"><?= formatTime($user['last_login']) ?></span></div>
            </div>
            <div class="controls">
                <button class="btn btn-success" onclick="exportExcel('success')"><i class="fas fa-file-excel"></i> تصدير الناجحة</button>
                <button class="btn btn-warning" onclick="exportExcel('failed')"><i class="fas fa-file-excel"></i> تصدير الفاشلة</button>
                <button class="btn btn-danger" onclick="location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> خروج</button>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if(count($failed_logs) > 0): ?>
        <div class="warning-box"><i class="fas fa-exclamation-triangle"></i> تنبيه: يوجد <?= count($failed_logs) ?> محاولة فاشلة</div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('success')">
                <i class="fas fa-check-circle"></i> الناجحة (<?= count($success_logs) ?>)
            </button>
            <button class="tab" onclick="showTab('failed')">
                <i class="fas fa-times-circle"></i> الفاشلة (<?= count($failed_logs) ?>)
            </button>
        </div>
        
        <!-- تبويب الدخول الناجح -->
        <div id="successTab" class="table-container">
            <?php if(count($success_logs) == 0): ?>
            <div class="no-data"><i class="fas fa-database"></i> لا توجد سجلات دخول ناجحة</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>المستخدم</th><th>الوقت</th><th>IP</th><th>الموقع</th>
                        <th>الجهاز</th><th>النظام</th><th>المتصفح</th><th>الشبكة</th><th>التفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($success_logs as $i => $log): 
                        $sys = json_decode($log['system_data'] ?? '{}', true) ?: [];
                        $browser = json_decode($log['browser_data'] ?? '{}', true) ?: [];
                        $network = json_decode($log['network_data'] ?? '{}', true) ?: [];
                        $hardware = json_decode($log['hardware_data'] ?? '{}', true) ?: [];
                        $screen = json_decode($log['screen_data'] ?? '{}', true) ?: [];
                        $location = json_decode($log['location_data'] ?? '{}', true) ?: [];
                    ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= clean($log['username']) ?></strong></td>
                        <td><span class="time-12h"><?= formatTime($log['login_time']) ?></span></td>
                        <td><span class="badge badge-ip"><?= clean($log['ip_address']) ?></span></td>
                        <td>
                            <?php if(!empty($location['latitude']) && !empty($location['longitude'])): ?>
                            <?= round($location['latitude'], 4) ?>, <?= round($location['longitude'], 4) ?>
                            <?php else: ?>غير متوفر<?php endif; ?>
                        </td>
                        <td><span class="badge badge-info"><?= clean($sys['device'] ?? 'غير معروف') ?></span></td>
                        <td><span class="badge badge-info"><?= clean($sys['os'] ?? 'غير معروف') ?></span></td>
                        <td><?= clean($browser['name'] ?? 'غير معروف') ?></td>
                        <td><span class="badge badge-info"><?= clean($network['connection'] ?? 'غير معروف') ?></span></td>
                        <td>
                            <button class="details-btn" onclick="showSuccessDetails(<?= $i ?>)">
                                <i class="fas fa-eye"></i> عرض
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- تبويب المحاولات الفاشلة -->
        <div id="failedTab" class="table-container" style="display:none;">
            <?php if(count($failed_logs) == 0): ?>
            <div class="no-data"><i class="fas fa-ban"></i> لا توجد محاولات فاشلة</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>المستخدم</th><th>الوقت</th><th>IP</th>
                        <th>النظام</th><th>المتصفح</th><th>الجهاز</th><th>الحالة</th><th>التفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($failed_logs as $j => $failed): 
                        $ua = $failed['user_agent'] ?? '';
                        $device_info = [
                            'os' => 'غير معروف',
                            'browser' => 'غير معروف',
                            'device' => 'غير معروف'
                        ];
                        
                        if (!empty($ua)) {
                            $ua_lower = strtolower($ua);
                            
                            if (strpos($ua_lower, 'windows') !== false) $device_info['os'] = 'Windows';
                            elseif (strpos($ua_lower, 'mac') !== false) $device_info['os'] = 'macOS';
                            elseif (strpos($ua_lower, 'linux') !== false) $device_info['os'] = 'Linux';
                            elseif (strpos($ua_lower, 'android') !== false) $device_info['os'] = 'Android';
                            elseif (strpos($ua_lower, 'iphone') !== false || strpos($ua_lower, 'ipod') !== false) $device_info['os'] = 'iOS';
                            elseif (strpos($ua_lower, 'ipad') !== false) $device_info['os'] = 'iOS';
                            
                            if (strpos($ua_lower, 'chrome') !== false) $device_info['browser'] = 'Chrome';
                            elseif (strpos($ua_lower, 'firefox') !== false) $device_info['browser'] = 'Firefox';
                            elseif (strpos($ua_lower, 'safari') !== false && strpos($ua_lower, 'chrome') === false) $device_info['browser'] = 'Safari';
                            elseif (strpos($ua_lower, 'edge') !== false) $device_info['browser'] = 'Edge';
                            elseif (strpos($ua_lower, 'opera') !== false) $device_info['browser'] = 'Opera';
                            
                            if (strpos($ua_lower, 'mobile') !== false || $device_info['os'] == 'Android' || $device_info['os'] == 'iOS') {
                                $device_info['device'] = strpos($ua_lower, 'tablet') !== false || strpos($ua_lower, 'ipad') !== false ? 'تابلت' : 'هاتف';
                            } else {
                                $device_info['device'] = 'كمبيوتر';
                            }
                        }
                    ?>
                    <tr>
                        <td><?= $j+1 ?></td>
                        <td><strong><?= clean($failed['username']) ?></strong></td>
                        <td><span class="time-12h"><?= formatTime($failed['attempt_time']) ?></span></td>
                        <td><span class="badge badge-failed"><?= clean($failed['ip_address']) ?></span></td>
                        <td><?= $device_info['os'] ?></td>
                        <td><?= $device_info['browser'] ?></td>
                        <td><?= $device_info['device'] ?></td>
                        <td><span class="badge badge-failed"><i class="fas fa-times"></i> فاشل</span></td>
                        <td>
                            <button class="details-btn" onclick="showFailedDetails(<?= $j ?>)">
                                <i class="fas fa-info-circle"></i> تفاصيل
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- إخفاء البيانات في الصفحة للوصول إليها من JavaScript -->
    <div id="dataContainer" style="display:none;">
        <div id="successData"><?= htmlspecialchars(json_encode($success_logs), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="failedData"><?= htmlspecialchars(json_encode($failed_logs), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="formattedTimes"><?= htmlspecialchars(json_encode([
            'success' => array_map('formatTimeJS', array_column($success_logs, 'login_time')),
            'failed' => array_map('formatTimeJS', array_column($failed_logs, 'attempt_time')),
            'last_login' => formatTimeJS($user['last_login'])
        ]), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    
    <script>
        // جلب البيانات من العناصر المخفية في الصفحة
        const successLogs = JSON.parse(document.getElementById('successData').textContent || '[]');
        const failedLogs = JSON.parse(document.getElementById('failedData').textContent || '[]');
        const formattedTimes = JSON.parse(document.getElementById('formattedTimes').textContent || '{}');
        
        function showTab(tab) {
            document.getElementById('successTab').style.display = 'none';
            document.getElementById('failedTab').style.display = 'none';
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tab + 'Tab').style.display = 'block';
            event.target.classList.add('active');
        }
        
        function showSuccessDetails(index) {
            const log = successLogs[index];
            if (!log) {
                alert('لا توجد بيانات لهذا السجل');
                return;
            }
            
            try {
                const sys = JSON.parse(log.system_data || '{}');
                const browser = JSON.parse(log.browser_data || '{}');
                const network = JSON.parse(log.network_data || '{}');
                const hardware = JSON.parse(log.hardware_data || '{}');
                const screen = JSON.parse(log.screen_data || '{}');
                const location = JSON.parse(log.location_data || '{}');
                
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <div class="modal-title"><i class="fas fa-info-circle"></i> تفاصيل الدخول #${index+1}</div>
                            <button class="close-btn" onclick="this.closest('.modal').style.display='none'">×</button>
                        </div>
                        <div class="details-grid">
                            <div class="detail-card">
                                <h3><i class="fas fa-user"></i> معلومات المستخدم</h3>
                                <div class="detail-item"><span class="detail-label">اسم المستخدم:</span><span class="detail-value">${escapeHtml(log.username || '')}</span></div>
                                <div class="detail-item"><span class="detail-label">وقت الدخول:</span><span class="detail-value time-12h">${formattedTimes.success[index] || formatTimeJS(log.login_time)}</span></div>
                                <div class="detail-item"><span class="detail-label">IP:</span><span class="detail-value">${escapeHtml(log.ip_address || '')}</span></div>
                                <div class="detail-item"><span class="detail-label">معرف الجلسة:</span><span class="detail-value">${escapeHtml(log.session_id || 'غير متوفر')}</span></div>
                            </div>
                            <div class="detail-card">
                                <h3><i class="fas fa-laptop"></i> معلومات الجهاز</h3>
                                <div class="detail-item"><span class="detail-label">نظام التشغيل:</span><span class="detail-value">${escapeHtml(sys.os || 'غير معروف')}</span></div>
                                <div class="detail-item"><span class="detail-label">نوع الجهاز:</span><span class="detail-value">${escapeHtml(sys.device || 'غير معروف')}</span></div>
                                <div class="detail-item"><span class="detail-label">اللغة:</span><span class="detail-value">${escapeHtml(sys.language || 'غير معروف')}</span></div>
                                <div class="detail-item"><span class="detail-label">عدد الأنوية:</span><span class="detail-value">${hardware.cores || 'غير معروف'}</span></div>
                            </div>
                            <div class="detail-card">
                                <h3><i class="fas fa-desktop"></i> الشاشة والشبكة</h3>
                                <div class="detail-item"><span class="detail-label">الدقة:</span><span class="detail-value">${screen.resolution || 'غير معروف'}</span></div>
                                <div class="detail-item"><span class="detail-label">عمق الألوان:</span><span class="detail-value">${screen.color_depth || 'غير معروف'}</span></div>
                                <div class="detail-item"><span class="detail-label">المتصفح:</span><span class="detail-value">${escapeHtml(browser.name || 'غير معروف')}</span></div>
                                <div class="detail-item"><span class="detail-label">نوع الاتصال:</span><span class="detail-value">${escapeHtml(network.connection || 'غير معروف')}</span></div>
                            </div>
                            <div class="detail-card">
                                <h3><i class="fas fa-map-marker-alt"></i> الموقع الجغرافي</h3>
                                <div class="detail-item"><span class="detail-label">خط العرض:</span><span class="detail-value">${location.latitude || 'غير متوفر'}</span></div>
                                <div class="detail-item"><span class="detail-label">خط الطول:</span><span class="detail-value">${location.longitude || 'غير متوفر'}</span></div>
                                <div class="detail-item"><span class="detail-label">الدقة:</span><span class="detail-value">${location.accuracy || '0'} متر</span></div>
                            </div>
                            <div class="detail-card">
                                <h3><i class="fas fa-code"></i> معلومات تقنية</h3>
                                <div class="detail-item"><span class="detail-label">User Agent:</span><span class="detail-value" style="font-size:11px">${escapeHtml(log.user_agent || 'غير متوفر')}</span></div>
                                <div class="detail-item"><span class="detail-label">بصمة الجهاز:</span><span class="detail-value" style="font-size:11px">${escapeHtml(log.device_fingerprint || 'غير متوفر')}</span></div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.style.display = 'flex';
                modal.onclick = function(e) { 
                    if(e.target === this) this.style.display = 'none'; 
                };
            } catch (error) {
                console.error('Error showing details:', error);
                alert('حدث خطأ في عرض التفاصيل');
            }
        }
        
        function showFailedDetails(index) {
            const log = failedLogs[index];
            if (!log) {
                alert('لا توجد بيانات لهذه المحاولة');
                return;
            }
            
            try {
                const ua = log.user_agent || '';
                const deviceInfo = {
                    os: 'غير معروف',
                    browser: 'غير معروف',
                    device: 'غير معروف'
                };
                
                if (ua) {
                    const uaLower = ua.toLowerCase();
                    
                    if (uaLower.includes('windows')) deviceInfo.os = 'Windows';
                    else if (uaLower.includes('mac')) deviceInfo.os = 'macOS';
                    else if (uaLower.includes('linux')) deviceInfo.os = 'Linux';
                    else if (uaLower.includes('android')) deviceInfo.os = 'Android';
                    else if (uaLower.includes('iphone') || uaLower.includes('ipod')) deviceInfo.os = 'iOS';
                    else if (uaLower.includes('ipad')) deviceInfo.os = 'iOS';
                    
                    if (uaLower.includes('chrome')) deviceInfo.browser = 'Chrome';
                    else if (uaLower.includes('firefox')) deviceInfo.browser = 'Firefox';
                    else if (uaLower.includes('safari') && !uaLower.includes('chrome')) deviceInfo.browser = 'Safari';
                    else if (uaLower.includes('edge')) deviceInfo.browser = 'Edge';
                    else if (uaLower.includes('opera')) deviceInfo.browser = 'Opera';
                    
                    if (uaLower.includes('mobile') || deviceInfo.os === 'Android' || deviceInfo.os === 'iOS') {
                        deviceInfo.device = uaLower.includes('tablet') || uaLower.includes('ipad') ? 'تابلت' : 'هاتف';
                    } else {
                        deviceInfo.device = 'كمبيوتر';
                    }
                }
                
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <div class="modal-title"><i class="fas fa-exclamation-triangle"></i> تفاصيل المحاولة #${index+1}</div>
                            <button class="close-btn" onclick="this.closest('.modal').style.display='none'">×</button>
                        </div>
                        <div class="details-grid">
                            <div class="detail-card">
                                <h3><i class="fas fa-user-times"></i> معلومات المحاولة</h3>
                                <div class="detail-item"><span class="detail-label">اسم المستخدم:</span><span class="detail-value">${escapeHtml(log.username || '')}</span></div>
                                <div class="detail-item"><span class="detail-label">الوقت:</span><span class="detail-value time-12h">${formattedTimes.failed[index] || formatTimeJS(log.attempt_time)}</span></div>
                                <div class="detail-item"><span class="detail-label">IP:</span><span class="detail-value">${escapeHtml(log.ip_address || '')}</span></div>
                                <div class="detail-item"><span class="detail-label">الحالة:</span><span class="detail-value" style="color:red">❌ فاشل</span></div>
                                <div class="detail-item"><span class="detail-label">معرف السجل:</span><span class="detail-value">#${log.id || 'غير معروف'}</span></div>
                            </div>
                            <div class="detail-card">
                                <h3><i class="fas fa-laptop"></i> معلومات الجهاز</h3>
                                <div class="detail-item"><span class="detail-label">نظام التشغيل:</span><span class="detail-value">${deviceInfo.os}</span></div>
                                <div class="detail-item"><span class="detail-label">المتصفح:</span><span class="detail-value">${deviceInfo.browser}</span></div>
                                <div class="detail-item"><span class="detail-label">نوع الجهاز:</span><span class="detail-value">${deviceInfo.device}</span></div>
                            </div>
                            ${ua ? `
                            <div class="detail-card">
                                <h3><i class="fas fa-code"></i> معلومات تقنية</h3>
                                <div class="detail-item"><span class="detail-label">User Agent:</span><span class="detail-value" style="font-size:11px">${escapeHtml(ua)}</span></div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.style.display = 'flex';
                modal.onclick = function(e) { 
                    if(e.target === this) this.style.display = 'none'; 
                };
            } catch (error) {
                console.error('Error showing failed details:', error);
                alert('حدث خطأ في عرض تفاصيل المحاولة');
            }
        }
        
        function exportExcel(type) {
            const table = document.querySelector(`#${type}Tab table`);
            if (!table) {
                alert('لا توجد بيانات للتصدير');
                return;
            }
            
            const html = table.outerHTML;
            const blob = new Blob([`
                <html>
                    <head>
                        <meta charset="UTF-8">
                        <style>
                            table { border-collapse: collapse; width: 100%; direction: rtl; }
                            th { background-color: #1e3c72; color: white; padding: 10px; text-align: right; }
                            td { border: 1px solid #ddd; padding: 8px; text-align: right; }
                            tr:nth-child(even) { background-color: #f2f2f2; }
                        </style>
                    </head>
                    <body>${html}</body>
                </html>
            `], { type: 'application/vnd.ms-excel' });
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${type === 'success' ? 'الدخول_الناجحة' : 'المحاولات_الفاشلة'}_${new Date().toISOString().slice(0,10)}.xls`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        function formatTimeJS(timeStr) {
            if (!timeStr || timeStr === 'غير محدد' || timeStr === '0000-00-00 00:00:00') return 'غير محدد';
            try {
                const date = new Date(timeStr + ' UTC');
                const options = {
                    timeZone: 'Africa/Cairo',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };
                const formatter = new Intl.DateTimeFormat('ar-EG', options);
                const parts = formatter.formatToParts(date);
                
                let year = '', month = '', day = '', hour = '', minute = '', second = '', ampm = '';
                
                parts.forEach(part => {
                    switch(part.type) {
                        case 'year': year = part.value; break;
                        case 'month': month = part.value; break;
                        case 'day': day = part.value; break;
                        case 'hour': hour = part.value; break;
                        case 'minute': minute = part.value; break;
                        case 'second': second = part.value; break;
                        case 'dayPeriod': ampm = part.value; break;
                    }
                });
                
                return `${year}-${month}-${day} ${hour}:${minute}:${second} ${ampm}`;
            } catch(e) {
                console.error('Error formatting time:', e, timeStr);
                return timeStr;
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
        
        // إغلاق النافذة بالزر Esc
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
        
        // تحديث الأوقات في الجدول بعد تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // تحديث أوقات الجدول الرئيسي
            document.querySelectorAll('#successTab td:nth-child(3) .time-12h').forEach((td, index) => {
                if (formattedTimes.success && formattedTimes.success[index]) {
                    td.textContent = formattedTimes.success[index];
                }
            });
            
            document.querySelectorAll('#failedTab td:nth-child(3) .time-12h').forEach((td, index) => {
                if (formattedTimes.failed && formattedTimes.failed[index]) {
                    td.textContent = formattedTimes.failed[index];
                }
            });
            
            // تحديث وقت آخر دخول في الهيدر
            const lastLoginEl = document.querySelector('.user-info div:nth-child(3) .time-12h');
            if (lastLoginEl && formattedTimes.last_login) {
                lastLoginEl.textContent = formattedTimes.last_login;
            }
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>