# Admin Login (simple)

One **Admin ID + password** for every management page. No access codes.

## Everyday use

1. Go to **`/pages/login.php`**
2. Sign in with your Admin ID + password (created at `/pages/install.php`)
3. You land on **Admin Hub** (`/admin-hub.html`)
4. Open Courses, Admissions, Library, Blog, or Results from the cards

Change password: `/pages/change-password.php`.

Direct links to any admin page also send you to the same login, then return you there.

## What students do (not Admin Login)

| Task | How |
|------|-----|
| Download results | `/student-portal/public/` → **Student ID** only |
| Download coursebooks | `/library/` → same **Student ID** |

## Install / recovery

- First account: `/pages/install.php` (delete after creating Admin)
- Forgot password: `/pages/forgot-password.php`

## Technical note

Session name: `SABEELAUTH`. Role **Admin** or **Super Admin** opens every admin panel.
