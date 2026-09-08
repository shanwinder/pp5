-- สร้างฐานข้อมูล และกำหนด Character Set ให้รองรับภาษาไทยได้สมบูรณ์
CREATE DATABASE IF NOT EXISTS `pp5_online` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pp5_online`;

-- 1. ตาราง users (ครูผู้สอน / ผู้บริหาร)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL, -- เก็บแบบ Hashed (เช่น password_hash ใน PHP)
  `role` ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
  `prefix` VARCHAR(20) NOT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) COMMENT='เก็บข้อมูลผู้ใช้งานระบบ';

-- 2. ตาราง students (ข้อมูลนักเรียน)
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'รหัสประจำตัวนักเรียน',
  `prefix` VARCHAR(20) NOT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `current_grade` VARCHAR(20) NOT NULL COMMENT 'ระดับชั้นปัจจุบัน เช่น ป.1, ป.4',
  `status` ENUM('active', 'inactive', 'graduated') DEFAULT 'active'
) COMMENT='เก็บข้อมูลพื้นฐานนักเรียน';

-- 3. ตาราง subjects (ข้อมูลรายวิชา)
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'รหัสวิชา เช่น ท14101',
  `subject_name` VARCHAR(150) NOT NULL COMMENT 'ชื่อวิชา เช่น ภาษาไทย',
  `credit` DECIMAL(3,1) NOT NULL COMMENT 'หน่วยกิต',
  `total_hours` INT NOT NULL COMMENT 'จำนวนชั่วโมงเรียนต่อปี/เทอม',
  `type` ENUM('fundamental', 'additional') NOT NULL DEFAULT 'fundamental' COMMENT 'รายวิชาพื้นฐาน/เพิ่มเติม'
) COMMENT='เก็บข้อมูลรายวิชา';

-- 4. ตาราง enrollments (การลงทะเบียนเรียน จับคู่นักเรียน วิชา และเทอม)
CREATE TABLE `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `semester` TINYINT NOT NULL COMMENT 'ภาคเรียนที่ (1 หรือ 2)',
  `academic_year` YEAR NOT NULL COMMENT 'ปีการศึกษา',
  `teacher_id` INT NOT NULL COMMENT 'ครูประจำวิชานี้',
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`, `subject_id`, `semester`, `academic_year`)
) COMMENT='การลงทะเบียนเรียน นักเรียน 1 คนกับหลายวิชาในแต่ละเทอม';

-- 5. ตาราง attendances (ข้อมูลการเช็คชื่อ)
CREATE TABLE `attendances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `enrollment_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'leave', 'late') NOT NULL COMMENT 'มา, ขาด, ลา, สาย',
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_attendance` (`enrollment_id`, `date`)
) COMMENT='ข้อมูลบันทึกเวลาเรียนรายวัน';

-- 6. ตาราง scores (ข้อมูลคะแนนเก็บ กลางภาค และปลายภาค)
CREATE TABLE `scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `enrollment_id` INT NOT NULL UNIQUE,
  `collect_score_1` DECIMAL(5,2) DEFAULT 0,
  `collect_score_2` DECIMAL(5,2) DEFAULT 0,
  `collect_score_3` DECIMAL(5,2) DEFAULT 0,
  `collect_score_4` DECIMAL(5,2) DEFAULT 0,
  `collect_score_5` DECIMAL(5,2) DEFAULT 0,
  `collect_score_6` DECIMAL(5,2) DEFAULT 0,
  `collect_score_7` DECIMAL(5,2) DEFAULT 0,
  `collect_score_8` DECIMAL(5,2) DEFAULT 0,
  `collect_score_9` DECIMAL(5,2) DEFAULT 0,
  `collect_score_10` DECIMAL(5,2) DEFAULT 0,
  `midterm_score` DECIMAL(5,2) DEFAULT 0,
  `final_score` DECIMAL(5,2) DEFAULT 0,
  `total_score` DECIMAL(5,2) DEFAULT 0 COMMENT 'คะแนนรวมทั้งหมด จะถูกคำนวณและบันทึก',
  `grade` VARCHAR(5) DEFAULT NULL COMMENT 'ผลการเรียน 4, 3.5, 3... 0',
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) COMMENT='คะแนนระหว่างเรียน กลางภาค และปลายภาค';

-- 7. ตาราง evaluations (ประเมินคุณลักษณะอันพึงประสงค์ และ อ่านคิดวิเคราะห์เขียน)
CREATE TABLE `evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `enrollment_id` INT NOT NULL UNIQUE,
  -- คุณลักษณะอันพึงประสงค์ 8 ประการ (คะแนน 3, 2, 1, 0)
  `char_1` TINYINT DEFAULT 0 COMMENT 'รักชาติ ศาสน์ กษัตริย์',
  `char_2` TINYINT DEFAULT 0 COMMENT 'ซื่อสัตย์สุจริต',
  `char_3` TINYINT DEFAULT 0 COMMENT 'มีวินัย',
  `char_4` TINYINT DEFAULT 0 COMMENT 'ใฝ่หาความรู้',
  `char_5` TINYINT DEFAULT 0 COMMENT 'อยู่อย่างพอเพียง',
  `char_6` TINYINT DEFAULT 0 COMMENT 'มุ่งมั่นในการทำงาน',
  `char_7` TINYINT DEFAULT 0 COMMENT 'รักความเป็นไทย',
  `char_8` TINYINT DEFAULT 0 COMMENT 'มีจิตสาธารณะ',
  `result_char` ENUM('ดีเยี่ยม', 'ดี', 'ผ่าน', 'ไม่ผ่าน') DEFAULT NULL COMMENT 'ผลประเมินคุณลักษณะฯ รวม',
  
  -- อ่าน คิดวิเคราะห์ และเขียน (คะแนน 3, 2, 1, 0)
  `read_skill` TINYINT DEFAULT 0 COMMENT 'การอ่าน',
  `think_skill` TINYINT DEFAULT 0 COMMENT 'การคิดวิเคราะห์',
  `write_skill` TINYINT DEFAULT 0 COMMENT 'การเขียน',
  `result_read` ENUM('ดีเยี่ยม', 'ดี', 'ผ่าน', 'ไม่ผ่าน') DEFAULT NULL COMMENT 'ผลประเมินอ่านคิดวิเคราะห์เขียน รวม',
  
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) COMMENT='ข้อมูลการประเมิน 2 ส่วนด้านค่านิยมและทักษะ';
