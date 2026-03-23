<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_destroy();
    header('Location: login.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'مستخدم';
$theme = $_COOKIE['theme'] ?? 'light';

// مصفوفة القوائم
$menu_items = [
    '👥 العملاء' => 'clients/',
    '🧾 العمليات' => 'operations/',
    '🧮 الحاسبة' => 'javascript:void(0)', // سيتم التحكم بها بـ JS
    '💳 الدفع' => 'payments/',
    '📱 الكروت' => 'cards/',
    '💰 الكاش' => 'cash/',
    '📊 التقارير' => 'reports/',
    '⚙️ الإعدادات' => 'settings/'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم</title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --primary: #2563eb;
            --hover-bg: #f1f5f9;
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --text-color: #f1f5f9;
            --card-bg: #1e293b;
            --border-color: #334155;
            --primary: #3b82f6;
            --hover-bg: #1e293b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .header {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .user-info h2 {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background: #dc2626;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.1);
        }
        
        .card-icon {
            font-size: 40px;
            margin-bottom: 15px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
        }
        
        .footer-links {
            margin-top: 10px;
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* تصميم الآلة الحاسبة العائمة */
        .calculator-floating {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .calculator-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .calculator-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .calculator-window {
            position: absolute;
            bottom: 70px;
            left: 0;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 300px;
            overflow: hidden;
            display: none;
        }
        
        .calculator-header {
            background: var(--primary);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
        }
        
        .calculator-display {
            padding: 20px;
            background: var(--hover-bg);
            text-align: right;
            font-size: 24px;
            min-height: 80px;
            direction: ltr;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .calculator-buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: var(--border-color);
        }
        
        .calc-btn {
            border: none;
            background: var(--card-bg);
            color: var(--text-color);
            padding: 20px;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .calc-btn:hover {
            background: var(--hover-bg);
        }
        
        .calc-operator {
            background: var(--primary);
            color: white;
        }
        
        .calc-clear {
            background: #dc2626;
            color: white;
        }
        
        /* تحسينات للشاشات الصغيرة */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .calculator-window {
                width: 280px;
                left: -110px;
            }
        }
        
        @media (max-width: 480px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .controls {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="user-info">
                <h2>مرحباً <?= htmlspecialchars($user_name) ?></h2>
            </div>
            <div class="controls">
                <button class="btn" onclick="toggleTheme()">
                    <span id="theme-icon">🌙</span> المظهر
                </button>
                <button class="btn" onclick="location.href='dashboard1.php'">
                    📊 لوحة الإحصائيات
                </button>
                <button class="btn btn-logout" onclick="confirmLogout()">
                    🚪 خروج
                </button>
            </div>
        </header>
        
        <main class="grid">
            <?php foreach ($menu_items as $title => $link): ?>
                <?php if ($title === '🧮 الحاسبة'): ?>
                    <a href="javascript:void(0)" class="card" onclick="toggleCalculator()">
                        <div class="card-icon">🧮</div>
                        <div class="card-title">الحاسبة</div>
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($link) ?>" class="card">
                        <div class="card-icon"><?= mb_substr($title, 0, 2) ?></div>
                        <div class="card-title"><?= mb_substr($title, 2) ?></div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </main>
        
        <footer class="footer">
            <div>© <?= date('Y') ?> جميع الحقوق محفوظة</div>
            <div class="footer-links">
                <a href="dashboard1.php">📱 سجلات الدخول</a>
                <a href="#" onclick="confirmLogout()">🚪 خروج</a>
            </div>
        </footer>
    </div>
    
    <!-- الآلة الحاسبة العائمة -->
    <div class="calculator-floating">
        <div class="calculator-window" id="calculatorWindow">
            <div class="calculator-header" id="calculatorHeader">
                <span>آلة حاسبة</span>
                <button class="calc-btn" onclick="toggleCalculator()" style="background:none;padding:5px;font-size:16px;">✕</button>
            </div>
            <div class="calculator-display" id="calcDisplay">0</div>
            <div class="calculator-buttons">
                <button class="calc-btn calc-clear" onclick="calcClear()">C</button>
                <button class="calc-btn" onclick="calcBackspace()">⌫</button>
                <button class="calc-btn calc-operator" onclick="calcOperator('%')">%</button>
                <button class="calc-btn calc-operator" onclick="calcOperator('/')">÷</button>
                
                <button class="calc-btn" onclick="calcNumber('7')">7</button>
                <button class="calc-btn" onclick="calcNumber('8')">8</button>
                <button class="calc-btn" onclick="calcNumber('9')">9</button>
                <button class="calc-btn calc-operator" onclick="calcOperator('*')">×</button>
                
                <button class="calc-btn" onclick="calcNumber('4')">4</button>
                <button class="calc-btn" onclick="calcNumber('5')">5</button>
                <button class="calc-btn" onclick="calcNumber('6')">6</button>
                <button class="calc-btn calc-operator" onclick="calcOperator('-')">-</button>
                
                <button class="calc-btn" onclick="calcNumber('1')">1</button>
                <button class="calc-btn" onclick="calcNumber('2')">2</button>
                <button class="calc-btn" onclick="calcNumber('3')">3</button>
                <button class="calc-btn calc-operator" onclick="calcOperator('+')">+</button>
                
                <button class="calc-btn" style="grid-column: span 2;" onclick="calcNumber('0')">0</button>
                <button class="calc-btn" onclick="calcDecimal()">.</button>
                <button class="calc-btn calc-operator" onclick="calcEquals()">=</button>
            </div>
        </div>
        <button class="calculator-toggle" onclick="toggleCalculator()">🧮</button>
    </div>
    
    <script>
        // تبديل المظهر
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            document.cookie = `theme=${newTheme}; path=/; max-age=${60*60*24*365}`;
            
            // تغيير الأيقونة
            const themeIcon = document.getElementById('theme-icon');
            themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }
        
        // تأكيد الخروج
        function confirmLogout() {
            if (confirm('هل تريد تسجيل الخروج؟')) {
                location.href = '?logout=1';
            }
        }
        
        // الآلة الحاسبة - المتغيرات
        let calcDisplay = '0';
        let prevValue = '';
        let currentOperator = '';
        let waitingForNewNumber = false;
        
        // تحديث عرض الآلة الحاسبة
        function updateCalcDisplay() {
            document.getElementById('calcDisplay').textContent = calcDisplay;
        }
        
        // إدخال رقم
        function calcNumber(num) {
            if (waitingForNewNumber) {
                calcDisplay = num;
                waitingForNewNumber = false;
            } else {
                calcDisplay = calcDisplay === '0' ? num : calcDisplay + num;
            }
            updateCalcDisplay();
        }
        
        // علامة عشرية
        function calcDecimal() {
            if (!calcDisplay.includes('.')) {
                calcDisplay += '.';
                updateCalcDisplay();
            }
        }
        
        // عمليات حسابية
        function calcOperator(op) {
            if (calcDisplay !== '0') {
                if (currentOperator && !waitingForNewNumber) {
                    calcEquals();
                }
                prevValue = calcDisplay;
                currentOperator = op;
                waitingForNewNumber = true;
            }
        }
        
        // حساب النتيجة
        function calcEquals() {
            if (!currentOperator || !prevValue) return;
            
            const prev = parseFloat(prevValue);
            const current = parseFloat(calcDisplay);
            let result;
            
            switch (currentOperator) {
                case '+':
                    result = prev + current;
                    break;
                case '-':
                    result = prev - current;
                    break;
                case '*':
                    result = prev * current;
                    break;
                case '/':
                    result = current !== 0 ? prev / current : 'خطأ';
                    break;
                case '%':
                    result = prev % current;
                    break;
                default:
                    return;
            }
            
            calcDisplay = typeof result === 'number' ? 
                (Number.isInteger(result) ? result.toString() : result.toFixed(4)) : 
                result;
            
            currentOperator = '';
            prevValue = '';
            waitingForNewNumber = true;
            updateCalcDisplay();
        }
        
        // مسح
        function calcClear() {
            calcDisplay = '0';
            prevValue = '';
            currentOperator = '';
            waitingForNewNumber = false;
            updateCalcDisplay();
        }
        
        // حذف آخر رقم
        function calcBackspace() {
            if (calcDisplay.length > 1) {
                calcDisplay = calcDisplay.slice(0, -1);
            } else {
                calcDisplay = '0';
            }
            updateCalcDisplay();
        }
        
        // تبديل إظهار/إخفاء الآلة الحاسبة
        function toggleCalculator() {
            const calculatorWindow = document.getElementById('calculatorWindow');
            calculatorWindow.style.display = calculatorWindow.style.display === 'block' ? 'none' : 'block';
        }
        
        // تفعيل سحب الآلة الحاسبة
        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        
        document.getElementById('calculatorHeader').addEventListener('mousedown', startDrag);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDrag);
        
        function startDrag(e) {
            isDragging = true;
            const rect = document.getElementById('calculatorWindow').getBoundingClientRect();
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            e.preventDefault();
        }
        
        function drag(e) {
            if (!isDragging) return;
            
            const calculatorWindow = document.getElementById('calculatorWindow');
            calculatorWindow.style.position = 'fixed';
            calculatorWindow.style.left = (e.clientX - dragOffset.x) + 'px';
            calculatorWindow.style.bottom = 'auto';
            calculatorWindow.style.top = (e.clientY - dragOffset.y) + 'px';
        }
        
        function stopDrag() {
            isDragging = false;
        }
        
        // إدارة المهلة (Time-out)
        let timeoutTimer;
        const timeoutDuration = 30 * 60 * 1000; // 30 دقيقة
        
        function resetTimeout() {
            clearTimeout(timeoutTimer);
            timeoutTimer = setTimeout(() => {
                if (confirm('انتهت المهلة، هل تريد تحديث الصفحة؟')) {
                    location.reload();
                }
            }, timeoutDuration);
        }
        
        // إعادة تعيين المهلة عند التفاعل مع الصفحة
        ['click', 'mousemove', 'keydown'].forEach(event => {
            document.addEventListener(event, resetTimeout);
        });
        
        // اختصارات لوحة المفاتيح
        document.addEventListener('keydown', function(e) {
            // Ctrl + T لتبديل المظهر
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                toggleTheme();
            }
            
            // Ctrl + L لتسجيل الخروج
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                confirmLogout();
            }
            
            // Esc لإغلاق الآلة الحاسبة
            if (e.key === 'Escape') {
                const calculatorWindow = document.getElementById('calculatorWindow');
                if (calculatorWindow.style.display === 'block') {
                    toggleCalculator();
                }
            }
        });
        
        // تهيئة المهلة
        resetTimeout();
        
        // تعيين أيقونة المظهر الصحيحة عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const themeIcon = document.getElementById('theme-icon');
            themeIcon.textContent = currentTheme === 'dark' ? '☀️' : '🌙';
        });
    </script>
</body>
</html>