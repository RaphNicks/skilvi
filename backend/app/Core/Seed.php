<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Seed
{
    public static function run(): void
    {
        self::users();
        self::catalog();
        self::fixTree();
        self::demoOrders();
        self::demoComms();
        self::demoAdmin();
        \App\Services\WalletService::syncFromOrders();
        Db::pdo()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['min_withdrawal_kobo', '500000']);
    }

    /** Re-parent leftover v1 categories so filters work on existing DBs. */
    private static function fixTree(): void
    {
        $parent = static function (string $slug): ?int {
            $r = \App\Core\Db::fetch('SELECT id FROM categories WHERE slug = ?', [$slug]);
            return $r ? (int) $r['id'] : null;
        };
        $map = [
            'web-dev'    => ['digital-tech', 'Web Development'],
            'design'     => ['digital-tech', 'Design'],
            'electrical' => ['trades', 'Electrical'],
            'plumbing'   => ['trades', 'Plumbing'],
            'writing'    => ['creative', 'Writing & Content'],
        ];
        foreach ($map as $slug => [$p, $name]) {
            $pid = $parent($p);
            if ($pid) {
                \App\Core\Db::run('UPDATE categories SET parent_id = ?, name = ? WHERE slug = ? AND (parent_id IS NULL OR parent_id = ?)', [$pid, $name, $slug, $pid]);
            }
        }
        $web = \App\Core\Db::fetch('SELECT id FROM categories WHERE slug = ? OR name = ?', ['web-dev', 'Web Development']);
        if ($web) {
            \App\Core\Db::run('UPDATE jobs SET category_id = ? WHERE category_id IS NULL AND title LIKE ?', [$web['id'], '%Landing page%']);
        }
    }

    private static function users(): void
    {
        $hash = password_hash('password1', PASSWORD_DEFAULT);
        $people = [
            ['2348031112233', 'chinedu@okafordev.ng', 'Chinedu Okafor', 'client,worker', 'Frontend developer & WordPress specialist', "I've been building websites for 6 years — first for a software house in Port Harcourt, then for restaurants, pharmacies and real-estate agencies. I work fully remotely and stay available after launch.", 'Rivers', 'Port Harcourt'],
            ['2348010001028', 'ifeanyi@okoro.ng', 'Ifeanyi Okoro', 'client,worker', 'Tiler & mason — clean finishing', 'Twelve years of bathroom and kitchen finishing across Rivers and Lagos. Same-week availability.', 'Rivers', 'Port Harcourt'],
            ['2348020000008', 'ada@w08.ng', 'Adaeze Nwosu', 'worker', 'Residential electrician', null, 'Rivers', 'Port Harcourt'],
            ['2348000000001', 'admin@skilvi.ng', 'Skilvi Admin', 'admin', null, null, null, null],
        ];
        foreach ($people as $p) {
            if (User::findByPhone($p[0])) {
                continue;
            }
            User::create([
                'phone' => $p[0],
                'email' => $p[1],
                'password_hash' => $hash,
                'full_name' => $p[2],
                'roles' => $p[3],
                'headline' => $p[4],
                'bio' => $p[5],
                'state' => $p[6],
                'city' => $p[7],
            ]);
        }
        Db::pdo()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['fee_percent', '10']);
        Db::pdo()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['currency', 'NGN']);
        Db::pdo()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['stats_professionals', '12000']);
        Db::pdo()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['stats_gmv_naira', '4200000000']);
        Db::pdo()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['stats_jobs_done', '8500']);
    }

    private static function catalog(): void
    {
        if (Db::fetch('SELECT id FROM jobs LIMIT 1')) {
            return;
        }
        $hash = password_hash('password1', PASSWORD_DEFAULT);
        $pdo = Db::pdo();

        $parents = [
            ['digital-tech', 'Digital & Tech', 'digital', 'code', 'Remote', 'Web, apps, UI and IT — escrow-protected from quote to payout.', 1],
            ['creative', 'Creative & Media', 'digital', 'pen', 'Remote', 'Brand, video, photo and writing from designers clients rate 4.8+.', 2],
            ['trades', 'Trades & Home Services', 'trade', 'wrench', 'On-site', 'Plumbing, electrical, tiling, painting, AC, generator power and more.', 3],
            ['business', 'Business & Professional', 'digital', 'briefcase', 'Remote', 'Accounts, VA, consulting and marketing for small Nigerian businesses.', 4],
            ['education', 'Education & Personal', 'both', 'heart', 'Both', 'Tutoring, tailoring, hair, beauty and wellness.', 5],
        ];
        $insP = $pdo->prepare('INSERT OR IGNORE INTO categories (slug, name, kind, sort, icon, mode_label, blurb) VALUES (?,?,?,?,?,?,?)');
        foreach ($parents as $c) {
            $insP->execute([$c[0], $c[1], $c[2], $c[6], $c[3], $c[4], $c[5]]);
        }
        $parentId = [];
        foreach (Db::fetchAll('SELECT id, slug FROM categories WHERE parent_id IS NULL') as $r) {
            $parentId[$r['slug']] = (int) $r['id'];
        }

        $skills = [
            ['web-dev', 'Web Development', 'digital-tech'],
            ['uiux', 'UI/UX Design', 'digital-tech'],
            ['graphic', 'Graphic Design', 'creative'],
            ['video', 'Video & Motion', 'creative'],
            ['writing', 'Writing & Content', 'creative'],
            ['plumbing', 'Plumbing', 'trades'],
            ['electrical', 'Electrical', 'trades'],
            ['tiling', 'Tiling & Masonry', 'trades'],
            ['painting', 'Painting', 'trades'],
            ['power', 'Generator & Power', 'trades'],
            ['accounting', 'Accounting & Bookkeeping', 'business'],
            ['fashion', 'Fashion & Tailoring', 'education'],
        ];
        $insS = $pdo->prepare('INSERT OR IGNORE INTO categories (slug, name, kind, sort, parent_id) VALUES (?,?,?,?,?)');
        $i = 10;
        foreach ($skills as $s) {
            $insS->execute([$s[0], $s[1], 'skill', $i++, $parentId[$s[2]] ?? null]);
        }
        $catId = [];
        foreach (Db::fetchAll('SELECT id, name, slug FROM categories') as $r) {
            $catId[$r['name']] = (int) $r['id'];
            $catId[$r['slug']] = (int) $r['id'];
        }

        $workers = [
            ['w01', '2348031112233', 'chinedu@okafordev.ng', 'Chinedu Okafor', 'a1', 'Frontend developer & WordPress specialist', 'Web Development', 'Port Harcourt', 'Rivers', 'remote', 4.9, 132, 187, '1 hr', 1, 0, 45000, 'client,worker'],
            ['w02', '2348052220002', 'aisha@bello.ng', 'Aisha Bello', 'a2', 'UI/UX designer for web & mobile', 'UI/UX Design', 'Abuja', 'FCT', 'remote', 4.8, 98, 120, '2 hrs', 1, 0, 30000, 'worker'],
            ['w03', '2348063330003', 'emeka@obi.ng', 'Emeka Obi', 'a3', 'Plumber — 12 years experience', 'Plumbing', 'Lagos', 'Lagos', 'on-site', 4.9, 210, 342, '3 hrs', 1, 1, 8000, 'worker'],
            ['w04', '2348094440004', 'ngozi@eze.ng', 'Ngozi Eze', 'a4', 'Logo, brand & social media design', 'Graphic Design', 'Lagos', 'Lagos', 'remote', 4.7, 156, 265, '4 hrs', 1, 0, 15000, 'worker'],
            ['w05', '2348075550005', 'tunde@adeyemi.ng', 'Tunde Adeyemi', 'a5', 'Electrician — wiring, sockets, panels', 'Electrical', 'Ibadan', 'Oyo', 'on-site', 4.8, 88, 140, '1 day', 0, 0, 12000, 'worker'],
            ['w06', '2348010001028', 'ifeanyi@okoro.ng', 'Ifeanyi Okoro', 'a1', 'Tiler & mason — clean finishing', 'Tiling & Masonry', 'Port Harcourt', 'Rivers', 'on-site', 4.9, 64, 91, '2 hrs', 1, 0, 25000, 'client,worker'],
            ['w07', '2348087770007', 'amara@nwosu.ng', 'Amara Nwosu', 'a2', 'Video editor & motion graphics', 'Video & Motion', 'Enugu', 'Enugu', 'remote', 4.6, 71, 104, '6 hrs', 0, 0, 20000, 'worker'],
            ['w08', '2348080000008', 'femi@balogun.ng', 'Femi Balogun', 'a3', 'Generator, inverter & AC installation', 'Generator & Power', 'Lagos', 'Lagos', 'on-site', 4.8, 133, 205, '1 hr', 1, 0, 35000, 'worker'],
            ['w09', '2348090000009', 'kemi@adeleke.ng', 'Kemi Adeleke', 'a4', 'Bookkeeper & small-business accountant', 'Accounting & Bookkeeping', 'Lagos', 'Lagos', 'remote', 4.9, 54, 87, '3 hrs', 1, 0, 10000, 'worker'],
            ['w10', '2348100000010', 'yusuf@garba.ng', 'Yusuf Garba', 'a5', 'House & office painter', 'Painting', 'Kano', 'Kano', 'on-site', 4.7, 45, 78, '1 day', 0, 0, 18000, 'worker'],
            ['w11', '2348110000011', 'blessing@umoh.ng', 'Blessing Umoh', 'a1', 'Copywriter & content editor', 'Writing & Content', 'Abuja', 'FCT', 'remote', 4.8, 66, 93, '5 hrs', 1, 0, 12000, 'worker'],
            ['w12', '2348120000012', 'halima@sani.ng', 'Halima Sani', 'a2', 'Tailor & fashion stylist', 'Fashion & Tailoring', 'Kano', 'Kano', 'on-site', 4.8, 59, 84, '4 hrs', 1, 0, 15000, 'worker'],
        ];

        $svcTitle = [
            'w01' => 'Website development — landing pages to full sites',
            'w02' => 'UI/UX design — web app & mobile screens',
            'w03' => 'Plumbing — installations, repairs & leak fixing',
            'w04' => 'Brand identity — logo, brand kit & socials',
            'w05' => 'Electrical work — wiring, sockets, panel changes',
            'w06' => 'Tiling & masonry — bathrooms, kitchens, floors',
            'w07' => 'Video editing — YouTube, ads & brand films',
            'w08' => 'Generator & inverter installation + AC service',
            'w09' => 'Bookkeeping & monthly financial reports',
            'w10' => 'Painting — interior, exterior & touch-ups',
            'w11' => 'Copywriting — websites, ads & brand voice',
            'w12' => 'Tailoring — custom fits, alterations, event wear',
        ];

        foreach ($workers as $w) {
            $user = User::findByPhone($w[1]);
            if ($user === null) {
                $id = User::create([
                    'phone' => $w[1],
                    'email' => $w[2],
                    'password_hash' => $hash,
                    'full_name' => $w[3],
                    'roles' => $w[17],
                    'headline' => $w[5],
                    'bio' => null,
                    'state' => $w[8],
                    'city' => $w[7],
                ]);
            } else {
                $id = (int) $user['id'];
                Db::run('UPDATE users SET roles = ?, full_name = ?, email = COALESCE(email, ?) WHERE id = ?', [$w[17], $w[3], $w[2], $id]);
            }
            Db::run(
                'UPDATE profiles SET public_code=?, tone=?, skill=?, work_mode=?, reply=?, rating_avg=?, review_count=?, orders_completed=?, verified=?, promo=?, headline=?, state=?, city=? WHERE user_id=?',
                [$w[0], $w[4], $w[6], $w[9], $w[13], $w[10], $w[11], $w[12], $w[14], $w[15], $w[5], $w[8], $w[7], $id]
            );
            if ($w[14]) {
                Db::run(
                    'INSERT INTO verifications (user_id, status, amount_kobo, created_at, updated_at, expires_at)
                     SELECT ?, \'approved\', 500000, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM verifications WHERE user_id=?)',
                    [$id, now_iso(), now_iso(), '2028-09-21 00:00:00', $id]
                );
            }
            $packages = [['name' => 'Starter', 'price_naira' => $w[16], 'price_kobo' => $w[16] * 100, 'days' => 5, 'revisions' => 1]];
            if ($w[0] === 'w01') {
                $packages = [
                    ['name' => 'Starter', 'price_naira' => 45000, 'price_kobo' => 4500000, 'days' => 5, 'revisions' => 1],
                    ['name' => 'Standard', 'price_naira' => 85000, 'price_kobo' => 8500000, 'days' => 10, 'revisions' => 2],
                    ['name' => 'Premium', 'price_naira' => 150000, 'price_kobo' => 15000000, 'days' => 20, 'revisions' => 4],
                ];
            }
            Db::run(
                'INSERT INTO services (worker_id, title, description, price_kobo, status, created_at, updated_at, category_id, packages_json, public_code, work_mode)
                 VALUES (?,?,?,?,\'live\',?,?,?,?,?,?)',
                [
                    $id, $svcTitle[$w[0]], $w[5], $w[16] * 100, now_iso(), now_iso(),
                    $catId[$w[6]] ?? null,
                    json_encode($packages),
                    's-' . $w[0],
                    $w[9],
                ]
            );
            if ($w[0] === 'w01') {
                $care = [
                    ['name' => 'Fix package', 'price_naira' => 15000, 'price_kobo' => 1500000, 'days' => 2, 'revisions' => 1],
                    ['name' => 'Monthly care', 'price_naira' => 30000, 'price_kobo' => 3000000, 'days' => 30, 'revisions' => 3],
                ];
                Db::run(
                    'INSERT INTO services (worker_id, title, description, price_kobo, status, created_at, updated_at, category_id, packages_json, public_code, work_mode)
                     VALUES (?,?,?,?,\'live\',?,?,?,?,?,?)',
                    [$id, 'WordPress care & fixes', 'Fixes, updates and monthly care for WordPress sites.', 1500000, now_iso(), now_iso(), $catId['Web Development'] ?? null, json_encode($care), 's-w01-care', 'remote']
                );
            }
        }

        $clients = [
            ['2348052223344', 'adaeze@boutique.ng', 'Adaeze Boutique', 'Lagos', 'Lekki', 4.8, 1],
            ['2348011110001', 'hello@naijafoods.ng', 'Naija Foods Ltd', 'Lagos', 'Ikeja', 4.9, 1],
            ['2348011110002', 'hello@zarawears.ng', 'Zara Wears', 'Lagos', 'Surulere', 4.6, 0],
            ['2348011110003', 'm.okonkwo@mail.ng', 'M. Okonkwo', 'Rivers', 'Port Harcourt', 5.0, 1],
            ['2348011110004', 'hi@brewandco.ng', 'Brew & Co', 'Lagos', 'Yaba', 4.7, 1],
            ['2348011110005', 't.balogun@mail.ng', 'T. Balogun', 'Lagos', 'Ikoyi', 4.5, 0],
        ];
        foreach ($clients as $c) {
            if (User::findByPhone($c[0])) {
                continue;
            }
            $id = User::create([
                'phone' => $c[0], 'email' => $c[1], 'password_hash' => $hash,
                'full_name' => $c[2], 'roles' => 'client', 'state' => $c[3], 'city' => $c[4],
            ]);
            Db::run('UPDATE profiles SET rating_avg=?, verified=?, tone=? WHERE user_id=?', [$c[5], $c[6], 'a2', $id]);
        }

        $uid = function (string $phone): int {
            return (int) User::findByPhone($phone)['id'];
        };

        $jobs = [
            ['j01', '2348052223344', 'Bathroom tiling — 2 rooms, Lekki Phase 1', 'Tiling & Masonry', 'on-site', 'Lekki, Lagos', 30000000, 'fixed', '2026-09-19 10:00:00', 'Oct 10, 2026',
                '2 bathrooms (floor + walls), grouting, clean-up. Cement & tiles supplied by client.',
                "We're finishing a 4-bedroom duplex and need an experienced tiler for the two bathrooms — roughly 80m² of wall and 24m² of floor. We have a reference photo set for the layout you should follow. Work needs to start within 5 days of award. Please show photos of similar bathroom work in your proposal."],
            ['j02', '2348011110001', 'Landing page for a restaurant (Figma to web)', 'Web Development', 'remote', 'Remote', 8500000, 'fixed', '2026-09-21 12:00:00', 'Oct 2, 2026',
                'Figma file ready. Mobile-first landing page, contact form, menu section.',
                'Naija Foods needs a fast landing page from an existing Figma. Mobile-first, contact form, and a menu section. Hosting is on us.'],
            ['j03', '2348011110002', 'Bookkeeping & monthly reports for a fashion brand', 'Accounting & Bookkeeping', 'remote', 'Remote', 6000000, 'fixed', '2026-09-20 09:00:00', null,
                'Monthly close, inventory, and a one-page report.',
                'We sell ready-to-wear online and need someone to close the books each month and send a one-page report.'],
            ['j04', '2348011110003', 'Full electrical wiring — 4-bed bungalow, GRA Port Harcourt', 'Electrical', 'on-site', 'GRA, Port Harcourt', 45000000, 'negotiable', '2026-09-18 08:00:00', 'Oct 25, 2026',
                'New 4-bed bungalow, full wiring including DB and earthing.',
                'House is at first-fix stage. Need a licensed electrician for full wiring, DB, earthing and sockets. Quote with a materials list.'],
            ['j05', '2348011110004', 'Logo + brand kit for a specialty cafe', 'Graphic Design', 'remote', 'Remote', 4500000, 'fixed', '2026-09-21 09:00:00', 'Sep 30, 2026',
                'Logo, colour, type, and a one-page brand guide.',
                'Brew & Co is opening in Yaba. We need a logo, colour, type, and a one-page brand guide we can hand to a printer.'],
            ['j06', '2348011110005', 'Install 10kVA generator + inverter, Ikoyi', 'Generator & Power', 'on-site', 'Ikoyi, Lagos', 18000000, 'fixed', '2026-09-15 11:00:00', 'Oct 5, 2026',
                'Supply excluded. Install, changeover, and test.',
                'Generator and inverter already purchased. Need install, changeover, and a test run the same day.'],
        ];
        foreach ($jobs as $j) {
            Db::run(
                'INSERT INTO jobs (code, client_id, category_id, title, description, budget_kobo, work_mode, location, deadline, status, created_at, updated_at, budget_type, scope)
                 VALUES (?,?,?,?,?,?,?,?,?,\'open\',?,?,?,?)',
                [$j[0], $uid($j[1]), $catId[$j[3]] ?? null, $j[2], $j[11], $j[6], $j[4], $j[5], $j[9], $j[8], $j[8], $j[7], $j[10]]
            );
        }

        $jobId = [];
        foreach (Db::fetchAll('SELECT id, code FROM jobs') as $r) {
            $jobId[$r['code']] = (int) $r['id'];
        }

        $props = [
            ['j01', '2348010001028', 'I can start Monday. Photos of two Lekki bathrooms attached — similar wall+floor porcelain. ₦280k covers labour, grout, and clean-up. Tiles already on site.', 28000000, 'sent', 0],
            ['j01', '2348075550005', 'Available next week. I usually pair with a tiler; happy to quote labour only.', 26500000, 'sent', 0],
            ['j01', '2348100000010', 'Painter here — I can grout and finish after your tiler if you split the job.', 9000000, 'sent', 0],
            ['j02', '2348031112233', 'I build Figma-to-web landing pages every month. 10 days, two revision rounds, escrow.', 8500000, 'sent', 1],
            ['j05', '2348094440004', 'Brand kits are my main work. 7 days, logo + colour + type + one-pager.', 4000000, 'sent', 0],
            ['j04', '2348075550005', 'Licensed. Materials list on request. 30 days including inspection.', 42000000, 'sent', 0],
            ['j06', '2348080000008', 'Same-day install in Ikoyi is fine. ₦175k labour, you supply the set.', 17500000, 'sent', 0],
        ];
        foreach ($props as $p) {
            Db::run(
                'INSERT OR IGNORE INTO proposals (job_id, worker_id, cover_note, bid_kobo, status, shortlisted, created_at) VALUES (?,?,?,?,?,?,?)',
                [$jobId[$p[0]], $uid($p[1]), $p[2], $p[3], $p[4], $p[5], now_iso()]
            );
        }

        // Completed order + reviews on Chinedu so the profile has real review rows.
        $chinedu = $uid('2348031112233');
        $adaeze = $uid('2348052223344');
        Db::run(
            'INSERT INTO orders (code, job_id, client_id, worker_id, amount_kobo, fee_kobo, status, created_at, updated_at)
             VALUES (\'OR-1024\', ?, ?, ?, 8500000, 850000, \'released\', ?, ?)',
            [$jobId['j02'] ?? null, $uid('2348011110001'), $chinedu, '2026-09-08 10:00:00', '2026-09-14 10:00:00']
        );
        $orderId = Db::lastInsertId();
        foreach ([
            [$uid('2348011110001'), 5, '2026-09-14 12:00:00', '["Delivered on time","Great communication"]',
                'Chinedu rebuilt our landing page in 8 days — two of the included revisions. The page loads fast and our enquiries went up. Paid through Skilvi escrow, zero stress.',
                'Thank you! It was a pleasure working with you — reach out any time you need the site updated.'],
            [$uid('2348011110003'), 5, '2026-08-30 12:00:00', '["Professional","Clean work"]',
                'Very organised. He shared a file list of everything that was delivered and explained how to manage it ourselves. Worth every naira.',
                'Thank you!'],
            [$uid('2348011110002'), 4, '2026-08-18 12:00:00', '["Good value"]',
                'Great work overall. One revision round took a bit longer than expected but the final result is exactly what we asked for.',
                null],
        ] as $rv) {
            Db::run(
                'INSERT INTO reviews (order_id, from_user, to_user, rating, comment, created_at, tags, reply) VALUES (?,?,?,?,?,?,?,?)',
                [$orderId, $rv[0], $chinedu, $rv[1], $rv[4], $rv[2], $rv[3], $rv[5]]
            );
        }

        Db::run(
            'INSERT OR IGNORE INTO saved_workers (user_id, worker_id, note, created_at) VALUES (?,?,?,?)',
            [$adaeze, $chinedu, 'Built our landing page last month — ask him about the blog', now_iso()]
        );
        Db::run(
            'INSERT OR IGNORE INTO saved_workers (user_id, worker_id, note, created_at) VALUES (?,?,?,?)',
            [$adaeze, $uid('2348052220002'), 'Shortlisted for the app redesign in August', now_iso()]
        );
        Db::run(
            'INSERT OR IGNORE INTO saved_workers (user_id, worker_id, note, created_at) VALUES (?,?,?,?)',
            [$adaeze, $uid('2348080000008'), 'Quoted the Ikoyi inverter job — compare if we need AC service', now_iso()]
        );
    }

    /** Extra dashboard orders for Adaeze — skipped if already present. */
    private static function demoOrders(): void
    {
        if (Db::fetch("SELECT id FROM orders WHERE code = 'OR-1042'")) {
            Db::run("UPDATE orders SET title = COALESCE(NULLIF(title,''), 'Landing page for a restaurant (Figma to web)') WHERE code = 'OR-1024'");
            return;
        }
        $uid = static function (string $phone): int {
            $u = User::findByPhone($phone);
            if ($u === null) {
                return 0;
            }
            return (int) $u['id'];
        };
        $adaeze = $uid('2348052223344');
        $chinedu = $uid('2348031112233');
        if (!$adaeze || !$chinedu) {
            return;
        }
        $now = now_iso();
        $rows = [
            ['OR-1042', 'Landing page — Naija Foods', $adaeze, $chinedu, 8500000, 850000, 'completion_submitted', '2026-09-14 10:22:00'],
            ['OR-1036', 'Logo design — Adaeze Boutique', $adaeze, $uid('2348094440004'), 4500000, 450000, 'in_progress', '2026-09-11 09:00:00'],
            ['OR-1028', 'Bathroom tiling — Lekki', $adaeze, $uid('2348010001028'), 30000000, 3000000, 'released', '2026-09-02 09:00:00'],
            ['OR-1019', 'Wiring — 4-bed bungalow, GRA', $adaeze, $uid('2348075550005'), 45000000, 4500000, 'funded', '2026-08-28 09:00:00'],
        ];
        foreach ($rows as $r) {
            if (!$r[3]) {
                continue;
            }
            Db::run(
                'INSERT OR IGNORE INTO orders (code, job_id, client_id, worker_id, amount_kobo, fee_kobo, status, created_at, updated_at, title)
                 VALUES (?,NULL,?,?,?,?,?,?,?,?)',
                [$r[0], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $now, $r[1]]
            );
        }
        Db::run("UPDATE orders SET title = COALESCE(NULLIF(title,''), 'Landing page for a restaurant (Figma to web)') WHERE code = 'OR-1024'");
        foreach ([$chinedu, $uid('2348094440004'), $uid('2348010001028'), $uid('2348075550005')] as $wid) {
            if ($wid && !Db::fetch('SELECT user_id FROM wallets WHERE user_id=?', [$wid])) {
                Db::run('INSERT INTO wallets (user_id, available_kobo, pending_kobo, updated_at) VALUES (?,0,0,?)', [$wid, $now]);
            }
        }
    }

    private static function demoComms(): void
    {
        $o = Db::fetch("SELECT * FROM orders WHERE code = 'OR-1042'");
        if ($o === null) {
            return;
        }
        if (Db::fetch('SELECT id FROM conversations WHERE order_id = ? AND dispute_id IS NULL', [$o['id']])) {
            return;
        }
        $now = now_iso();
        Db::run(
            'INSERT INTO conversations (order_id, client_id, worker_id, created_at, last_at) VALUES (?,?,?,?,?)',
            [$o['id'], $o['client_id'], $o['worker_id'], '2026-09-14 11:38:00', '2026-09-14 11:42:00']
        );
        $cid = Db::lastInsertId();
        $msgs = [
            [$o['client_id'], 'Hi Chinedu! The staging link is live — opening it now.', '2026-09-14 11:38:00'],
            [$o['client_id'], 'Looks great! One thing: can we swap the hero image for the restaurant shot I sent earlier?', '2026-09-14 11:40:00'],
            [$o['worker_id'], "Yes — that's within your 2 revision rounds. I'll push it tonight and update the link.", '2026-09-14 11:41:00'],
            [$o['client_id'], 'Perfect. Also, do you think the menu section should be a dropdown or a separate page?', '2026-09-14 11:42:00'],
        ];
        foreach ($msgs as $m) {
            Db::run(
                'INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)',
                [$cid, $m[0], $m[1], $m[2]]
            );
        }
        if (!Db::fetch('SELECT id FROM notifications WHERE user_id = ? LIMIT 1', [$o['worker_id']])) {
            Db::run(
                'INSERT INTO notifications (user_id, kind, title, body, href, created_at) VALUES (?,?,?,?,?,?)',
                [$o['worker_id'], 'message', 'New message from Adaeze Boutique', 'Perfect. Also, do you think the menu section…', 'messages.html?id=' . $cid, $now]
            );
        }
    }

    private static function demoAdmin(): void
    {
        $tunde = User::findByPhone('2348075550005');
        $adaeze = User::findByPhone('2348052223344');
        $admin = User::findByPhone('2348000000001');
        if ($tunde && !Db::fetch("SELECT id FROM verifications WHERE user_id=? AND status='pending'", [$tunde['id']])) {
            $now = now_iso();
            Db::run(
                "INSERT INTO verifications (user_id, status, amount_kobo, notes, created_at, updated_at)
                 VALUES (?, 'pending', 500000, ?, ?, ?)",
                [$tunde['id'], json_encode(['id_type' => 'NIN + selfie', 'full_name' => $tunde['full_name']]), $now, $now]
            );
        }
        if ($adaeze && $tunde && !Db::fetch('SELECT id FROM reports LIMIT 1')) {
            $now = now_iso();
            Db::run(
                "INSERT INTO reports (public_code, reporter_id, target_type, target_id, reason, note, status, created_at)
                 VALUES ('RPT-881', ?, 'user', ?, 'Scam / fraud', 'Asked to pay off Skilvi over WhatsApp', 'open', ?)",
                [$adaeze['id'], $tunde['id'], $now]
            );
        }
        if ($adaeze && !Db::fetch('SELECT id FROM support_tickets LIMIT 1')) {
            $now = now_iso();
            Db::run(
                "INSERT INTO support_tickets (user_id, subject, body, status, code, created_at, last_at)
                 VALUES (?, 'When my worker delivers on-site, how do I confirm?', 'Can my brother confirm on my behalf if I am travelling?', 'open', 'T-1001', ?, ?)",
                [$adaeze['id'], $now, $now]
            );
            $tid = Db::lastInsertId();
            Db::run(
                'INSERT INTO ticket_messages (ticket_id, author_id, body, from_admin, created_at) VALUES (?,?,?,0,?)',
                [$tid, $adaeze['id'], 'When my worker delivers tiling on-site, how do I confirm completion?', $now]
            );
        }
        if (!Db::fetch('SELECT id FROM payments LIMIT 1')) {
            $i = 1;
            foreach (Db::fetchAll("SELECT * FROM orders WHERE status != 'pending_payment'") as $o) {
                $code = 'PAY-' . (5500 + $i);
                $method = $i % 3 === 0 ? 'ussd' : ($i % 2 === 0 ? 'card' : 'bank_transfer');
                Db::run(
                    "INSERT INTO payments (code, user_id, order_id, purpose, provider, provider_ref, amount_kobo, method, status, created_at, updated_at)
                     VALUES (?,?,?,'escrow','paystack',?,?,?,'succeeded',?,?)",
                    [$code, $o['client_id'], $o['id'], 'ref_' . $o['code'], $o['amount_kobo'], $method, $o['created_at'], $o['created_at']]
                );
                $i++;
            }
        }
        if ($admin && !Db::fetch('SELECT id FROM admin_audit LIMIT 1')) {
            Db::run(
                'INSERT INTO admin_audit (admin_id, action, target, meta, created_at, ip) VALUES (?,?,?,?,?,?)',
                [$admin['id'], 'seed', 'platform', json_encode(['note' => 'Dev seed']), now_iso(), '127.0.0.1']
            );
        }
    }
}
