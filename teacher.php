<?php
require 'config.php';

if (empty($_SESSION['is_teacher'])) {
    header('Location: teacher_login.php');
    exit;
}

$db = getDB();
$stmt = $db->query('
    SELECT l.id, s.name, s.mobile, l.type, l.log_date, l.latitude, l.longitude,
           l.selfie_path, l.distance_from_checkin_meters, l.created_at
    FROM attendance_logs l
    JOIN students s ON s.id = l.student_id
    ORDER BY l.created_at DESC
    LIMIT 200
');
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پنل گزارش معلم</title>
<style>
    body { font-family: Tahoma, sans-serif; background: #f5f5f5; padding: 24px; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 13px; text-align: right; }
    th { background: #fafafa; }
    img.selfie { width: 44px; height: 44px; object-fit: cover; border-radius: 50%; }
    a.map-link { color: #185fa5; }
</style>
</head>
<body>
<h2>گزارش حضور و غیاب</h2>
<p><a href="logout.php">خروج</a></p>
<table>
<tr>
    <th>عکس</th><th>نام</th><th>موبایل</th><th>نوع</th><th>تاریخ</th><th>ساعت</th><th>موقعیت</th><th>فاصله ورود/خروج</th>
</tr>
<?php foreach ($logs as $log): ?>
<tr>
    <td><img class="selfie" src="<?= htmlspecialchars($log['selfie_path']) ?>" alt=""></td>
    <td><?= htmlspecialchars($log['name']) ?></td>
    <td><?= htmlspecialchars($log['mobile']) ?></td>
    <td><?= $log['type'] === 'in' ? 'ورود' : 'خروج' ?></td>
    <td><?= $log['log_date'] ?></td>
    <td><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
    <td><a class="map-link" target="_blank" href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>">نمایش روی نقشه</a></td>
    <td><?= $log['distance_from_checkin_meters'] !== null ? $log['distance_from_checkin_meters'] . ' متر' : '-' ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
