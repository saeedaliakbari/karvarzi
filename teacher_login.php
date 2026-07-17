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
<title>ورود معلم</title>
<style>
    body { font-family: Tahoma, sans-serif; background: #f5f5f5; display: flex; justify-content: center; padding-top: 60px; }
    .box { background: #fff; padding: 24px; border-radius: 12px; width: 300px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
    input { width: 100%; padding: 10px; margin-top: 6px; margin-bottom: 14px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 6px; }
    button { width: 100%; padding: 10px; background: #333; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
    .error { color: #c0392b; margin-bottom: 10px; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    <h3>ورود معلم</h3>
    <?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="POST">
        <label>رمز عبور</label>
        <input type="password" name="password" required>
        <button type="submit">ورود</button>
    </form>
</div>
</body>
</html>
