<?php
require 'config.php';

if (empty($_SESSION['is_teacher'])) {
    header('Location: teacher_login.php');
    exit;
}

$db = getDB();

$search = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'created_desc';

$orderMap = [
    'created_desc' => 'l.created_at DESC',
    'created_asc' => 'l.created_at ASC',
    'date_desc' => 'l.log_date DESC, l.created_at DESC',
    'date_asc' => 'l.log_date ASC, l.created_at ASC',
    'name_asc' => 's.name ASC, l.created_at DESC',
    'name_desc' => 's.name DESC, l.created_at DESC',
    'type_asc' => 'l.type ASC, l.created_at DESC',
    'type_desc' => 'l.type DESC, l.created_at DESC',
];
$orderBy = $orderMap[$sort] ?? $orderMap['created_desc'];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(s.name LIKE :search OR s.mobile LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if (in_array($typeFilter, ['in', 'out'], true)) {
    $where[] = 'l.type = :type';
    $params[':type'] = $typeFilter;
}

if ($dateFrom !== '') {
    $where[] = 'l.log_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'l.log_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$sql = '
    SELECT l.id, s.name, s.mobile, l.type, l.log_date, l.latitude, l.longitude,
           l.selfie_path, l.distance_from_checkin_meters, l.duration_from_checkin_minutes, l.created_at
    FROM attendance_logs l
    JOIN students s ON s.id = l.student_id
';

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY ' . $orderBy . ' LIMIT 200';

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
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
        .filter-card {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .filter-card .form-control,
        .filter-card .form-select {
            background: var(--card-bg);
            color: var(--text);
            border-color: var(--border);
        }
        .filter-card .form-control::placeholder {
            color: var(--muted);
        }
        .filter-actions .btn {
            border-radius: 12px;
        }

        :root {
            --body-bg: #f0f2f5;
            --card-bg: #ffffff;
            --panel-bg: #f8f9fa;
            --text: #1f2937;
            --muted: #6c757d;
            --border: #f0f0f0;
            --accent: #f5576c;
            --accent-2: #f093fb;
        }
        body[data-theme="dark"] {
            --body-bg: #0f172a;
            --card-bg: #111827;
            --panel-bg: #1f2937;
            --text: #f8fafc;
            --muted: #cbd5e1;
            --border: #334155;
            --accent: #f472b6;
            --accent-2: #a78bfa;
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
        .table-custom thead {
            background: var(--panel-bg);
            color: var(--text);
        }
        .table-custom td, .table-custom th {
            border-color: var(--border);
            color: var(--text);
        }
        .table-custom tbody tr:hover {
            background: var(--panel-bg);
        }
        .stat-box, .empty-state {
            background: var(--panel-bg);
            color: var(--text);
        }
        .logout-btn, .theme-toggle {
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
    <div class="container-fluid">
        <div class="card card-dashboard">
            <!-- Header -->
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h2>
                    <i class="fas fa-chart-line"></i>
                    گزارش حضور و غیاب
                </h2>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="themeToggle" class="btn btn-sm theme-toggle">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        خروج
                    </a>
                </div>
            </div>
            
            <!-- Body -->
            <div class="card-body p-4 p-md-5">
                <div class="filter-card p-3 p-md-4 mb-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label fw-bold">جستجو</label>
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="نام یا موبایل">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">نوع</label>
                            <select name="type" class="form-select">
                                <option value="" <?= $typeFilter === '' ? 'selected' : '' ?>>همه</option>
                                <option value="in" <?= $typeFilter === 'in' ? 'selected' : '' ?>>ورود</option>
                                <option value="out" <?= $typeFilter === 'out' ? 'selected' : '' ?>>خروج</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">از تاریخ</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">تا تاریخ</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">مرتب‌سازی</label>
                            <select name="sort" class="form-select">
                                <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>جدیدترین</option>
                                <option value="created_asc" <?= $sort === 'created_asc' ? 'selected' : '' ?>>قدیمی‌ترین</option>
                                <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>تاریخ نزولی</option>
                                <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>تاریخ صعودی</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>نام الفبایی</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>نام معکوس</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-12 col-lg-1 d-grid filter-actions">
                            <button type="submit" class="btn btn-primary">اعمال</button>
                        </div>
                        <div class="col-12 col-md-12 col-lg-1 d-grid filter-actions">
                            <a href="teacher.php" class="btn btn-outline-secondary">پاک</a>
                        </div>
                    </form>
                </div>

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
                                    <th>مدت</th>
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
                                    <td><?= htmlspecialchars(jalaliDate($log['log_date'])) ?></td>
                                    <td><?= htmlspecialchars(jalaliDate($log['created_at'], 'Y/m/d H:i:s')) ?></td>
                                    <td>
                                        <?php if ($log['duration_from_checkin_minutes'] !== null): ?>
                                            <?php
                                                $totalMinutes = (int) $log['duration_from_checkin_minutes'];
                                                $hours = intdiv($totalMinutes, 60);
                                                $minutes = $totalMinutes % 60;
                                            ?>
                                            <span class="badge bg-light text-dark">
                                                <?= $hours ?> ساعت و <?= $minutes ?> دقیقه
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
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
                    آخرین به‌روزرسانی: <?= htmlspecialchars(jalaliDate(date('Y-m-d H:i:s'), 'Y/m/d H:i:s')) ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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