# Skilvi PHP backend

Vanilla PHP 8.2+ (no framework). Ajax JSON API + 1:1 pages for the 47-page frontend.

**Phases 0–8 are in this tree:** scaffold → auth → discovery → escrow → payments → wallet → comms → admin → hardening.

## Run (dev)

```bash
php tools/install.php
php -S 0.0.0.0:8080 -t public public/index.php
```

In this sandbox the PHP binary is `/home/user/php/php`.

## Seed accounts

Password for all: `password1`

| Name | Phone | Roles |
|---|---|---|
| Chinedu Okafor | +234 803 111 2233 | client, worker |
| Ifeanyi Okoro | +234 801 000 1028 | client |
| Adaeze Nwosu | +234 802 000 0008 | worker |
| Skilvi Admin | +234 800 000 0001 | admin |

OTP is logged to `storage/logs/last_otp.json` and returned as `dev_code` while `APP_ENV=dev`.

## Ops (Phase 8)

```bash
php tools/ledger-audit.php          # exit 0 = money checks green
php tools/backup.php                # sqlite snapshot, keep 30
php tools/smoke.php                 # health + landing against :8080
php tools/perf.php                  # p95 < 300ms on /api/landing
# k6 run tools/k6-discovery.js      # optional load
```

Cutover/rollback: `docs/CUTOVER.md`. OWASP checklist: `docs/OWASP.md`.

Mutating `/api/*` needs `X-CSRF-Token`. Payments also accept `Idempotency-Key`. Set `maintenance=1` in `settings` for a 503 fence (admins exempt).

## Tests

```bash
php tests/test_auth.php
php tests/test_discovery.php
php tests/test_orders.php
php tests/test_payments.php
php tests/test_wallet.php
php tests/test_comms.php
php tests/test_admin.php
php tests/test_hardening.php
```
