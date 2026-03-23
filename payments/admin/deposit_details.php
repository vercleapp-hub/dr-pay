<?php
/************************************
 * Deposit Details – Admin
 ************************************/

require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// التحقق من وجود ID الإيداع
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: pending.php');
    exit();
}

$deposit_id = intval($_GET['id']);
$errors = [];
$success_messages = [];

// جلب بيانات الإيداع
try {
    $stmt = $conn->prepare("
        SELECT d.*, u.name, u.email, u.phone, u.balance as user_balance, 
               u.id as user_id, u.created_at as user_created
        FROM deposits d
        JOIN users u ON u.id = d.user_id
        WHERE d.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
    }
    
    $stmt->bind_param('i', $deposit_id);
    
    if (!$stmt->execute()) {
        throw new Exception("خطأ في تنفيذ الاستعلام: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: pending.php?error=deposit_not_found');
        exit();
    }
    
    $deposit = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $errors[] = "خطأ في جلب بيانات الإيداع: " . $e->getMessage();
    $deposit = null;
}

// جلب تاريخ الإيداعات السابقة لنفس المستخدم
if ($deposit) {
    try {
        $prev_deposits = $conn->query("
            SELECT id, amount, status, created_at
            FROM deposits 
            WHERE user_id = " . intval($deposit['user_id']) . " 
            AND id != " . intval($deposit_id) . "
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        
        if (!$prev_deposits) {
            throw new Exception("خطأ في جلب الإيداعات السابقة: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $prev_deposits = false;
    }

    // جلب العمليات المرتبطة
    try {
        $transactions = $conn->query("
            SELECT t.*
            FROM transactions t
            WHERE t.user_id = " . intval($deposit['user_id']) . "
            ORDER BY t.created_at DESC 
            LIMIT 10
        );
        
        if (!$transactions) {
            throw new Exception("خطأ في جلب العمليات: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $transactions = false;
    }

    // التحقق من وجود جدول deposit_history
    $has_history_table = false;
    $history = false;
    
    try {
        // محاولة جلب البيانات من الجدول إذا كان موجودًا
        $check_history = $conn->query("
            SELECT * FROM deposit_history 
            WHERE deposit_id = " . intval($deposit_id) . "
            ORDER BY created_at DESC
        );
        
        if ($check_history) {
            $has_history_table = true;
            $history = $check_history;
        } else {
            // الجدول غير موجود
            $has_history_table = false;
        }
        
    } catch (Exception $e) {
        // الجدول غير موجود، هذا ليس خطأ
        $has_history_table = false;
    }

    // معالجة تحديث الإيداع
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($action === 'approve') {
            try {
                $conn->begin_transaction();
                
                // تحديث حالة الإيداع
                $update_stmt = $conn->prepare("
                    UPDATE deposits 
                    SET status = 'approved', 
                        approved_at = NOW(), 
                        notes = CONCAT(COALESCE(notes, ''), '\n', ?)
                    WHERE id = ? AND status = 'pending'
                ");
                
                if (!$update_stmt) {
                    throw new Exception("خطأ في إعداد استعلام التحديث: " . $conn->error);
                }
                
                $update_stmt->bind_param('si', $notes, $deposit_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("خطأ في تنفيذ التحديث: " . $update_stmt->error);
                }
                
                // زيادة رصيد المستخدم
                $balance_stmt = $conn->prepare("
                    UPDATE users 
                    SET balance = balance + ? 
                    WHERE id = ?
                ");
                
                if (!$balance_stmt) {
                    throw new Exception("خطأ في إعداد استعلام الرصيد: " . $conn->error);
                }
                
                $balance_stmt->bind_param('di', $deposit['amount'], $deposit['user_id']);
                
                if (!$balance_stmt->execute()) {
                    throw new Exception("خطأ في تحديث الرصيد: " . $balance_stmt->error);
                }
                
                // تسجيل العملية
                $transaction_stmt = $conn->prepare("
                    INSERT INTO transactions 
                    (user_id, amount, type, description, status, created_at)
                    VALUES (?, ?, 'deposit', ?, 'completed', NOW())
                ");
                
                if (!$transaction_stmt) {
                    throw new Exception("خطأ في إعداد استعلام العملية: " . $conn->error);
                }
                
                $desc = "إيداع #{$deposit_id} - {$deposit['amount']} EGP";
                $transaction_stmt->bind_param('ids', $deposit['user_id'], $deposit['amount'], $desc);
                
                if (!$transaction_stmt->execute()) {
                    throw new Exception("خطأ في تسجيل العملية: " . $transaction_stmt->error);
                }
                
                // محاولة إنشاء جدول التاريخ إذا لم يكن موجودًا
                try {
                    $create_table_sql = "
                        CREATE TABLE IF NOT EXISTS deposit_history (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            deposit_id INT NOT NULL,
                            action VARCHAR(50) NOT NULL,
                            old_status VARCHAR(50),
                            new_status VARCHAR(50),
                            notes TEXT,
                            admin_id INT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_deposit_id (deposit_id),
                            INDEX idx_created_at (created_at)
                        )
                    ";
                    
                    if (!$conn->query($create_table_sql)) {
                        // تجاهل خطأ إنشاء الجدول
                    }
                    
                    // تسجيل التاريخ
                    $history_stmt = $conn->prepare("
                        INSERT INTO deposit_history 
                        (deposit_id, action, old_status, new_status, notes, admin_id, created_at)
                        VALUES (?, 'approved', 'pending', 'approved', ?, ?, NOW())
                    ");
                    
                    if ($history_stmt) {
                        $admin_id = $_SESSION['user_id'] ?? 1;
                        $history_stmt->bind_param('isi', $deposit_id, $notes, $admin_id);
                        $history_stmt->execute();
                    }
                    
                } catch (Exception $e) {
                    // تجاهل خطأ جدول التاريخ
                }
                
                $conn->commit();
                $success_messages[] = "تم قبول الإيداع بنجاح وتمت زيادة رصيد المستخدم.";
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "خطأ في المعالجة: " . $e->getMessage();
            }
            
        } elseif ($action === 'reject') {
            $reject_notes = $_POST['reject_reason'] ?? '';
            
            try {
                $reject_stmt = $conn->prepare("
                    UPDATE deposits 
                    SET status = 'rejected', 
                        rejected_at = NOW(), 
                        notes = CONCAT(COALESCE(notes, ''), '\nرفض: ', ?)
                    WHERE id = ?
                ");
                
                if (!$reject_stmt) {
                    throw new Exception("خطأ في إعداد استعلام الرفض: " . $conn->error);
                }
                
                $reject_stmt->bind_param('si', $reject_notes, $deposit_id);
                
                if (!$reject_stmt->execute()) {
                    throw new Exception("خطأ في تنفيذ الرفض: " . $reject_stmt->error);
                }
                
                $success_messages[] = "تم رفض الإيداع بنجاح.";
                
            } catch (Exception $e) {
                $errors[] = "خطأ في رفض الإيداع: " . $e->getMessage();
            }
            
        } elseif ($action === 'update_amount') {
            $new_amount = floatval($_POST['new_amount']);
            
            if ($new_amount > 0) {
                try {
                    $conn->begin_transaction();
                    
                    $old_amount = $deposit['amount'];
                    
                    // تحديث المبلغ في الإيداع
                    $update_stmt = $conn->prepare("
                        UPDATE deposits 
                        SET amount = ?, notes = CONCAT(COALESCE(notes, ''), '\nتعديل المبلغ: ', ?, ' -> ', ?)
                        WHERE id = ?
                    ");
                    
                    if (!$update_stmt) {
                        throw new Exception("خطأ في إعداد استعلام تحديث المبلغ: " . $conn->error);
                    }
                    
                    $note_text = "تم تعديل المبلغ من {$old_amount} إلى {$new_amount}";
                    $update_stmt->bind_param('ddsi', $new_amount, $old_amount, $new_amount, $deposit_id);
                    
                    if (!$update_stmt->execute()) {
                        throw new Exception("خطأ في تحديث المبلغ: " . $update_stmt->error);
                    }
                    
                    $conn->commit();
                    $success_messages[] = "تم تحديث المبلغ بنجاح.";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "خطأ في تحديث المبلغ: " . $e->getMessage();
                }
            }
            
        } elseif ($action === 'add_note') {
            $note_text = $_POST['note_text'] ?? '';
            
            if (!empty($note_text)) {
                try {
                    $note_stmt = $conn->prepare("
                        UPDATE deposits 
                        SET notes = CONCAT(COALESCE(notes, ''), '\n', NOW(), ': ', ?)
                        WHERE id = ?
                    ");
                    
                    if (!$note_stmt) {
                        throw new Exception("خطأ في إعداد استعلام الملاحظة: " . $conn->error);
                    }
                    
                    $note_stmt->bind_param('si', $note_text, $deposit_id);
                    
                    if (!$note_stmt->execute()) {
                        throw new Exception("خطأ في إضافة الملاحظة: " . $note_stmt->error);
                    }
                    
                    $success_messages[] = "تمت إضافة الملاحظة بنجاح.";
                    
                } catch (Exception $e) {
                    $errors[] = "خطأ في إضافة الملاحظة: " . $e->getMessage();
                }
            }
            
        } elseif ($action === 'delete') {
            if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
                try {
                    $delete_stmt = $conn->prepare("DELETE FROM deposits WHERE id = ?");
                    
                    if (!$delete_stmt) {
                        throw new Exception("خطأ في إعداد استعلام الحذف: " . $conn->error);
                    }
                    
                    $delete_stmt->bind_param('i', $deposit_id);
                    
                    if (!$delete_stmt->execute()) {
                        throw new Exception("خطأ في حذف الإيداع: " . $delete_stmt->error);
                    }
                    
                    header("Location: pending.php?success=deleted");
                    exit();
                    
                } catch (Exception $e) {
                    $errors[] = "خطأ في حذف الإيداع: " . $e->getMessage();
                }
            }
        }
        
        // إعادة جلب البيانات بعد التحديث
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("
                    SELECT d.*, u.name, u.email, u.phone, u.balance as user_balance, 
                           u.id as user_id, u.created_at as user_created
                    FROM deposits d
                    JOIN users u ON u.id = d.user_id
                    WHERE d.id = ?
                ");
                
                if ($stmt) {
                    $stmt->bind_param('i', $deposit_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $deposit = $result->fetch_assoc();
                    $stmt->close();
                }
                
            } catch (Exception $e) {
                $errors[] = "خطأ في إعادة جلب البيانات: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تفاصيل الإيداع #<?= $deposit_id ?> | Dr Pay</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* إعادة تعيين عام */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f8;
    color: #333;
    line-height: 1.6;
}

/* الحاويات */
.container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

/* رأس الصفحة */
.header {
    background: linear-gradient(135deg, #2c3e50, #4a6491);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.header h1 {
    margin: 0;
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header h1 i {
    font-size: 28px;
}

/* أزرار الرجوع */
.back-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}

.back-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

/* رسائل التنبيه */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 20px;
    margin-top: 2px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-success i {
    color: #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-error i {
    color: #dc3545;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.alert-warning i {
    color: #ffc107;
}

/* المحتوى الرئيسي */
.content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 25px;
    margin-top: 20px;
}

@media (max-width: 1024px) {
    .content {
        grid-template-columns: 1fr;
    }
}

/* البطاقات */
.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    border: 1px solid #e9ecef;
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

.card h2 {
    margin: 0 0 25px 0;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    color: #2c3e50;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card h2 i {
    color: #007bff;
    font-size: 22px;
}

/* معلومات الإيداع */
.deposit-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-top: 15px;
}

.info-item {
    padding: 15px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 8px;
    border-left: 4px solid #007bff;
    transition: all 0.3s;
}

.info-item:hover {
    background: linear-gradient(135deg, #e9ecef, #dee2e6);
    transform: translateX(5px);
}

.info-item label {
    display: block;
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item .value {
    font-size: 17px;
    font-weight: bold;
    color: #2c3e50;
    line-height: 1.4;
}

/* المبلغ الكبير */
.amount-large {
    font-size: 42px;
    font-weight: 800;
    color: #28a745;
    text-align: center;
    margin: 25px 0;
    padding: 20px;
    background: linear-gradient(135deg, #f1f8e9, #e8f5e9);
    border-radius: 12px;
    border: 2px dashed #c8e6c9;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

/* شارات الحالة */
.status-badge {
    padding: 8px 18px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.status-pending {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    color: #856404;
    border: 1px solid #ffd54f;
}

.status-approved {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border: 1px solid #a3d9a5;
}

.status-rejected {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border: 1px solid #f1b0b7;
}

/* الأزرار */
.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    font-size: 15px;
    text-align: center;
    justify-content: center;
    min-width: 120px;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.btn:active {
    transform: translateY(0);
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c82333, #bd2130);
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #212529;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e0a800, #d39e00);
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #138496, #117a8b);
}

.btn-secondary {
    background: linear-gradient(135deg, #6c757d, #545b62);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #545b62, #4a5056);
}

/* أزرار الإجراءات */
.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 25px;
    justify-content: center;
}

.action-buttons .btn {
    flex: 1;
    min-width: 160px;
    max-width: 200px;
}

/* النوافذ المنبثقة */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    padding: 35px;
    border-radius: 15px;
    max-width: 550px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    animation: slideUp 0.4s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.modal-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #6c757d;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.close-modal:hover {
    background: #f8f9fa;
    color: #dc3545;
}

/* المجموعات النموذجية */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
    font-size: 15px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.3s;
    background: #f8f9fa;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    background: white;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.form-control:disabled {
    background: #e9ecef;
    cursor: not-allowed;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
}

/* إجراءات النموذج */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

/* الجداول */
.table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    margin-top: 10px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.table th {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 16px;
    text-align: right;
    font-weight: 700;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    padding: 14px 16px;
    border-bottom: 1px solid #e9ecef;
    text-align: right;
    font-size: 14px;
}

.table tr {
    transition: all 0.3s;
}

.table tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
}

.table tr:last-child td {
    border-bottom: none;
}

/* الملاحظات */
.notes-box {
    background: #fff9c4;
    border: 1px solid #ffd54f;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    white-space: pre-wrap;
    font-size: 14px;
    line-height: 1.8;
    border-left: 4px solid #ffc107;
}

.notes-box strong {
    color: #856404;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 16px;
}

/* التبويبات */
.tabs {
    display: flex;
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 25px;
    gap: 5px;
    flex-wrap: wrap;
}

.tab {
    padding: 12px 25px;
    cursor: pointer;
    border: none;
    background: none;
    font-size: 15px;
    font-weight: 600;
    color: #6c757d;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab:hover {
    color: #007bff;
    background: #f8f9fa;
}

.tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: #f8f9fa;
}

.tab-content {
    display: none;
    animation: fadeIn 0.5s ease;
}

.tab-content.active {
    display: block;
}

/* رابط المستخدم */
.user-link {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    padding: 5px 10px;
    border-radius: 5px;
}

.user-link:hover {
    color: #0056b3;
    background: #e7f1ff;
    text-decoration: none;
}

/* صندوق الإيصال */
.receipt-container {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px dashed #dee2e6;
}

.receipt-img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 8px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid #dee2e6;
}

.receipt-img:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}

/* رسالة عدم وجود بيانات */
.empty-message {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px dashed #dee2e6;
}

.empty-message i {
    font-size: 60px;
    margin-bottom: 20px;
    color: #adb5bd;
}

.empty-message h3 {
    margin-bottom: 10px;
    color: #495057;
}

/* حالة التحميل */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* تصميم متجاوب */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .header {
        flex-direction: column;
        text-align: center;
        padding: 15px;
    }
    
    .header-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }
    
    .back-btn {
        width: 100%;
        justify-content: center;
    }
    
    .amount-large {
        font-size: 32px;
        padding: 15px;
    }
    
    .deposit-info {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
        max-width: none;
    }
    
    .modal-content {
        padding: 20px;
        margin: 10px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .tabs {
        flex-direction: column;
    }
    
    .tab {
        border-radius: 8px;
        border-bottom: none;
        border-left: 3px solid transparent;
        justify-content: flex-start;
    }
    
    .tab.active {
        border-left-color: #007bff;
    }
}

/* رسائل الأخطاء التفصيلية */
.error-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    border-left: 4px solid #dc3545;
    font-family: monospace;
    font-size: 13px;
    overflow-x: auto;
    display: none;
}

.error-details pre {
    margin: 0;
    white-space: pre-wrap;
}

.error-toggle {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-size: 12px;
    margin-top: 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* أزرار صغيرة */
.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
    min-width: auto;
}

/* تلميحات */
.tooltip {
    position: relative;
    display: inline-block;
}

.tooltip .tooltip-text {
    visibility: hidden;
    width: 200px;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 12px;
}

.tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

/* تأثيرات خاصة */
.glow {
    animation: glow 2s infinite;
}

@keyframes glow {
    0%, 100% { box-shadow: 0 0 5px rgba(0,123,255,0.5); }
    50% { box-shadow: 0 0 20px rgba(0,123,255,0.8); }
}

.shake {
    animation: shake 0.5s;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

/* شريط التقدم */
.progress-bar {
    width: 100%;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #00bfff);
    border-radius: 3px;
    transition: width 0.3s;
}

/* تصميم للأخطاء */
.error-highlight {
    border-color: #dc3545 !important;
    background: #fff5f5 !important;
}

.error-highlight:focus {
    box-shadow: 0 0 0 3px rgba(220,53,69,0.1) !important;
}

/* حالة الطلب */
.loading-state {
    display: none;
    text-align: center;
    padding: 20px;
}

.loading-state.active {
    display: block;
}

/* تأثيرات الوصول */
:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* طباعة */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn {
        display: none !important;
    }
    
    .tabs {
        display: none !important;
    }
    
    .tab-content {
        display: block !important;
    }
}
</style>
</head>

<body>

<div class="container">
    <!-- رأس الصفحة -->
    <div class="header">
        <h1><i class="fas fa-money-check-alt"></i> تفاصيل الإيداع #<?= $deposit_id ?></h1>
        <div class="header-buttons">
            <a href="pending.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع للإيداعات المعلقة
            </a>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-tachometer-alt"></i> لوحة التحكم
            </a>
        </div>
    </div>

    <!-- عرض رسائل النجاح -->
    <?php if (!empty($success_messages)): ?>
        <?php foreach ($success_messages as $message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- عرض رسائل الأخطاء -->
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- تحذير إذا لم توجد بيانات الإيداع -->
    <?php if (!$deposit): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>خطأ في تحميل بيانات الإيداع</strong><br>
                تعذر تحميل بيانات الإيداع المطلوب. الرجاء التحقق من رقم الإيداع والمحاولة مرة أخرى.
            </div>
        </div>
    <?php else: ?>

    <div class="content">
        <!-- العمود الرئيسي -->
        <div>
            <!-- معلومات الإيداع -->
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> معلومات الإيداع</h2>
                
                <div class="amount-large">
                    <?= number_format($deposit['amount'], 2) ?> EGP
                </div>
                
                <div class="deposit-info">
                    <div class="info-item">
                        <label>الحالة</label>
                        <span class="value">
                            <span class="status-badge status-<?= $deposit['status'] ?>">
                                <i class="fas fa-<?= 
                                    $deposit['status'] == 'pending' ? 'clock' : 
                                    ($deposit['status'] == 'approved' ? 'check-circle' : 'times-circle') 
                                ?>"></i>
                                <?= $deposit['status'] ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <label>رقم الإيداع</label>
                        <span class="value">#<?= $deposit_id ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>تاريخ الإنشاء</label>
                        <span class="value">
                            <?= date('Y-m-d H:i:s', strtotime($deposit['created_at'])) ?>
                        </span>
                    </div>
                    
                    <?php if ($deposit['approved_at']): ?>
                    <div class="info-item">
                        <label>تاريخ القبول</label>
                        <span class="value">
                            <?= date('Y-m-d H:i:s', strtotime($deposit['approved_at'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($deposit['rejected_at']): ?>
                    <div class="info-item">
                        <label>تاريخ الرفض</label>
                        <span class="value">
                            <?= date('Y-m-d H:i:s', strtotime($deposit['rejected_at'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <label>الوسيلة</label>
                        <span class="value"><?= htmlspecialchars($deposit['method'] ?? 'غير محدد') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>رقم المرجع</label>
                        <span class="value"><?= htmlspecialchars($deposit['reference'] ?? 'غير متوفر') ?></span>
                    </div>
                </div>
                
                <!-- الملاحظات -->
                <?php if (!empty($deposit['notes'])): ?>
                <div class="notes-box">
                    <strong><i class="fas fa-sticky-note"></i> الملاحظات:</strong><br>
                    <?= nl2br(htmlspecialchars($deposit['notes'])) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- معلومات المستخدم -->
            <div class="card">
                <h2><i class="fas fa-user"></i> معلومات المستخدم</h2>
                
                <div class="deposit-info">
                    <div class="info-item">
                        <label>اسم المستخدم</label>
                        <span class="value">
                            <a href="user_profile.php?id=<?= $deposit['user_id'] ?>" class="user-link">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($deposit['name']) ?>
                            </a>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <label>البريد الإلكتروني</label>
                        <span class="value"><?= htmlspecialchars($deposit['email']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>رقم الهاتف</label>
                        <span class="value"><?= htmlspecialchars($deposit['phone']) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>الرصيد الحالي</label>
                        <span class="value" style="color: #28a745; font-size: 18px;">
                            <?= number_format($deposit['user_balance'], 2) ?> EGP
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <label>رقم العضو</label>
                        <span class="value">#<?= $deposit['user_id'] ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>تاريخ التسجيل</label>
                        <span class="value">
                            <?= date('Y-m-d', strtotime($deposit['user_created'])) ?>
                        </span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="user_profile.php?id=<?= $deposit['user_id'] ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> عرض الملف الشخصي
                    </a>
                    <a href="add_balance.php?id=<?= $deposit['user_id'] ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> إضافة رصيد
                    </a>
                    <a href="edit_user.php?id=<?= $deposit['user_id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> تعديل المستخدم
                    </a>
                </div>
            </div>

            <!-- إجراءات التحكم -->
            <?php if ($deposit['status'] == 'pending'): ?>
            <div class="card">
                <h2><i class="fas fa-cogs"></i> إجراءات التحكم</h2>
                
                <div class="action-buttons">
                    <button class="btn btn-success" onclick="openModal('approveModal')">
                        <i class="fas fa-check"></i> قبول الإيداع
                    </button>
                    
                    <button class="btn btn-danger" onclick="openModal('rejectModal')">
                        <i class="fas fa-times"></i> رفض الإيداع
                    </button>
                    
                    <button class="btn btn-warning" onclick="openModal('updateAmountModal')">
                        <i class="fas fa-edit"></i> تعديل المبلغ
                    </button>
                    
                    <button class="btn btn-info" onclick="openModal('noteModal')">
                        <i class="fas fa-sticky-note"></i> إضافة ملاحظة
                    </button>
                    
                    <button class="btn btn-secondary" onclick="openModal('deleteModal')">
                        <i class="fas fa-trash"></i> حذف الإيداع
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- التبويبات -->
            <div class="card">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('history')">
                        <i class="fas fa-history"></i> سجل الإيداعات
                    </button>
                    <button class="tab" onclick="switchTab('transactions')">
                        <i class="fas fa-exchange-alt"></i> العمليات الأخيرة
                    </button>
                    <?php if ($has_history_table): ?>
                    <button class="tab" onclick="switchTab('depositHistory')">
                        <i class="fas fa-list-alt"></i> سجل التعديلات
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- سجل الإيداعات -->
                <div id="history" class="tab-content active">
                    <?php if ($prev_deposits && $prev_deposits->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($prev = $prev_deposits->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $prev['id'] ?></td>
                                    <td><?= number_format($prev['amount'], 2) ?> EGP</td>
                                    <td>
                                        <span class="status-badge status-<?= $prev['status'] ?>">
                                            <?= $prev['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($prev['created_at'])) ?></td>
                                    <td>
                                        <a href="deposit_details.php?id=<?= $prev['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-inbox"></i>
                        <h3>لا توجد إيداعات سابقة</h3>
                        <p>لا توجد إيداعات سابقة لهذا المستخدم</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- العمليات الأخيرة -->
                <div id="transactions" class="tab-content">
                    <?php if ($transactions && $transactions->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المبلغ</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($trans = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $trans['id'] ?></td>
                                    <td><?= number_format($trans['amount'], 2) ?> EGP</td>
                                    <td><?= htmlspecialchars($trans['type'] ?? 'غير محدد') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $trans['status'] ?>">
                                            <?= $trans['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d H:i', strtotime($trans['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-exchange-alt"></i>
                        <h3>لا توجد عمليات</h3>
                        <p>لا توجد عمليات لهذا المستخدم</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- سجل التعديلات (يظهر فقط إذا كان الجدول موجودًا) -->
                <?php if ($has_history_table): ?>
                <div id="depositHistory" class="tab-content">
                    <?php if ($history && $history->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>الإجراء</th>
                                    <th>التفاصيل</th>
                                    <th>المسؤول</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($hist = $history->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="status-badge status-<?= $hist['action'] ?>">
                                            <?= $hist['action'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($hist['notes'] ?? '') ?></td>
                                    <td>Admin #<?= $hist['admin_id'] ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($hist['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-list-alt"></i>
                        <h3>لا توجد سجلات تعديل</h3>
                        <p>لا توجد سجلات تعديل لهذا الإيداع</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- العمود الجانبي -->
        <div>
            <!-- صورة الإيصال -->
            <div class="card">
                <h2><i class="fas fa-receipt"></i> صورة الإيصال</h2>
                
                <div class="receipt-container">
                    <?php 
                    $receipt_path = '../uploads/receipts/' . ($deposit['receipt'] ?? '');
                    if ($deposit['receipt'] && file_exists($receipt_path)): 
                    ?>
                        <img src="<?= $receipt_path ?>" 
                             alt="إيصال الإيداع" 
                             class="receipt-img"
                             onclick="openImageModal(this.src)">
                        
                        <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                            <a href="<?= $receipt_path ?>" 
                               download="receipt_<?= $deposit_id ?>.jpg" 
                               class="btn btn-info">
                                <i class="fas fa-download"></i> تحميل الصورة
                            </a>
                            
                            <a href="<?= $receipt_path ?>" 
                               target="_blank" 
                               class="btn btn-secondary">
                                <i class="fas fa-external-link-alt"></i> فتح في نافذة جديدة
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-receipt"></i>
                            <h3>لا توجد صورة إيصال</h3>
                            <p>لم يتم رفع إيصال لهذا الإيداع</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- إحصائيات سريعة -->
            <div class="card">
                <h2><i class="fas fa-chart-bar"></i> إحصائيات سريعة</h2>
                
                <div class="deposit-info">
                    <?php
                    try {
                        $total_deposits = $conn->query("
                            SELECT COUNT(*) as count, SUM(amount) as total 
                            FROM deposits 
                            WHERE user_id = " . intval($deposit['user_id']) . "
                        ")->fetch_assoc();
                    } catch (Exception $e) {
                        $total_deposits = ['count' => 0, 'total' => 0];
                    }
                    
                    $avg_amount = $total_deposits['count'] > 0 ? 
                        ($total_deposits['total'] / $total_deposits['count']) : 0;
                    $percentage = $total_deposits['total'] > 0 ? 
                        ($deposit['amount'] / $total_deposits['total'] * 100) : 0;
                    ?>
                    
                    <div class="info-item">
                        <label>الإيداعات الكلية</label>
                        <span class="value"><?= $total_deposits['count'] ?> إيداع</span>
                    </div>
                    
                    <div class="info-item">
                        <label>إجمالي الإيداعات</label>
                        <span class="value"><?= number_format($total_deposits['total'] ?? 0, 2) ?> EGP</span>
                    </div>
                    
                    <div class="info-item">
                        <label>متوسط الإيداعات</label>
                        <span class="value"><?= number_format($avg_amount, 2) ?> EGP</span>
                    </div>
                    
                    <div class="info-item">
                        <label>نسبة هذا الإيداع</label>
                        <span class="value"><?= number_format($percentage, 1) ?>%</span>
                    </div>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <div class="card">
                <h2><i class="fas fa-bolt"></i> إجراءات سريعة</h2>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> طباعة الإيداع
                    </button>
                    
                    <button onclick="copyToClipboard('#depositInfo')" class="btn btn-info">
                        <i class="fas fa-copy"></i> نسخ المعلومات
                    </button>
                    
                    <button onclick="refreshPage()" class="btn btn-warning">
                        <i class="fas fa-redo"></i> تحديث الصفحة
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modals (نفس النوافذ المنبثقة السابقة) -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check"></i> قبول الإيداع</h3>
            <button class="close-modal" onclick="closeModal('approveModal')">&times;</button>
        </div>
        <form method="POST" action="" onsubmit="return confirmApprove()">
            <input type="hidden" name="action" value="approve">
            
            <div class="form-group">
                <label>المبلغ: <strong><?= isset($deposit) ? number_format($deposit['amount'], 2) : 0 ?> EGP</strong></label>
            </div>
            
            <?php if (isset($deposit)): ?>
            <div class="form-group">
                <label>الرصيد الحالي للمستخدم: <?= number_format($deposit['user_balance'], 2) ?> EGP</label>
            </div>
            
            <div class="form-group">
                <label>الرصيد بعد القبول: <strong style="color: #28a745;">
                    <?= number_format($deposit['user_balance'] + $deposit['amount'], 2) ?> EGP
                </strong></label>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="notes">ملاحظات إضافية (اختياري)</label>
                <textarea name="notes" id="notes" class="form-control" rows="4" 
                          placeholder="يمكنك إضافة ملاحظات هنا..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">
                    إلغاء
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> تأكيد القبول
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal رفض الإيداع -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times"></i> رفض الإيداع</h3>
            <button class="close-modal" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="POST" action="" onsubmit="return confirmReject()">
            <input type="hidden" name="action" value="reject">
            
            <div class="form-group">
                <label>سبب الرفض (مطلوب)</label>
                <select name="reject_reason" class="form-control" required>
                    <option value="">اختر سبب الرفض</option>
                    <option value="الإيصال غير واضح">الإيصال غير واضح</option>
                    <option value="المبلغ غير مطابق">المبلغ غير مطابق</option>
                    <option value="معلومات غير كاملة">معلومات غير كاملة</option>
                    <option value="مشكلة في التحويل">مشكلة في التحويل</option>
                    <option value="سبب آخر">سبب آخر</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>تفاصيل إضافية (اختياري)</label>
                <textarea name="notes" class="form-control" rows="4" 
                          placeholder="يمكنك إضافة تفاصيل إضافية هنا..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">
                    إلغاء
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> تأكيد الرفض
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل المبلغ -->
<div id="updateAmountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> تعديل مبلغ الإيداع</h3>
            <button class="close-modal" onclick="closeModal('updateAmountModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_amount">
            
            <div class="form-group">
                <label>المبلغ الحالي</label>
                <input type="text" class="form-control" 
                       value="<?= isset($deposit) ? number_format($deposit['amount'], 2) : 0 ?> EGP" readonly>
            </div>
            
            <div class="form-group">
                <label for="new_amount">المبلغ الجديد (EGP)</label>
                <input type="number" name="new_amount" id="new_amount" 
                       class="form-control" step="0.01" min="1" 
                       value="<?= isset($deposit) ? $deposit['amount'] : 0 ?>" required>
            </div>
            
            <div class="form-group">
                <label>سبب التعديل (اختياري)</label>
                <input type="text" name="notes" class="form-control" 
                       placeholder="سبب تعديل المبلغ...">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateAmountModal')">
                    إلغاء
                </button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save"></i> حفظ التعديل
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal إضافة ملاحظة -->
<div id="noteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-sticky-note"></i> إضافة ملاحظة</h3>
            <button class="close-modal" onclick="closeModal('noteModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_note">
            
            <div class="form-group">
                <label for="note_text">الملاحظة</label>
                <textarea name="note_text" id="note_text" class="form-control" rows="6" 
                          placeholder="اكتب ملاحظتك هنا..." required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('noteModal')">
                    إلغاء
                </button>
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-save"></i> حفظ الملاحظة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal حذف الإيداع -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-trash"></i> حذف الإيداع</h3>
            <button class="close-modal" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete">
            
            <div class="form-group">
                <p style="color: #dc3545; font-weight: bold; text-align: center;">
                    <i class="fas fa-exclamation-triangle"></i>
                    تحذير: هذا الإجراء لا يمكن التراجع عنه!
                </p>
                
                <p>هل أنت متأكد من حذف الإيداع #<?= $deposit_id ?>؟</p>
                <?php if (isset($deposit)): ?>
                <p>المبلغ: <strong><?= number_format($deposit['amount'], 2) ?> EGP</strong></p>
                <p>المستخدم: <strong><?= htmlspecialchars($deposit['name']) ?></strong></p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="confirm_delete" value="yes" required>
                    نعم، أريد حذف هذا الإيداع
                </label>
            </div>
            
            <div class="form-group">
                <label>سبب الحذف (اختياري)</label>
                <input type="text" name="notes" class="form-control" 
                       placeholder="سبب حذف الإيداع...">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">
                    إلغاء
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> تأكيد الحذف
                </button>
            </div>
        </form>
    </div>
</div>

<!-- معلومات الإيداع للنسخ -->
<div id="depositInfo" style="display: none;">
تفاصيل الإيداع #<?= $deposit_id ?>
المبلغ: <?= isset($deposit) ? number_format($deposit['amount'], 2) : 0 ?> EGP
الحالة: <?= isset($deposit) ? $deposit['status'] : 'غير معروف' ?>
المستخدم: <?= isset($deposit) ? htmlspecialchars($deposit['name']) : 'غير معروف' ?>
البريد: <?= isset($deposit) ? htmlspecialchars($deposit['email']) : 'غير معروف' ?>
التاريخ: <?= isset($deposit) ? date('Y-m-d H:i:s', strtotime($deposit['created_at'])) : 'غير معروف' ?>
</div>

<script>
// إدارة التبويبات
function switchTab(tabName) {
    // إخفاء كل المحتويات
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // إزالة النشاط من كل الأزرار
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // إظهار المحتوى المطلوب
    const tabContent = document.getElementById(tabName);
    if (tabContent) {
        tabContent.classList.add('active');
    }
    
    // تفعيل الزر المطلوب
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// إدارة النوافذ المنبثقة
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// إغلاق النوافذ عند النقر خارجها أو بالزر Escape
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// نسخ المعلومات
function copyToClipboard(elementId) {
    const element = document.querySelector(elementId);
    if (!element) {
        alert('عنصر المعلومات غير موجود');
        return;
    }
    
    const text = element.innerText;
    
    // طريقة بديلة للنسخ للتوافق مع المتصفحات القديمة
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert('تم نسخ معلومات الإيداع إلى الحافظة');
        } else {
            alert('فشل نسخ المعلومات. الرجاء المحاولة يدوياً');
        }
    } catch (err) {
        console.error('خطأ في النسخ:', err);
        alert('حدث خطأ أثناء النسخ: ' + err.message);
    }
    
    document.body.removeChild(textarea);
}

// تأكيد الإجراءات
function confirmApprove() {
    const amount = <?= isset($deposit) ? $deposit['amount'] : 0 ?>;
    return confirm(`هل تريد قبول الإيداع بمبلغ ${amount.toFixed(2)} EGP؟`);
}

function confirmReject() {
    return confirm('هل أنت متأكد من رفض هذا الإيداع؟');
}

// تحديث الصفحة
function refreshPage() {
    if (confirm('هل تريد تحديث الصفحة؟')) {
        window.location.reload();
    }
}

// فتح صورة الإيصال في نافذة جديدة
function openImageModal(src) {
    window.open(src, '_blank');
}

// التحقق من صحة المبلغ عند التعديل
document.getElementById('new_amount')?.addEventListener('input', function(e) {
    const value = parseFloat(e.target.value);
    if (value <= 0) {
        e.target.classList.add('error-highlight');
        e.target.setCustomValidity('المبلغ يجب أن يكون أكبر من صفر');
    } else {
        e.target.classList.remove('error-highlight');
        e.target.setCustomValidity('');
    }
});

// إظهار تفاصيل الخطأ
document.querySelectorAll('.error-toggle').forEach(button => {
    button.addEventListener('click', function() {
        const details = this.nextElementSibling;
        if (details.style.display === 'block') {
            details.style.display = 'none';
            this.innerHTML = '<i class="fas fa-chevron-down"></i> عرض التفاصيل';
        } else {
            details.style.display = 'block';
            this.innerHTML = '<i class="fas fa-chevron-up"></i> إخفاء التفاصيل';
        }
    });
});

// تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // إضافة تأثير للبطاقات
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // إضافة تأثير للأخطاء
    const errors = document.querySelectorAll('.alert-error');
    errors.forEach(error => {
        error.classList.add('shake');
    });
    
    console.log('صفحة تفاصيل الإيداع جاهزة');
});

// منع إرسال النموذج بالزر Enter
document.addEventListener('keydown', function(event) {
    if (event.key === 'Enter' && event.target.tagName === 'INPUT' && !event.target.type === 'submit') {
        event.preventDefault();
    }
});

// إدارة حالة التحميل للنماذج
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="loading"></span> جاري المعالجة...';
            submitBtn.disabled = true;
        }
    });
});
</script>

</body>
</html>