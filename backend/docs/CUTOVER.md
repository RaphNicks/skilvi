# Skilvi cutover & rollback (Phase 8)

PHP already serves the 47-page frontend from `FRONTEND_ROOT` plus `/api/*`. Cutover is DNS + process, not a rewrite.

## Go-live

1. Set `APP_ENV=prod`, `APP_KEY` (32+ chars), `PAYSTACK_SECRET` / `PAYSTACK_WEBHOOK`, `SMS_DRIVER` when keys exist.
2. `php tools/install.php` on a fresh host **or** copy `storage/skilvi.sqlite` from staging (never re-seed production wallets).
3. Nightly: `php tools/backup.php` (keeps 30 copies under `storage/backups/`).
4. Cron: `php tools/ledger-audit.php` — exit 2 means money drift; freeze withdrawals.
5. Point Nginx `root` at `public/`, `try_files $uri /index.php?$query_string`.
6. TLS (Let’s Encrypt). HSTS is on when `APP_ENV=prod`.
7. Hit `/api/health` — `status=ok`, `maintenance=false`.
8. Smoke: `SMOKE_BASE=https://skilvi.ng php tools/smoke.php`.
9. Flip DNS. Keep the old static host for 48h.

## Rollback (≤ 15 minutes)

1. `UPDATE settings SET value='1' WHERE key='maintenance';` — public API returns 503; admins still in.
2. Restore last `storage/backups/skilvi-*.sqlite` over `storage/skilvi.sqlite` (stop php-fpm first).
3. Or CNAME back to the static frontend host; API 502 is better than a split-brain ledger.
4. `maintenance=0` only after `php tools/ledger-audit.php` exits 0.

## Do not

- Re-run `Seed::run()` on production (it is mostly idempotent, but never a recovery tool).
- Force-release disputed orders from admin — use the dispute queue.
- Ship `APP_ENV=dev` (OTP `dev_code` leaks in JSON).
