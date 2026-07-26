-- =============================================================================
-- Sabeel Online — Authentication & Authorization schema (Hostinger / MySQL)
-- =============================================================================
-- SAFE TO RUN on an existing database:
--   • Uses CREATE TABLE IF NOT EXISTS only
--   • Does NOT DROP, TRUNCATE, or recreate any existing tables
--   • Does NOT modify existing user rows or passwords
-- Import into your existing Hostinger DB (e.g. u917534606_u123sabeel) via phpMyAdmin.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Roles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  slug VARCHAR(50) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug),
  UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id, name, slug, description) VALUES
  (1, 'Super Admin', 'super_admin', 'Full system access including admin management'),
  (2, 'Admin', 'admin', 'Manage users, roles, and view audit logs'),
  (3, 'Teacher', 'teacher', 'Staff access for teaching tools'),
  (4, 'Student', 'student', 'Student portal access');

-- -----------------------------------------------------------------------------
-- Users (unified accounts — Super Admin manages Blog / Library / Portal / Courses)
-- modules: comma list — blog,library,portal,courses (super_admin always has all)
-- blog_teacher_id: linked teachers.id for blog authorship (posts.teacher_id)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NULL,
  full_name VARCHAR(120) NOT NULL DEFAULT '',
  role_id TINYINT UNSIGNED NOT NULL DEFAULT 4,
  modules VARCHAR(120) NOT NULL DEFAULT '',
  blog_teacher_id INT UNSIGNED NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role_id),
  KEY idx_users_active (is_active),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Failed login attempts (brute-force tracking)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  identifier VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(512) NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_login_attempts_identifier_time (identifier, attempted_at),
  KEY idx_login_attempts_ip_time (ip_address, attempted_at),
  KEY idx_login_attempts_user (user_id),
  CONSTRAINT fk_login_attempts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Password reset tokens
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  requested_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id),
  KEY idx_password_resets_expires (expires_at),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Remember-me tokens (selector / validator pattern)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS remember_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  user_agent VARCHAR(512) NULL,
  ip_address VARCHAR(45) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_remember_selector (selector),
  KEY idx_remember_user (user_id),
  KEY idx_remember_expires (expires_at),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Audit log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  event_type VARCHAR(40) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  details VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_event_time (event_type, created_at),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
