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
<title>ورود دانش‌آموز</title>
<style>
    body { font-family: Tahoma, sans-serif; background: #f5f5f5; display: flex; justify-content: center; padding-top: 60px; }
    .box { background: #fff; padding: 24px; border-radius: 12px; width: 320px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
    input { width: 100%; padding: 10px; margin-top: 6px; margin-bottom: 14px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
    button { width: 100%; padding: 10px; background: #333; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    .error { color: #c0392b; margin-bottom: 10px; font-size: 14px; }
    label { font-size: 13px; color: #555; }
</style>
</head>
<body>
<div class="box">
    <h3>ورود دانش‌آموز</h3>
    <?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="POST">
        <label>شماره موبایل</label>
        <input type="tel" name="mobile" required placeholder="09123456789">
        <label>نام و نام خانوادگی (فقط برای اولین ورود)</label>
        <input type="text" name="name" placeholder="مثلا: سارا احمدی">
        <button type="submit">ورود</button>
    </form>
    <p style="margin-top:14px;"><a href="teacher_login.php">ورود معلم</a></p>
</div>
</body>
</html>
