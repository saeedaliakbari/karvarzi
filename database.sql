-- دیتابیس سیستم ثبت حضور و غیاب کارورزی
-- این فایل رو در phpMyAdmin هاست خودتون Import کنید

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobile VARCHAR(15) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    log_date DATE NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    selfie_path VARCHAR(255) NOT NULL,
    distance_from_checkin_meters INT NULL,
    duration_from_checkin_minutes INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY one_per_day (student_id, type, log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ایندکس برای جستجوی سریع‌تر لاگ‌ها بر اساس دانش‌آموز و تاریخ
CREATE INDEX idx_student_logs ON attendance_logs(student_id, log_date);

-- اگر جدول از قبل ساخته شده و این دو ستون را ندارد، این دو خط را اجرا کنید:
-- ALTER TABLE attendance_logs ADD COLUMN distance_from_checkin_meters INT NULL AFTER selfie_path;
-- ALTER TABLE attendance_logs ADD COLUMN duration_from_checkin_minutes INT NULL AFTER distance_from_checkin_meters;
