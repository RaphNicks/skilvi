# Login codes (OTP)

Skilvi signs in with **email + password**, then a 6-digit code.

## Where the code goes

1. **Email (default, production-ready path)**  
   Copy `backend/.env.example` to `backend/.env` and fill in SMTP. Codes go to the inbox.

   ```
   MAIL_MAILER=smtp
   MAIL_SCHEME=
   MAIL_ENCRYPTION=tls
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USERNAME=your-login
   MAIL_PASSWORD=your-smtp-key
   MAIL_FROM_ADDRESS=noreply@yourdomain.ng
   MAIL_FROM_NAME=Skilvi
   MAIL_EHLO_DOMAIN=yourdomain.ng
   ```

   Leave the values blank on a fresh Windows install to stay on `console` (code in the PHP terminal and `backend/storage/logs/last_otp.json`).

   If `MAIL_HOST` is set and `MAIL_MAILER` is empty, SMTP is used automatically.

2. **SMS (optional, when the account has a Nigerian mobile)**  
   **Termii** (`https://termii.com`) — Nigerian routes, Naira billing.

   ```
   SMS_DRIVER=termii
   TERMII_KEY=...
   TERMII_SENDER=Skilvi
   ```

   Phone is optional at signup. SMS is only used if we have a real `234…` number.

## Data store

Live data is **MySQL** (`skilvi` in phpMyAdmin). Tests use a throwaway sqlite file.
