# Deploy on Hostinger — Sabeel Us Salaam Student Portal

The results software lives in the website folder as **`student-portal/`** and is linked from the main site menu as **Student Portal**.

## Live URLs (same domain as website)

| Place | URL |
|--------|-----|
| Student Portal (public) | `https://sabeelussalamonline.com/student-portal/` |
| Admin login | `https://sabeelussalamonline.com/pages/login.php` (then Admin Hub → Results) |

Main website already links to `student-portal/` in the header, footer, and Results section.

---

## Hostinger deployment (step by step)

### 1) Hosting plan
- Need **PHP** + **MySQL** (Hostinger shared / cloud / business is fine).
- PHP **8.0+** (set in hPanel → Advanced → PHP Configuration).

### 2) Create MySQL database
In **hPanel → Databases → MySQL Databases**:
1. Create database, e.g. `u123456789_sabeel`
2. Create user + strong password
3. Assign user to database (All privileges)
4. Note: **DB name, username, password, hostname** (usually `localhost`)

### 3) Upload files
Upload the whole website (including `student-portal/`) into `public_html/`.

Keep this folder name exact: **`student-portal`** (matches menu links and `config.php` `base_url`).

Inside `student-portal/` you need:

```
admin/
assets/
config/
includes/
public/
sql/
index.php
install.php
.htaccess
```

### 4) Edit config
Edit `student-portal/config/config.php` on Hostinger:

```php
'db_host' => 'localhost',
'db_name' => 'u123456789_sabeel',   // your Hostinger DB name
'db_user' => 'u123456789_user',     // your Hostinger DB user
'db_pass' => 'YOUR_STRONG_PASSWORD',
'base_url' => '/student-portal',
```

### 5) Folder permissions
- `student-portal/assets/uploads/photos` → writable (755 or 775)

### 6) Run installer once
Open:

`https://sabeelussalamonline.com/student-portal/install.php`

Click **Run Installation**.

Login: `admin` / `Admin@123` → **change password immediately**.

### 7) Lock down after install
1. **Delete** `install.php` from the server (important).
2. Do not upload Access `.accdb` or `_extract/`.
3. Keep `sql/` and `config/` on server (blocked by `.htaccess` / `index.php`).

### 8) SSL
In hPanel enable **Free SSL**. Always use `https://`.

---

## Daily use after go-live

1. Admin → Courses / Subjects / Students  
2. Marks Entry → select semester (old semesters stay saved)  
3. Publish result  
4. Student opens **Student Portal** from the website → enters **Student ID** e.g. `Sabeel-26-610`  
5. View / Print / Save PDF  

---

## Quick troubleshooting on Hostinger

| Problem | Fix |
|---------|-----|
| Blank page / 500 | PHP version 8.0+; check Error Log in hPanel |
| DB connection error | Fix `student-portal/config/config.php` credentials |
| CSS/logo missing | Confirm `base_url` is `/student-portal` |
| Cannot upload photo | `assets/uploads/photos` permissions |
| Install page still open | Delete `install.php` |
