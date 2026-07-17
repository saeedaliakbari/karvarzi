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
    // مرحله 3: خواندن محتوای فایل
    // ============================================
    $fileContent = @file_get_contents($sourcePath);
    if ($fileContent === false) {
        error_log("Failed to read source file");
        return false;
    }
    
    // ============================================
    // مرحله 4: اگر حجم کم است، با file_put_contents ذخیره کن
    // ============================================
    if ($fileSize <= $maxBytes) {
        if (@file_put_contents($destPath, $fileContent) !== false) {
            chmod($destPath, 0666);
            error_log("File saved with file_put_contents (small): $destPath");
            return true;
        }
        error_log("file_put_contents failed for small file");
        return false;
    }
    
    // ============================================
    // مرحله 5: بررسی وجود GD
    // ============================================
    if (!extension_loaded('gd')) {
        error_log("GD not loaded - saving original with file_put_contents");
        if (@file_put_contents($destPath, $fileContent) !== false) {
            chmod($destPath, 0666);
            return true;
        }
        return false;
    }
    
    // ============================================
    // مرحله 6: فشرده‌سازی با GD
    // ============================================
    try {
        // تشخیص نوع تصویر
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            error_log("Cannot get image info - saving original");
            if (@file_put_contents($destPath, $fileContent) !== false) {
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
                error_log("Unsupported mime: $mime - saving original");
                if (@file_put_contents($destPath, $fileContent) !== false) {
                    chmod($destPath, 0666);
                    return true;
                }
                return false;
        }
        
        if (!$image) {
            error_log("Failed to create image - saving original");
            if (@file_put_contents($destPath, $fileContent) !== false) {
                chmod($destPath, 0666);
                return true;
            }
            return false;
        }
        
        // ============================================
        // مرحله 7: کوچک‌سازی تصویر
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
        // مرحله 8: ذخیره با کیفیت‌های مختلف
        // ============================================
        $quality = 85;
        $saved = false;
        
        do {
            // ذخیره در حافظه
            ob_start();
            if (imagejpeg($image, null, $quality)) {
                $imageData = ob_get_clean();
                if ($imageData !== false) {
                    $size = strlen($imageData);
                    error_log("Quality $quality: size $size bytes");
                    
                    if ($size <= $maxBytes) {
                        // ذخیره در فایل با file_put_contents
                        if (@file_put_contents($destPath, $imageData) !== false) {
                            $saved = true;
                            break;
                        }
                    }
                }
            } else {
                ob_end_clean();
            }
            $quality -= 10;
        } while ($quality > 20);
        
        imagedestroy($image);
        
        // ============================================
        // مرحله 9: اگر فشرده‌سازی نشد، فایل اصلی را ذخیره کن
        // ============================================
        if (!$saved || !file_exists($destPath) || filesize($destPath) == 0) {
            error_log("Compression failed - saving original with file_put_contents");
            if (file_exists($destPath)) {
                @unlink($destPath);
            }
            if (@file_put_contents($destPath, $fileContent) !== false) {
                chmod($destPath, 0666);
                error_log("Original file saved with file_put_contents");
                return true;
            }
            error_log("Failed to save original file");
            return false;
        }
        
        chmod($destPath, 0666);
        error_log("Final file size: " . filesize($destPath) . " bytes");
        return true;
        
    } catch (Exception $e) {
        error_log("GD Error: " . $e->getMessage());
        // در صورت خطا، فایل اصلی را با file_put_contents ذخیره کن
        if (@file_put_contents($destPath, $fileContent) !== false) {
            chmod($destPath, 0666);
            return true;
        }
        return false;
    }
}
?>