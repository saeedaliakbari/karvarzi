<?php
// ============================================
// مخفی کردن خطاهای نمایشی
// ============================================
error_reporting(0);
ini_set('display_errors', 0);
<<<<<<< HEAD
ini_set('log_errors', 1);
=======
>>>>>>> parent of 5206c4f (fix bug upload picture)

// ============================================
// بارگذاری تنظیمات
// ============================================
require 'config.php';
header('Content-Type: application/json');

// ============================================
<<<<<<< HEAD
// تابع لاگ
// ============================================
function debugLog($msg, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $msg;
    if ($data !== null) {
        $log .= " - " . print_r($data, true);
    }
    @file_put_contents(__DIR__ . '/debug.log', $log . PHP_EOL, FILE_APPEND);
}

debugLog("=== شروع درخواست ===");
debugLog("POST", $_POST);
debugLog("FILES", $_FILES);

// ============================================
=======
>>>>>>> parent of 5206c4f (fix bug upload picture)
// بررسی ورود کاربر
// ============================================
if (empty($_SESSION['student_id'])) {
    echo json_encode(['ok' => false, 'error' => 'ابتدا وارد شوید.']);
    exit;
}

$studentId = $_SESSION['student_id'];
$type = $_POST['type'] ?? '';
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$today = date('Y-m-d');

// ============================================
// اعتبارسنجی
// ============================================
<<<<<<< HEAD
if ($lat == 0 || $lng == 0) {
    echo json_encode(['ok' => false, 'error' => 'مختصات مکانی دریافت نشد (0,0).']);
    exit;
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['ok' => false, 'error' => 'مختصات مکانی نامعتبر است.']);
    exit;
}

=======
>>>>>>> parent of 5206c4f (fix bug upload picture)
if (!in_array($type, ['in', 'out'])) {
    echo json_encode(['ok' => false, 'error' => 'نوع ثبت نامعتبر است.']);
    exit;
}

<<<<<<< HEAD
=======
if ($lat === null || $lng === null) {
    echo json_encode(['ok' => false, 'error' => 'مختصات دریافت نشد.']);
    exit;
}

>>>>>>> parent of 5206c4f (fix bug upload picture)
if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'عکس دریافت نشد.']);
    exit;
}

// ============================================
// اتصال به دیتابیس
// ============================================
try {
    $db = getDB();
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در اتصال به دیتابیس.']);
    exit;
}

// ============================================
// بررسی دیتابیس
// ============================================
try {
    $stmt = $db->prepare('SELECT id FROM attendance_logs WHERE student_id = ? AND type = ? AND log_date = ?');
    $stmt->execute([$studentId, $type, $today]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'قبلا برای امروز ثبت شده است.']);
        exit;
    }

    if ($type === 'out') {
        $stmt = $db->prepare('SELECT id FROM attendance_logs WHERE student_id = ? AND type = ? AND log_date = ?');
        $stmt->execute([$studentId, 'in', $today]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'ابتدا باید ورود ثبت شود.']);
            exit;
        }
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در بررسی دیتابیس.']);
    exit;
}

// ============================================
<<<<<<< HEAD
// ذخیره عکس با فشرده‌سازی
// ============================================
$relativePath = '';

try {
    // ایجاد نام فایل
    $ext = strtolower(pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
        $ext = 'jpg';
    }
    
=======
// ذخیره عکس با مدیریت خطا
// ============================================
$relativePath = '';
try {
    $uploadDir = __DIR__ . '/uploads/selfies/';
    
    // ایجاد پوشه با دسترسی کامل
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // تنظیم دسترسی پوشه
    chmod($uploadDir, 0777);
    
    $ext = strtolower(pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        $ext = 'jpg';
    }
    
>>>>>>> parent of 5206c4f (fix bug upload picture)
    $fileName = 'student' . $studentId . '_' . $type . '_' . date('Ymd_His') . '.' . $ext;
    $destPath = '/var/www/html/uploads/selfies/' . $fileName;
    
<<<<<<< HEAD
    debugLog("ذخیره عکس در: " . $destPath);
    debugLog("حجم فایل: " . $_FILES['selfie']['size'] . " bytes");
    
    // ============================================
    // استفاده از تابع فشرده‌سازی
    // ============================================
    if (compressImageUnder250KB($_FILES['selfie']['tmp_name'], $destPath, 250000)) {
        $relativePath = 'uploads/selfies/' . $fileName;
        debugLog("✅ عکس با موفقیت ذخیره شد: " . $relativePath);
        if (file_exists($destPath)) {
            debugLog("حجم نهایی: " . filesize($destPath) . " bytes");
        }
    } else {
        throw new Exception('فشرده‌سازی و ذخیره عکس ناموفق بود.');
    }
    
} catch (Exception $e) {
    debugLog("❌ خطا در ذخیره عکس: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره عکس: ' . $e->getMessage()]);
=======
    // ذخیره و فشرده‌سازی
    compressImageUnder250KB($_FILES['selfie']['tmp_name'], $destPath, 250000);
    
    if (!file_exists($destPath)) {
        throw new Exception('فایل ذخیره نشد.');
    }
    
    $relativePath = 'uploads/selfies/' . $fileName;
    
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره عکس.']);
>>>>>>> parent of 5206c4f (fix bug upload picture)
    exit;
}

// ============================================
// محاسبه فاصله
// ============================================
$distance = null;
if ($type === 'out') {
    try {
        $stmt = $db->prepare('SELECT latitude, longitude FROM attendance_logs WHERE student_id = ? AND type = ? AND log_date = ?');
        $stmt->execute([$studentId, 'in', $today]);
        $inRow = $stmt->fetch();
        if ($inRow) {
            $distance = haversineMeters($inRow['latitude'], $inRow['longitude'], $lat, $lng);
        }
    } catch (Exception $e) {
        $distance = null;
    }
}

// ============================================
// ذخیره در دیتابیس
// ============================================
try {
    $stmt = $db->prepare('
        INSERT INTO attendance_logs 
        (student_id, type, log_date, latitude, longitude, selfie_path, distance_from_checkin_meters) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $studentId, 
        $type, 
        $today, 
        $lat, 
        $lng, 
        $relativePath, 
        $distance
    ]);
    
<<<<<<< HEAD
    debugLog("✅ ثبت در دیتابیس موفق");
    
=======
>>>>>>> parent of 5206c4f (fix bug upload picture)
    echo json_encode([
        'ok' => true,
        'message' => 'ثبت با موفقیت انجام شد'
    ]);
    
} catch (PDOException $e) {
<<<<<<< HEAD
    debugLog("❌ خطا در ذخیره دیتابیس: " . $e->getMessage());
    
    // حذف عکس اگر خطا رخ داد
    if (isset($destPath) && file_exists($destPath)) {
        @unlink($destPath);
        debugLog("عکس حذف شد");
=======
    // حذف عکس اگر خطا رخ داد
    if (file_exists($destPath)) {
        unlink($destPath);
>>>>>>> parent of 5206c4f (fix bug upload picture)
    }
    
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره دیتابیس.']);
    exit;
}
?>