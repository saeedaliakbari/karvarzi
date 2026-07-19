<?php
require 'config.php';

if (empty($_SESSION['is_teacher'])) {
    header('Location: teacher_login.php');
    exit;
}

$db = getDB();
$view = ($_GET['view'] ?? '') === 'students' ? 'students' : 'attendance';
$search = trim($_GET['q'] ?? '');
$logs = [];
$students = [];
$selectedStudent = null;
$selectedStudentId = (int) ($_GET['id'] ?? 0);

function displayOrDash($value) {
    $value = trim((string) $value);
    return $value !== '' ? htmlspecialchars($value) : '<span class="text-muted">-</span>';
}

if ($view === 'students') {
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(name LIKE :search OR mobile LIKE :search OR national_id LIKE :search OR guardian_name LIKE :search OR guardian_mobile LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $sql = 'SELECT id, name, mobile, national_id, guardian_name, internship_address, internship_lat, internship_lng, guardian_mobile, created_at
            FROM students';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY name ASC';

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selectedStudentId > 0) {
        foreach ($students as $row) {
            if ((int) $row['id'] === $selectedStudentId) {
                $selectedStudent = $row;
                break;
            }
        }
        if (!$selectedStudent) {
            $stmt = $db->prepare('SELECT id, name, mobile, national_id, guardian_name, internship_address, internship_lat, internship_lng, guardian_mobile, created_at FROM students WHERE id = ?');
            $stmt->execute([$selectedStudentId]);
            $selectedStudent = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
} else {
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
               l.selfie_path, l.work_report, l.distance_from_checkin_meters, l.duration_from_checkin_minutes, l.created_at
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
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view === 'students' ? 'اطلاعات دانش‌آموزان' : 'پنل گزارش معلم' ?></title>
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
        .student-detail-card {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
        }
        .student-detail-card .detail-label {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        .student-detail-card .detail-value {
            font-weight: 600;
            word-break: break-word;
        }
        .btn-view-student {
            border-radius: 10px;
            white-space: nowrap;
        }
        .table-custom tbody tr.selected-student {
            background: rgba(245, 87, 108, 0.08);
        }
        .btn-more {
            border-radius: 10px;
            white-space: nowrap;
        }
        .log-detail-modal .modal-content {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .log-detail-modal .modal-header,
        .log-detail-modal .modal-footer {
            border-color: var(--border);
        }
        .log-detail-modal .detail-label {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        .log-detail-modal .detail-value {
            font-weight: 600;
            word-break: break-word;
            white-space: pre-wrap;
        }
        .log-detail-modal .detail-selfie {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 14px;
            border: 3px solid var(--border);
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
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2>
                    <i class="fas <?= $view === 'students' ? 'fa-users' : 'fa-chart-line' ?>"></i>
                    <?= $view === 'students' ? 'اطلاعات دانش‌آموزان' : 'گزارش حضور و غیاب' ?>
                </h2>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <nav class="page-nav">
                        <a href="teacher.php" class="<?= $view === 'attendance' ? 'active' : '' ?>">
                            <i class="fas fa-clipboard-list me-1"></i>
                            حضور
                        </a>
                        <a href="teacher.php?view=students" class="<?= $view === 'students' ? 'active' : '' ?>">
                            <i class="fas fa-user-graduate me-1"></i>
                            دانش‌آموزان
                        </a>
                    </nav>
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
                <?php if ($view === 'students'): ?>
                    <div class="filter-card p-3 p-md-4 mb-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="view" value="students">
                            <?php if ($selectedStudentId > 0): ?>
                                <input type="hidden" name="id" value="<?= $selectedStudentId ?>">
                            <?php endif; ?>
                            <div class="col-12 col-md-8 col-lg-9">
                                <label class="form-label fw-bold">جستجوی دانش‌آموز</label>
                                <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>"
                                       placeholder="نام، موبایل، کدملی یا سرپرست">
                            </div>
                            <div class="col-6 col-md-2 col-lg-1 d-grid filter-actions">
                                <button type="submit" class="btn btn-primary">جستجو</button>
                            </div>
                            <div class="col-6 col-md-2 col-lg-2 d-grid filter-actions">
                                <a href="teacher.php?view=students" class="btn btn-outline-secondary">پاک</a>
                            </div>
                        </form>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="stat-box text-center">
                                <div class="number text-primary"><?= count($students) ?></div>
                                <div class="label">تعداد دانش‌آموزان</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 <?= $selectedStudent ? 'col-lg-7' : '' ?>">
                            <div class="table-responsive-custom">
                                <?php if (empty($students)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash"></i>
                                        <h5 class="mt-3 text-muted">هیچ دانش‌آموزی یافت نشد</h5>
                                    </div>
                                <?php else: ?>
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>نام</th>
                                                <th>موبایل</th>
                                                <th>کدملی</th>
                                                <th>سرپرست</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                            <tr class="<?= $selectedStudentId === (int) $student['id'] ? 'selected-student' : '' ?>">
                                                <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                                                <td dir="ltr"><?= displayOrDash($student['mobile']) ?></td>
                                                <td dir="ltr"><?= displayOrDash($student['national_id'] ?? '') ?></td>
                                                <td><?= displayOrDash($student['guardian_name'] ?? '') ?></td>
                                                <td>
                                                    <a class="btn btn-sm btn-outline-primary btn-view-student"
                                                       href="teacher.php?view=students&id=<?= (int) $student['id'] ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                                                        <i class="fas fa-eye me-1"></i>
                                                        مشاهده
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($selectedStudent): ?>
                        <div class="col-12 col-lg-5">
                            <div class="student-detail-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-bold mb-1">
                                            <i class="fas fa-id-card me-2"></i>
                                            جزئیات دانش‌آموز
                                        </h5>
                                        <small class="text-muted">کد: <?= (int) $selectedStudent['id'] ?></small>
                                    </div>
                                    <a href="teacher.php?view=students<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm btn-outline-secondary">
                                        بستن
                                    </a>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="detail-label">نام و نام خانوادگی</div>
                                        <div class="detail-value"><?= displayOrDash($selectedStudent['name']) ?></div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="detail-label">شماره موبایل</div>
                                        <div class="detail-value" dir="ltr"><?= displayOrDash($selectedStudent['mobile']) ?></div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="detail-label">کدملی</div>
                                        <div class="detail-value" dir="ltr"><?= displayOrDash($selectedStudent['national_id'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="detail-label">نام سرپرست</div>
                                        <div class="detail-value"><?= displayOrDash($selectedStudent['guardian_name'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="detail-label">موبایل سرپرست</div>
                                        <div class="detail-value" dir="ltr"><?= displayOrDash($selectedStudent['guardian_mobile'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">آدرس محل کارورزی</div>
                                        <div class="detail-value"><?= displayOrDash($selectedStudent['internship_address'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">موقعیت محل کارورزی</div>
                                        <div class="detail-value">
                                            <?php if (!empty($selectedStudent['internship_lat']) && !empty($selectedStudent['internship_lng'])): ?>
                                                <a class="map-link" target="_blank"
                                                   href="https://www.google.com/maps?q=<?= urlencode($selectedStudent['internship_lat'] . ',' . $selectedStudent['internship_lng']) ?>">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    مشاهده روی نقشه
                                                </a>
                                                <div class="text-muted small mt-1" dir="ltr">
                                                    <?= htmlspecialchars($selectedStudent['internship_lat']) ?>, <?= htmlspecialchars($selectedStudent['internship_lng']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">تاریخ ثبت‌نام</div>
                                        <div class="detail-value"><?= htmlspecialchars(jalaliDate($selectedStudent['created_at'], 'Y/m/d H:i')) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <div class="filter-card p-3 p-md-4 mb-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label fw-bold">جستجو</label>
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="نام یا موبایل">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">نوع</label>
                            <select name="type" class="form-select">
                                <option value="" <?= ($typeFilter ?? '') === '' ? 'selected' : '' ?>>همه</option>
                                <option value="in" <?= ($typeFilter ?? '') === 'in' ? 'selected' : '' ?>>ورود</option>
                                <option value="out" <?= ($typeFilter ?? '') === 'out' ? 'selected' : '' ?>>خروج</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">از تاریخ</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom ?? '') ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">تا تاریخ</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo ?? '') ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label fw-bold">مرتب‌سازی</label>
                            <select name="sort" class="form-select">
                                <option value="created_desc" <?= ($sort ?? '') === 'created_desc' ? 'selected' : '' ?>>جدیدترین</option>
                                <option value="created_asc" <?= ($sort ?? '') === 'created_asc' ? 'selected' : '' ?>>قدیمی‌ترین</option>
                                <option value="date_desc" <?= ($sort ?? '') === 'date_desc' ? 'selected' : '' ?>>تاریخ نزولی</option>
                                <option value="date_asc" <?= ($sort ?? '') === 'date_asc' ? 'selected' : '' ?>>تاریخ صعودی</option>
                                <option value="name_asc" <?= ($sort ?? '') === 'name_asc' ? 'selected' : '' ?>>نام الفبایی</option>
                                <option value="name_desc" <?= ($sort ?? '') === 'name_desc' ? 'selected' : '' ?>>نام معکوس</option>
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <?php
                                    $durationText = '-';
                                    if ($log['duration_from_checkin_minutes'] !== null) {
                                        $totalMinutes = (int) $log['duration_from_checkin_minutes'];
                                        $hours = intdiv($totalMinutes, 60);
                                        $minutes = $totalMinutes % 60;
                                        $durationText = $hours . ' ساعت و ' . $minutes . ' دقیقه';
                                    }
                                    $distanceText = $log['distance_from_checkin_meters'] !== null
                                        ? number_format($log['distance_from_checkin_meters']) . ' متر'
                                        : '-';
                                    $typeText = $log['type'] === 'in' ? 'ورود' : 'خروج';
                                ?>
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
                                            <span class="badge bg-light text-dark"><?= htmlspecialchars($durationText) ?></span>
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
                                            <span class="badge bg-light text-dark"><?= htmlspecialchars($distanceText) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary btn-more"
                                            data-bs-toggle="modal"
                                            data-bs-target="#logDetailModal"
                                            data-name="<?= htmlspecialchars($log['name'], ENT_QUOTES) ?>"
                                            data-mobile="<?= htmlspecialchars($log['mobile'], ENT_QUOTES) ?>"
                                            data-type="<?= htmlspecialchars($typeText, ENT_QUOTES) ?>"
                                            data-date="<?= htmlspecialchars(jalaliDate($log['log_date']), ENT_QUOTES) ?>"
                                            data-time="<?= htmlspecialchars(jalaliDate($log['created_at'], 'Y/m/d H:i:s'), ENT_QUOTES) ?>"
                                            data-duration="<?= htmlspecialchars($durationText, ENT_QUOTES) ?>"
                                            data-distance="<?= htmlspecialchars($distanceText, ENT_QUOTES) ?>"
                                            data-lat="<?= htmlspecialchars((string) $log['latitude'], ENT_QUOTES) ?>"
                                            data-lng="<?= htmlspecialchars((string) $log['longitude'], ENT_QUOTES) ?>"
                                            data-selfie="<?= htmlspecialchars($log['selfie_path'], ENT_QUOTES) ?>"
                                            data-report="<?= htmlspecialchars($log['work_report'] ?? '', ENT_QUOTES) ?>"
                                        >
                                            <i class="fas fa-ellipsis-h me-1"></i>
                                            بیشتر
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="modal fade log-detail-modal" id="logDetailModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-info-circle me-2"></i>
                                            جزئیات ثبت حضور
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-3 text-center">
                                                <img id="detailSelfie" class="detail-selfie" src="" alt="عکس">
                                            </div>
                                            <div class="col-12 col-md-9">
                                                <div class="row g-3">
                                                    <div class="col-12 col-md-6">
                                                        <div class="detail-label">نام</div>
                                                        <div class="detail-value" id="detailName">-</div>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <div class="detail-label">موبایل</div>
                                                        <div class="detail-value" id="detailMobile" dir="ltr">-</div>
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <div class="detail-label">نوع</div>
                                                        <div class="detail-value" id="detailType">-</div>
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <div class="detail-label">تاریخ</div>
                                                        <div class="detail-value" id="detailDate">-</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="detail-label">ساعت</div>
                                                        <div class="detail-value" id="detailTime">-</div>
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <div class="detail-label">مدت</div>
                                                        <div class="detail-value" id="detailDuration">-</div>
                                                    </div>
                                                    <div class="col-6 col-md-4">
                                                        <div class="detail-label">فاصله</div>
                                                        <div class="detail-value" id="detailDistance">-</div>
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <div class="detail-label">موقعیت</div>
                                                        <div class="detail-value">
                                                            <a id="detailMap" class="map-link" target="_blank" href="#">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                مشاهده روی نقشه
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="detail-label">گزارش کار</div>
                                                <div class="detail-value" id="detailReport">-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">بستن</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
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

        const logDetailModal = document.getElementById('logDetailModal');
        if (logDetailModal) {
            logDetailModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                if (!button) return;

                const report = (button.getAttribute('data-report') || '').trim();
                document.getElementById('detailName').textContent = button.getAttribute('data-name') || '-';
                document.getElementById('detailMobile').textContent = button.getAttribute('data-mobile') || '-';
                document.getElementById('detailType').textContent = button.getAttribute('data-type') || '-';
                document.getElementById('detailDate').textContent = button.getAttribute('data-date') || '-';
                document.getElementById('detailTime').textContent = button.getAttribute('data-time') || '-';
                document.getElementById('detailDuration').textContent = button.getAttribute('data-duration') || '-';
                document.getElementById('detailDistance').textContent = button.getAttribute('data-distance') || '-';
                document.getElementById('detailReport').textContent = report !== '' ? report : 'گزارش کاری ثبت نشده است.';

                const selfie = button.getAttribute('data-selfie') || '';
                const selfieImg = document.getElementById('detailSelfie');
                selfieImg.src = selfie;
                selfieImg.style.display = selfie ? 'inline-block' : 'none';

                const lat = button.getAttribute('data-lat') || '';
                const lng = button.getAttribute('data-lng') || '';
                document.getElementById('detailMap').href = `https://www.google.com/maps?q=${lat},${lng}`;
            });
        }
    </script>
</body>
</html>