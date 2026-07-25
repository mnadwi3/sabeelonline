-- =========================================================
-- Sabeel Us Salaam Online — Blog Management System
-- Database setup (MySQL) — Hostinger ready
-- =========================================================
-- How to use on Hostinger:
-- 1. hPanel → Databases → create a MySQL database + user
-- 2. Open phpMyAdmin → click YOUR database name on the left
-- 3. Import this file (do NOT run CREATE DATABASE)
-- 4. Default admin login after import:
--    Email:    admin@sabeel.com
--    Password: password
-- =========================================================

-- ---------------------------------------------------------
-- Table: teachers
-- Stores Admin + Teacher accounts
-- role = 'admin' or 'teacher'
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS teachers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,          -- password_hash() value
  role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
  bio TEXT NULL,
  profile_image VARCHAR(255) NULL,        -- optional profile photo path
  is_active TINYINT(1) NOT NULL DEFAULT 1, -- 1 = can login, 0 = blocked
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teachers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: categories
-- Blog categories managed by Admin
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: posts
-- All blog posts written by teachers
-- status values:
--   draft           = teacher is still writing
--   pending_review  = sent to admin for approval
--   published       = live on website
--   rejected        = admin rejected
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_id INT UNSIGNED NOT NULL,       -- account that saved the post
  author_name VARCHAR(120) NULL,          -- public writer name (shown on blog)
  category_id INT UNSIGNED NULL,          -- optional category
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,              -- URL-friendly title
  featured_image VARCHAR(255) NULL,       -- path inside uploads/blog-images/
  short_description VARCHAR(500) NULL,
  content MEDIUMTEXT NOT NULL,            -- main article text
  tags VARCHAR(255) NULL,                 -- simple comma-separated tags
  meta_title VARCHAR(200) NULL,
  meta_description VARCHAR(300) NULL,
  status ENUM('draft', 'pending_review', 'published', 'rejected')
         NOT NULL DEFAULT 'draft',
  admin_note VARCHAR(500) NULL,           -- optional reject / review note
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_status (status),
  KEY idx_posts_teacher (teacher_id),
  KEY idx_posts_category (category_id),
  CONSTRAINT fk_posts_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_posts_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Starter data: default Admin account
-- Login: admin@sabeel.com / password
-- ---------------------------------------------------------
INSERT INTO teachers (name, email, password, role, bio, is_active)
VALUES (
  'Site Admin',
  'admin@sabeel.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin',
  'Default administrator account for Sabeel Us Salaam Online blog.',
  1
);

-- ---------------------------------------------------------
-- Starter categories (optional examples)
-- ---------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
('Quran', 'quran'),
('Arabic', 'arabic'),
('Islamic Studies', 'islamic-studies'),
('Announcements', 'announcements');
