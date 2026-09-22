# Run Skilvi on your computer (VS Code + PHP / XAMPP)

You need **PHP 8.2+** with `pdo_sqlite`, `openssl`, `mbstring`, `fileinfo`. XAMPP’s PHP is enough — you do **not** need MySQL for local (SQLite is the default).

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

**Windows (PowerShell)** — XAMPP PHP:

```powershell
cd C:\path\to\skilvi\backend
C:\xampp\php\php.exe -m | findstr /i "pdo_sqlite openssl mbstring fileinfo"
C:\xampp\php\php.exe tools\install.php
C:\xampp\php\php.exe -S 127.0.0.1:8080 tools\dev-router.php
```

**macOS / Linux:**

```bash
cd skilvi/backend
php -m | grep -E 'pdo_sqlite|openssl|mbstring|fileinfo'
php tools/install.php
php -S 127.0.0.1:8080 tools/dev-router.php
```

Open **http://127.0.0.1:8080**

`install.php` creates `backend/storage/skilvi.sqlite` and seed accounts. Safe to re-run (idempotent). Do not commit the sqlite file.

If `pdo_sqlite` is missing in XAMPP: `C:\xampp\php\php.ini` — uncomment `extension=pdo_sqlite` and `extension=sqlite3`, restart the server command.

## 3. Log in (dev)

Password for all seeds: `password1`

| Who | Phone |
|---|---|
| Client + worker | `08031112233` or `2348031112233` |
| Client | `08010001028` |
| Admin | `08000000001` |

Login is **phone + password → OTP**. In dev the JSON includes `dev_code`, and the code is also in `backend/storage/logs/last_otp.json`.

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

Each test uses a **temp sqlite file**, not your live `storage/skilvi.sqlite`.

Browser smoke:

1. Home loads with CSS and logo.
2. Search / jobs / a worker profile.
3. Register or log in as Adaeze (`08052223344`) / Chinedu, complete OTP.
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
- Don’t commit `backend/storage/skilvi.sqlite` or `storage/.app_key`.
