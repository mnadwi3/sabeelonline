# Secure Authentication System (Unified)

Production PHP 8 + MySQL (PDO) auth for Hostinger. **Super Admin manages all staff logins** for Blog, Digital Library, Student Portal (results admin), and Courses/Admissions.

Public Student ID result lookup (`student-portal/public/`) stays password-less and is unchanged.

## Deploy / first use

1. Confirm DB settings in `config/config.php`.
2. If not already done: visit `/pages/install.php` → create Super Admin → **delete** `install.php`.
3. Sign in at `/pages/login.php`.
4. As Super Admin open **Import legacy accounts** (`/pages/admin/migrate-accounts.php`) to copy:
   - Blog `teachers` → `users` (Blog access, same passwords)
   - Portal `tbl_users` → `users` (Portal access, same passwords)
5. Under **Manage users**, tick portal access for each account:
   - **Blog** — Blog Admin / Teacher
   - **Digital Library** — Library student + admin UI
   - **Student Portal** — Results admin
   - **Courses & Admissions** — Courses Admin, Admissions Admin, Admin Hub
6. Create Library/Courses staff as new users (legacy access codes are emergency-only).

Schema uses `CREATE TABLE IF NOT EXISTS` + safe `ALTER` for `modules` / `blog_teacher_id`. Never drops existing tables or user rows.

## How portals authenticate

| Portal | Login | Access flag |
|--------|--------|-------------|
| Unified dashboard | `/pages/login.php` | any signed-in user |
| Blog | redirects to unified login | `blog` module |
| Library / Library Admin | unified session (codes = fallback) | `library` |
| Courses / Admissions / Hub | unified session | `courses` (Hub also accepts `library`) |
| Student Portal admin | redirects to unified login | `portal` |

Super Admin always has every module.

## File map

| Path | Purpose |
|------|---------|
| `config/config.php` | App + DB + security settings |
| `includes/bootstrap.php` | Auth bootstrap |
| `includes/sabeel_gate.php` | Cross-portal session peek + legacy import |
| `includes/auth.php` | `requireLogin()`, `requireModule()`, role helpers |
| `classes/Auth.php` / `User.php` | Login, users, modules |
| `pages/admin/users.php` | Create users, roles, **portal access**, passwords |
| `pages/admin/migrate-accounts.php` | Import Blog + Portal accounts |
| `sql/auth_schema.sql` | Tables including `users.modules`, `blog_teacher_id` |
| `library/api/session.php` | JSON session check for Hub/Library JS |

## Protecting a page

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requireModule('blog'); // or library, portal, courses
```

Cross-app (Blog/Portal) without loading conflicting helpers:

```php
require_once __DIR__ . '/../../includes/sabeel_gate.php';
$user = sabeel_peek_user();
if (!$user || !sabeel_user_has_module($user, 'portal')) { /* deny */ }
```

## Roles

`student` → `teacher` → `admin` → `super_admin`

Portal access is separate from role: a Teacher can have Blog only; an Admin can have Portal + Courses, etc.

## Notes

- Session name: `SABEELAUTH`
- Failed logins: 5 attempts → 15 minute lock
- Existing plaintext passwords in `users` auto-hash on successful login
- Blog authorship still uses `teachers` via `users.blog_teacher_id` (auto-created when Blog access is granted)
