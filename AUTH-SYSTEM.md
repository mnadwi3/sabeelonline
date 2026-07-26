# Secure Authentication System

Production-ready PHP 8 + MySQL (PDO) auth for Hostinger. **Independent** of the existing Blog (`teachers`) and Student Portal (`tbl_users`) logins — those keep working unchanged.

## Deploy (Hostinger)

1. Upload the new folders/files (`config/`, `includes/` auth files, `classes/`, `pages/`, `sql/`, `assets/css/auth.css`).
2. Confirm DB settings in `config/config.php` (defaults match the blog Hostinger database).
3. Visit `https://your-domain/pages/install.php`
4. Create the first **Super Admin** (only if `users` is empty).
5. **Delete** `pages/install.php` after install.
6. Sign in at `/pages/login.php`

Schema uses `CREATE TABLE IF NOT EXISTS` only — it never drops or rewrites existing tables/rows.

## File map

| Path | Purpose |
|------|---------|
| `config/config.php` | App + DB + security settings |
| `config/database.php` | PDO singleton |
| `config/config.local.php.example` | Optional secret overrides |
| `sql/auth_schema.sql` | Tables: users, roles, login_attempts, password_resets, remember_tokens, audit_logs |
| `includes/bootstrap.php` | Load config, session, CSRF, Auth |
| `includes/session.php` | Secure cookies, timeout, regenerate |
| `includes/csrf.php` | CSRF create / validate / rotate |
| `includes/security.php` | Security headers, IP, HTTPS |
| `includes/functions.php` | `e()`, validation, password migration |
| `includes/auth.php` | `requireLogin()`, `requireRole()`, `isAdmin()`, … |
| `classes/Auth.php` | Login, logout, remember-me, reset, audit |
| `classes/User.php` | User CRUD / lockout |
| `pages/login.php` | Login + Remember Me |
| `pages/logout.php` | Logout |
| `pages/forgot-password.php` | Request reset email |
| `pages/reset-password.php` | Consume reset token |
| `pages/change-password.php` | Logged-in password change |
| `pages/dashboard.php` | Protected home |
| `pages/admin/users.php` | Create / disable / roles / reset passwords |
| `pages/admin/login-history.php` | Audit / login history |
| `pages/install.php` | One-time schema + first Super Admin |
| `assets/css/auth.css` | Auth UI |

## Protecting a new page

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();                 // any signed-in role
// requireRole('admin', 'super_admin');
// requireMinRole('teacher');
```

## Password migration (existing plaintext)

If a `users.password` value is **not** a `password_hash()` string, login still works with the current plaintext password. On success it is automatically replaced with `password_hash()` — no forced reset, no username/ID changes.

## Roles

`student` → `teacher` → `admin` → `super_admin`

## Notes

- Session name: `SABEELAUTH` (does not wipe Blog/Portal PHP sessions that use other names/keys in the default cookie — they still share the browser cookie jar by path; this module uses its own session name).
- Failed logins: 5 attempts → 15 minute lock + DB logging + IP rate limit.
- Forgot-password emails use PHP `mail()` (Hostinger). If mail fails, the reset link is written to the PHP error log.
