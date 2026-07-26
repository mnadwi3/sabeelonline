-- Sample data — import into the same database as schema.sql
INSERT INTO tbl_courses (course_name, month_year) VALUES
('Short Term Alimiyyat', 'March 2024'),
('Advanced Arabic Diploma', 'January 2025'),
('Translation of the Quran', 'June 2024');

INSERT INTO tbl_subjects (course_id, subject_name, max_marks, sort_order)
SELECT course_id, x.name, 100, x.ord
FROM tbl_courses
CROSS JOIN (
  SELECT 'Nahw' AS name, 1 AS ord UNION ALL
  SELECT 'Sarf', 2 UNION ALL
  SELECT 'Fiqh', 3 UNION ALL
  SELECT 'Aqeedah', 4 UNION ALL
  SELECT 'Hadith', 5 UNION ALL
  SELECT 'Tafsir', 6
) x
WHERE course_name = 'Short Term Alimiyyat';

INSERT INTO tbl_students (student_roll_no, roll_no, s_name_e, f_name_e, dob, address_e, course_id, semester, semester_year)
SELECT 'SUS-001', 'K7M2NP9QXH', 'Ahmed Khan', 'Mohammad Khan', '2005-03-15', 'Shaheen Bagh, Okhla, New Delhi',
       course_id, 'Semester 1', '2024'
FROM tbl_courses WHERE course_name = 'Short Term Alimiyyat' LIMIT 1;

INSERT INTO tbl_students (student_roll_no, roll_no, s_name_e, f_name_e, dob, address_e, course_id, semester, semester_year)
SELECT 'SUS-002', 'R4T8W3YB6C', 'Fatima Zahra', 'Abdul Rahman', '2004-07-22', 'Okhla, New Delhi',
       course_id, 'Semester 1', '2024'
FROM tbl_courses WHERE course_name = 'Short Term Alimiyyat' LIMIT 1;
