# Admin Login (simple)

One **Admin ID + password** for every management page.

## How you use it

1. Go to `/pages/login.php`
2. Enter your Admin ID (username or email) and password  
   (the Super Admin account you created at install)
3. You land on the Admin Dashboard with links to:
   - Admin Hub  
   - Blog Admin  
   - Library Admin  
   - Courses Admin  
   - Admissions Admin  
   - Results Admin (Student Portal)

You stay logged in across those pages. Use **Logout** when finished.  
Change your password anytime at `/pages/change-password.php`.

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
