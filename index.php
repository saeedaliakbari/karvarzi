<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if ($mobile === '') {
        $error = 'شماره موبایل را وارد کنید.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name FROM students WHERE mobile = ?');
        $stmt->execute([$mobile]);
        $student = $stmt->fetch();

        if (!$student) {
            if ($name === '') {
                $error = 'برای اولین ورود، نام خود را هم وارد کنید.';
            } else {
                $stmt = $db->prepare('INSERT INTO students (mobile, name) VALUES (?, ?)');
                $stmt->execute([$mobile, $name]);
                $studentId = $db->lastInsertId();
                $_SESSION['student_id'] = $studentId;
                $_SESSION['student_name'] = $name;
                header('Location: student.php');
                exit;
            }
        } else {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            header('Location: student.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود دانش‌آموز</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card-login {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        .card-login .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px 20px 0 0 !important;
            padding: 25px;
            border: none;
        }
        .card-login .card-header h3 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        .card-login .card-header i {
            font-size: 2rem;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-primary-custom:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .input-icon .form-control {
            padding-right: 45px;
        }
        .teacher-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .teacher-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        :root {
            --bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-bg: rgba(255,255,255,0.95);
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --text: #1f2937;
            --muted: #64748b;
            --border: #e0e0e0;
            --accent: #667eea;
            --accent-2: #764ba2;
        }
        body[data-theme="dark"] {
            --bg: linear-gradient(135deg, #0f172a 0%, #111827 100%);
            --card-bg: rgba(15, 23, 42, 0.95);
            --surface: #111827;
            --surface-2: #1f2937;
            --text: #f8fafc;
            --muted: #cbd5e1;
            --border: #334155;
            --accent: #8b5cf6;
            --accent-2: #38bdf8;
        }
        body {
            font-family: 'Sahel', 'Segoe UI', Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .card-login {
            background: var(--card-bg);
            color: var(--text);
        }
        .card-login .card-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
        }
        .card-login .card-body {
            background: transparent;
        }
        .form-control {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border);
        }
        .form-control::placeholder {
            color: var(--muted);
        }
        .teacher-link {
            color: var(--accent);
        }
        .theme-toggle {
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
        }
        .theme-toggle:hover {
            background: var(--surface-2);
            color: var(--text);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="card card-login">
                    <div class="card-header text-center">
                        <i class="fas fa-user-graduate mb-2"></i>
                        <h3>ورود دانش‌آموز</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" id="themeToggle" class="btn btn-sm theme-toggle">
                                <i class="fas fa-moon me-1"></i>
                                تم تاریک
                            </button>
                        </div>

                        <form method="POST">
                            <div class="mb-4 input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" class="form-control" name="mobile" placeholder="شماره موبایل" required>
                            </div>
                            
                            <div class="mb-4 input-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" class="form-control" name="name" placeholder="نام و نام خانوادگی (فقط برای اولین ورود)">
                                <small class="text-muted form-text">اگر قبلاً ثبت‌نام کرده‌اید، این فیلد را خالی بگذارید.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary-custom btn-lg w-100 text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                ورود
                            </button>
                        </form>
                        
                        <!-- <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="teacher_login.php" class="teacher-link">
                                <i class="fas fa-chalkboard-teacher me-1"></i>
                                ورود معلم
                            </a>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>