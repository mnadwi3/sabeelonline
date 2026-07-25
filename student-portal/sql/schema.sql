-- Simple Result Management schema
-- Import into the selected Hostinger database (e.g. u917534606_results).
-- Do NOT create a separate database here — Hostinger already created it.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS tbl_marks;
DROP TABLE IF EXISTS tbl_results;
DROP TABLE IF EXISTS tbl_enrollment;
DROP TABLE IF EXISTS tbl_subjects;
DROP TABLE IF EXISTS tbl_students;
DROP TABLE IF EXISTS tbl_semesters;
DROP TABLE IF EXISTS tbl_sessions;
DROP TABLE IF EXISTS tbl_courses;
DROP TABLE IF EXISTS tbl_users;

CREATE TABLE tbl_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  t_name        VARCHAR(120) NOT NULL,
  login_name    VARCHAR(80)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Course = admin name (may include Batch) + marksheet title + Month/Year
CREATE TABLE tbl_courses (
  course_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_name       VARCHAR(200) NOT NULL COMMENT 'Admin label, e.g. Short Term Alimiyyat Batch 4',
  marksheet_title   VARCHAR(200) DEFAULT NULL COMMENT 'Printed title, e.g. Diploma In Short Term Alimiyyat',
  month_year        VARCHAR(40)  NOT NULL COMMENT 'e.g. March 2024 or 03/2024',
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Subjects belong to a course (simple list)
CREATE TABLE tbl_subjects (
  subject_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id    INT UNSIGNED NOT NULL,
  subject_name VARCHAR(200) NOT NULL,
  max_marks    INT UNSIGNED NOT NULL DEFAULT 100,
  sort_order   INT NOT NULL DEFAULT 1,
  CONSTRAINT fk_sub_course FOREIGN KEY (course_id) REFERENCES tbl_courses(course_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tbl_students (
  admin_no       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Student ID',
  student_roll_no VARCHAR(40) DEFAULT NULL,
  roll_no        VARCHAR(40)  NOT NULL,
  s_name_e       VARCHAR(160) NOT NULL,
  f_name_e       VARCHAR(160) DEFAULT NULL,
  dob            DATE DEFAULT NULL,
  address_e      VARCHAR(255) DEFAULT NULL,
  course_id      INT UNSIGNED DEFAULT NULL,
  semester       VARCHAR(40)  DEFAULT NULL COMMENT 'e.g. Semester 1',
  semester_year  VARCHAR(40)  DEFAULT NULL COMMENT 'e.g. 2024',
  photo          VARCHAR(255) DEFAULT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roll (roll_no),
  CONSTRAINT fk_stu_course FOREIGN KEY (course_id) REFERENCES tbl_courses(course_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tbl_results (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_no       INT UNSIGNED NOT NULL,
  roll_no        VARCHAR(40) NOT NULL,
  course_id      INT UNSIGNED DEFAULT NULL,
  semester       VARCHAR(40) DEFAULT NULL,
  semester_year  VARCHAR(40) DEFAULT NULL,
  grand_total    DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_total      DECIMAL(10,2) NOT NULL DEFAULT 0,
  percentage     DECIMAL(5,2) NOT NULL DEFAULT 0,
  grade          VARCHAR(10) DEFAULT NULL,
  result_status  VARCHAR(20) DEFAULT NULL,
  remarks        VARCHAR(255) DEFAULT NULL,
  is_published   TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_result (roll_no, course_id, semester, semester_year),
  CONSTRAINT fk_res_stu FOREIGN KEY (admin_no) REFERENCES tbl_students(admin_no)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_res_course FOREIGN KEY (course_id) REFERENCES tbl_courses(course_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Marks store subject name directly (easy manual entry)
CREATE TABLE tbl_marks (
  mark_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  result_id    INT UNSIGNED NOT NULL,
  subject_name VARCHAR(200) NOT NULL,
  max_marks    INT UNSIGNED NOT NULL DEFAULT 100,
  obtained     DECIMAL(6,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_mark_result FOREIGN KEY (result_id) REFERENCES tbl_results(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
