<?php
require 'config.php';
header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['ok' => false, 'error' => 'ابتدا وارد شوید.']);
    exit;
}

$studentId = $_SESSION['student_id'];
$type = $_POST['type'] ?? '';
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$today = date('Y-m-d');

if (!in_array($type, ['in', 'out']) || $lat === null || $lng === null) {
    echo json_encode(['ok' => false, 'error' => 'اطلاعات ناقص است.']);
    exit;
}

if (empty($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'عکس دریافت نشد.']);
    exit;
}

$db = getDB();

// جلوگیری از ثبت تکراری در همان روز
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

// ذخیره فایل عکس
$uploadDir = __DIR__ . '/uploads/selfies/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$ext = strtolower(pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
    $ext = 'jpg';
}
$fileName = 'student' . $studentId . '_' . $type . '_' . date('Ymd_His') . '.jpg';
$destPath = $uploadDir . $fileName;
compressImageUnder250KB($_FILES['selfie']['tmp_name'], $destPath, 250000);
$relativePath = 'uploads/selfies/' . $fileName;

// محاسبه فاصله بین ورود و خروج همان روز
$distance = null;
if ($type === 'out') {
    $stmt = $db->prepare('SELECT latitude, longitude FROM attendance_logs WHERE student_id = ? AND type = ? AND log_date = ?');
    $stmt->execute([$studentId, 'in', $today]);
    $inRow = $stmt->fetch();
    if ($inRow) {
        $distance = haversineMeters($inRow['latitude'], $inRow['longitude'], $lat, $lng);
    }
}

$stmt = $db->prepare('INSERT INTO attendance_logs (student_id, type, log_date, latitude, longitude, selfie_path, distance_from_checkin_meters) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$studentId, $type, $today, $lat, $lng, $relativePath, $distance]);

echo json_encode(['ok' => true]);
