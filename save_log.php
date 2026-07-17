<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';
header('Content-Type: application/json');

// ============================================
// شروع دیباگ - لاگ در فایل
// ============================================
function debugLog($msg, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $msg;
    if ($data !== null) {
        $log .= " - " . print_r($data, true);
    }
    file_put_contents(__DIR__ . '/debug.log', $log . PHP_EOL, FILE_APPEND);
}

debugLog("=== شروع درخواست ===");
debugLog("POST", $_POST);
debugLog("FILES", $_FILES);

// ============================================
// بررسی سشن
// ============================================
if (empty($_SESSION['student_id'])) {
    echo json_encode(['ok' => false, 'error' => 'ابتدا وارد شوید.']);
    exit;
}

$studentId = $_SESSION['student_id'];
$type = $_POST['type'] ?? '';
$lat = floatval($_POST['lat'] ?? 0);
$lng = floatval($_POST['lng'] ?? 0);
$today = date('Y-m-d');

debugLog("studentId: $studentId, type: $type, lat: $lat, lng: $lng");

// ============================================
// اعتبارسنجی مختصات
// ============================================
if ($lat == 0 || $lng == 0) {
    echo json_encode(['ok' => false, 'error' => 'مختصات مکانی دریافت نشد (0,0).']);
    exit;
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['ok' => false, 'error' => 'مختصات مکانی نامعتبر است.']);
    exit;
}

// ============================================
// اعتبارسنجی نوع
// ============================================
if (!in_array($type, ['in', 'out'])) {
    echo json_encode(['ok' => false, 'error' => 'نوع ثبت نامعتبر است.']);
    exit;
}

// ============================================
// بررسی عکس
// ============================================
if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'عکس دریافت نشد.';
    if (isset($_FILES['selfie']['error'])) {
        $errorMsg .= ' کد خطا: ' . $_FILES['selfie']['error'];
    }
    echo json_encode(['ok' => false, 'error' => $errorMsg]);
    exit;
}

// ============================================
// اتصال به دیتابیس
// ============================================
try {
    $db = getDB();
    debugLog("اتصال به دیتابیس موفق");
} catch (PDOException $e) {
    debugLog("خطا در اتصال به دیتابیس: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'خطا در اتصال به دیتابیس.']);
    exit;
}

// ============================================
// بررسی تکراری
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
    debugLog("خطا در بررسی دیتابیس: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'خطا در بررسی دیتابیس.']);
    exit;
}

// ============================================
// ذخیره عکس
// ============================================
try {
    $uploadDir = __DIR__ . '/uploads/selfies/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        debugLog("پوشه uploads ایجاد شد");
    }
    
    chmod($uploadDir, 0777);
    
    $ext = strtolower(pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION));
    $fileName = 'student' . $studentId . '_' . $type . '_' . date('Ymd_His') . '.' . $ext;
    $destPath = $uploadDir . $fileName;
    
    debugLog("ذخیره عکس در: " . $destPath);
    
    compressImageUnder250KB($_FILES['selfie']['tmp_name'], $destPath, 250000);
    
    if (!file_exists($destPath)) {
        throw new Exception('فایل ذخیره نشد.');
    }
    
    $relativePath = 'uploads/selfies/' . $fileName;
    debugLog("عکس ذخیره شد: " . $relativePath);
    
} catch (Exception $e) {
    debugLog("خطا در ذخیره عکس: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره عکس: ' . $e->getMessage()]);
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
            debugLog("فاصله محاسبه شد: " . $distance);
        }
    } catch (Exception $e) {
        debugLog("خطا در محاسبه فاصله: " . $e->getMessage());
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
    
    debugLog("ثبت در دیتابیس موفق");
    
    echo json_encode([
        'ok' => true,
        'message' => 'ثبت با موفقیت انجام شد',
        'distance' => $distance
    ]);
    
} catch (PDOException $e) {
    debugLog("خطا در ذخیره دیتابیس: " . $e->getMessage());
    if (file_exists($destPath)) {
        unlink($destPath);
        debugLog("عکس حذف شد");
    }
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره دیتابیس.']);
    exit;
}
?>