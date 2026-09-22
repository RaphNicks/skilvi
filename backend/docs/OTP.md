# Login codes (OTP)

Skilvi signs in with **email + password**, then a 6-digit code.

## Where the code goes

1. **Email (default, production-ready path)**  
   Set SMTP and we send the code to the inbox.

   Recommended starter for Nigeria: **[Brevo](https://www.brevo.com/)** (free tier) or Mailgun / Amazon SES / Google Workspace.

   ```
   MAIL_DRIVER=smtp
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USER=your-brevo-login
   MAIL_PASS=your-smtp-key
   MAIL_FROM=Skilvi <noreply@yourdomain.ng>
   APP_ENV=prod
   ```

   On Windows local, leave `MAIL_DRIVER` unset (`console`). The code is printed in the PHP terminal and saved to `backend/storage/logs/last_otp.json`.

2. **SMS (optional, when the account has a Nigerian mobile)**  
   **Termii** (`https://termii.com`) — Nigerian routes, Naira billing.

   ```
   SMS_DRIVER=termii
   TERMII_KEY=...
   TERMII_SENDER=Skilvi
   ```

   Phone is optional at signup. SMS is only used if we have a real `234…` number.

## Data store

All accounts, jobs, orders, escrow, messages live in **SQLite**:

`backend/storage/skilvi.sqlite`

That file is local to the machine running PHP. Production can switch to MySQL with `DB_DRIVER=mysql`.
