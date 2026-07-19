-- دیتابیس سیستم ثبت حضور و غیاب کارورزی
-- این فایل رو در phpMyAdmin هاست خودتون Import کنید

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobile VARCHAR(15) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    national_id VARCHAR(10) NULL,
    guardian_name VARCHAR(100) NULL,
    internship_address TEXT NULL,
    guardian_mobile VARCHAR(15) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- اگر جدول از قبل ساخته شده و این ستون‌ها را ندارد، این خطوط را اجرا کنید:
-- ALTER TABLE students ADD COLUMN national_id VARCHAR(10) NULL AFTER name;
-- ALTER TABLE students ADD COLUMN guardian_name VARCHAR(100) NULL AFTER national_id;
-- ALTER TABLE students ADD COLUMN internship_address TEXT NULL AFTER guardian_name;
-- ALTER TABLE students ADD COLUMN guardian_mobile VARCHAR(15) NULL AFTER internship_address;

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
