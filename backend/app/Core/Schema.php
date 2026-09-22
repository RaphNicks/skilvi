<?php
declare(strict_types=1);

namespace App\Core;

final class Schema
{
    public static function install(): void
    {
        $pdo = Db::pdo();
        $pdo->exec('PRAGMA foreign_keys=ON');
        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }
        self::migrate();
    }

    public static function migrate(): void
    {
        $adds = [
            'profiles' => [
                'public_code TEXT',
                "tone TEXT DEFAULT 'a1'",
                'skill TEXT',
                'work_mode TEXT',
                'reply TEXT',
                'rating_avg REAL NOT NULL DEFAULT 0',
                'review_count INTEGER NOT NULL DEFAULT 0',
                'orders_completed INTEGER NOT NULL DEFAULT 0',
                'verified INTEGER NOT NULL DEFAULT 0',
                'promo INTEGER NOT NULL DEFAULT 0',
            ],
            'jobs' => [
                "budget_type TEXT NOT NULL DEFAULT 'fixed'",
                'scope TEXT',
                'max_proposals INTEGER',
            ],
            'proposals' => [
                'days INTEGER',
            ],
            'orders' => [
                'title TEXT',
                'proposal_id INTEGER',
                'started_at TEXT',
                'delivered_at TEXT',
                'released_at TEXT',
                'completion_note TEXT',
                'revision_count INTEGER NOT NULL DEFAULT 0',
            ],
            'services' => [
                'category_id INTEGER',
                'packages_json TEXT',
                'public_code TEXT',
                'work_mode TEXT',
            ],
            'categories' => [
                'parent_id INTEGER',
                'icon TEXT',
                'blurb TEXT',
                'mode_label TEXT',
            ],
            'reviews' => [
                'tags TEXT',
                'reply TEXT',
            ],
            'disputes' => [
                'public_code TEXT',
                'reason_type TEXT',
                'decision TEXT',
                'worker_pct INTEGER',
                'resolution_note TEXT',
                'resolved_at TEXT',
                'conversation_id INTEGER',
            ],
            'conversations' => [
                'client_id INTEGER',
                'worker_id INTEGER',
                'dispute_id INTEGER',
                'last_at TEXT',
            ],
            'notifications' => [
                'href TEXT',
            ],
        ];
        foreach ($adds as $table => $cols) {
            $existing = array_column(Db::fetchAll('PRAGMA table_info(' . $table . ')'), 'name');
            foreach ($cols as $def) {
                $name = explode(' ', $def, 2)[0];
                if (!in_array($name, $existing, true)) {
                    Db::pdo()->exec("ALTER TABLE $table ADD COLUMN $def");
                }
            }
        }
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS saved_workers (
                user_id INTEGER NOT NULL REFERENCES users(id),
                worker_id INTEGER NOT NULL REFERENCES users(id),
                note TEXT,
                created_at TEXT NOT NULL,
                PRIMARY KEY (user_id, worker_id)
            )'
        );
        Db::pdo()->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_profiles_public ON profiles(public_code)');
        Db::pdo()->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_services_public ON services(public_code)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status, created_at)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_jobs_mode ON jobs(work_mode)');
        Db::pdo()->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_payments_provider_ref ON payments(provider_ref)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_payments_user ON payments(user_id, created_at)');
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS conversation_reads (
                conversation_id INTEGER NOT NULL REFERENCES conversations(id),
                user_id INTEGER NOT NULL REFERENCES users(id),
                last_read_at TEXT NOT NULL,
                PRIMARY KEY (conversation_id, user_id)
            )'
        );
        Db::pdo()->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_disputes_code ON disputes(public_code)');
        $more = [
            'support_tickets' => ['body TEXT', 'code TEXT', 'last_at TEXT'],
            'categories'      => ['active INTEGER NOT NULL DEFAULT 1'],
            'admin_audit'     => ['ip TEXT'],
        ];
        foreach ($more as $table => $cols) {
            $existing = array_column(Db::fetchAll('PRAGMA table_info(' . $table . ')'), 'name');
            foreach ($cols as $def) {
                $name = explode(' ', $def, 2)[0];
                if (!in_array($name, $existing, true)) {
                    Db::pdo()->exec("ALTER TABLE $table ADD COLUMN $def");
                }
            }
        }
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_code TEXT,
                reporter_id INTEGER REFERENCES users(id),
                target_type TEXT NOT NULL,
                target_id INTEGER,
                reason TEXT,
                note TEXT,
                status TEXT NOT NULL DEFAULT \'open\',
                created_at TEXT NOT NULL,
                updated_at TEXT
            )'
        );
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS ticket_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL REFERENCES support_tickets(id),
                author_id INTEGER REFERENCES users(id),
                body TEXT NOT NULL,
                from_admin INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )'
        );
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS idempotency_keys (
                idem_key TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (user_id, idem_key)
            )'
        );
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status, created_at)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_orders_client ON orders(client_id, status)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_orders_worker ON orders(worker_id, status)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_ledger_order ON ledger(order_id)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_ledger_user ON ledger(user_id, id)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_messages_conv ON messages(conversation_id, id)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, read_at)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_wd_status ON withdrawals(status, created_at)');
        Db::pdo()->exec('CREATE INDEX IF NOT EXISTS idx_jobs_client ON jobs(client_id, status)');
        if (!Db::fetch("SELECT key FROM settings WHERE key='maintenance'")) {
            Db::run("INSERT INTO settings (key, value) VALUES ('maintenance', '0')");
        }
    }

    /** @return list<string> */
    public static function statements(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone TEXT NOT NULL UNIQUE,
                email TEXT UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                roles TEXT NOT NULL DEFAULT \'client\',
                status TEXT NOT NULL DEFAULT \'active\',
                phone_verified_at TEXT,
                email_verified_at TEXT,
                last_login_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS otp_codes (
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
            )',
            'CREATE INDEX IF NOT EXISTS idx_otp_phone_purpose ON otp_codes(phone, purpose, created_at)',
            'CREATE TABLE IF NOT EXISTS profiles (
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
            )',
            'CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                kind TEXT NOT NULL DEFAULT \'skill\',
                sort INTEGER NOT NULL DEFAULT 0
            )',
            'CREATE TABLE IF NOT EXISTS skills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER REFERENCES categories(id),
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE
            )',
            'CREATE TABLE IF NOT EXISTS verifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                status TEXT NOT NULL DEFAULT \'pending\',
                amount_kobo INTEGER NOT NULL DEFAULT 500000,
                payment_id INTEGER,
                reviewed_by INTEGER,
                notes TEXT,
                expires_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS jobs (
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
                status TEXT NOT NULL DEFAULT \'open\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS proposals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id INTEGER NOT NULL REFERENCES jobs(id),
                worker_id INTEGER NOT NULL REFERENCES users(id),
                cover_note TEXT,
                bid_kobo INTEGER,
                status TEXT NOT NULL DEFAULT \'sent\',
                shortlisted INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                UNIQUE(job_id, worker_id)
            )',
            'CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                job_id INTEGER REFERENCES jobs(id),
                service_id INTEGER,
                client_id INTEGER NOT NULL REFERENCES users(id),
                worker_id INTEGER NOT NULL REFERENCES users(id),
                amount_kobo INTEGER NOT NULL,
                fee_kobo INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT \'pending_payment\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL REFERENCES users(id),
                order_id INTEGER,
                purpose TEXT NOT NULL,
                provider TEXT,
                provider_ref TEXT,
                amount_kobo INTEGER NOT NULL,
                method TEXT,
                status TEXT NOT NULL DEFAULT \'initiated\',
                raw_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS ledger (
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
            )',
            'CREATE TABLE IF NOT EXISTS wallets (
                user_id INTEGER PRIMARY KEY REFERENCES users(id),
                available_kobo INTEGER NOT NULL DEFAULT 0,
                pending_kobo INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS withdrawals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                amount_kobo INTEGER NOT NULL,
                fee_kobo INTEGER NOT NULL DEFAULT 0,
                bank_name TEXT,
                account_number TEXT,
                account_name TEXT,
                status TEXT NOT NULL DEFAULT \'pending\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                worker_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT,
                price_kobo INTEGER,
                status TEXT NOT NULL DEFAULT \'draft\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS disputes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL REFERENCES orders(id),
                opened_by INTEGER NOT NULL REFERENCES users(id),
                reason TEXT,
                status TEXT NOT NULL DEFAULT \'open\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER REFERENCES orders(id),
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL REFERENCES conversations(id),
                sender_id INTEGER NOT NULL REFERENCES users(id),
                body TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                kind TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT,
                read_at TEXT,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL REFERENCES orders(id),
                from_user INTEGER NOT NULL REFERENCES users(id),
                to_user INTEGER NOT NULL REFERENCES users(id),
                rating INTEGER NOT NULL,
                comment TEXT,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS saved_jobs (
                user_id INTEGER NOT NULL REFERENCES users(id),
                job_id INTEGER NOT NULL REFERENCES jobs(id),
                created_at TEXT NOT NULL,
                PRIMARY KEY (user_id, job_id)
            )',
            'CREATE TABLE IF NOT EXISTS promotions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                plan TEXT NOT NULL,
                amount_kobo INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT \'pending\',
                starts_at TEXT,
                ends_at TEXT,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS support_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER REFERENCES users(id),
                subject TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'open\',
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS admin_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_id INTEGER NOT NULL REFERENCES users(id),
                action TEXT NOT NULL,
                target TEXT,
                meta TEXT,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS rate_limits (
                bucket TEXT PRIMARY KEY,
                hits INTEGER NOT NULL,
                reset_at INTEGER NOT NULL
            )',
        ];
    }
}
