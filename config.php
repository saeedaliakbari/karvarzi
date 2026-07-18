<?php
// ============================================
// تنظیمات اولیه
// ============================================
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

date_default_timezone_set('Asia/Tehran');

// اطلاعات اتصال به دیتابیس
define('DB_HOST', 'remote-fanhab.runflare.com:32154');
define('DB_NAME', 'dbtestzkt_db');
define('DB_USER', 'root');
define('DB_PASS', 'lBRI613ruFnVjbhIMnog');
define('TEACHER_PASSWORD', 'S@eed1375');

// ============================================
// شروع سشن
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// تابع اتصال به دیتابیس
// ============================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // هم‌تراز کردن ساعت سشن MySQL با تهران تا NOW()/CURRENT_TIMESTAMP یکسان باشند
        $pdo->exec("SET time_zone = '+03:30'");
    }
    return $pdo;
}

// ============================================
// تابع محاسبه فاصله
// ============================================
function haversineMeters($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return (int) round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 365 * $gy + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}

function jalaliDate($date, $format = 'Y/m/d') {
    $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
    if (!$timestamp) {
        return (string) $date;
    }

    [$jy, $jm, $jd] = gregorianToJalali((int) date('Y', $timestamp), (int) date('n', $timestamp), (int) date('j', $timestamp));
    return strtr($format, [
        'Y' => (string) $jy,
        'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
        'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
        'H' => date('H', $timestamp),
        'i' => date('i', $timestamp),
        's' => date('s', $timestamp),
    ]);
}

// ============================================
// تابع فشرده‌سازی عکس (نسخه نهایی و ساده)
// ============================================
function compressImageUnder250KB($sourcePath, $destPath, $maxBytes = 250000) {
    // ============================================
    // مرحله 1: ایجاد پوشه
    // ============================================
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0777, true)) {
            error_log("Failed to create directory: $destDir");
            return false;
        }
    }
    chmod($destDir, 0777);
    
    // ============================================
    // مرحله 2: بررسی فایل منبع
    // ============================================
    if (!file_exists($sourcePath)) {
        error_log("Source file not found: $sourcePath");
        return false;
    }
    
    $fileSize = filesize($sourcePath);
    error_log("Source file size: $fileSize bytes");
    
    // ============================================
    // مرحله 3: اگر حجم کم است، با move_uploaded_file منتقل کن
    // ============================================
    if ($fileSize <= $maxBytes) {
        $data = @file_get_contents($sourcePath);
        if ($data !== false && file_put_contents($destPath, $data) !== false) {
            chmod($destPath, 0666);
            error_log("File saved (small): $destPath");
            return true;
        }
        error_log("Failed to save small file. source=$sourcePath dest=$destPath writable=" . (is_writable($destDir) ? 'yes' : 'no'));
        return false;
    }
    
    // ============================================
    // مرحله 4: بررسی وجود GD
    // ============================================
    if (!extension_loaded('gd')) {
        error_log("GD not loaded - moving original");
        $data = @file_get_contents($sourcePath);
        if ($data !== false && file_put_contents($destPath, $data) !== false) {
            chmod($destPath, 0666);
            return true;
        }
        return false;
    }
    
    // ============================================
    // مرحله 5: فشرده‌سازی با GD
    // ============================================
    try {
        // تشخیص نوع تصویر
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            error_log("Cannot get image info - moving original");
            $data = @file_get_contents($sourcePath);
            if ($data !== false && file_put_contents($destPath, $data) !== false) {
                chmod($destPath, 0666);
                return true;
            }
            return false;
        }
        
        $mime = $info['mime'];
        $image = null;
        
        // ایجاد تصویر بر اساس نوع
        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($sourcePath);
                }
                break;
            default:
                error_log("Unsupported mime: $mime - moving original");
                $data = @file_get_contents($sourcePath);
                if ($data !== false && file_put_contents($destPath, $data) !== false) {
                    chmod($destPath, 0666);
                    return true;
                }
                return false;
        }
        
        if (!$image) {
            error_log("Failed to create image - moving original");
            $data = @file_get_contents($sourcePath);
            if ($data !== false && file_put_contents($destPath, $data) !== false) {
                chmod($destPath, 0666);
                return true;
            }
            return false;
        }
        
        // ============================================
        // مرحله 6: کوچک‌سازی تصویر
        // ============================================
        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = 1280;
        
        if ($width > $maxDimension || $height > $maxDimension) {
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = (int) ($width * $ratio);
            $newHeight = (int) ($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized) {
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
                error_log("Image resized to: {$newWidth}x{$newHeight}");
            }
        }
        
        // ============================================
        // مرحله 7: ذخیره با کیفیت‌های مختلف
        // ============================================
        $quality = 85;
        $saved = false;
        
        do {
            // ذخیره موقت در حافظه
            ob_start();
            imagejpeg($image, null, $quality);
            $imageData = ob_get_clean();
            
            if ($imageData !== false) {
                $size = strlen($imageData);
                error_log("Quality $quality: size $size bytes");
                
                if ($size <= $maxBytes) {
                    // ذخیره در فایل
                    if (file_put_contents($destPath, $imageData) !== false) {
                        $saved = true;
                        break;
                    }
                }
            }
            $quality -= 10;
        } while ($quality > 20);
        
        imagedestroy($image);
        
        // ============================================
        // مرحله 8: اگر فشرده‌سازی نشد، فایل اصلی را منتقل کن
        // ============================================
        if (!$saved || !file_exists($destPath) || filesize($destPath) == 0) {
            error_log("Compression failed or file empty - moving original");
            if (file_exists($destPath)) {
                @unlink($destPath);
            }
            $data = @file_get_contents($sourcePath);
            if ($data !== false && file_put_contents($destPath, $data) !== false) {
                chmod($destPath, 0666);
                error_log("Original file moved successfully");
                return true;
            }
            error_log("Failed to move original file");
            return false;
        }
        
        chmod($destPath, 0666);
        error_log("Final file size: " . filesize($destPath) . " bytes");
        return true;
        
    } catch (Exception $e) {
        error_log("GD Error: " . $e->getMessage());
        // در صورت خطا، فایل اصلی را منتقل کن
        $data = @file_get_contents($sourcePath);
        if ($data !== false && file_put_contents($destPath, $data) !== false) {
            chmod($destPath, 0666);
            return true;
        }
        return false;
    }
}
?>