# Run Skilvi on your computer (VS Code + PHP / XAMPP)

You need **PHP 8.2+** with `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, and **XAMPP MySQL** running. Live data is stored in MySQL — you inspect it in **phpMyAdmin**, not in a file inside the project.

## 1. Download

In VS Code: Terminal → New Terminal.

```bash
git clone https://github.com/RaphNicks/skilvi.git
cd skilvi
code .
```

Or **Code → File → Open Folder** on that `skilvi` folder.

Layout:

```
skilvi/                 ← pages (index.html, admin/, assets/)
  backend/              ← PHP API
    public/index.php
    tools/install.php
```

## 2. Recommended: PHP built-in server (easiest)

Use XAMPP only as the PHP binary if you already have it.

**Windows (PowerShell)** — XAMPP PHP + MySQL:

1. Open **XAMPP Control Panel** → Start **Apache** and **MySQL**.
2. Optional: open **http://localhost/phpmyadmin** → New → database name `skilvi`, collation `utf8mb4_unicode_ci` → Create. (`install.php` will create it if you skip this.)
3. In `C:\xampp\php\php.ini` make sure these are **uncommented** (no `;` in front):

```ini
extension=pdo_mysql
extension=mysqli
extension=openssl
extension=mbstring
extension=fileinfo
```

4. Then:

```powershell
cd C:\Users\Admin\skilvi\backend
C:\xampp\php\php.exe -m | findstr /i "pdo_mysql openssl mbstring fileinfo"
C:\xampp\php\php.exe tools\install.php
C:\xampp\php\php.exe -S 127.0.0.1:8080 tools\dev-router.php
```

Default MySQL login is XAMPP’s `root` with an **empty password**, database `skilvi`. After install, refresh phpMyAdmin — you should see tables `users`, `jobs`, `orders`, …

**macOS / Linux:**

```bash
cd skilvi/backend
php -m | grep -E 'pdo_sqlite|openssl|mbstring|fileinfo'
php tools/install.php
php -S 127.0.0.1:8080 tools/dev-router.php
```

Open **http://127.0.0.1:8080**

`install.php` creates the MySQL database/tables and seed accounts. Safe to re-run. **Data is not stored in the project folder.**

## 3. Log in (dev)

Password for all seeds: `password1`

| Who | Email |
|---|---|
| Client + worker | `chinedu@okafordev.ng` |
| Client + worker | `ifeanyi@okoro.ng` |
| Admin | `admin@skilvi.ng` |

Login is **email + password → 6-digit code to that email**. Copy `backend/.env.example` to `backend/.env` and fill the `MAIL_*` lines for real SMTP. Until then (`console`) the code is in the PHP terminal and `backend/storage/logs/last_otp.json`. See `docs/OTP.md`.

## 4. Optional: Apache in XAMPP

Only if you want `http://localhost/skilvi` instead of `:8080`.

1. Start **Apache** in the XAMPP control panel (MySQL off is fine).
2. Document root must run `backend/public/index.php` **and** still see `assets/`. Easiest: keep using the built-in server above.
3. If you insist on Apache, add `skilvi/backend/public/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

…and Alias the frontend:

```apache
# C:\xampp\apache\conf\extra\httpd-vhosts.conf
<VirtualHost *:80>
  ServerName skilvi.local
  DocumentRoot "C:/path/to/skilvi/backend/public"
  SetEnv FRONTEND_ROOT "C:/path/to/skilvi"
  SetEnv APP_ENV "dev"
  Alias /assets "C:/path/to/skilvi/assets"
  <Directory "C:/path/to/skilvi/backend/public">
    AllowOverride All
    Require all granted
  </Directory>
  <Directory "C:/path/to/skilvi/assets">
    Require all granted
  </Directory>
</VirtualHost>
```

Add `127.0.0.1 skilvi.local` to `C:\Windows\System32\drivers\etc\hosts`. The **PHP `-S` router is less fiddly** — prefer that for day-to-day.

## 5. Tests

```powershell
cd skilvi\backend
C:\xampp\php\php.exe tests\test_auth.php
C:\xampp\php\php.exe tests\test_discovery.php
C:\xampp\php\php.exe tests\test_orders.php
C:\xampp\php\php.exe tests\test_payments.php
C:\xampp\php\php.exe tests\test_wallet.php
C:\xampp\php\php.exe tests\test_comms.php
C:\xampp\php\php.exe tests\test_admin.php
C:\xampp\php\php.exe tests\test_hardening.php
```

Each test uses a **temp sqlite file**, not your MySQL `skilvi` database.

Browser smoke:

1. Home loads with CSS and logo.
2. Search / jobs / a worker profile.
3. Register or log in as Chinedu (`08031112233`), complete OTP. Sign out is in the dashboard sidebar.
4. Post a job → propose → accept → checkout → **Simulate payment** (dev only) → start → submit → approve.
5. Worker wallet: available balance after release.
6. Admin (`08000000001`) → `/admin/index.html`.

Health: http://127.0.0.1:8080/api/health → `"status":"ok"`.

## 6. VS Code

- Open the **repo root** (`skilvi`), not only `backend/`.
- Optional extensions: **PHP Intelephense**, **SQLite Viewer**.
- Debugger: listen on 8080; no extra config required for the built-in server.

## 7. Don’t

- Don’t point Apache at the repo root without a front controller — `/api/*` will 404.
- Don’t set `APP_ENV=prod` locally (OTP `dev_code` disappears; HTTPS cookies).
- Don’t commit `storage/.app_key` or any database dump with live passwords.
