# OWASP-style checklist (Phase 8)

Closed against `skilvi-backend-plan.pdf` §8. Items that need live keys stay **wired, sandbox-default**.

| Area | Control | Status |
|---|---|---|
| SQL injection | PDO prepared statements only (`Db::run/fetch`) | Closed |
| XSS | JSON API; `e()` for HTML; CSP (self + Google Fonts + unsafe-inline for existing pages) | Closed |
| CSRF | Double-submit `skilvi_csrf` / `X-CSRF-Token` on mutating `/api/*`; webhooks exempt + HMAC | Closed |
| Auth abuse | OTP hashed, 5 attempts, 10 min TTL, 60s resend, per-phone + per-IP caps; session rotate on login | Closed |
| IDOR | Queries scoped by session user; admin `requireRole`; payments 403 if not owner | Closed |
| Payment fraud | Amounts server-side; HMAC-SHA512 webhooks; amount match; duplicate `provider_ref` no-op; simulate **dev only** | Closed |
| PII | NUBAN AES-256-GCM at rest (`enc:v1:`); API returns masked `····1234` only | Closed |
| Rate limit | Auth 20/min/IP, money 30/min, GET 180/min, POST 60/min; `Retry-After` on 429 | Closed |
| Uploads | JPG/PNG/WebP, 5 MB, MIME sniff, random names, outside webroot, proof docs owner/admin | Closed |
| Headers | nosniff, Referrer-Policy, Permissions-Policy, CSP; HSTS + DENY frames **prod only** (dev preview must iframe) | Closed |
| Audit | `admin_audit` append-only; `payments.raw_json`; `tools/ledger-audit.php` | Closed |
| Ops | `display_errors` off in prod; 500 never leaks stack; `storage/logs/error.log`; maintenance flag; backups 30-day | Closed |
| Idempotency | `Idempotency-Key` on payment initiate; webhook replay returns `replayed: true` | Closed |
| SMS budget | `SMS_DAILY_BUDGET` (default 200) via rate_limits; health exposes `sms_left` | Closed |
| Open redirect | `Response::redirect` only allows same-origin paths | Closed |
| Body limit | 1 MB JSON | Closed |

**Still operator-owned (not code):** Paystack live keys, Africa’s Talking/Termii, VPS backups off-box, k6 against staging at 10× traffic.
