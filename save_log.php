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
$calcWarning = null;
$calcDebug = null;

if ($type === 'out') {
    try {
        // محاسبه مدت با ساعت خود MySQL تا اختلاف timezone بین PHP و سرور DB مشکل‌ساز نشود
        $stmt = $db->prepare('
            SELECT
                latitude,
                longitude,
                created_at,
                TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS duration_minutes,
                NOW() AS db_now
            FROM attendance_logs
            WHERE student_id = ? AND type = ? AND log_date = ?
        ');
        $stmt->execute([$studentId, 'in', $today]);
        $inRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inRow) {
            $calcWarning = 'رکورد ورود امروز برای محاسبه مدت پیدا نشد.';
            error_log("duration calc: check-in row missing student_id={$studentId} date={$today}");
        } else {
            $distance = haversineMeters(
                (float) $inRow['latitude'],
                (float) $inRow['longitude'],
                (float) $lat,
                (float) $lng
            );

            $rawDuration = $inRow['duration_minutes'];
            $calcDebug = [
                'checkin_at' => $inRow['created_at'],
                'db_now' => $inRow['db_now'],
                'raw_duration_minutes' => $rawDuration,
            ];

            if ($rawDuration === null || $rawDuration === '') {
                $calcWarning = 'محاسبه مدت از دیتابیس مقدار خالی برگرداند.';
                error_log('duration calc empty: ' . json_encode($calcDebug, JSON_UNESCAPED_UNICODE));
            } else {
                $durationMinutes = (int) $rawDuration;
                if ($durationMinutes < 0) {
                    $calcWarning = 'زمان خروج از ورود عقب‌تر است (احتمالاً اختلاف ساعت سرور). checkin='
                        . $inRow['created_at'] . ' now=' . $inRow['db_now'];
                    error_log('duration calc negative: ' . json_encode($calcDebug, JSON_UNESCAPED_UNICODE));
                    $durationMinutes = null;
                }
            }
        }
    } catch (Exception $e) {
        $distance = null;
        $durationMinutes = null;
        $calcWarning = 'خطا در محاسبه فاصله/مدت: ' . $e->getMessage();
        error_log('duration/distance calc exception: ' . $e->getMessage());
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

    $response = [
        'ok' => true,
        'message' => 'ثبت با موفقیت انجام شد',
        'type' => $type,
        'duration_minutes' => $durationMinutes,
        'distance_meters' => $distance,
    ];

    if ($calcWarning !== null) {
        $response['warning'] = $calcWarning;
        $response['debug'] = $calcDebug;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($destPath !== '' && file_exists($destPath)) {
        @unlink($destPath);
    }

    $dbError = $e->getMessage();
    error_log('attendance insert failed: ' . $dbError);

    $userError = 'خطا در ذخیره دیتابیس.';
    if (stripos($dbError, 'Unknown column') !== false) {
        $userError = 'ستون duration/distance در جدول attendance_logs وجود ندارد. فایل database.sql را روی دیتابیس اعمال کنید یا ستون‌ها را دستی اضافه کنید.';
    } elseif (stripos($dbError, 'Duplicate') !== false) {
        $userError = 'قبلا برای امروز ثبت شده است.';
    }

    echo json_encode([
        'ok' => false,
        'error' => $userError,
        'debug' => $dbError,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>