<?php
// ============================================
// مخفی کردن خطاهای نمایشی
// ============================================
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// بارگذاری تنظیمات
// ============================================
require 'config.php';
header('Content-Type: application/json');

// ============================================
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
if (!in_array($type, ['in', 'out'])) {
    echo json_encode(['ok' => false, 'error' => 'نوع ثبت نامعتبر است.']);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(['ok' => false, 'error' => 'مختصات دریافت نشد.']);
    exit;
}

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
    
    $fileName = 'student' . $studentId . '_' . $type . '_' . date('Ymd_His') . '.' . $ext;
    $destPath = $uploadDir . $fileName;
    
    // ذخیره و فشرده‌سازی
    compressImageUnder250KB($_FILES['selfie']['tmp_name'], $destPath, 250000);
    
    if (!file_exists($destPath)) {
        throw new Exception('فایل ذخیره نشد.');
    }
    
    $relativePath = 'uploads/selfies/' . $fileName;
    
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره عکس.']);
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
    $stmt->execute([$studentId, $type, $today, $lat, $lng, $relativePath, $distance]);
    
    echo json_encode([
        'ok' => true,
        'message' => 'ثبت با موفقیت انجام شد'
    ]);
    
} catch (PDOException $e) {
    // حذف عکس اگر خطا رخ داد
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره دیتابیس.']);
    exit;
}
?>