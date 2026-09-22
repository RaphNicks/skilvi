CREATE TABLE admin_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NOT NULL REFERENCES users(id),
                action TEXT NOT NULL,
                target TEXT,
                meta TEXT,
                created_at TEXT NOT NULL
            );

CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                kind TEXT NOT NULL DEFAULT 'skill',
                sort INTEGER NOT NULL DEFAULT 0
            );

CREATE TABLE conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER REFERENCES orders(id),
                created_at TEXT NOT NULL
            );

CREATE TABLE disputes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL REFERENCES orders(id),
                opened_by INTEGER NOT NULL REFERENCES users(id),
                reason TEXT,
                status TEXT NOT NULL DEFAULT 'open',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE INDEX idx_otp_phone_purpose ON otp_codes(phone, purpose, created_at);

CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                client_id INTEGER NOT NULL REFERENCES users(id),
                category_id INTEGER REFERENCES categories(id),
                title TEXT NOT NULL,
                description TEXT,
                budget_kobo INTEGER,
                work_mode TEXT,
                location TEXT,
                deadline TEXT,
                status TEXT NOT NULL DEFAULT 'open',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE ledger (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_no INTEGER NOT NULL,
                account TEXT NOT NULL,
                user_id INTEGER,
                order_id INTEGER,
                payment_id INTEGER,
                debit_kobo INTEGER NOT NULL DEFAULT 0,
                credit_kobo INTEGER NOT NULL DEFAULT 0,
                memo TEXT,
                created_at TEXT NOT NULL
            );

CREATE TABLE messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL REFERENCES conversations(id),
                sender_id INTEGER NOT NULL REFERENCES users(id),
                body TEXT NOT NULL,
                created_at TEXT NOT NULL
            );

CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                kind TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT,
                read_at TEXT,
                created_at TEXT NOT NULL
            );

CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                job_id INTEGER REFERENCES jobs(id),
                service_id INTEGER,
                client_id INTEGER NOT NULL REFERENCES users(id),
                worker_id INTEGER NOT NULL REFERENCES users(id),
                amount_kobo INTEGER NOT NULL,
                fee_kobo INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'pending_payment',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE otp_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone TEXT NOT NULL,
                purpose TEXT NOT NULL,
                code_hash TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                expires_at INTEGER NOT NULL,
                consumed_at INTEGER,
                ip TEXT,
                created_at INTEGER NOT NULL
            );

CREATE TABLE payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL REFERENCES users(id),
                order_id INTEGER,
                purpose TEXT NOT NULL,
                provider TEXT,
                provider_ref TEXT,
                amount_kobo INTEGER NOT NULL,
                method TEXT,
                status TEXT NOT NULL DEFAULT 'initiated',
                raw_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE profiles (
                user_id INTEGER PRIMARY KEY REFERENCES users(id),
                headline TEXT,
                bio TEXT,
                state TEXT,
                city TEXT,
                avatar_path TEXT,
                notify_sms INTEGER NOT NULL DEFAULT 1,
                notify_jobs INTEGER NOT NULL DEFAULT 1,
                notify_marketing INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE promotions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                plan TEXT NOT NULL,
                amount_kobo INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                starts_at TEXT,
                ends_at TEXT,
                created_at TEXT NOT NULL
            );

CREATE TABLE proposals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id INTEGER NOT NULL REFERENCES jobs(id),
                worker_id INTEGER NOT NULL REFERENCES users(id),
                cover_note TEXT,
                bid_kobo INTEGER,
                status TEXT NOT NULL DEFAULT 'sent',
                shortlisted INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                UNIQUE(job_id, worker_id)
            );

CREATE TABLE rate_limits (
                bucket TEXT PRIMARY KEY,
                hits INTEGER NOT NULL,
                reset_at INTEGER NOT NULL
            );

CREATE TABLE reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL REFERENCES orders(id),
                from_user INTEGER NOT NULL REFERENCES users(id),
                to_user INTEGER NOT NULL REFERENCES users(id),
                rating INTEGER NOT NULL,
                comment TEXT,
                created_at TEXT NOT NULL
            );

CREATE TABLE saved_jobs (
                user_id INTEGER NOT NULL REFERENCES users(id),
                job_id INTEGER NOT NULL REFERENCES jobs(id),
                created_at TEXT NOT NULL,
                PRIMARY KEY (user_id, job_id)
            );

CREATE TABLE services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                worker_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT,
                price_kobo INTEGER,
                status TEXT NOT NULL DEFAULT 'draft',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

CREATE TABLE skills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER REFERENCES categories(id),
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE
            );

CREATE TABLE sqlite_sequence(name,seq);

CREATE TABLE support_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER REFERENCES users(id),
                subject TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                created_at TEXT NOT NULL
            );

CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone TEXT NOT NULL UNIQUE,
                email TEXT UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                roles TEXT NOT NULL DEFAULT 'client',
                status TEXT NOT NULL DEFAULT 'active',
                phone_verified_at TEXT,
                email_verified_at TEXT,
                last_login_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE verifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                status TEXT NOT NULL DEFAULT 'pending',
                amount_kobo INTEGER NOT NULL DEFAULT 500000,
                payment_id INTEGER,
                reviewed_by INTEGER,
                notes TEXT,
                expires_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

CREATE TABLE wallets (
                user_id INTEGER PRIMARY KEY REFERENCES users(id),
                available_kobo INTEGER NOT NULL DEFAULT 0,
                pending_kobo INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            );

CREATE TABLE withdrawals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                amount_kobo INTEGER NOT NULL,
                fee_kobo INTEGER NOT NULL DEFAULT 0,
                bank_name TEXT,
                account_number TEXT,
                account_name TEXT,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
