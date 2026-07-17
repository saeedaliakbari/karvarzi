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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت حضور</title>
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8 col-xl-7">
                <div class="card card-dashboard">
                    <!-- Header -->
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-user-circle"></i>
                            سلام <?= htmlspecialchars($_SESSION['student_name']) ?>
                        </h3>
                    </div>
                    
                    <!-- Body -->
                    <div class="card-body p-4 p-md-5">
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
                    setMessage('با موفقیت ثبت شد.', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    setMessage(data.error || 'خطا در ثبت.', 'error');
                }
            })
            .catch(() => {
                setMessage('خطا در ارتباط با سرور.', 'error');
            });
        }
    </script>
</body>
</html>
<?php
// تابع کمکی برای تبدیل تاریخ شمسی (اختیاری)
function jalaliDate($gregorian) {
    // این تابع را می‌توانید با کتابخانه‌های تبدیل تاریخ شمسی تکمیل کنید
    // فعلاً همان تاریخ میلادی را برمی‌گرداند
    return $gregorian;
}
?>