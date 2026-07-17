<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['password'] ?? '') === TEACHER_PASSWORD) {
        $_SESSION['is_teacher'] = true;
        header('Location: teacher.php');
        exit;
    }
    $error = 'رمز عبور اشتباه است.';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود معلم</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card-login {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
        }
        .card-login .card-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 20px 20px 0 0;
            padding: 25px;
            border: none;
        }
        .card-login .card-header h3 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        .btn-teacher {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-teacher:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.4);
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #f5576c;
            box-shadow: 0 0 0 0.2rem rgba(245, 87, 108, 0.25);
        }
        .back-link {
            color: #f5576c;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            color: #f093fb;
            text-decoration: underline;
        }

        :root {
            --bg: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --card-bg: rgba(255,255,255,0.95);
            --surface: #ffffff;
            --text: #1f2937;
            --muted: #64748b;
            --border: #e0e0e0;
            --accent: #f5576c;
            --accent-2: #f093fb;
        }
        body[data-theme="dark"] {
            --bg: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            --card-bg: rgba(15, 23, 42, 0.95);
            --surface: #111827;
            --text: #f8fafc;
            --muted: #cbd5e1;
            --border: #334155;
            --accent: #f472b6;
            --accent-2: #a78bfa;
        }
        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
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
        .form-control {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border);
        }
        .form-control::placeholder {
            color: var(--muted);
        }
        .theme-toggle {
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
        }
        .theme-toggle:hover {
            background: var(--surface);
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="card card-login">
                    <div class="card-header text-center">
                        <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                        <h3>ورود معلم</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" id="themeToggle" class="btn btn-sm theme-toggle">
                                <i class="fas fa-moon me-1"></i>
                                تم تاریک
                            </button>
                        </div>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-lock me-2"></i>
                                    رمز عبور
                                </label>
                                <input type="password" class="form-control" name="password" placeholder="رمز عبور را وارد کنید" required>
                            </div>
                            <button type="submit" class="btn btn-teacher btn-lg w-100 text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                ورود به پنل معلم
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="index.php" class="back-link">
                                <i class="fas fa-arrow-right me-1"></i>
                                بازگشت به صفحه دانش‌آموز
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>