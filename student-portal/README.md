# Sabeel Us Salaam Online — Student Portal (Results)

Result management system, shown on the main website as **Student Portal**.

Website path: `/student-portal/` (folder next to `index.html`, `blog/`, `library/`).

## Requirements

- PHP 8.0+ with PDO MySQL
- MySQL 5.7+ / MariaDB 10.3+
- Apache (XAMPP / WAMP / Hostinger) or any PHP-capable web server

## Setup

1. Keep this folder named `student-portal` inside the website root.
2. Edit `config/config.php` — set MySQL credentials. Keep `'base_url' => '/student-portal'`.
3. Open `http://localhost/student-portal/install.php` and click **Run Installation**.
4. Login: **admin** / **Admin@123** (change immediately).
5. Delete or rename `install.php` after install.

## URLs

- Website menu: **Student Portal** → `/student-portal/`
- Public result search: `/student-portal/` or `/student-portal/public/index.php`
- Admin panel: `/pages/login.php` → Admin Hub → Results Admin

## Access DB mapping

| Access table | MySQL table |
|--------------|-------------|
| `tblStudents` | `tbl_students` |
| `tblClass` (Sem1–6) | `tbl_semesters` (+ new `tbl_courses`) |
| `tblSession` | `tbl_sessions` |
| `tblSyllabus` Book1–6 | `tbl_subjects` (normalized) |
| `tblEnrollment` | `tbl_enrollment` |
| `tblResult` B1–B6 | `tbl_results` + `tbl_marks` |
| `tblUsers` | `tbl_users` |

Grading matches Access marksheet: A1–C2 bands, pass at 40%.

## Features

- Admin dashboard (students, courses, results, subjects, pass/fail)
- Student / Course / Semester / Subject CRUD
- Marks entry with live total, %, grade, Pass/Fail
- Publish / Hide / Delete results
- Professional marksheet (logo, photo, signatures, print PDF)
- Public search by Student ID
- Linked from main website as **Student Portal**

See `DEPLOY-HOSTINGER.md` for live hosting steps.
