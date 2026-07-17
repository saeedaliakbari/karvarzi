<?php
// ============================================
// تنظیمات اولیه
// ============================================
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

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

// ============================================
// تابع فشرده‌سازی عکس (نسخه مقاوم)
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
    // مرحله 3: اگر حجم کم است، فقط کپی کن
    // ============================================
    if ($fileSize <= $maxBytes) {
        if (copy($sourcePath, $destPath)) {
            chmod($destPath, 0666);
            error_log("File copied (small): $destPath");
            return true;
        }
        error_log("Copy failed for small file");
        return false;
    }
    
    // ============================================
    // مرحله 4: بررسی وجود GD
    // ============================================
    if (!extension_loaded('gd')) {
        error_log("GD extension not loaded - copying original");
        copy($sourcePath, $destPath);
        chmod($destPath, 0666);
        return true;
    }
    
    // ============================================
    // مرحله 5: فشرده‌سازی با GD
    // ============================================
    try {
        // تشخیص نوع تصویر
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            error_log("Cannot get image info - copying original");
            copy($sourcePath, $destPath);
            chmod($destPath, 0666);
            return true;
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
                error_log("Unsupported mime type: $mime - copying original");
                copy($sourcePath, $destPath);
                chmod($destPath, 0666);
                return true;
        }
        
        if (!$image) {
            error_log("Failed to create image from source - copying original");
            copy($sourcePath, $destPath);
            chmod($destPath, 0666);
            return true;
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
            if (imagejpeg($image, $destPath, $quality)) {
                if (file_exists($destPath)) {
                    $newSize = filesize($destPath);
                    error_log("Saved with quality $quality: size $newSize bytes");
                    if ($newSize <= $maxBytes) {
                        $saved = true;
                        break;
                    }
                }
            }
            $quality -= 10;
        } while ($quality > 20);
        
        imagedestroy($image);
        
        // ============================================
        // مرحله 8: اگر فشرده‌سازی نشد، کپی کن
        // ============================================
        if (!$saved || !file_exists($destPath)) {
            error_log("Compression failed - copying original");
            copy($sourcePath, $destPath);
        }
        
        chmod($destPath, 0666);
        error_log("Final file size: " . filesize($destPath) . " bytes");
        return true;
        
    } catch (Exception $e) {
        error_log("GD Error: " . $e->getMessage() . " - copying original");
        copy($sourcePath, $destPath);
        chmod($destPath, 0666);
        return true;
    }
}

// ============================================
// تابع جایگزین ساده (اگر GD کار نکرد)
// ============================================
function saveSelfie($sourcePath, $destPath) {
    $destDir = dirname($destPath);
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0777, true)) {
            return false;
        }
    }
    chmod($destDir, 0777);
    
    if (copy($sourcePath, $destPath)) {
        chmod($destPath, 0666);
        return true;
    }
    return false;
}
?>