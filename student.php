<?php
require 'config.php';

if (empty($_SESSION['student_id'])) {
    header('Location: index.php');
    exit;
}

$studentId = $_SESSION['student_id'];
$db = getDB();
$view = ($_GET['view'] ?? '') === 'profile' ? 'profile' : 'attendance';
$profileMessage = '';
$profileError = '';

$stmt = $db->prepare('SELECT id, name, mobile, national_id, guardian_name, internship_address, guardian_mobile FROM students WHERE id = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$_SESSION['student_name'] = $student['name'];

if ($view === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $nationalId = trim($_POST['national_id'] ?? '');
    $guardianName = trim($_POST['guardian_name'] ?? '');
    $internshipAddress = trim($_POST['internship_address'] ?? '');
    $guardianMobile = trim($_POST['guardian_mobile'] ?? '');

    if ($name === '') {
        $profileError = 'نام و نام خانوادگی الزامی است.';
    } elseif ($mobile === '') {
        $profileError = 'شماره موبایل الزامی است.';
    } elseif ($nationalId !== '' && !preg_match('/^\d{10}$/', $nationalId)) {
        $profileError = 'کدملی باید ۱۰ رقم باشد.';
    } elseif ($guardianMobile !== '' && !preg_match('/^09\d{9}$/', $guardianMobile)) {
        $profileError = 'شماره موبایل سرپرست معتبر نیست.';
    } elseif (!preg_match('/^09\d{9}$/', $mobile)) {
        $profileError = 'شماره موبایل معتبر نیست.';
    } else {
        $check = $db->prepare('SELECT id FROM students WHERE mobile = ? AND id != ?');
        $check->execute([$mobile, $studentId]);
        if ($check->fetch()) {
            $profileError = 'این شماره موبایل قبلاً ثبت شده است.';
        } else {
            $update = $db->prepare(
                'UPDATE students SET name = ?, mobile = ?, national_id = ?, guardian_name = ?, internship_address = ?, guardian_mobile = ? WHERE id = ?'
            );
            $update->execute([
                $name,
                $mobile,
                $nationalId !== '' ? $nationalId : null,
                $guardianName !== '' ? $guardianName : null,
                $internshipAddress !== '' ? $internshipAddress : null,
                $guardianMobile !== '' ? $guardianMobile : null,
                $studentId,
            ]);

            $_SESSION['student_name'] = $name;
            $profileMessage = 'اطلاعات پروفایل با موفقیت ذخیره شد.';

            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($profileError !== '') {
        $student['name'] = $name;
        $student['mobile'] = $mobile;
        $student['national_id'] = $nationalId;
        $student['guardian_name'] = $guardianName;
        $student['internship_address'] = $internshipAddress;
        $student['guardian_mobile'] = $guardianMobile;
    }
}

$today = date('Y-m-d');
$stmt = $db->prepare('SELECT type FROM attendance_logs WHERE student_id = ? AND log_date = ?');
$stmt->execute([$studentId, $today]);
$todayTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
$hasIn = in_array('in', $todayTypes);
$hasOut = in_array('out', $todayTypes);

$stmt = $db->prepare('SELECT type, created_at FROM attendance_logs WHERE student_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$studentId]);
$recentLogs = $stmt->fetchAll();

function profileValue($value) {
    $value = trim((string) $value);
    return $value !== '' ? htmlspecialchars($value) : '<span class="text-muted">ثبت نشده</span>';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view === 'profile' ? 'پروفایل دانش‌آموز' : 'ثبت حضور' ?></title>
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @font-face {
            font-family: 'Sahel';
            src: url('fonts/Sahel-FD-WOL.woff') format('woff');
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: 'Sahel';
            src: url('fonts/Sahel-Bold-FD-WOL.woff') format('woff');
            font-weight: 700;
            font-style: normal;
        }
        @font-face {
            font-family: 'Sahel';
            src: url('fonts/Sahel-Black-FD-WOL.woff') format('woff');
            font-weight: 900;
            font-style: normal;
        }
        body {
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px 0;
        }
        .card-dashboard {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .card-dashboard .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px 20px 0 0;
            padding: 20px 30px;
            border: none;
        }
        .card-dashboard .card-header h3 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        .card-dashboard .card-header h3 i {
            margin-left: 10px;
        }
        .btn-in {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: transform 0.2s;
        }
        .btn-in:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
        }
        .btn-out {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: transform 0.2s;
        }
        .btn-out:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.4);
        }
        .btn-disabled {
            background: #e9ecef !important;
            color: #6c757d !important;
            border: none;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: not-allowed !important;
        }
        .btn-disabled i {
            color: #6c757d !important;
        }
        .btn:active {
            transform: scale(0.98) !important;
        }
        .msg-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px 20px;
            min-height: 50px;
            display: flex;
            align-items: center;
        }
        .log-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .log-item:hover {
            background: #f8f9fa;
            padding-right: 10px;
            border-radius: 8px;
        }
        .log-item .badge-in {
            background: #11998e;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .log-item .badge-out {
            background: #f5576c;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .log-item .log-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .logout-link {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.3s;
        }
        .logout-link:hover {
            color: #dc3545;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .page-nav {
            display: flex;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 4px;
        }
        .page-nav a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: background 0.2s, color 0.2s;
        }
        .page-nav a.active,
        .page-nav a:hover {
            background: rgba(255,255,255,0.22);
            color: #fff;
        }
        .profile-field {
            background: var(--panel-bg);
            border-radius: 12px;
            padding: 14px 16px;
            height: 100%;
        }
        .profile-field .label {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .profile-field .value {
            font-weight: 600;
            word-break: break-word;
        }
        .profile-form .form-control,
        .profile-form .form-label {
            color: var(--text);
        }
        .profile-form .form-control {
            background: var(--panel-bg);
            border-color: var(--border);
        }
        .profile-form .form-control:focus {
            background: var(--card-bg);
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.2);
        }
        .btn-save-profile {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 12px 20px;
        }
        .btn-save-profile:hover {
            color: #fff;
            opacity: 0.95;
        }

        :root {
            --body-bg: #f0f2f5;
            --card-bg: #ffffff;
            --panel-bg: #f8f9fa;
            --text: #1f2937;
            --muted: #6c757d;
            --border: #f0f0f0;
            --accent: #667eea;
            --accent-2: #764ba2;
        }
        body[data-theme="dark"] {
            --body-bg: #0f172a;
            --card-bg: #111827;
            --panel-bg: #1f2937;
            --text: #f8fafc;
            --muted: #cbd5e1;
            --border: #334155;
            --accent: #8b5cf6;
            --accent-2: #38bdf8;
        }
        body {
            font-family: 'Sahel', 'Segoe UI', Tahoma, sans-serif;
            background: var(--body-bg);
            color: var(--text);
        }
        .card-dashboard {
            background: var(--card-bg);
            color: var(--text);
        }
        .card-dashboard .card-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
        }
        .msg-box, .stat-box, .log-item:hover {
            background: var(--panel-bg);
            color: var(--text);
        }
        .log-item {
            border-bottom-color: var(--border);
        }
        .logout-link, .status-badge, .text-muted {
            color: var(--muted) !important;
        }
        .theme-toggle {
            border: 1px solid var(--border);
            background: var(--panel-bg);
            color: var(--text);
        }
        .theme-toggle:hover {
            background: var(--card-bg);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8 col-xl-7">
                <div class="card card-dashboard">
                    <!-- Header -->
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <h3 class="mb-0">
                                <i class="fas fa-user-circle"></i>
                                سلام <?= htmlspecialchars($_SESSION['student_name']) ?>
                            </h3>
                            <div class="d-flex align-items-center gap-2">
                                <nav class="page-nav">
                                    <a href="student.php" class="<?= $view === 'attendance' ? 'active' : '' ?>">
                                        <i class="fas fa-clipboard-check me-1"></i>
                                        حضور
                                    </a>
                                    <a href="student.php?view=profile" class="<?= $view === 'profile' ? 'active' : '' ?>">
                                        <i class="fas fa-id-card me-1"></i>
                                        پروفایل
                                    </a>
                                </nav>
                                <button type="button" id="themeToggle" class="btn btn-sm theme-toggle">
                                    <i class="fas fa-moon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="card-body p-4 p-md-5">
                        <?php if ($view === 'profile'): ?>
                            <div class="mb-4">
                                <h5 class="fw-bold mb-1">
                                    <i class="fas fa-user-edit me-2"></i>
                                    پروفایل دانش‌آموز
                                </h5>
                                <p class="text-muted mb-0">اطلاعات زیر را مشاهده و در صورت نیاز ویرایش کنید.</p>
                            </div>

                            <?php if ($profileMessage): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($profileMessage) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($profileError): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?= htmlspecialchars($profileError) ?>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3 mb-4">
                                <div class="col-12 col-md-6">
                                    <div class="profile-field">
                                        <div class="label">نام و نام خانوادگی</div>
                                        <div class="value"><?= profileValue($student['name']) ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="profile-field">
                                        <div class="label">شماره موبایل</div>
                                        <div class="value" dir="ltr"><?= profileValue($student['mobile']) ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="profile-field">
                                        <div class="label">کدملی</div>
                                        <div class="value" dir="ltr"><?= profileValue($student['national_id'] ?? '') ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="profile-field">
                                        <div class="label">نام سرپرست</div>
                                        <div class="value"><?= profileValue($student['guardian_name'] ?? '') ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="profile-field">
                                        <div class="label">شماره موبایل سرپرست</div>
                                        <div class="value" dir="ltr"><?= profileValue($student['guardian_mobile'] ?? '') ?></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="profile-field">
                                        <div class="label">آدرس محل کارورزی</div>
                                        <div class="value"><?= profileValue($student['internship_address'] ?? '') ?></div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-pen me-2"></i>
                                ویرایش اطلاعات
                            </h6>
                            <form method="post" action="student.php?view=profile" class="profile-form">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="name">نام و نام خانوادگی</label>
                                        <input type="text" class="form-control" id="name" name="name" required
                                               value="<?= htmlspecialchars($student['name'] ?? '') ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="mobile">شماره موبایل</label>
                                        <input type="tel" class="form-control" id="mobile" name="mobile" required dir="ltr"
                                               value="<?= htmlspecialchars($student['mobile'] ?? '') ?>" placeholder="09xxxxxxxxx">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="national_id">کدملی</label>
                                        <input type="text" class="form-control" id="national_id" name="national_id" dir="ltr" maxlength="10"
                                               value="<?= htmlspecialchars($student['national_id'] ?? '') ?>" placeholder="۱۰ رقم">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="guardian_name">نام سرپرست</label>
                                        <input type="text" class="form-control" id="guardian_name" name="guardian_name"
                                               value="<?= htmlspecialchars($student['guardian_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="guardian_mobile">شماره موبایل سرپرست</label>
                                        <input type="tel" class="form-control" id="guardian_mobile" name="guardian_mobile" dir="ltr"
                                               value="<?= htmlspecialchars($student['guardian_mobile'] ?? '') ?>" placeholder="09xxxxxxxxx">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="internship_address">آدرس محل کارورزی</label>
                                        <textarea class="form-control" id="internship_address" name="internship_address" rows="3"><?= htmlspecialchars($student['internship_address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-save-profile w-100">
                                            <i class="fas fa-save me-2"></i>
                                            ذخیره اطلاعات
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                        <!-- Status -->
                        <div class="mb-4 text-center">
                            <span class="status-badge bg-light">
                                <i class="far fa-calendar-alt me-2"></i>
                                امروز: <?= jalaliDate($today) ?>
                            </span>
                        </div>
                        
                        <!-- Buttons -->
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <?php if (!$hasIn): ?>
                                    <button class="btn btn-in w-100 text-white" onclick="triggerCapture('in')">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        ثبت ورود
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-disabled w-100" disabled>
                                        <i class="fas fa-check-circle me-2"></i>
                                        ورود امروز ثبت شده
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <?php if ($hasIn && !$hasOut): ?>
                                    <button class="btn btn-out w-100 text-white" onclick="triggerCapture('out')">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        ثبت خروج
                                    </button>
                                <?php elseif ($hasOut): ?>
                                    <button class="btn btn-disabled w-100" disabled>
                                        <i class="fas fa-check-circle me-2"></i>
                                        خروج امروز ثبت شده
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-disabled w-100" disabled>
                                        <i class="fas fa-clock me-2"></i>
                                        ابتدا ورود را ثبت کنید
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Message -->
                        <div class="msg-box" id="msg">
                            <i class="fas fa-info-circle text-muted me-2"></i>
                            <span class="text-muted">برای ثبت حضور، یکی از دکمه‌های بالا را بزنید</span>
                        </div>
                        
                        <!-- Hidden Camera Input -->
                        <input type="file" id="cameraInput" accept="image/*" capture="user" style="display:none;">
                        
                        <!-- Recent Logs -->
                        <div class="mt-5">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    تاریخچه اخیر
                                </h5>
                                <span class="badge bg-light text-muted">آخرین ۱۰</span>
                            </div>
                            
                            <?php if (empty($recentLogs)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    هنوز هیچ ثبت حضوری ندارید
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): ?>
                                    <div class="log-item">
                                        <span>
                                            <?php if ($log['type'] === 'in'): ?>
                                                <span class="badge-in">
                                                    <i class="fas fa-sign-in-alt me-1"></i>
                                                    ورود
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-out">
                                                    <i class="fas fa-sign-out-alt me-1"></i>
                                                    خروج
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="log-time">
                                            <i class="far fa-clock me-1"></i>
                                            <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Footer -->
                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="logout.php" class="logout-link">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                خروج از حساب
                            </a>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                کد: <?= $studentId ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($view === 'attendance'): ?>
    <script>
        let pendingType = null;
        const cameraInput = document.getElementById('cameraInput');
        const msg = document.getElementById('msg');

        function setMessage(text, type = 'info') {
            const icons = {
                'info': 'fa-info-circle',
                'success': 'fa-check-circle',
                'error': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle'
            };
            const colors = {
                'info': 'text-muted',
                'success': 'text-success',
                'error': 'text-danger',
                'warning': 'text-warning'
            };
            msg.innerHTML = `<i class="fas ${icons[type] || icons.info} me-2 ${colors[type] || colors.info}"></i>
                            <span class="${colors[type] || colors.info}">${text}</span>`;
        }

        function triggerCapture(type) {
            pendingType = type;
            cameraInput.value = '';
            cameraInput.click();
        }

        cameraInput.addEventListener('change', function() {
            if (!cameraInput.files.length) return;

            if (!navigator.geolocation) {
                setMessage('مرورگر شما از موقعیت مکانی پشتیبانی نمی‌کند.', 'error');
                return;
            }

            setMessage('در حال دریافت موقعیت مکانی...', 'info');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    upload(position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    const messages = {
                        1: 'دسترسی به موقعیت مکانی رد شد.',
                        2: 'اطلاعات موقعیت در دسترس نیست.',
                        3: 'زمان دریافت موقعیت به پایان رسید.'
                    };
                    setMessage(messages[error.code] || 'خطا در دریافت موقعیت مکانی.', 'error');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });

        function upload(lat, lng) {
            if (lat === 0 || lng === 0) {
                setMessage('مختصات مکانی نامعتبر است.', 'error');
                return;
            }

            setMessage('در حال ثبت...', 'info');
            const formData = new FormData();
            formData.append('type', pendingType);
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('selfie', cameraInput.files[0]);

            fetch('save_log.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    let msg = data.message || 'با موفقیت ثبت شد.';
                    if (data.type === 'out' && data.duration_minutes != null) {
                        const h = Math.floor(data.duration_minutes / 60);
                        const m = data.duration_minutes % 60;
                        msg += ` مدت حضور: ${h} ساعت و ${m} دقیقه.`;
                    }
                    if (data.warning) {
                        console.warn('attendance warning:', data.warning, data.debug || null);
                        msg += ' هشدار: ' + data.warning;
                        setMessage(msg, 'error');
                    } else {
                        setMessage(msg, 'success');
                    }
                    setTimeout(() => location.reload(), data.warning ? 3500 : 800);
                } else {
                    console.error('attendance error:', data);
                    let err = data.error || 'خطا در ثبت.';
                    if (data.debug) {
                        err += ' [' + data.debug + ']';
                    }
                    setMessage(err, 'error');
                }
            })
            .catch((err) => {
                console.error(err);
                setMessage('خطا در ارتباط با سرور.', 'error');
            });
        }
    </script>
    <?php endif; ?>
    <script>
        const themeToggle = document.getElementById('themeToggle');
        const savedTheme = localStorage.getItem('karvarzi-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');
        document.body.setAttribute('data-theme', initialTheme);
        if (themeToggle) {
            themeToggle.innerHTML = initialTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            themeToggle.addEventListener('click', () => {
                const nextTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.body.setAttribute('data-theme', nextTheme);
                localStorage.setItem('karvarzi-theme', nextTheme);
                themeToggle.innerHTML = nextTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            });
        }
    </script>
</body>
</html>
