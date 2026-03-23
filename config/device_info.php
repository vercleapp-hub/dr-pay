<?php
class DeviceInfo {
    
    /**
     * جمع جميع معلومات الجهاز والشبكة
     */
    public static function getAllDeviceInfo() {
        $info = [];
        
        // 1. معلومات الشبكة والأمان
        $info['network'] = self::getNetworkInfo();
        
        // 2. معلومات الجهاز والمتصفح
        $info['device'] = self::getDeviceInfo();
        
        // 3. معلومات النظام
        $info['system'] = self::getSystemInfo();
        
        // 4. معلومات الموقع
        $info['location'] = self::getLocationInfo();
        
        // 5. معلومات الشاشة والعرض
        $info['display'] = self::getDisplayInfo();
        
        // 6. معلومات العتاد
        $info['hardware'] = self::getHardwareInfo();
        
        // 7. معلومات إضافية
        $info['extras'] = self::getExtraInfo();
        
        // 8. إنشاء بصمة فريدة للجهاز
        $info['fingerprint'] = self::generateDeviceFingerprint($info);
        
        return $info;
    }
    
    /**
     * معلومات الشبكة والأمان
     */
    private static function getNetworkInfo() {
        return [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'غير معروف',
            'real_ip' => self::getRealIP(),
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'client_ip' => $_SERVER['HTTP_CLIENT_IP'] ?? null,
            'hostname' => gethostbyaddr($_SERVER['REMOTE_ADDR'] ?? '') ?: 'غير معروف',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'غير معروف',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'غير معروف',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? null,
            'connection' => $_SERVER['HTTP_CONNECTION'] ?? null,
            'https' => isset($_SERVER['HTTPS']) ? 'نعم' : 'لا',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'غير معروف',
            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'غير معروف',
            'server_port' => $_SERVER['SERVER_PORT'] ?? null,
            'remote_port' => $_SERVER['REMOTE_PORT'] ?? null,
            'is_tor' => self::isTorExitNode($_SERVER['REMOTE_ADDR'] ?? ''),
            'is_proxy' => self::isProxyConnection(),
            'cloudflare' => isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? [
                'country' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null,
                'ray' => $_SERVER['HTTP_CF_RAY'] ?? null,
                'connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null
            ] : null
        ];
    }
    
    /**
     * معلومات الجهاز والمتصفح
     */
    private static function getDeviceInfo() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return [
            'browser' => self::detectBrowser($user_agent),
            'browser_version' => self::getBrowserVersion($user_agent),
            'os' => self::detectOS($user_agent),
            'os_version' => self::getOSVersion($user_agent),
            'device_type' => self::detectDeviceType($user_agent),
            'device_brand' => self::detectDeviceBrand($user_agent),
            'device_model' => self::detectDeviceModel($user_agent),
            'is_mobile' => self::isMobile($user_agent),
            'is_tablet' => self::isTablet($user_agent),
            'is_desktop' => self::isDesktop($user_agent),
            'is_bot' => self::isBot($user_agent),
            'user_agent_raw' => $user_agent
        ];
    }
    
    /**
     * معلومات النظام
     */
    private static function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'غير معروف',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'غير معروف',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'غير معروف',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'غير معروف',
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
            'timezone' => date_default_timezone_get(),
            'session_status' => session_status(),
            'cookies_enabled' => isset($_COOKIE[session_name()]) ? 'نعم' : 'لا'
        ];
    }
    
    /**
     * معلومات الموقع
     */
    private static function getLocationInfo() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            try {
                // استخدام خدمة مجانية للحصول على معلومات الموقع
                $location_data = @file_get_contents("http://ip-api.com/json/{$ip}");
                if ($location_data) {
                    $location = json_decode($location_data, true);
                    if ($location['status'] === 'success') {
                        return [
                            'country' => $location['country'] ?? 'غير معروف',
                            'country_code' => $location['countryCode'] ?? null,
                            'region' => $location['regionName'] ?? null,
                            'region_code' => $location['region'] ?? null,
                            'city' => $location['city'] ?? 'غير معروف',
                            'zip' => $location['zip'] ?? null,
                            'lat' => $location['lat'] ?? null,
                            'lon' => $location['lon'] ?? null,
                            'timezone' => $location['timezone'] ?? null,
                            'isp' => $location['isp'] ?? 'غير معروف',
                            'org' => $location['org'] ?? null,
                            'as' => $location['as'] ?? null,
                            'query' => $location['query'] ?? null
                        ];
                    }
                }
            } catch (Exception $e) {
                // تجاهل الخطأ
            }
        }
        
        return ['country' => 'غير معروف', 'city' => 'غير معروف', 'isp' => 'غير معروف'];
    }
    
    /**
     * معلومات الشاشة والعرض (يتم جمعها من JavaScript)
     */
    private static function getDisplayInfo() {
        return [
            'screen_width' => $_POST['screen_width'] ?? null,
            'screen_height' => $_POST['screen_height'] ?? null,
            'avail_width' => $_POST['avail_width'] ?? null,
            'avail_height' => $_POST['avail_height'] ?? null,
            'color_depth' => $_POST['color_depth'] ?? null,
            'pixel_ratio' => $_POST['pixel_ratio'] ?? null,
            'viewport_width' => $_POST['viewport_width'] ?? null,
            'viewport_height' => $_POST['viewport_height'] ?? null,
            'orientation' => $_POST['orientation'] ?? 'غير معروف',
            'touch_support' => isset($_POST['touch_support']) ? ($_POST['touch_support'] ? 'نعم' : 'لا') : 'غير معروف',
            'device_memory' => $_POST['device_memory'] ?? null,
            'hardware_concurrency' => $_POST['hardware_concurrency'] ?? null
        ];
    }
    
    /**
     * معلومات العتاد (يتم جمعها من JavaScript)
     */
    private static function getHardwareInfo() {
        return [
            'cores' => $_POST['cpu_cores'] ?? null,
            'memory' => $_POST['device_memory'] ?? null,
            'storage' => $_POST['storage'] ?? null,
            'battery' => $_POST['battery'] ?? null,
            'connection' => $_POST['connection_type'] ?? null,
            'network_type' => $_POST['effective_type'] ?? null,
            'downlink' => $_POST['downlink'] ?? null,
            'rtt' => $_POST['rtt'] ?? null,
            'save_data' => isset($_POST['save_data']) ? ($_POST['save_data'] ? 'نعم' : 'لا') : 'غير معروف'
        ];
    }
    
    /**
     * معلومات إضافية (يتم جمعها من JavaScript)
     */
    private static function getExtraInfo() {
        return [
            'timezone' => $_POST['timezone'] ?? null,
            'locale' => $_POST['locale'] ?? null,
            'platform' => $_POST['platform'] ?? null,
            'vendor' => $_POST['vendor'] ?? null,
            'max_touch_points' => $_POST['max_touch_points'] ?? null,
            'webgl_renderer' => $_POST['webgl_renderer'] ?? null,
            'webgl_vendor' => $_POST['webgl_vendor'] ?? null,
            'canvas_fingerprint' => $_POST['canvas_fp'] ?? null,
            'fonts' => isset($_POST['fonts']) ? json_decode($_POST['fonts'], true) : [],
            'plugins' => isset($_POST['plugins']) ? json_decode($_POST['plugins'], true) : [],
            'timezone_offset' => $_POST['timezone_offset'] ?? null,
            'daylight_saving' => isset($_POST['dst']) ? ($_POST['dst'] ? 'نعم' : 'لا') : 'غير معروف',
            'client_time' => $_POST['client_time'] ?? null
        ];
    }
    
    /**
     * توليد بصمة فريدة للجهاز
     */
    private static function generateDeviceFingerprint($info) {
        $components = [
            $info['device']['browser'] ?? '',
            $info['device']['os'] ?? '',
            $info['device']['device_type'] ?? '',
            $info['display']['screen_width'] ?? '',
            $info['display']['screen_height'] ?? '',
            $info['display']['color_depth'] ?? '',
            $info['extras']['timezone'] ?? '',
            $info['network']['user_agent'] ?? '',
            $info['extras']['canvas_fingerprint'] ?? '',
            implode(',', $info['extras']['fonts'] ?? [])
        ];
        
        return hash('sha256', implode('|', array_filter($components)));
    }
    
    /**
     * الحصول على الـ IP الحقيقي
     */
    private static function getRealIP() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'غير معروف';
    }
    
    /**
     * الكشف عن المتصفح
     */
    private static function detectBrowser($user_agent) {
        $browsers = [
            '/chrome/i' => 'Chrome',
            '/firefox/i' => 'Firefox',
            '/safari/i' => 'Safari',
            '/edge/i' => 'Edge',
            '/edg/i' => 'Edge',
            '/opera|opr/i' => 'Opera',
            '/msie|trident/i' => 'Internet Explorer',
            '/brave/i' => 'Brave',
            '/vivaldi/i' => 'Vivaldi',
            '/yandex/i' => 'Yandex',
            '/maxthon/i' => 'Maxthon',
            '/ucbrowser/i' => 'UC Browser'
        ];
        
        foreach ($browsers as $regex => $browser) {
            if (preg_match($regex, $user_agent)) {
                return $browser;
            }
        }
        
        return 'غير معروف';
    }
    
    /**
     * الحصول على إصدار المتصفح
     */
    private static function getBrowserVersion($user_agent) {
        $pattern = '/(chrome|firefox|safari|edge|opera|msie|trident|edg)[\/\s](\d+\.\d+)/i';
        if (preg_match($pattern, $user_agent, $matches)) {
            return $matches[2] ?? 'غير معروف';
        }
        return 'غير معروف';
    }
    
    /**
     * الكشف عن نظام التشغيل
     */
    private static function detectOS($user_agent) {
        $oses = [
            '/windows nt 11/i' => 'Windows 11',
            '/windows nt 10/i' => 'Windows 10',
            '/windows nt 6.3/i' => 'Windows 8.1',
            '/windows nt 6.2/i' => 'Windows 8',
            '/windows nt 6.1/i' => 'Windows 7',
            '/windows nt 6.0/i' => 'Windows Vista',
            '/windows nt 5.2/i' => 'Windows Server 2003',
            '/windows nt 5.1/i' => 'Windows XP',
            '/windows nt 5.0/i' => 'Windows 2000',
            '/windows|win32/i' => 'Windows',
            '/mac os x/i' => 'macOS',
            '/macintosh/i' => 'Mac OS',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Ubuntu',
            '/android/i' => 'Android',
            '/iphone|ipad|ipod/i' => 'iOS',
            '/cros/i' => 'Chrome OS',
            '/blackberry/i' => 'BlackBerry'
        ];
        
        foreach ($oses as $regex => $os) {
            if (preg_match($regex, $user_agent)) {
                return $os;
            }
        }
        
        return 'غير معروف';
    }
    
    /**
     * الحصول على إصدار نظام التشغيل
     */
    private static function getOSVersion($user_agent) {
        $patterns = [
            '/windows nt (\d+\.\d+)/i',
            '/mac os x (\d+[._]\d+)/i',
            '/android (\d+\.\d+)/i',
            '/iphone os (\d+[._]\d+)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $user_agent, $matches)) {
                return str_replace('_', '.', $matches[1] ?? 'غير معروف');
            }
        }
        
        return 'غير معروف';
    }
    
    /**
     * الكشف عن نوع الجهاز
     */
    private static function detectDeviceType($user_agent) {
        if (self::isMobile($user_agent)) return 'هاتف';
        if (self::isTablet($user_agent)) return 'تابلت';
        if (self::isDesktop($user_agent)) return 'كمبيوتر';
        return 'غير معروف';
    }
    
    /**
     * الكشف عن ماركة الجهاز
     */
    private static function detectDeviceBrand($user_agent) {
        $brands = [
            '/iphone/i' => 'Apple',
            '/ipad/i' => 'Apple',
            '/ipod/i' => 'Apple',
            '/macintosh/i' => 'Apple',
            '/samsung/i' => 'Samsung',
            '/huawei/i' => 'Huawei',
            '/xiaomi|redmi|poco/i' => 'Xiaomi',
            '/oppo/i' => 'Oppo',
            '/vivo/i' => 'Vivo',
            '/oneplus/i' => 'OnePlus',
            '/google pixel/i' => 'Google',
            '/nokia/i' => 'Nokia',
            '/sony/i' => 'Sony',
            '/lg/i' => 'LG',
            '/htc/i' => 'HTC',
            '/motorola/i' => 'Motorola',
            '/lenovo/i' => 'Lenovo',
            '/asus/i' => 'ASUS',
            '/dell/i' => 'Dell',
            '/hp/i' => 'HP'
        ];
        
        foreach ($brands as $regex => $brand) {
            if (preg_match($regex, $user_agent)) {
                return $brand;
            }
        }
        
        return 'غير معروف';
    }
    
    /**
     * الكشف عن موديل الجهاز
     */
    private static function detectDeviceModel($user_agent) {
        $models = [
            '/iphone (\d+)/i' => 'iPhone $1',
            '/ipad (\d+)/i' => 'iPad $1',
            '/galaxy s(\d+)/i' => 'Galaxy S$1',
            '/galaxy note(\d+)/i' => 'Galaxy Note$1',
            '/redmi note (\d+)/i' => 'Redmi Note $1',
            '/poco (\w+)/i' => 'POCO $1'
        ];
        
        foreach ($models as $regex => $model) {
            if (preg_match($regex, $user_agent, $matches)) {
                return str_replace('$1', $matches[1] ?? '', $model);
            }
        }
        
        return 'غير معروف';
    }
    
    /**
     * التحقق إذا كان الجهاز موبايل
     */
    private static function isMobile($user_agent) {
        return preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $user_agent);
    }
    
    /**
     * التحقق إذا كان الجهاز تابلت
     */
    private static function isTablet($user_agent) {
        return preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $user_agent);
    }
    
    /**
     * التحقق إذا كان الجهاز كمبيوتر
     */
    private static function isDesktop($user_agent) {
        return !self::isMobile($user_agent) && !self::isTablet($user_agent);
    }
    
    /**
     * التحقق إذا كان بوت
     */
    private static function isBot($user_agent) {
        return preg_match('/bot|crawl|slurp|spider|google|yahoo|bing|facebook/i', $user_agent);
    }
    
    /**
     * التحقق إذا كان اتصال TOR
     */
    private static function isTorExitNode($ip) {
        // قائمة جزئية لعناوين TOR الخروج (يمكن تحديثها)
        $tor_exits = [
            // يمكن إضافة عناوين TOR معروفة
        ];
        
        return in_array($ip, $tor_exits);
    }
    
    /**
     * التحقق إذا كان اتصال بروكسي
     */
    private static function isProxyConnection() {
        $proxy_headers = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR_IP',
            'VIA',
            'X_FORWARDED_FOR',
            'FORWARDED_FOR',
            'X_FORWARDED',
            'FORWARDED',
            'CLIENT_IP',
            'FORWARDED_FOR_IP',
            'HTTP_PROXY_CONNECTION'
        ];
        
        foreach ($proxy_headers as $header) {
            if (isset($_SERVER[$header])) {
                return true;
            }
        }
        
        return false;
    }
}
?>