<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['ok' => false, 'error' => 'ابتدا وارد شوید.']);
    exit;
}

$studentId = (int) $_SESSION['student_id'];
$type = $_POST['type'] ?? '';
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$today = date('Y-m-d');

if (!in_array($type, ['in', 'out'], true)) {
    echo json_encode(['ok' => false, 'error' => 'نوع ثبت نامعتبر است.']);
    exit;
}

if ($lat === null || $lng === null || $lat == 0 || $lng == 0) {
    echo json_encode(['ok' => false, 'error' => 'مختصات مکانی دریافت نشد.']);
    exit;
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['ok' => false, 'error' => 'مختصات مکانی نامعتبر است.']);
    exit;
}

if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'عکس دریافت نشد.']);
    exit;
}

try {
    $db = getDB();
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در اتصال به دیتابیس.']);
    exit;
}

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

$relativePath = '';
$destPath = '';

try {
    $uploadDir = __DIR__ . '/uploads/selfies';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        throw new Exception('پوشه آپلود ساخته نشد.');
    }

    @chmod($uploadDir, 0777);

    $ext = strtolower(pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $ext = 'jpg';
    }

    $fileName = 'student' . $studentId . '_' . $type . '_' . date('Ymd_His') . '.' . $ext;
    $destPath = $uploadDir . '/' . $fileName;

    if (!compressImageUnder250KB($_FILES['selfie']['tmp_name'], $destPath, 250000)) {
        throw new Exception('ذخیره عکس ناموفق بود.');
    }

    if (!file_exists($destPath)) {
        throw new Exception('فایل ذخیره نشد.');
    }

    $relativePath = 'uploads/selfies/' . $fileName;
} catch (Exception $e) {
    if ($destPath !== '' && file_exists($destPath)) {
        @unlink($destPath);
    }
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره عکس.']);
    exit;
}

$distance = null;
$durationMinutes = null;
if ($type === 'out') {
    try {
        $durationMinutes = 0;
        $stmt = $db->prepare('SELECT latitude, longitude, created_at FROM attendance_logs WHERE student_id = ? AND type = ? AND log_date = ?');
        $stmt->execute([$studentId, 'in', $today]);
        $inRow = $stmt->fetch();
        if ($inRow) {
            $distance = haversineMeters((float) $inRow['latitude'], (float) $inRow['longitude'], (float) $lat, (float) $lng);
            $checkinTime = strtotime($inRow['created_at']);
            $checkoutTime = time();
            if ($checkinTime !== false && $checkoutTime >= $checkinTime) {
                $durationMinutes = (int) floor(($checkoutTime - $checkinTime) / 60);
            }
        }
    } catch (Exception $e) {
        $distance = null;
        $durationMinutes = null;
    }
}

try {
    $stmt = $db->prepare('
        INSERT INTO attendance_logs
        (student_id, type, log_date, latitude, longitude, selfie_path, distance_from_checkin_meters, duration_from_checkin_minutes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $studentId,
        $type,
        $today,
        $lat,
        $lng,
        $relativePath,
        $distance,
        $durationMinutes
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'ثبت با موفقیت انجام شد'
    ]);
} catch (PDOException $e) {
    if ($destPath !== '' && file_exists($destPath)) {
        @unlink($destPath);
    }

    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره دیتابیس.']);
    exit;
}
?>