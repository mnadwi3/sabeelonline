# Admin Login (simple)

One **Admin ID + password** for every management page.

## How you use it

### Everyday use (recommended)

1. Open **`/admin-hub.html`**
2. Enter the hub code: `admin@sabeel` or `ADMIN-SABEEL`
3. Open Courses, Admissions, Library, Blog, or Results from the cards

### Full Admin Login (Blog / Results / password)

1. Go to `/pages/login.php` (or the “Admin Login” button on the hub)
2. Enter your Admin ID + password (Super Admin from install)
3. You return to **Admin Hub**

Change password: `/pages/change-password.php`.

## What students do (not this login)

| Task | How |
|------|-----|
| Download results | `/student-portal/public/` → enter **Student ID** (no password) |
| Download coursebooks | Library page — access code or a separate student login if you create one |

## Emergency / install

- First account: `/pages/install.php` (delete after creating Admin)
- Forgot password: `/pages/forgot-password.php`

## Technical note

Session name: `SABEELAUTH`. Any account with role **Admin** or **Super Admin** can open every admin panel. Module checkboxes are no longer required for admins.
