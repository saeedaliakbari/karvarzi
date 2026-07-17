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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل گزارش معلم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            padding: 20px;
        }
        .card-dashboard {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .card-dashboard .card-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 20px 20px 0 0;
            padding: 20px 30px;
            border: none;
        }
        .card-dashboard .card-header h2 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        .card-dashboard .card-header h2 i {
            margin-left: 10px;
        }
        .table-responsive-custom {
            overflow-x: auto;
            border-radius: 12px;
        }
        .table-custom {
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        .table-custom thead {
            background: #f8f9fa;
        }
        .table-custom th {
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
            padding: 12px 15px;
        }
        .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        .table-custom tbody tr:hover {
            background: #f8f9fa;
        }
        .badge-in {
            background: #11998e;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        .badge-out {
            background: #f5576c;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        .selfie-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #f0f0f0;
            transition: transform 0.3s;
        }
        .selfie-img:hover {
            transform: scale(2.5);
            z-index: 1000;
            position: relative;
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .map-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .map-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px 20px;
            transition: all 0.3s;
        }
        .stat-box:hover {
            background: #e9ecef;
        }
        .stat-box .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #495057;
        }
        .stat-box .label {
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="card card-dashboard">
            <!-- Header -->
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h2>
                    <i class="fas fa-chart-line"></i>
                    گزارش حضور و غیاب
                </h2>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    خروج
                </a>
            </div>
            
            <!-- Body -->
            <div class="card-body p-4 p-md-5">
                <!-- Statistics -->
                <?php 
                    $totalIn = 0;
                    $totalOut = 0;
                    foreach ($logs as $log) {
                        if ($log['type'] === 'in') $totalIn++;
                        else $totalOut++;
                    }
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <div class="number"><?= count($logs) ?></div>
                            <div class="label">کل ثبت‌ها</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <div class="number text-success"><?= $totalIn ?></div>
                            <div class="label">ورود</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <div class="number text-danger"><?= $totalOut ?></div>
                            <div class="label">خروج</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <div class="number text-primary"><?= count(array_unique(array_column($logs, 'name'))) ?></div>
                            <div class="label">دانش‌آموزان</div>
                        </div>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="table-responsive-custom">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-database"></i>
                            <h5 class="mt-3 text-muted">هیچ داده‌ای برای نمایش وجود ندارد</h5>
                        </div>
                    <?php else: ?>
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>عکس</th>
                                    <th>نام</th>
                                    <th>موبایل</th>
                                    <th>نوع</th>
                                    <th>تاریخ</th>
                                    <th>ساعت</th>
                                    <th>موقعیت</th>
                                    <th>فاصله</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <img class="selfie-img" src="<?= htmlspecialchars($log['selfie_path']) ?>" alt="عکس">
                                    </td>
                                    <td><strong><?= htmlspecialchars($log['name']) ?></strong></td>
                                    <td dir="ltr"><?= htmlspecialchars($log['mobile']) ?></td>
                                    <td>
                                        <?php if ($log['type'] === 'in'): ?>
                                            <span class="badge-in"><i class="fas fa-sign-in-alt me-1"></i> ورود</span>
                                        <?php else: ?>
                                            <span class="badge-out"><i class="fas fa-sign-out-alt me-1"></i> خروج</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $log['log_date'] ?></td>
                                    <td><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <a class="map-link" target="_blank" href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            نقشه
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($log['distance_from_checkin_meters'] !== null): ?>
                                            <span class="badge bg-light text-dark">
                                                <?= number_format($log['distance_from_checkin_meters']) ?> متر
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Footer -->
                <div class="mt-4 text-center text-muted small">
                    <i class="far fa-clock me-1"></i>
                    آخرین به‌روزرسانی: <?= date('Y/m/d H:i:s') ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>