<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آلة حاسبة عالمية</title>
    <style>
        /* تنسيق الآلة الحاسبة */
        .calculator-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            transition: all 0.3s ease;
        }
        
        .calculator-minimized {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #ff9500;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            cursor: pointer;
            z-index: 10000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .calculator-minimized:hover {
            background: #ffaa33;
            transform: scale(1.1);
        }
        
        .calculator {
            background: #2c2c2c;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            width: 320px;
            overflow: hidden;
            border: 1px solid #444;
        }
        
        .calculator-header {
            background: #ff9500;
            color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            user-select: none;
        }
        
        .calculator-title {
            font-size: 16px;
            font-weight: bold;
        }
        
        .calculator-controls {
            display: flex;
            gap: 10px;
        }
        
        .calculator-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 25px;
            height: 25px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .calculator-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .display {
            background: #1a1a1a;
            padding: 20px;
            text-align: right;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        
        .previous {
            color: #888;
            font-size: 14px;
            min-height: 20px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .current {
            color: white;
            font-size: 36px;
            font-weight: 300;
            overflow: hidden;
            text-overflow: ellipsis;
            direction: ltr;
            text-align: right;
        }
        
        .buttons {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: #444;
            padding: 1px;
        }
        
        .calc-btn {
            border: none;
            background: #3c3c3c;
            color: white;
            font-size: 20px;
            height: 60px;
            cursor: pointer;
            transition: all 0.2s;
            outline: none;
        }
        
        .calc-btn:hover {
            background: #4c4c4c;
        }
        
        .calc-btn:active {
            background: #505050;
        }
        
        .operator {
            background: #ff9500;
            font-size: 24px;
        }
        
        .operator:hover {
            background: #ffaa33;
        }
        
        .equals {
            background: #ff9500;
        }
        
        .zero {
            grid-column: span 2;
        }
        
        .function {
            background: #505050;
            font-size: 18px;
        }
        
        .function:hover {
            background: #606060;
        }
        
        /* زر إظهار الآلة الحاسبة في جميع الصفحات */
        .add-calculator-btn {
            position: fixed;
            bottom: 90px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 20px;
            cursor: pointer;
            z-index: 9999;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-calculator-btn:hover {
            background: #0056b3;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- زر لإضافة الآلة الحاسبة إذا لم تكن موجودة -->
    <button class="add-calculator-btn" id="addCalculatorBtn" title="إضافة آلة حاسبة">+</button>
    
    <!-- الآلة الحاسبة - مخفية افتراضياً -->
    <div id="calculatorContainer" class="calculator-container" style="display: none;">
        <div class="calculator" id="calculator">
            <div class="calculator-header" id="calculatorHeader">
                <div class="calculator-title">آلة حاسبة</div>
                <div class="calculator-controls">
                    <button class="calculator-btn" id="minimizeBtn" title="تصغير">−</button>
                    <button class="calculator-btn" id="closeBtn" title="إغلاق">×</button>
                </div>
            </div>
            <div class="display">
                <div class="previous" id="previous"></div>
                <div class="current" id="current">0</div>
            </div>
            <div class="buttons">
                <button class="function calc-btn" onclick="calcClearAll()">C</button>
                <button class="function calc-btn" onclick="calcBackspace()">⌫</button>
                <button class="function calc-btn" onclick="calcPercentage()">%</button>
                <button class="operator calc-btn" onclick="calcAppendOperator('/')">÷</button>
                
                <button class="calc-btn" onclick="calcAppendNumber('7')">7</button>
                <button class="calc-btn" onclick="calcAppendNumber('8')">8</button>
                <button class="calc-btn" onclick="calcAppendNumber('9')">9</button>
                <button class="operator calc-btn" onclick="calcAppendOperator('*')">×</button>
                
                <button class="calc-btn" onclick="calcAppendNumber('4')">4</button>
                <button class="calc-btn" onclick="calcAppendNumber('5')">5</button>
                <button class="calc-btn" onclick="calcAppendNumber('6')">6</button>
                <button class="operator calc-btn" onclick="calcAppendOperator('-')">-</button>
                
                <button class="calc-btn" onclick="calcAppendNumber('1')">1</button>
                <button class="calc-btn" onclick="calcAppendNumber('2')">2</button>
                <button class="calc-btn" onclick="calcAppendNumber('3')">3</button>
                <button class="operator calc-btn" onclick="calcAppendOperator('+')">+</button>
                
                <button class="zero calc-btn" onclick="calcAppendNumber('0')">0</button>
                <button class="calc-btn" onclick="calcAppendDecimal()">.</button>
                <button class="equals calc-btn" onclick="calcCalculate()">=</button>
            </div>
        </div>
    </div>

    <!-- حالة التصغير -->
    <button class="calculator-minimized" id="minimizedCalculator" style="display: none;">🧮</button>

    <script>
        // حالة الآلة الحاسبة
        let calcState = {
            currentDisplay: '0',
            previousDisplay: '',
            operation: null,
            waitingForNewNumber: false,
            isMinimized: false,
            position: { x: 0, y: 0 },
            isDragging: false,
            dragOffset: { x: 0, y: 0 }
        };

        // حفظ الحالة في localStorage
        function saveCalculatorState() {
            localStorage.setItem('calculatorState', JSON.stringify(calcState));
        }

        // تحميل الحالة من localStorage
        function loadCalculatorState() {
            const saved = localStorage.getItem('calculatorState');
            if (saved) {
                calcState = JSON.parse(saved);
                updateCalculatorDisplay();
                
                // استعادة الوضع
                if (calcState.isMinimized) {
                    document.getElementById('calculatorContainer').style.display = 'none';
                    document.getElementById('minimizedCalculator').style.display = 'flex';
                } else {
                    document.getElementById('calculatorContainer').style.display = 'block';
                    document.getElementById('minimizedCalculator').style.display = 'none';
                }
                
                // استعادة الموضع
                if (calcState.position.x !== 0 || calcState.position.y !== 0) {
                    const container = document.getElementById('calculatorContainer');
                    container.style.right = 'auto';
                    container.style.bottom = 'auto';
                    container.style.left = calcState.position.x + 'px';
                    container.style.top = calcState.position.y + 'px';
                }
            }
        }

        // تحديث العرض
        function updateCalculatorDisplay() {
            document.getElementById('current').textContent = calcState.currentDisplay;
            document.getElementById('previous').textContent = calcState.previousDisplay;
        }

        // إضافة رقم
        function calcAppendNumber(number) {
            if (calcState.waitingForNewNumber) {
                calcState.currentDisplay = number;
                calcState.waitingForNewNumber = false;
            } else {
                calcState.currentDisplay = calcState.currentDisplay === '0' ? number : calcState.currentDisplay + number;
            }
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // إضافة علامة عشرية
        function calcAppendDecimal() {
            if (calcState.waitingForNewNumber) {
                calcState.currentDisplay = '0.';
                calcState.waitingForNewNumber = false;
            } else if (!calcState.currentDisplay.includes('.')) {
                calcState.currentDisplay += '.';
            }
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // إضافة عامل حسابي
        function calcAppendOperator(op) {
            if (calcState.currentDisplay === '0' && op !== '-') return;
            
            const inputValue = parseFloat(calcState.currentDisplay);
            
            if (calcState.previousDisplay === '') {
                calcState.previousDisplay = calcState.currentDisplay;
            } else if (calcState.operation) {
                const result = calcCalculateResult();
                calcState.previousDisplay = result;
            }
            
            calcState.operation = op;
            calcState.waitingForNewNumber = true;
            calcState.previousDisplay = `${calcState.previousDisplay} ${op}`;
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // حساب النتيجة
        function calcCalculate() {
            if (!calcState.operation || calcState.waitingForNewNumber) return;
            
            const result = calcCalculateResult();
            calcState.previousDisplay = `${calcState.previousDisplay} ${calcState.currentDisplay} =`;
            calcState.currentDisplay = result;
            calcState.operation = null;
            calcState.waitingForNewNumber = true;
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // حساب النتيجة الرياضية
        function calcCalculateResult() {
            const parts = calcState.previousDisplay.split(' ');
            const prev = parseFloat(parts[0]);
            const current = parseFloat(calcState.currentDisplay);
            
            if (isNaN(prev) || isNaN(current)) return '0';
            
            let result;
            switch (calcState.operation) {
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
                default:
                    return current;
            }
            
            if (typeof result === 'number') {
                return Number.isInteger(result) ? result.toString() : parseFloat(result.toFixed(10)).toString();
            }
            
            return result;
        }

        // النسبة المئوية
        function calcPercentage() {
            calcState.currentDisplay = (parseFloat(calcState.currentDisplay) / 100).toString();
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // مسح الكل
        function calcClearAll() {
            calcState.currentDisplay = '0';
            calcState.previousDisplay = '';
            calcState.operation = null;
            calcState.waitingForNewNumber = false;
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // حذف آخر رقم
        function calcBackspace() {
            if (calcState.currentDisplay.length === 1) {
                calcState.currentDisplay = '0';
            } else {
                calcState.currentDisplay = calcState.currentDisplay.slice(0, -1);
            }
            updateCalculatorDisplay();
            saveCalculatorState();
        }

        // التحكم في عرض الآلة الحاسبة
        function toggleCalculator() {
            const container = document.getElementById('calculatorContainer');
            const minimized = document.getElementById('minimizedCalculator');
            
            if (calcState.isMinimized) {
                // إظهار الآلة الحاسبة
                container.style.display = 'block';
                minimized.style.display = 'none';
                calcState.isMinimized = false;
            } else {
                // تصغير الآلة الحاسبة
                container.style.display = 'none';
                minimized.style.display = 'flex';
                calcState.isMinimized = true;
            }
            saveCalculatorState();
        }

        // إغلاق الآلة الحاسبة
        function closeCalculator() {
            document.getElementById('calculatorContainer').style.display = 'none';
            document.getElementById('minimizedCalculator').style.display = 'none';
            calcState.isMinimized = false;
            saveCalculatorState();
        }

        // إظهار الآلة الحاسبة
        function showCalculator() {
            document.getElementById('calculatorContainer').style.display = 'block';
            document.getElementById('minimizedCalculator').style.display = 'none';
            calcState.isMinimized = false;
            saveCalculatorState();
        }

        // سحب الآلة الحاسبة
        function initDragAndDrop() {
            const header = document.getElementById('calculatorHeader');
            const container = document.getElementById('calculatorContainer');
            
            header.addEventListener('mousedown', startDrag);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', stopDrag);
            
            function startDrag(e) {
                calcState.isDragging = true;
                const rect = container.getBoundingClientRect();
                calcState.dragOffset.x = e.clientX - rect.left;
                calcState.dragOffset.y = e.clientY - rect.top;
                container.style.cursor = 'grabbing';
                e.preventDefault();
            }
            
            function drag(e) {
                if (!calcState.isDragging) return;
                
                container.style.position = 'fixed';
                container.style.left = (e.clientX - calcState.dragOffset.x) + 'px';
                container.style.top = (e.clientY - calcState.dragOffset.y) + 'px';
                container.style.right = 'auto';
                container.style.bottom = 'auto';
                
                // حفظ الموضع
                calcState.position.x = e.clientX - calcState.dragOffset.x;
                calcState.position.y = e.clientY - calcState.dragOffset.y;
            }
            
            function stopDrag() {
                calcState.isDragging = false;
                container.style.cursor = 'default';
                saveCalculatorState();
            }
        }

        // التحكم بلوحة المفاتيح
        function initKeyboardControls() {
            document.addEventListener('keydown', (event) => {
                // التحقق إذا كان المستخدم يكتب في حقل إدخال
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
                    return;
                }
                
                const key = event.key;
                
                if (key >= '0' && key <= '9') {
                    calcAppendNumber(key);
                } else if (key === '.') {
                    calcAppendDecimal();
                } else if (key === '+') {
                    calcAppendOperator('+');
                    event.preventDefault();
                } else if (key === '-') {
                    calcAppendOperator('-');
                    event.preventDefault();
                } else if (key === '*') {
                    calcAppendOperator('*');
                    event.preventDefault();
                } else if (key === '/') {
                    calcAppendOperator('/');
                    event.preventDefault();
                } else if (key === 'Enter' || key === '=') {
                    calcCalculate();
                    event.preventDefault();
                } else if (key === 'Escape' || key === 'Delete') {
                    calcClearAll();
                } else if (key === 'Backspace') {
                    calcBackspace();
                } else if (key === '%') {
                    calcPercentage();
                }
            });
        }

        // تهيئة الآلة الحاسبة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // تحميل الحالة المحفوظة
            loadCalculatorState();
            
            // إضافة الآلة الحاسبة تلقائياً إذا لم تكن موجودة
            setTimeout(() => {
                if (!localStorage.getItem('calculatorAdded')) {
                    showCalculator();
                    localStorage.setItem('calculatorAdded', 'true');
                }
            }, 1000);
            
            // إعداد عناصر التحكم
            document.getElementById('minimizeBtn').addEventListener('click', toggleCalculator);
            document.getElementById('closeBtn').addEventListener('click', closeCalculator);
            document.getElementById('minimizedCalculator').addEventListener('click', toggleCalculator);
            document.getElementById('addCalculatorBtn').addEventListener('click', showCalculator);
            
            // تهيئة السحب والإفلات
            initDragAndDrop();
            
            // تهيئة التحكم بلوحة المفاتيح
            initKeyboardControls();
            
            // حفظ الحالة عند مغادرة الصفحة
            window.addEventListener('beforeunload', saveCalculatorState);
        });
    </script>
</body>
</html>