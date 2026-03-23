<?php
class DeviceInfoCollector {
    private $conn;
    private $session_id;
    private $fingerprint;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->session_id = session_id();
        $this->fingerprint = $this->generateFingerprint();
    }
    
    // تسجيل كل شيء عند فتح الصفحة
    public function logAllDeviceInfo($post_data = []) {
        try {
            // 1. معلومات الجهاز الأساسية
            $device_info = $this->collectDeviceInfo($post_data);
            
            // 2. معلومات المتصفح
            $browser_info = $this->collectBrowserInfo();
            
            // 3. معلومات النظام
            $system_info = $this->collectSystemInfo();
            
            // 4. معلومات الشبكة
            $network_info = $this->collectNetworkInfo($post_data);
            
            // 5. معلومات الموقع
            $location_info = $this->collectLocationInfo($post_data);
            
            // 6. البصمات
            $fingerprints = $this->collectFingerprints($post_data);
            
            // 7. معلومات إضافية
            $extra_info = $this->collectExtraInfo($post_data);
            
            // دمج كل المعلومات
            $all_info = array_merge(
                $device_info,
                $browser_info,
                $system_info,
                $network_info,
                $location_info,
                $fingerprints,
                $extra_info
            );
            
            // حفظ في قاعدة البيانات
            $this->saveDeviceInfo($all_info);
            
            // تحديث تتبع الجلسة
            $this->updateSessionTracking();
            
            // تتبع البصمة
            $this->trackFingerprint($fingerprints);
            
            return $all_info;
            
        } catch (Exception $e) {
            $this->logError('device_info_collection', $e->getMessage());
            return false;
        }
    }
    
    // تسجيل جميع المحاولات الفاشلة
    public function logFailedAttempt($type, $username, $error_message, $post_data = []) {
        try {
            $failed_data = [
                'attempt_type' => $type,
                'username' => $username,
                'password_hash' => isset($post_data['password']) ? 
                    hash('sha256', $post_data['password']) : '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'device_info' => json_encode($this->collectDeviceInfo($post_data)),
                'location_data' => json_encode($this->collectLocationInfo($post_data)),
                'form_data' => json_encode(array_diff_key($post_data, ['password' => ''])),
                'headers' => json_encode($this->getAllHeaders()),
                'cookies' => json_encode($_COOKIE),
                'session_data' => json_encode($_SESSION ?? []),
                'error_message' => $error_message,
                'error_code' => $this->getErrorCode($error_message),
                'error_stack' => $this->getErrorStack(),
                'fail_reason' => $this->getFailReason($error_message),
                'attempt_time' => date('Y-m-d H:i:s'),
                'attempt_date' => date('Y-m-d'),
                'attempt_hour' => (int)date('H')
            ];
            
            $this->saveFailedAttempt($failed_data);
            
            // إذا كان هناك محاولات متكررة، سجل تحذير
            $this->checkRepeatedAttempts($username, $_SERVER['REMOTE_ADDR']);
            
        } catch (Exception $e) {
            error_log("Failed attempt logging error: " . $e->getMessage());
        }
    }
    
    // جمع معلومات الجهاز الكاملة
    private function collectDeviceInfo($post_data) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return [
            'ip_address' => $this->getRealIP(),
            'user_agent' => $ua,
            'browser_name' => $this->getBrowserName($ua),
            'browser_version' => $this->getBrowserVersion($ua),
            'browser_engine' => $this->getBrowserEngine($ua),
            'os_name' => $this->getOSName($ua),
            'os_version' => $this->getOSVersion($ua),
            'device_type' => $this->getDeviceType($ua),
            'device_brand' => $this->getDeviceBrand($ua),
            'device_model' => $this->getDeviceModel($ua),
            'screen_resolution' => $post_data['screen_resolution'] ?? '0x0',
            'screen_color_depth' => (int)($post_data['color_depth'] ?? 0),
            'screen_pixel_ratio' => (float)($post_data['pixel_ratio'] ?? 1),
            'window_resolution' => $post_data['window_resolution'] ?? '0x0',
            'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'languages' => $post_data['languages'] ?? '[]',
            'timezone' => $post_data['timezone'] ?? '',
            'timezone_offset' => (int)($post_data['timezone_offset'] ?? 0),
            'cookies_enabled' => isset($_COOKIE) && count($_COOKIE) > 0,
            'javascript_enabled' => true,
            'do_not_track' => isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == 1,
            'session_id' => session_id(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'page_url' => $this->getCurrentUrl(),
            'visit_time' => date('Y-m-d H:i:s'),
            'visit_date' => date('Y-m-d'),
            'visit_hour' => (int)date('H'),
            'day_of_week' => (int)date('w')
        ];
    }
    
    // جمع معلومات المتصفح المتقدمة
    private function collectBrowserInfo() {
        return [
            'plugins' => $_POST['plugins'] ?? '[]',
            'fonts' => $_POST['fonts'] ?? '[]',
            'touch_support' => isset($_POST['touch_support']) && $_POST['touch_support'],
            'touch_points' => (int)($_POST['max_touch_points'] ?? 0),
            'installed_apps' => $_POST['installed_apps'] ?? '[]',
            'webgl_vendor' => $_POST['webgl_vendor'] ?? '',
            'webgl_renderer' => $_POST['webgl_renderer'] ?? '',
            'pdf_viewer_enabled' => isset($_POST['pdf_viewer']) ? $_POST['pdf_viewer'] : false,
            'adblock_enabled' => isset($_POST['adblock']) ? $_POST['adblock'] : false,
            'incognito_mode' => $this->detectIncognito(),
            'local_storage' => isset($_POST['local_storage']) ? $_POST['local_storage'] : false,
            'session_storage' => isset($_POST['session_storage']) ? $_POST['session_storage'] : false,
            'indexed_db' => isset($_POST['indexed_db']) ? $_POST['indexed_db'] : false
        ];
    }
    
    // جمع معلومات النظام
    private function collectSystemInfo() {
        return [
            'hardware_cores' => (int)($_POST['hardware_cores'] ?? 0),
            'hardware_memory' => (float)($_POST['device_memory'] ?? 0),
            'hardware_ram' => (float)($_POST['ram_gb'] ?? 0),
            'cpu_architecture' => $_POST['cpu_architecture'] ?? '',
            'gpu_info' => $_POST['gpu_info'] ?? '',
            'platform' => $_POST['platform'] ?? '',
            'vendor' => $_POST['vendor'] ?? '',
            'battery_charging' => isset($_POST['battery_charging']) ? $_POST['battery_charging'] : null,
            'battery_level' => isset($_POST['battery_level']) ? (float)$_POST['battery_level'] : null
        ];
    }
    
    // جمع معلومات الشبكة
    private function collectNetworkInfo($post_data) {
        $connection = $post_data['connection'] ?? [];
        
        return [
            'connection_type' => $connection['type'] ?? 'unknown',
            'connection_speed' => $connection['speed'] ?? 'unknown',
            'connection_rtt' => (int)($connection['rtt'] ?? 0),
            'downlink' => (float)($connection['downlink'] ?? 0),
            'downlink_max' => (float)($connection['downlink_max'] ?? 0),
            'effective_type' => $connection['effective_type'] ?? '',
            'save_data' => isset($connection['save_data']) ? $connection['save_data'] : false,
            'online_status' => isset($post_data['online']) ? $post_data['online'] : true,
            'network_type' => $this->detectNetworkType(),
            'isp_info' => $this->detectISP()
        ];
    }
    
    // جمع معلومات الموقع
    private function collectLocationInfo($post_data) {
        return [
            'latitude' => isset($post_data['lat']) ? (float)$post_data['lat'] : null,
            'longitude' => isset($post_data['lng']) ? (float)$post_data['lng'] : null,
            'accuracy' => isset($post_data['accuracy']) ? (float)$post_data['accuracy'] : null,
            'altitude' => isset($post_data['altitude']) ? (float)$post_data['altitude'] : null,
            'altitude_accuracy' => isset($post_data['altitude_accuracy']) ? (float)$post_data['altitude_accuracy'] : null,
            'heading' => isset($post_data['heading']) ? (float)$post_data['heading'] : null,
            'speed' => isset($post_data['speed']) ? (float)$post_data['speed'] : null,
            'location_timestamp' => isset($post_data['location_timestamp']) ? (int)$post_data['location_timestamp'] : null,
            'geolocation_method' => $post_data['geolocation_method'] ?? 'unknown',
            'permission_status' => $post_data['permission_status'] ?? 'unknown',
            'location_enabled' => isset($post_data['location_enabled']) ? $post_data['location_enabled'] : false
        ];
    }
    
    // جمع البصمات
    private function collectFingerprints($post_data) {
        $canvas = $post_data['canvas_fingerprint'] ?? '';
        $webgl = $post_data['webgl_fingerprint'] ?? '';
        $audio = $post_data['audio_fingerprint'] ?? '';
        $fonts = $post_data['fonts_fingerprint'] ?? '';
        $plugins = $post_data['plugins_fingerprint'] ?? '';
        $hardware = $post_data['hardware_fingerprint'] ?? '';
        
        $combined = hash('sha256', 
            $canvas . $webgl . $audio . $fonts . $plugins . $hardware . 
            ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? '')
        );
        
        return [
            'canvas_fingerprint' => $canvas,
            'webgl_fingerprint' => $webgl,
            'audio_fingerprint' => $audio,
            'fonts_fingerprint' => $fonts,
            'plugins_fingerprint' => $plugins,
            'hardware_fingerprint' => $hardware,
            'combined_fingerprint' => $combined
        ];
    }
    
    // جمع معلومات إضافية
    private function collectExtraInfo($post_data) {
        return [
            'is_mobile' => $this->isMobile(),
            'is_tablet' => $this->isTablet(),
            'is_desktop' => !$this->isMobile() && !$this->isTablet(),
            'is_bot' => $this->isBot(),
            'viewport_width' => (int)($post_data['viewport_width'] ?? 0),
            'viewport_height' => (int)($post_data['viewport_height'] ?? 0),
            'color_gamut' => $post_data['color_gamut'] ?? '',
            'contrast_preference' => $post_data['contrast'] ?? '',
            'reduced_motion' => isset($post_data['reduced_motion']) ? $post_data['reduced_motion'] : false,
            'inverted_colors' => isset($post_data['inverted_colors']) ? $post_data['inverted_colors'] : false,
            'forced_colors' => isset($post_data['forced_colors']) ? $post_data['forced_colors'] : false,
            'monochrome' => (int)($post_data['monochrome'] ?? 0),
            'device_orientation' => $post_data['orientation'] ?? '',
            'device_pixel_ratio' => (float)($post_data['pixel_ratio'] ?? 1),
            'math_fingerprint' => $post_data['math_fingerprint'] ?? ''
        ];
    }
    
    // حفظ معلومات الجهاز
    private function saveDeviceInfo($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO device_info_full SET
            session_id = ?,
            ip_address = ?,
            user_agent = ?,
            browser_name = ?,
            browser_version = ?,
            browser_engine = ?,
            os_name = ?,
            os_version = ?,
            device_type = ?,
            device_brand = ?,
            device_model = ?,
            screen_resolution = ?,
            screen_color_depth = ?,
            screen_pixel_ratio = ?,
            window_resolution = ?,
            language = ?,
            languages = ?,
            timezone = ?,
            timezone_offset = ?,
            cookies_enabled = ?,
            javascript_enabled = ?,
            do_not_track = ?,
            hardware_cores = ?,
            hardware_memory = ?,
            hardware_ram = ?,
            connection_type = ?,
            connection_speed = ?,
            connection_rtt = ?,
            battery_charging = ?,
            battery_level = ?,
            touch_support = ?,
            touch_points = ?,
            plugins = ?,
            fonts = ?,
            canvas_fingerprint = ?,
            webgl_fingerprint = ?,
            audio_fingerprint = ?,
            installed_apps = ?,
            referrer = ?,
            page_url = ?,
            visit_time = ?,
            visit_date = ?,
            visit_hour = ?,
            day_of_week = ?,
            is_mobile = ?,
            is_tablet = ?,
            is_desktop = ?,
            is_bot = ?,
            latitude = ?,
            longitude = ?,
            accuracy = ?,
            altitude = ?,
            altitude_accuracy = ?,
            heading = ?,
            speed = ?,
            location_timestamp = ?,
            geolocation_method = ?,
            permission_status = ?
        ");
        
        $stmt->bind_param(
            "ssssssssssssssssssssiiiiiiisiiiiisssssssssssssiiiiiiidddddddds",
            $data['session_id'],
            $data['ip_address'],
            $data['user_agent'],
            $data['browser_name'],
            $data['browser_version'],
            $data['browser_engine'],
            $data['os_name'],
            $data['os_version'],
            $data['device_type'],
            $data['device_brand'],
            $data['device_model'],
            $data['screen_resolution'],
            $data['screen_color_depth'],
            $data['screen_pixel_ratio'],
            $data['window_resolution'],
            $data['language'],
            $data['languages'],
            $data['timezone'],
            $data['timezone_offset'],
            $data['cookies_enabled'],
            $data['javascript_enabled'],
            $data['do_not_track'],
            $data['hardware_cores'],
            $data['hardware_memory'],
            $data['hardware_ram'],
            $data['connection_type'],
            $data['connection_speed'],
            $data['connection_rtt'],
            $data['battery_charging'],
            $data['battery_level'],
            $data['touch_support'],
            $data['touch_points'],
            $data['plugins'],
            $data['fonts'],
            $data['canvas_fingerprint'],
            $data['webgl_fingerprint'],
            $data['audio_fingerprint'],
            $data['installed_apps'],
            $data['referrer'],
            $data['page_url'],
            $data['visit_time'],
            $data['visit_date'],
            $data['visit_hour'],
            $data['day_of_week'],
            $data['is_mobile'],
            $data['is_tablet'],
            $data['is_desktop'],
            $data['is_bot'],
            $data['latitude'],
            $data['longitude'],
            $data['accuracy'],
            $data['altitude'],
            $data['altitude_accuracy'],
            $data['heading'],
            $data['speed'],
            $data['location_timestamp'],
            $data['geolocation_method'],
            $data['permission_status']
        );
        
        return $stmt->execute();
    }
    
    // حفظ المحاولة الفاشلة
    private function saveFailedAttempt($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO failed_attempts_full SET
            attempt_type = ?,
            username = ?,
            password_hash = ?,
            ip_address = ?,
            user_agent = ?,
            device_info = ?,
            location_data = ?,
            form_data = ?,
            headers = ?,
            cookies = ?,
            session_data = ?,
            error_message = ?,
            error_code = ?,
            error_stack = ?,
            fail_reason = ?,
            attempt_time = ?,
            attempt_date = ?,
            attempt_hour = ?
        ");
        
        $stmt->bind_param(
            "sssssssssssssssssi",
            $data['attempt_type'],
            $data['username'],
            $data['password_hash'],
            $data['ip_address'],
            $data['user_agent'],
            $data['device_info'],
            $data['location_data'],
            $data['form_data'],
            $data['headers'],
            $data['cookies'],
            $data['session_data'],
            $data['error_message'],
            $data['error_code'],
            $data['error_stack'],
            $data['fail_reason'],
            $data['attempt_time'],
            $data['attempt_date'],
            $data['attempt_hour']
        );
        
        return $stmt->execute();
    }
    
    // تحديث تتبع الجلسة
    private function updateSessionTracking() {
        $fingerprint = $this->generateFingerprint();
        $now = date('Y-m-d H:i:s');
        
        // التحقق من وجود الجلسة
        $stmt = $this->conn->prepare("
            SELECT id, page_views, pages_visited 
            FROM session_tracking 
            WHERE session_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->bind_param("s", $this->session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // تحديث الجلسة الموجودة
            $page_views = $row['page_views'] + 1;
            $pages_visited = json_decode($row['pages_visited'] ?? '[]', true);
            $pages_visited[] = [
                'url' => $this->getCurrentUrl(),
                'time' => $now
            ];
            
            $stmt = $this->conn->prepare("
                UPDATE session_tracking SET
                last_activity = ?,
                duration = TIMESTAMPDIFF(SECOND, start_time, ?),
                page_views = ?,
                pages_visited = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssisi", $now, $now, $page_views, json_encode($pages_visited), $row['id']);
            $stmt->execute();
            
        } else {
            // إنشاء جلسة جديدة
            $stmt = $this->conn->prepare("
                INSERT INTO session_tracking
                (session_id, ip_address, user_agent, device_fingerprint, start_time, last_activity, pages_visited)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $pages_visited = json_encode([[
                'url' => $this->getCurrentUrl(),
                'time' => $now
            ]]);
            $stmt->bind_param(
                "sssssss",
                $this->session_id,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                $fingerprint,
                $now,
                $now,
                $pages_visited
            );
            $stmt->execute();
        }
    }
    
    // تتبع البصمة
    private function trackFingerprint($fingerprints) {
        $combined_hash = $fingerprints['combined_fingerprint'];
        $now = date('Y-m-d H:i:s');
        
        $stmt = $this->conn->prepare("
            SELECT id, visit_count, first_seen 
            FROM fingerprint_tracking 
            WHERE combined_hash = ?
        ");
        $stmt->bind_param("s", $combined_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $visit_count = $row['visit_count'] + 1;
            $stmt = $this->conn->prepare("
                UPDATE fingerprint_tracking SET
                last_seen = ?,
                visit_count = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $now, $visit_count, $row['id']);
            $stmt->execute();
            
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO fingerprint_tracking SET
                fingerprint_id = ?,
                ip_address = ?,
                user_agent = ?,
                canvas_hash = ?,
                webgl_hash = ?,
                audio_hash = ?,
                fonts_hash = ?,
                plugins_hash = ?,
                hardware_hash = ?,
                combined_hash = ?,
                first_seen = ?,
                last_seen = ?,
                visit_count = 1
            ");
            $stmt->bind_param(
                "ssssssssssss",
                $this->fingerprint,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                $fingerprints['canvas_fingerprint'],
                $fingerprints['webgl_fingerprint'],
                $fingerprints['audio_fingerprint'],
                $fingerprints['fonts_fingerprint'],
                $fingerprints['plugins_fingerprint'],
                $fingerprints['hardware_fingerprint'],
                $combined_hash,
                $now,
                $now
            );
            $stmt->execute();
        }
    }
    
    // التحقق من المحاولات المتكررة
    private function checkRepeatedAttempts($username, $ip) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts 
            FROM failed_attempts_full 
            WHERE (username = ? OR ip_address = ?)
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['attempts'] >= 10) {
            error_log("REPEATED FAILED ATTEMPTS - Username: $username, IP: $ip, Attempts: {$row['attempts']}");
            
            // يمكن إضافة كود لحظر مؤقت
            $this->temporaryBlock($ip);
        }
    }
    
    // حظر مؤقت
    private function temporaryBlock($ip) {
        $block_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        $stmt = $this->conn->prepare("
            INSERT INTO blocked_ips (ip_address, block_until, reason) 
            VALUES (?, ?, 'Too many failed attempts')
            ON DUPLICATE KEY UPDATE 
            block_until = VALUES(block_until),
            attempts = attempts + 1
        ");
        $stmt->bind_param("ss", $ip, $block_time);
        $stmt->execute();
    }
    
    // دعم الدوال المساعدة
    private function getRealIP() {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function getAllHeaders() {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
    
    private function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $protocol . $host . $uri;
    }
    
    private function generateFingerprint() {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];
        return hash('sha256', implode('|', $components));
    }
    
    private function getBrowserName($ua) {
        $browsers = [
            'Chrome' => 'chrome',
            'Firefox' => 'firefox',
            'Safari' => 'safari',
            'Edge' => 'edge',
            'Opera' => 'opera',
            'MSIE' => 'ie',
            'Trident' => 'ie'
        ];
        
        foreach ($browsers as $name => $pattern) {
            if (preg_match("/$pattern/i", $ua)) return $name;
        }
        return 'Unknown';
    }
    
    private function getBrowserVersion($ua) {
        $patterns = [
            'chrome' => 'Chrome/([0-9.]+)',
            'firefox' => 'Firefox/([0-9.]+)',
            'safari' => 'Version/([0-9.]+).*Safari',
            'edge' => 'Edge?/([0-9.]+)',
            'opera' => 'OPR/([0-9.]+)',
            'ie' => 'MSIE ([0-9.]+)'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match("/$pattern/i", $ua, $matches)) {
                return $matches[1];
            }
        }
        return 'Unknown';
    }
    
    private function getOSName($ua) {
        $oses = [
            'Windows' => 'windows',
            'macOS' => 'mac os',
            'iOS' => 'iphone|ipad|ipod',
            'Android' => 'android',
            'Linux' => 'linux',
            'Chrome OS' => 'cros'
        ];
        
        foreach ($oses as $name => $pattern) {
            if (preg_match("/$pattern/i", $ua)) return $name;
        }
        return 'Unknown';
    }
    
    private function getOSVersion($ua) {
        $os = $this->getOSName($ua);
        switch ($os) {
            case 'Windows':
                if (preg_match('/Windows NT ([0-9.]+)/', $ua, $match)) {
                    $versions = ['6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.2' => 'XP', '5.1' => 'XP', '5.0' => '2000'];
                    return $versions[$match[1]] ?? $match[1];
                }
                break;
            case 'macOS':
                if (preg_match('/Mac OS X ([0-9_]+)/', $ua, $match)) {
                    return str_replace('_', '.', $match[1]);
                }
                break;
            case 'Android':
                if (preg_match('/Android ([0-9.]+)/', $ua, $match)) {
                    return $match[1];
                }
                break;
            case 'iOS':
                if (preg_match('/OS ([0-9_]+)/', $ua, $match)) {
                    return str_replace('_', '.', $match[1]);
                }
                break;
        }
        return 'Unknown';
    }
    
    private function getDeviceType($ua) {
        if (preg_match('/tablet|ipad/i', $ua)) return 'tablet';
        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|opera mobi/i', $ua)) return 'mobile';
        return 'desktop';
    }
    
    private function isMobile() {
        return preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|opera mobi/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }
    
    private function isTablet() {
        return preg_match('/tablet|ipad/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }
    
    private function isBot() {
        $bots = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'headless'];
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        foreach ($bots as $bot) {
            if (strpos($ua, $bot) !== false) return true;
        }
        return false;
    }
    
    private function getErrorCode($message) {
        return hash('crc32', $message);
    }
    
    private function getErrorStack() {
        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        return ob_get_clean();
    }
    
    private function getFailReason($message) {
        $reasons = [
            'wrong_password' => ['كلمة مرور', 'password', 'رمز', 'pin'],
            'user_not_found' => ['مستخدم', 'user', 'account', 'حساب'],
            'location_required' => ['موقع', 'location', 'gps', 'جغرافي'],
            'csrf_error' => ['csrf', 'token', 'صالحية'],
            'rate_limit' => ['محاولات', 'attempts', 'limit', 'تجاوز'],
            'validation_error' => ['تنسيق', 'format', 'validation', 'صحيح']
        ];
        
        foreach ($reasons as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($message, $keyword) !== false) {
                    return $code;
                }
            }
        }
        
        return 'other_error';
    }
    
    private function detectNetworkType() {
        // يمكن استخدام API خارجي لجلب معلومات الشبكة
        return 'unknown';
    }
    
    private function detectISP() {
        // يمكن استخدام API خارجي لجلب معلومات مزود الخدمة
        return 'unknown';
    }
    
    private function detectIncognito() {
        // يتم الكشف من خلال JavaScript
        return isset($_POST['incognito']) ? (bool)$_POST['incognito'] : false;
    }
    
    private function logError($type, $message) {
        error_log("[$type] $message");
    }
}