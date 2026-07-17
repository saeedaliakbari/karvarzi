<?php
require 'config.php';

if (empty($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$studentId = $_SESSION['student_id'];
$db = getDB();

$today = date('Y-m-d');
$stmt = $db->prepare('SELECT type FROM attendance_logs WHERE student_id = ? AND log_date = ?');
$stmt->execute([$studentId, $today]);
$todayTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
$hasIn = in_array('in', $todayTypes);
$hasOut = in_array('out', $todayTypes);

$stmt = $db->prepare('SELECT type, created_at FROM attendance_logs WHERE student_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$studentId]);
$recentLogs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ثبت حضور</title>
<style>
    body { font-family: Tahoma, sans-serif; background: #f5f5f5; display: flex; justify-content: center; padding-top: 40px; }
    .box { background: #fff; padding: 24px; border-radius: 12px; width: 340px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
    button { width: 100%; padding: 12px; margin-top: 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; }
    .btn-in { background: #2e7d32; color: #fff; }
    .btn-out { background: #c0392b; color: #fff; }
    .btn-disabled { background: #ccc; color: #666; }
    .msg { font-size: 13px; margin-top: 10px; min-height: 18px; }
    .log-item { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class="box">
    <h3>سلام <?= htmlspecialchars($_SESSION['student_name']) ?></h3>

    <?php if (!$hasIn): ?>
        <button class="btn-in" onclick="triggerCapture('in')">ثبت ورود</button>
    <?php else: ?>
        <button class="btn-disabled" disabled>ورود امروز ثبت شده</button>
    <?php endif; ?>

    <?php if ($hasIn && !$hasOut): ?>
        <button class="btn-out" onclick="triggerCapture('out')">ثبت خروج</button>
    <?php elseif ($hasOut): ?>
        <button class="btn-disabled" disabled>خروج امروز ثبت شده</button>
    <?php else: ?>
        <button class="btn-disabled" disabled>ابتدا ورود را ثبت کنید</button>
    <?php endif; ?>

    <p class="msg" id="msg"></p>

    <input type="file" id="cameraInput" accept="image/*" capture="user" style="display:none;">

    <div style="margin-top:16px;">
        <p style="font-size:13px; color:#777; margin-bottom:4px;">تاریخچه اخیر</p>
        <?php foreach ($recentLogs as $log): ?>
            <div class="log-item">
                <span><?= $log['type'] === 'in' ? 'ورود' : 'خروج' ?></span>
                <span style="color:#888;"><?= $log['created_at'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <p style="margin-top:16px;"><a href="logout.php">خروج از حساب</a></p>
</div>

<script>
let pendingType = null;
const cameraInput = document.getElementById('cameraInput');
const msg = document.getElementById('msg');

function triggerCapture(type) {
    pendingType = type;
    cameraInput.value = '';
    cameraInput.click();
}

cameraInput.addEventListener('change', function() {
    if (!cameraInput.files.length) return;
    msg.textContent = 'در حال دریافت موقعیت مکانی...';

    navigator.geolocation.getCurrentPosition(function(pos) {
        upload(pos.coords.latitude, pos.coords.longitude);
    }, function() {
        // اگر موقعیت در دسترس نبود، با مقدار صفر ثبت می‌شود تا فرآیند متوقف نشود
        upload(0, 0);
    }, { enableHighAccuracy: true, timeout: 8000 });
});

function upload(lat, lng) {
    msg.textContent = 'در حال ثبت...';
    const formData = new FormData();
    formData.append('type', pendingType);
    formData.append('lat', lat);
    formData.append('lng', lng);
    formData.append('selfie', cameraInput.files[0]);

    console.log('📤 ارسال به سرور:', {type: pendingType, lat, lng}); // لاگ
    
    fetch('save_log.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        console.log('📥 پاسخ دریافت شد:', response.status, response.statusText); // لاگ
        return response.text(); // به جای json، متن بگیر
    })
    .then(text => {
        console.log('📄 محتوای پاسخ:', text); // لاگ مهم
        try {
            const data = JSON.parse(text);
            if (data.ok) {
                msg.textContent = 'با موفقیت ثبت شد.';
                setTimeout(() => location.reload(), 800);
            } else {
                msg.textContent = data.error || 'خطا در ثبت.';
            }
        } catch (e) {
            console.error('❌ خطا در parse JSON:', e);
            console.log('متن پاسخ:', text);
            msg.textContent = 'خطا در پاسخ سرور';
        }
    })
    .catch(err => {
        console.error('❌ خطای شبکه:', err);
        msg.textContent = 'خطا در ارتباط با سرور.';
    });
}
</script>
</body>
</html>
