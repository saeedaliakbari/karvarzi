<?php
// اطلاعات اتصال به دیتابیس را با اطلاعات هاست خودتان جایگزین کنید
define('DB_HOST', 'remote-fanhab.runflare.com:32154');
define('DB_NAME', 'dbtestzkt_db');
define('DB_USER', 'root');
define('DB_PASS', 'lBRI613ruFnVjbhIMnog');

// رمز عبور ورود به پنل معلم را اینجا تغییر دهید
define('TEACHER_PASSWORD', 'S@eed1375');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function haversineMeters($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return (int) round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function compressImageUnder250KB($sourcePath, $destPath, $maxBytes = 250000) {
    $info = getimagesize($sourcePath);
    if ($info === false) {
        // اگر تشخیص نوع عکس ممکن نبود، فایل اصلی را همان‌طور کپی کن
        copy($sourcePath, $destPath);
        return;
    }

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            copy($sourcePath, $destPath);
            return;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $maxDimension = 1280;

    // اگر عکس خیلی بزرگ بود، ابتدا ابعادش را کوچک کن
    if ($width > $maxDimension || $height > $maxDimension) {
        $ratio = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    // با کیفیت‌های نزولی ذخیره کن تا حجم زیر سقف مجاز برسد
    $quality = 85;
    do {
        imagejpeg($image, $destPath, $quality);
        $quality -= 10;
    } while (filesize($destPath) > $maxBytes && $quality > 20);

    // اگر باز هم حجم زیاد بود، ابعاد را بیشتر کوچک کن
    if (filesize($destPath) > $maxBytes) {
        $width2 = imagesx($image);
        $height2 = imagesy($image);
        while (filesize($destPath) > $maxBytes && $width2 > 300) {
            $width2 = (int) ($width2 * 0.8);
            $height2 = (int) ($height2 * 0.8);
            $smaller = imagecreatetruecolor($width2, $height2);
            imagecopyresampled($smaller, $image, 0, 0, 0, 0, $width2, $height2, imagesx($image), imagesy($image));
            imagejpeg($smaller, $destPath, 60);
            imagedestroy($smaller);
        }
    }

    imagedestroy($image);
}

session_start();
