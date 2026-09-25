<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;
use App\Models\User;

final class AdminService
{
    public static function dashboard(): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $gmv = (int) (Db::fetch(
            "SELECT COALESCE(SUM(amount_kobo),0) s FROM orders WHERE status = 'released' AND COALESCE(released_at, updated_at) >= ?",
            [$since]
        )['s'] ?? 0);
        $created = (int) (Db::fetch("SELECT COUNT(*) c FROM orders WHERE created_at >= ?", [$since])['c'] ?? 0);
        $done = (int) (Db::fetch("SELECT COUNT(*) c FROM orders WHERE status='released' AND COALESCE(released_at, updated_at) >= ?", [$since])['c'] ?? 0);
        $openD = (int) (Db::fetch("SELECT COUNT(*) c FROM disputes WHERE status='open'")['c'] ?? 0);
        $wd = Db::fetch("SELECT COUNT(*) c, COALESCE(SUM(amount_kobo),0) s FROM withdrawals WHERE status IN ('pending','approved')");
        $openR = (int) (Db::fetch("SELECT COUNT(*) c FROM reports WHERE status='open'")['c'] ?? 0);
        $signups = (int) (Db::fetch("SELECT COUNT(*) c FROM users WHERE created_at >= ?", [gmdate('Y-m-d 00:00:00')])['c'] ?? 0);

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $row = Db::fetch(
                "SELECT COALESCE(SUM(amount_kobo),0) s FROM orders
                 WHERE status='released' AND substr(COALESCE(released_at, updated_at),1,10)=?",
                [$d]
            );
            $days[] = [
                'day'   => date('D', strtotime($d . ' UTC') ?: time()),
                'date'  => $d,
                'kobo'  => (int) ($row['s'] ?? 0),
                'label' => ngn_fmt((int) ($row['s'] ?? 0)),
            ];
        }

        return [
            'kpis' => [
                ['label' => 'GMV (7d)', 'value' => self::compactNaira($gmv), 'sub' => $created . ' orders created'],
                ['label' => 'Orders created', 'value' => (string) $created, 'sub' => 'last 7 days'],
                ['label' => 'Orders completed', 'value' => (string) $done, 'sub' => 'released in 7 days'],
                ['label' => 'Open disputes', 'value' => (string) $openD, 'sub' => $openD ? 'escrow frozen' : 'none open'],
                ['label' => 'Pending withdrawals', 'value' => ngn_fmt((int) ($wd['s'] ?? 0)), 'sub' => (int) ($wd['c'] ?? 0) . ' in review queue'],
                ['label' => 'Open reports', 'value' => (string) $openR, 'sub' => $signups . ' signups today'],
            ],
            'gmv'          => $days,
            'disputes'     => array_slice(DisputeService::adminList(), 0, 5),
            'withdrawals'  => array_values(array_filter(
                WalletService::adminList(),
                static fn ($w) => in_array($w['status'], ['pending', 'approved'], true)
            )),
            'signups'      => self::recentUsers(8),
            'needs'        => self::needs($openD, (int) ($wd['c'] ?? 0), $openR),
        ];
    }

    public static function users(string $q, string $role, string $status): array
    {
        $where = ['1=1'];
        $bind = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $digits = preg_replace('/\D/', '', $q) ?? '';
            if (strlen($digits) >= 4) {
                $where[] = '(u.full_name LIKE ? OR u.phone LIKE ? OR IFNULL(u.email,\'\') LIKE ?)';
                array_push($bind, $like, '%' . $digits . '%', $like);
            } else {
                $where[] = '(u.full_name LIKE ? OR IFNULL(u.email,\'\') LIKE ?)';
                array_push($bind, $like, $like);
            }
        }
        if ($role === 'worker' || $role === 'client' || $role === 'admin') {
            $where[] = "u.roles LIKE ?";
            $bind[] = '%' . $role . '%';
        }
        if (in_array($status, ['active', 'suspended', 'banned'], true)) {
            $where[] = 'u.status = ?';
            $bind[] = $status;
        }
        $sql = implode(' AND ', $where);
        $rows = Db::fetchAll(
            "SELECT u.*, p.tone, p.verified, p.orders_completed, p.skill, p.city, p.state
             FROM users u LEFT JOIN profiles p ON p.user_id = u.id
             WHERE $sql
             ORDER BY u.id DESC LIMIT 200",
            $bind
        );
        $out = [];
        foreach ($rows as $r) {
            $flags = (int) (Db::fetch(
                "SELECT COUNT(*) c FROM reports WHERE target_type='user' AND target_id=? AND status='open'",
                [$r['id']]
            )['c'] ?? 0);
            $roles = User::roles($r);
            $roleLabel = in_array('admin', $roles, true) ? 'Admin'
                : ((in_array('worker', $roles, true) && in_array('client', $roles, true)) ? 'Both'
                : (in_array('worker', $roles, true) ? 'Worker' : 'Client'));
            $out[] = [
                'id'         => (int) $r['id'],
                'name'       => $r['full_name'],
                'initials'   => initials($r['full_name']),
                'tone'       => $r['tone'] ?: 'a1',
                'phone'      => format_phone($r['phone']),
                'email'      => $r['email'],
                'role'       => $roleLabel,
                'roles'      => $roles,
                'orders'     => (int) ($r['orders_completed'] ?? 0),
                'skill'      => $r['skill'] ?? '',
                'city'       => $r['city'] ?? '',
                'joined'     => date('M Y', strtotime($r['created_at']) ?: time()),
                'status'     => $r['status'],
                'stateLabel' => ucfirst($r['status']),
                'chip'       => $r['status'] === 'active' ? 'st-green' : ($r['status'] === 'banned' ? 'st-red' : 'st-amber'),
                'verified'   => (int) ($r['verified'] ?? 0) === 1,
                'flags'      => $flags,
            ];
        }
        return $out;
    }

    public static function userGet(string $key): array
    {
        $u = Db::fetch('SELECT * FROM users WHERE id = ?', [$key]);
        if ($u === null) {
            throw new AppError('not_found', 'User not found.', 404);
        }
        unset($u['password_hash']);
        $id = (int) $u['id'];
        $p = Db::fetch('SELECT * FROM profiles WHERE user_id = ?', [$id]) ?: [];
        $w = Db::fetch('SELECT available_kobo, pending_kobo, updated_at FROM wallets WHERE user_id = ?', [$id]) ?: [];
        $phoneRaw = (string) ($u['phone'] ?? '');
        $phone = ($phoneRaw === '' || str_starts_with($phoneRaw, 'e:')) ? '' : format_phone($phoneRaw);
        $roles = User::roles($u);
        $roleLabel = in_array('admin', $roles, true) ? 'Admin'
            : ((in_array('worker', $roles, true) && in_array('client', $roles, true)) ? 'Both'
            : (in_array('worker', $roles, true) ? 'Worker' : 'Client'));
        $flags = (int) (Db::fetch(
            "SELECT COUNT(*) c FROM reports WHERE target_type='user' AND target_id=? AND status='open'",
            [$id]
        )['c'] ?? 0);
        $v = Db::fetch('SELECT status, amount_kobo, created_at, expires_at FROM verifications WHERE user_id=? ORDER BY id DESC LIMIT 1', [$id]);
        $promo = Db::fetch("SELECT plan, status, starts_at, ends_at, amount_kobo FROM promotions WHERE user_id=? ORDER BY id DESC LIMIT 1", [$id]);
        $fmt = static function (?string $ts): string {
            if (!$ts) {
                return '—';
            }
            $t = strtotime($ts);
            return $t ? date('j M Y, H:i', $t) : $ts;
        };
        $yn = static fn ($v) => $v ? 'Yes' : 'No';
        $ngn = static fn ($kobo) => ngn_fmt((int) $kobo);
        $sections = [
            'Account' => [
                ['User ID', (string) $id],
                ['Full name', (string) $u['full_name']],
                ['Email', (string) ($u['email'] ?: '—')],
                ['Phone', $phone !== '' ? $phone : '—'],
                ['Roles', $roleLabel . ' (' . implode(', ', $roles) . ')'],
                ['Status', ucfirst((string) $u['status'])],
                ['Email verified', $fmt($u['email_verified_at'] ?? null)],
                ['Phone verified', $fmt($u['phone_verified_at'] ?? null)],
                ['Last login', $fmt($u['last_login_at'] ?? null)],
                ['Joined', $fmt($u['created_at'] ?? null)],
                ['Updated', $fmt($u['updated_at'] ?? null)],
            ],
            'Profile' => [
                ['Headline', (string) (($p['headline'] ?? '') !== '' ? $p['headline'] : '—')],
                ['Bio', (string) (($p['bio'] ?? '') !== '' ? $p['bio'] : '—')],
                ['Skill', (string) (($p['skill'] ?? '') !== '' ? $p['skill'] : '—')],
                ['Work mode', (string) (($p['work_mode'] ?? '') !== '' ? $p['work_mode'] : '—')],
                ['City', (string) (($p['city'] ?? '') !== '' ? $p['city'] : '—')],
                ['State', (string) (($p['state'] ?? '') !== '' ? $p['state'] : '—')],
                ['Public code', (string) (($p['public_code'] ?? '') !== '' ? $p['public_code'] : '—')],
                ['Verified identity', $yn(!empty($p['verified']))],
                ['Promoted', $yn(!empty($p['promo']))],
                ['Rating', isset($p['rating_avg']) ? (string) $p['rating_avg'] : '—'],
                ['Reviews', (string) (int) ($p['review_count'] ?? 0)],
                ['Orders completed', (string) (int) ($p['orders_completed'] ?? 0)],
                ['Typical reply', (string) (($p['reply'] ?? '') !== '' ? $p['reply'] : '—')],
                ['Notify SMS', $yn(!empty($p['notify_sms']))],
                ['Notify jobs', $yn(!empty($p['notify_jobs']))],
                ['Notify marketing', $yn(!empty($p['notify_marketing']))],
            ],
            'Money' => [
                ['Wallet available', $ngn((int) ($w['available_kobo'] ?? 0))],
                ['Wallet pending', $ngn((int) ($w['pending_kobo'] ?? 0))],
                ['Wallet updated', $fmt($w['updated_at'] ?? null)],
            ],
            'Activity' => [
                ['Orders as client', (string) (int) (Db::fetch('SELECT COUNT(*) c FROM orders WHERE client_id=?', [$id])['c'] ?? 0)],
                ['Orders as worker', (string) (int) (Db::fetch('SELECT COUNT(*) c FROM orders WHERE worker_id=?', [$id])['c'] ?? 0)],
                ['Jobs posted', (string) (int) (Db::fetch('SELECT COUNT(*) c FROM jobs WHERE client_id=?', [$id])['c'] ?? 0)],
                ['Services', (string) (int) (Db::fetch('SELECT COUNT(*) c FROM services WHERE worker_id=?', [$id])['c'] ?? 0)],
                ['Open reports', (string) $flags],
                ['Verification', $v ? (ucfirst((string) $v['status']) . ' · ' . $ngn((int) $v['amount_kobo']) . ' · ' . $fmt($v['created_at'] ?? null)) : 'None'],
                ['Latest promotion', $promo ? (ucfirst((string) $promo['status']) . ' · ' . (string) $promo['plan'] . ' · ' . $ngn((int) $promo['amount_kobo'])) : 'None'],
            ],
        ];
        $details = [];
        foreach ($sections as $title => $rows) {
            $details[] = ['section' => $title, 'rows' => array_map(static fn ($r) => ['label' => $r[0], 'value' => $r[1]], $rows)];
        }
        $join = (in_array('worker', $roles, true) && in_array('client', $roles, true))
            ? 'both'
            : (in_array('worker', $roles, true) ? 'worker' : 'client');
        $jobs = array_map(static fn ($j) => [
            'id'           => (int) $j['id'],
            'code'         => $j['code'],
            'title'        => $j['title'],
            'status'       => $j['status'],
            'budget_label' => $j['budget_kobo'] ? ngn_fmt((int) $j['budget_kobo']) : '—',
            'date'         => date('j M Y', strtotime($j['created_at']) ?: time()),
            'can_close'    => $j['status'] === 'open',
            'can_reopen'   => $j['status'] === 'closed',
        ], Db::fetchAll('SELECT id, code, title, status, budget_kobo, created_at FROM jobs WHERE client_id=? ORDER BY id DESC LIMIT 50', [$id]));
        $services = array_map(static fn ($s) => [
            'id'          => (int) $s['id'],
            'code'        => $s['public_code'] ?: ('s-' . $s['id']),
            'title'       => $s['title'],
            'status'      => $s['status'],
            'price_label' => $s['price_kobo'] ? ngn_fmt((int) $s['price_kobo']) : '—',
            'can_pause'   => $s['status'] === 'live',
            'can_live'    => $s['status'] !== 'live',
        ], Db::fetchAll('SELECT id, public_code, title, status, price_kobo FROM services WHERE worker_id=? ORDER BY id DESC LIMIT 50', [$id]));
        $orders = array_map(function ($o) use ($id) {
            return [
                'id'           => $o['code'],
                'title'        => $o['title'] ?: $o['code'],
                'status'       => $o['status'],
                'amount_label' => ngn_fmt((int) $o['amount_kobo']),
                'date'         => date('j M Y', strtotime($o['created_at']) ?: time()),
                'side'         => (int) $o['client_id'] === $id ? 'Client' : 'Worker',
            ];
        }, Db::fetchAll(
            'SELECT code, title, status, amount_kobo, created_at, client_id, worker_id FROM orders WHERE client_id=? OR worker_id=? ORDER BY id DESC LIMIT 50',
            [$id, $id]
        ));
        $skillOpts = array_column(Db::fetchAll(
            "SELECT name FROM categories WHERE (parent_id IS NOT NULL OR kind='skill') ORDER BY name"
        ), 'name');
        return [
            'id'         => $id,
            'name'       => $u['full_name'],
            'email'      => $u['email'],
            'phone'      => $phone,
            'initials'   => initials((string) $u['full_name']),
            'tone'       => ($p['tone'] ?? '') !== '' ? $p['tone'] : 'a1',
            'role'       => $roleLabel,
            'is_admin'   => in_array('admin', $roles, true),
            'status'     => $u['status'],
            'stateLabel' => ucfirst((string) $u['status']),
            'chip'       => $u['status'] === 'active' ? 'st-green' : ($u['status'] === 'banned' ? 'st-red' : 'st-amber'),
            'details'    => $details,
            'form'       => [
                'full_name' => (string) $u['full_name'],
                'email'     => (string) ($u['email'] ?? ''),
                'phone'     => $phone,
                'join_as'   => $join,
                'status'    => (string) $u['status'],
                'headline'  => (string) ($p['headline'] ?? ''),
                'bio'       => (string) ($p['bio'] ?? ''),
                'state'      => (string) ($p['state'] ?? ''),
                'city'       => (string) ($p['city'] ?? ''),
                'country'    => (string) ($p['country'] ?? ''),
                'country_code' => (string) ($p['country_code'] ?? ''),
                'dob'        => (string) ($p['dob'] ?? ''),
                'gender'     => (string) ($p['gender'] ?? ''),
                'skill'     => (string) ($p['skill'] ?? ''),
                'work_mode' => (string) ($p['work_mode'] ?? ''),
                'verified'  => !empty($p['verified']),
                'promo'     => !empty($p['promo']),
            ],
            'wallet'     => [
                'available' => $ngn((int) ($w['available_kobo'] ?? 0)),
                'pending'   => $ngn((int) ($w['pending_kobo'] ?? 0)),
            ],
            'jobs'       => $jobs,
            'services'   => $services,
            'orders'     => $orders,
            'skill_opts' => $skillOpts,
        ];
    }

    public static function userUpdate(int $adminId, string $key, array $in, string $ip): array
    {
        $u = Db::fetch('SELECT * FROM users WHERE id = ?', [$key]);
        if ($u === null) {
            throw new AppError('not_found', 'User not found.', 404);
        }
        $id = (int) $u['id'];
        $now = now_iso();
        $changed = [];

        $name = trim((string) ($in['full_name'] ?? ''));
        if ($name !== '' && $name !== (string) $u['full_name']) {
            if (mb_strlen($name) < 2) {
                throw new AppError('invalid', 'Enter a full name.', 422, ['full_name' => 'Enter a full name.']);
            }
            User::updateName($id, $name);
            $changed[] = 'name';
        }
        if (array_key_exists('email', $in)) {
            AuthService::updateEmail($id, (string) $in['email']);
            $changed[] = 'email';
        }
        if (array_key_exists('phone', $in)) {
            AuthService::updatePhone($id, (string) $in['phone']);
            $changed[] = 'phone';
        }
        $join = (string) ($in['join_as'] ?? '');
        if ($join !== '') {
            $base = match ($join) {
                'worker' => 'worker',
                'client' => 'client',
                'both'   => 'client,worker',
                default  => '',
            };
            if ($base === '') {
                throw new AppError('invalid', 'Role must be worker, client, or both.', 422, ['join_as' => 'Pick worker, client, or both.']);
            }
            if (str_contains((string) $u['roles'], 'admin')) {
                $base .= ',admin';
            }
            Db::run('UPDATE users SET roles=?, updated_at=? WHERE id=?', [$base, $now, $id]);
            $changed[] = 'roles';
        }
        $st = strtolower((string) ($in['status'] ?? ''));
        if ($st !== '' && $st !== (string) $u['status']) {
            if ($id === $adminId) {
                throw new AppError('forbidden', 'You cannot change your own status from here.', 403);
            }
            if (!in_array($st, ['active', 'suspended', 'banned'], true)) {
                throw new AppError('invalid', 'Unknown status.', 422);
            }
            if ($st !== 'active' && mb_strlen(trim((string) ($in['reason'] ?? ''))) < 8) {
                throw new AppError('invalid', 'Write a reason the user will see.', 422, ['reason' => 'Write a reason.']);
            }
            User::setStatus($id, $st);
            $changed[] = 'status';
        }
        $prof = [];
        foreach (['headline', 'bio', 'state', 'city', 'country', 'country_code', 'skill', 'work_mode'] as $k) {
            if (array_key_exists($k, $in)) {
                $val = trim((string) $in[$k]);
                $prof[$k] = $val === '' ? null : $val;
            }
        }
        if ($prof) {
            Profile::update($id, $prof);
            $changed[] = 'profile';
        }
        if (array_key_exists('verified', $in)) {
            $want = !empty($in['verified']);
            Db::run('UPDATE profiles SET verified=?, updated_at=? WHERE user_id=?', [$want ? 1 : 0, $now, $id]);
            $row = Db::fetch('SELECT id FROM verifications WHERE user_id=? ORDER BY id DESC LIMIT 1', [$id]);
            if ($want) {
                $exp = gmdate('Y-m-d H:i:s', time() + 2 * 365 * 86400);
                if ($row) {
                    Db::run(
                        "UPDATE verifications SET status='approved', reviewed_by=?, expires_at=?, updated_at=? WHERE id=?",
                        [$adminId, $exp, $now, $row['id']]
                    );
                } else {
                    Db::run(
                        "INSERT INTO verifications (user_id, status, amount_kobo, reviewed_by, notes, expires_at, created_at, updated_at)
                         VALUES (?, 'approved', 0, ?, 'Staff identity grant — not a skill certificate', ?, ?, ?)",
                        [$id, $adminId, $exp, $now, $now]
                    );
                }
            } elseif ($row) {
                Db::run(
                    "UPDATE verifications SET status='rejected', reviewed_by=?, notes=?, updated_at=? WHERE id=?",
                    [$adminId, 'Staff removed identity badge', $now, $row['id']]
                );
            }
            $changed[] = 'verified';
        }
        if (array_key_exists('promo', $in)) {
            $want = !empty($in['promo']);
            if ($want) {
                Db::run('UPDATE profiles SET promo=1, updated_at=? WHERE user_id=?', [$now, $id]);
                $active = Db::fetch(
                    "SELECT id FROM promotions WHERE user_id=? AND status='active' AND (ends_at IS NULL OR ends_at > ?) LIMIT 1",
                    [$id, $now]
                );
                if (!$active) {
                    $end = gmdate('Y-m-d H:i:s', time() + 365 * 86400);
                    Db::run(
                        "INSERT INTO promotions (user_id, plan, amount_kobo, status, starts_at, ends_at, created_at)
                         VALUES (?, 'staff', 0, 'active', ?, ?, ?)",
                        [$id, $now, $end, $now]
                    );
                }
            } else {
                Db::run("UPDATE promotions SET status='paused' WHERE user_id=? AND status='active'", [$id]);
                Db::run('UPDATE profiles SET promo=0, updated_at=? WHERE user_id=?', [$now, $id]);
            }
            $changed[] = 'promo';
        }
        self::audit($adminId, 'user.update', 'user:' . $id, [
            'changed' => $changed,
            'reason'  => (string) ($in['reason'] ?? ''),
        ], $ip);
        return self::userGet((string) $id);
    }

    public static function jobAction(int $adminId, string $key, string $action, string $ip): array
    {
        $row = Db::fetch('SELECT * FROM jobs WHERE code = ? OR id = ?', [$key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Job not found.', 404);
        }
        $action = strtolower($action);
        $now = now_iso();
        if ($action === 'close') {
            Db::run("UPDATE jobs SET status='closed', updated_at=? WHERE id=?", [$now, $row['id']]);
        } elseif ($action === 'reopen') {
            Db::run("UPDATE jobs SET status='open', updated_at=? WHERE id=?", [$now, $row['id']]);
        } else {
            throw new AppError('invalid', 'Close or reopen.', 422);
        }
        self::audit($adminId, 'job.' . $action, 'job:' . $row['code'], ['client_id' => $row['client_id']], $ip);
        return self::userGet((string) $row['client_id']);
    }

    public static function serviceAction(int $adminId, string $key, string $action, string $ip): array
    {
        $row = Db::fetch('SELECT * FROM services WHERE id = ? OR public_code = ?', [$key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Service not found.', 404);
        }
        $action = strtolower($action);
        $now = now_iso();
        if ($action === 'pause') {
            Db::run("UPDATE services SET status='paused', updated_at=? WHERE id=?", [$now, $row['id']]);
        } elseif ($action === 'live') {
            Db::run("UPDATE services SET status='live', updated_at=? WHERE id=?", [$now, $row['id']]);
        } else {
            throw new AppError('invalid', 'Pause or live.', 422);
        }
        self::audit($adminId, 'service.' . $action, 'service:' . $row['id'], ['worker_id' => $row['worker_id']], $ip);
        return self::userGet((string) $row['worker_id']);
    }

    public static function userAction(int $adminId, string $key, string $action, string $reason, string $ip): array
    {
        $u = Db::fetch('SELECT * FROM users WHERE id = ?', [$key]);
        if ($u === null) {
            throw new AppError('not_found', 'User not found.', 404);
        }
        if ((int) $u['id'] === $adminId) {
            throw new AppError('forbidden', 'You cannot change your own account from here.', 403);
        }
        $action = strtolower($action);
        $now = now_iso();
        if ($action === 'suspend') {
            if (mb_strlen(trim($reason)) < 8) {
                throw new AppError('invalid', 'Write a reason the user will see.', 422);
            }
            User::setStatus((int) $u['id'], 'suspended');
            NotificationService::push((int) $u['id'], 'order', 'Account suspended', $reason, 'account-settings.html');
        } elseif ($action === 'activate' || $action === 'unsuspend') {
            User::setStatus((int) $u['id'], 'active');
            NotificationService::push((int) $u['id'], 'order', 'Account reactivated', 'You can use Skilvi again.', 'index.html');
        } elseif ($action === 'ban') {
            if (mb_strlen(trim($reason)) < 8) {
                throw new AppError('invalid', 'Write a reason.', 422);
            }
            User::setStatus((int) $u['id'], 'banned');
            Db::run("UPDATE promotions SET status='paused' WHERE user_id=? AND status='active'", [$u['id']]);
            NotificationService::push((int) $u['id'], 'order', 'Account banned', $reason, 'help.html');
        } elseif ($action === 'grant_admin') {
            if (!str_contains((string) $u['roles'], 'admin')) {
                Db::run("UPDATE users SET roles = trim(roles || ',admin', ','), updated_at=? WHERE id=?", [$now, $u['id']]);
            }
        } elseif ($action === 'revoke_admin') {
            $roles = array_values(array_filter(User::roles($u), static fn ($r) => $r !== 'admin'));
            if (!$roles) {
                $roles = ['client'];
            }
            Db::run('UPDATE users SET roles=?, updated_at=? WHERE id=?', [implode(',', $roles), $now, $u['id']]);
        } else {
            throw new AppError('invalid', 'Unknown action.', 422);
        }
        self::audit($adminId, 'user.' . $action, 'user:' . $u['id'], ['reason' => $reason, 'name' => $u['full_name']], $ip);
        return self::users('', '', '');
    }

    public static function orders(string $status, string $q): array
    {
        $where = ['1=1'];
        $bind = [];
        if ($status !== '' && $status !== 'any' && $status !== 'All states') {
            $map = [
                'paid' => 'funded',
                'in progress' => 'in_progress',
                'delivered' => 'completion_submitted',
                'completed' => 'released',
                'disputed' => 'disputed',
                'cancelled' => 'cancelled',
            ];
            $st = $map[strtolower($status)] ?? $status;
            $where[] = 'o.status = ?';
            $bind[] = $st;
        }
        if ($q !== '') {
            $where[] = '(o.code LIKE ? OR o.title LIKE ? OR cu.full_name LIKE ? OR wu.full_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bind, $like, $like, $like, $like);
        }
        $sql = implode(' AND ', $where);
        $rows = Db::fetchAll(
            "SELECT o.*, cu.full_name AS client_name, wu.full_name AS worker_name
             FROM orders o
             JOIN users cu ON cu.id = o.client_id
             JOIN users wu ON wu.id = o.worker_id
             WHERE $sql
             ORDER BY o.id DESC LIMIT 200",
            $bind
        );
        return array_map(static function ($o) {
            $ui = match ($o['status']) {
                'pending_payment' => ['key' => 'awaiting_payment', 'label' => 'Awaiting payment', 'chip' => 'st-gray'],
                'funded' => ['key' => 'paid', 'label' => 'Paid · in escrow', 'chip' => 'st-royal'],
                'in_progress' => ['key' => 'in_progress', 'label' => 'In progress', 'chip' => 'st-amber'],
                'completion_submitted' => ['key' => 'delivered', 'label' => 'Awaiting approval', 'chip' => 'st-teal'],
                'released' => ['key' => 'completed', 'label' => 'Completed', 'chip' => 'st-green'],
                'cancelled' => ['key' => 'cancelled', 'label' => 'Cancelled', 'chip' => 'st-gray'],
                'disputed' => ['key' => 'disputed', 'label' => 'In dispute', 'chip' => 'st-red'],
                default => ['key' => $o['status'], 'label' => $o['status'], 'chip' => 'st-gray'],
            };
            $held = in_array($o['status'], ['funded', 'in_progress', 'completion_submitted', 'disputed'], true);
            return [
                'id'           => $o['code'],
                'title'        => $o['title'] ?: 'Order',
                'client'       => $o['client_name'],
                'worker'       => $o['worker_name'],
                'parties'      => $o['client_name'] . ' → ' . $o['worker_name'],
                'amount_label' => ngn_fmt((int) $o['amount_kobo']),
                'amount_kobo'  => (int) $o['amount_kobo'],
                'date'         => date('M j, Y', strtotime($o['created_at']) ?: time()),
                'status'       => $o['status'],
                'stateLabel'   => $ui['label'],
                'chip'         => $ui['chip'],
                'can_release'  => $held && $o['status'] !== 'disputed',
                'can_refund'   => $held && $o['status'] !== 'disputed',
            ];
        }, $rows);
    }

    public static function orderAction(int $adminId, string $key, string $action, string $reason, string $ip): array
    {
        $row = Db::fetch('SELECT * FROM orders WHERE code = ? OR id = ?', [$key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Order not found.', 404);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new AppError('invalid', 'Write a reason (10 characters). This is audited.', 422);
        }
        $action = strtolower(str_replace(['-', ' '], '_', $action));
        if (in_array($action, ['force_release', 'release'], true)) {
            if ($row['status'] === OrderService::RELEASED) {
                throw new AppError('conflict', 'Already released.', 409);
            }
            if ($row['status'] === OrderService::DISPUTED) {
                throw new AppError('conflict', 'Use the dispute queue to resolve a frozen order.', 409);
            }
            if (!in_array($row['status'], [OrderService::FUNDED, OrderService::IN_PROGRESS, OrderService::DELIVERED], true)) {
                throw new AppError('conflict', 'That order cannot be released.', 409);
            }
            $now = now_iso();
            $workerNet = (int) $row['amount_kobo'] - (int) $row['fee_kobo'];
            Db::pdo()->beginTransaction();
            try {
                Db::run('UPDATE orders SET status=?, released_at=?, updated_at=? WHERE id=?', [OrderService::RELEASED, $now, $now, $row['id']]);
                WalletService::ensure((int) $row['worker_id']);
                Db::run(
                    'UPDATE wallets SET available_kobo = available_kobo + ?, pending_kobo = MAX(0, pending_kobo - ?), updated_at=? WHERE user_id=?',
                    [$workerNet, (int) $row['amount_kobo'], $now, $row['worker_id']]
                );
                self::ledger((int) $row['id'], 'escrow', (int) $row['client_id'], (int) $row['amount_kobo'], 0, 'Admin force-release');
                self::ledger((int) $row['id'], 'worker', (int) $row['worker_id'], 0, $workerNet, 'Admin settlement');
                self::ledger((int) $row['id'], 'platform', null, 0, (int) $row['fee_kobo'], 'Commission (admin)');
                Db::pdo()->commit();
            } catch (\Throwable $e) {
                Db::pdo()->rollBack();
                throw $e;
            }
            NotificationService::push((int) $row['worker_id'], 'order', 'Payment released', ngn_fmt($workerNet) . ' is in your wallet from ' . $row['code'] . '.', 'worker-wallet.html');
            NotificationService::push((int) $row['client_id'], 'order', $row['code'] . ' closed by Skilvi', $reason, 'order-detail.html?id=' . $row['code']);
        } elseif (in_array($action, ['force_refund', 'refund'], true)) {
            if ($row['status'] === OrderService::RELEASED) {
                throw new AppError('conflict', 'Settled orders are refunded through finance, not this button.', 409);
            }
            if ($row['status'] === OrderService::DISPUTED) {
                throw new AppError('conflict', 'Use the dispute queue to refund a frozen order.', 409);
            }
            $now = now_iso();
            Db::pdo()->beginTransaction();
            try {
                Db::run('UPDATE orders SET status=?, updated_at=? WHERE id=?', [OrderService::CANCELLED, $now, $row['id']]);
                if (in_array($row['status'], [OrderService::FUNDED, OrderService::IN_PROGRESS, OrderService::DELIVERED], true)) {
                    Db::run(
                        'UPDATE wallets SET pending_kobo = MAX(0, pending_kobo - ?), updated_at=? WHERE user_id=?',
                        [(int) $row['amount_kobo'], $now, $row['worker_id']]
                    );
                    self::ledger((int) $row['id'], 'escrow', (int) $row['client_id'], (int) $row['amount_kobo'], 0, 'Admin refund');
                }
                Db::pdo()->commit();
            } catch (\Throwable $e) {
                Db::pdo()->rollBack();
                throw $e;
            }
            NotificationService::push((int) $row['client_id'], 'order', $row['code'] . ' refunded', $reason, 'order-detail.html?id=' . $row['code']);
            NotificationService::push((int) $row['worker_id'], 'order', $row['code'] . ' cancelled', $reason, 'worker-orders.html');
        } else {
            throw new AppError('invalid', 'Use release or refund.', 422);
        }
        self::audit($adminId, 'order.' . $action, 'order:' . $row['code'], ['reason' => $reason], $ip);
        return OrderService::get($row['code'], (int) $row['client_id']);
    }

    public static function payments(string $status, string $method, string $q): array
    {
        $where = ['1=1'];
        $bind = [];
        if ($status !== '' && !str_starts_with(strtolower($status), 'all')) {
            $raw = strtolower($status);
            $st = match (true) {
                str_contains($raw, 'refund') => 'refunded',
                str_contains($raw, 'fail') => 'failed',
                str_contains($raw, 'paid') => 'succeeded',
                default => $raw,
            };
            $where[] = 'p.status = ?';
            $bind[] = $st;
        }
        if ($method !== '' && !str_starts_with(strtolower($method), 'all')) {
            $raw = strtolower($method);
            $m = match (true) {
                str_contains($raw, 'bank') || str_contains($raw, 'transfer') => 'bank_transfer',
                str_contains($raw, 'card') => 'card',
                str_contains($raw, 'ussd') => 'ussd',
                default => $raw,
            };
            $where[] = 'p.method = ?';
            $bind[] = $m;
        }
        if ($q !== '') {
            $where[] = '(p.code LIKE ? OR o.code LIKE ? OR u.full_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bind, $like, $like, $like);
        }
        $sql = implode(' AND ', $where);
        $rows = Db::fetchAll(
            "SELECT p.*, u.full_name, o.code AS order_code
             FROM payments p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE $sql
             ORDER BY p.id DESC LIMIT 200",
            $bind
        );
        return array_map(static function ($p) {
            $st = $p['status'];
            return [
                'id'           => $p['code'],
                'order'        => $p['order_code'],
                'payer'        => $p['full_name'],
                'method'       => $p['method'] ? ucfirst(str_replace('_', ' ', (string) $p['method'])) : '—',
                'amount_label' => ngn_fmt((int) $p['amount_kobo']),
                'date'         => date('M j, Y', strtotime($p['created_at']) ?: time()),
                'status'       => $st,
                'stateLabel'   => $st === 'succeeded' ? 'Paid' : ucfirst($st),
                'chip'         => $st === 'succeeded' ? 'st-green' : ($st === 'failed' ? 'st-red' : 'st-amber'),
                'purpose'      => $p['purpose'],
            ];
        }, $rows);
    }

    public static function verifications(): array
    {
        $rows = Db::fetchAll(
            "SELECT v.*, u.full_name, p.tone
             FROM verifications v
             JOIN users u ON u.id = v.user_id
             LEFT JOIN profiles p ON p.user_id = u.id
             ORDER BY CASE v.status WHEN 'pending' THEN 0 ELSE 1 END, v.id DESC"
        );
        return array_map(static function ($v) {
            $notes = json_decode((string) ($v['notes'] ?? ''), true) ?: [];
            $docs = is_array($notes) ? ($notes['id_type'] ?? 'ID') : 'ID';
            $opened = strtotime($v['created_at']) ?: time();
            $sla = 2 - (int) floor((time() - $opened) / 86400);
            return [
                'id'         => (int) $v['id'],
                'name'       => $v['full_name'],
                'initials'   => initials($v['full_name']),
                'tone'       => $v['tone'] ?: 'a1',
                'docs'       => $docs,
                'paid'       => (int) $v['amount_kobo'] > 0,
                'paid_label' => 'Paid ' . ngn_fmt((int) $v['amount_kobo']),
                'date'       => date('M j, Y', $opened),
                'status'     => $v['status'],
                'sla'        => $v['status'] === 'pending' ? (max(0, $sla) . ' days left') : ucfirst($v['status']),
                'can_review' => $v['status'] === 'pending',
            ];
        }, $rows);
    }

    public static function promotions(): array
    {
        TrustService::expirePromos();
        $rows = Db::fetchAll(
            "SELECT pr.*, u.full_name, p.tone, p.skill
             FROM promotions pr
             JOIN users u ON u.id = pr.user_id
             LEFT JOIN profiles p ON p.user_id = u.id
             ORDER BY pr.id DESC LIMIT 100"
        );
        $active = 0;
        $inflight = 0;
        $week = 0;
        $since = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $items = [];
        foreach ($rows as $r) {
            if ($r['status'] === 'active') {
                $active++;
                $inflight += (int) $r['amount_kobo'];
            }
            if (($r['created_at'] ?? '') >= $since) {
                $week += (int) $r['amount_kobo'];
            }
            $plan = $r['plan'] === 'category' ? 'Category spotlight' : 'Search boost';
            $items[] = [
                'id'           => (int) $r['id'],
                'name'         => $r['full_name'],
                'initials'     => initials($r['full_name']),
                'tone'         => $r['tone'] ?: 'a1',
                'plan'         => $plan,
                'skill'        => $r['skill'] ?: '—',
                'starts'       => $r['starts_at'] ? date('M j, Y', strtotime($r['starts_at']) ?: time()) : '—',
                'ends'         => $r['ends_at'] ? date('M j, Y', strtotime($r['ends_at']) ?: time()) : '—',
                'amount_label' => ngn_fmt((int) $r['amount_kobo']),
                'status'       => $r['status'],
                'stateLabel'   => ucfirst($r['status']),
                'chip'         => $r['status'] === 'active' ? 'st-green' : ($r['status'] === 'paused' ? 'st-amber' : 'st-gray'),
                'can_pause'    => $r['status'] === 'active',
            ];
        }
        return [
            'kpis' => [
                ['label' => 'Active promotions', 'value' => (string) $active, 'sub' => ngn_fmt($inflight) . ' in-flight'],
                ['label' => 'Revenue (7d)', 'value' => ngn_fmt($week), 'sub' => 'paid placements'],
                ['label' => 'Label rule', 'value' => 'Promoted', 'sub' => 'never a trust badge'],
                ['label' => 'Cap', 'value' => '3 / page', 'sub' => 'hard-coded'],
            ],
            'items' => $items,
        ];
    }

    public static function pausePromo(int $adminId, string $key, string $ip): array
    {
        $row = Db::fetch('SELECT * FROM promotions WHERE id = ?', [$key]);
        if ($row === null) {
            throw new AppError('not_found', 'Promotion not found.', 404);
        }
        Db::run("UPDATE promotions SET status='paused' WHERE id=?", [$row['id']]);
        $still = Db::fetch("SELECT id FROM promotions WHERE user_id=? AND status='active'", [$row['user_id']]);
        if (!$still) {
            Db::run('UPDATE profiles SET promo=0 WHERE user_id=?', [$row['user_id']]);
        }
        self::audit($adminId, 'promo.pause', 'promotion:' . $row['id'], ['user_id' => $row['user_id']], $ip);
        return self::promotions();
    }

    public static function categories(): array
    {
        $parents = Db::fetchAll('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort, id');
        $out = [];
        foreach ($parents as $p) {
            $subs = Db::fetchAll('SELECT * FROM categories WHERE parent_id = ? ORDER BY sort, id', [$p['id']]);
            $n = (int) (Db::fetch(
                "SELECT COUNT(*) c FROM profiles pr JOIN categories c ON c.name = pr.skill WHERE c.parent_id=? OR c.id=?",
                [$p['id'], $p['id']]
            )['c'] ?? 0);
            $out[] = [
                'id'      => (int) $p['id'],
                'name'    => $p['name'],
                'slug'    => $p['slug'],
                'icon'    => $p['icon'],
                'mode'    => $p['mode_label'],
                'workers' => $n,
                'active'  => !isset($p['active']) || (int) $p['active'] === 1,
                'subs'    => array_map(static fn ($s) => [
                    'id'     => (int) $s['id'],
                    'name'   => $s['name'],
                    'slug'   => $s['slug'],
                    'active' => !isset($s['active']) || (int) $s['active'] === 1,
                ], $subs),
            ];
        }
        return $out;
    }

    public static function categoryAction(int $adminId, string $key, string $action, string $ip): array
    {
        $row = Db::fetch('SELECT * FROM categories WHERE id=? OR slug=?', [$key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Category not found.', 404);
        }
        if ($action === 'archive') {
            Db::run('UPDATE categories SET active=0 WHERE id=?', [$row['id']]);
        } elseif ($action === 'activate') {
            Db::run('UPDATE categories SET active=1 WHERE id=?', [$row['id']]);
        } else {
            throw new AppError('invalid', 'Archive or activate.', 422);
        }
        self::audit($adminId, 'category.' . $action, 'category:' . $row['slug'], [], $ip);
        return self::categories();
    }

    public static function reports(): array
    {
        $rows = Db::fetchAll(
            "SELECT r.*, u.full_name AS reporter, t.full_name AS target_name
             FROM reports r
             LEFT JOIN users u ON u.id = r.reporter_id
             LEFT JOIN users t ON t.id = r.target_id AND r.target_type='user'
             ORDER BY CASE r.status WHEN 'open' THEN 0 ELSE 1 END, r.id DESC"
        );
        $counts = [];
        foreach ($rows as $r) {
            if ($r['target_type'] === 'user') {
                $counts[(int) $r['target_id']] = ($counts[(int) $r['target_id']] ?? 0) + 1;
            }
        }
        return array_map(static function ($r) use ($counts) {
            $n = $r['target_type'] === 'user' ? ($counts[(int) $r['target_id']] ?? 1) : 1;
            return [
                'id'          => $r['public_code'] ?: ('RPT-' . $r['id']),
                'numeric_id'  => (int) $r['id'],
                'target'      => ($r['target_type'] === 'user' ? 'Profile — ' : ucfirst($r['target_type']) . ' — ') . ($r['target_name'] ?: ('#' . $r['target_id'])),
                'target_id'   => (int) $r['target_id'],
                'reason'      => $r['reason'],
                'count'       => $n,
                'threshold'   => $n >= 3,
                'date'        => date('M j, Y', strtotime($r['created_at']) ?: time()),
                'status'      => $r['status'],
                'stateLabel'  => ucfirst($r['status']),
                'chip'        => $r['status'] === 'open' ? 'st-amber' : ($r['status'] === 'banned' ? 'st-red' : 'st-green'),
            ];
        }, $rows);
    }

    public static function reportAction(int $adminId, string $key, string $action, string $note, string $ip): array
    {
        $row = Db::fetch('SELECT * FROM reports WHERE public_code=? OR id=?', [$key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Report not found.', 404);
        }
        $action = strtolower($action);
        $now = now_iso();
        if ($action === 'dismiss') {
            Db::run("UPDATE reports SET status='dismissed', updated_at=? WHERE id=?", [$now, $row['id']]);
        } elseif ($action === 'warn') {
            Db::run("UPDATE reports SET status='warned', updated_at=? WHERE id=?", [$now, $row['id']]);
            if ($row['target_type'] === 'user') {
                NotificationService::push((int) $row['target_id'], 'order', 'A warning from Skilvi', $note !== '' ? $note : 'A report on your account was reviewed. Please stay on-platform.', 'help.html');
            }
        } elseif ($action === 'ban' && $row['target_type'] === 'user') {
            Db::run("UPDATE reports SET status='banned', updated_at=? WHERE id=?", [$now, $row['id']]);
            User::setStatus((int) $row['target_id'], 'banned');
        } else {
            throw new AppError('invalid', 'Dismiss, warn, or ban.', 422);
        }
        self::audit($adminId, 'report.' . $action, 'report:' . $row['id'], ['note' => $note], $ip);
        return self::reports();
    }

    public static function analytics(): array
    {
        $gmv = (int) (Db::fetch("SELECT COALESCE(SUM(amount_kobo),0) s FROM orders WHERE status='released'")['s'] ?? 0);
        $fees = (int) (Db::fetch("SELECT COALESCE(SUM(fee_kobo),0) s FROM orders WHERE status='released'")['s'] ?? 0);
        $payouts = (int) (Db::fetch("SELECT COALESCE(SUM(amount_kobo),0) s FROM withdrawals WHERE status='paid'")['s'] ?? 0);
        $orders = (int) (Db::fetch("SELECT COUNT(*) c FROM orders")['c'] ?? 0);
        $disputed = (int) (Db::fetch("SELECT COUNT(*) c FROM disputes")['c'] ?? 0);
        $rate = $orders ? round(100 * $disputed / $orders, 1) : 0;
        return [
            'gmv_label'      => ngn_fmt($gmv),
            'fees_label'     => ngn_fmt($fees),
            'payouts_label'  => ngn_fmt($payouts),
            'dispute_rate'   => $rate . '%',
            'orders'         => $orders,
            'disputes'       => $disputed,
        ];
    }

    public static function analyticsCsv(): string
    {
        $a = self::analytics();
        $lines = ["metric,value", "gmv," . $a['gmv_label'], "fees," . $a['fees_label'], "payouts," . $a['payouts_label'], "dispute_rate," . $a['dispute_rate'], "orders," . $a['orders']];
        foreach (Db::fetchAll("SELECT code, title, status, amount_kobo FROM orders ORDER BY id DESC LIMIT 500") as $o) {
            $lines[] = $o['code'] . ',' . str_replace(',', ' ', (string) $o['title']) . ',' . $o['status'] . ',' . $o['amount_kobo'];
        }
        return implode("\n", $lines) . "\n";
    }

    public static function auditLog(string $q = ''): array
    {
        $rows = $q === ''
            ? Db::fetchAll(
                "SELECT a.*, u.full_name FROM admin_audit a JOIN users u ON u.id=a.admin_id ORDER BY a.id DESC LIMIT 200"
            )
            : Db::fetchAll(
                "SELECT a.*, u.full_name FROM admin_audit a JOIN users u ON u.id=a.admin_id
                 WHERE a.action LIKE ? OR a.target LIKE ? OR u.full_name LIKE ?
                 ORDER BY a.id DESC LIMIT 200",
                ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']
            );
        return array_map(static function ($a) {
            $meta = json_decode((string) ($a['meta'] ?? ''), true) ?: [];
            return [
                'when'   => date('M j, H:i', strtotime($a['created_at']) ?: time()),
                'admin'  => $a['full_name'],
                'action' => $a['action'],
                'target' => $a['target'],
                'reason' => $meta['reason'] ?? ($meta['note'] ?? ''),
                'ip'     => $a['ip'] ?? '',
            ];
        }, $rows);
    }

    public static function settings(): array
    {
        $all = [];
        foreach (Db::fetchAll('SELECT key, value FROM settings') as $r) {
            $all[$r['key']] = $r['value'];
        }
        return [
            'fee_percent'            => (int) ($all['fee_percent'] ?? 10),
            'min_withdrawal_kobo'    => (int) ($all['min_withdrawal_kobo'] ?? 500000),
            'min_package_naira'      => (int) ($all['min_package_naira'] ?? 500),
            'max_package_naira'      => (int) ($all['max_package_naira'] ?? 20000000),
            'auto_release_days'      => (int) ($all['auto_release_days'] ?? 5),
            'dispute_ack_days'       => (int) ($all['dispute_ack_days'] ?? 1),
            'dispute_resolve_days'   => (int) ($all['dispute_resolve_days'] ?? 5),
            'verification_kobo'      => (int) ($all['verification_kobo'] ?? 500000),
            'promo_search_kobo'      => (int) ($all['promo_search_kobo'] ?? 250000),
            'promo_category_kobo'    => (int) ($all['promo_category_kobo'] ?? 500000),
            'auto_approve_wd_naira'  => (int) ($all['auto_approve_wd_naira'] ?? 100000),
            'raw'                    => $all,
        ];
    }

    public static function saveSettings(int $adminId, array $in, string $ip): array
    {
        $map = [
            'fee_percent' => 'fee_percent',
            'min_withdrawal_naira' => 'min_withdrawal_kobo',
            'min_package_naira' => 'min_package_naira',
            'max_package_naira' => 'max_package_naira',
            'auto_release_days' => 'auto_release_days',
            'dispute_ack_days' => 'dispute_ack_days',
            'dispute_resolve_days' => 'dispute_resolve_days',
            'verification_naira' => 'verification_kobo',
            'promo_search_naira' => 'promo_search_kobo',
            'promo_category_naira' => 'promo_category_kobo',
            'auto_approve_wd_naira' => 'auto_approve_wd_naira',
        ];
        foreach ($map as $inKey => $dbKey) {
            if (!array_key_exists($inKey, $in) && !array_key_exists($dbKey, $in)) {
                continue;
            }
            $raw = $in[$inKey] ?? $in[$dbKey];
            $n = (int) preg_replace('/\D/', '', (string) $raw);
            if (str_contains($dbKey, 'kobo') && !str_contains($inKey, 'kobo') && $n < 10000) {
                $n *= 100;
            }
            if ($dbKey === 'fee_percent') {
                $n = (int) $raw;
                if ($n < 0 || $n > 30) {
                    throw new AppError('invalid', 'Fee percent must be 0–30.', 422);
                }
            }
            Db::run('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)', [$dbKey, (string) $n]);
        }
        self::audit($adminId, 'settings.save', 'settings', ['keys' => array_keys($in)], $ip);
        return self::settings();
    }

    public static function tickets(): array
    {
        $rows = Db::fetchAll(
            "SELECT t.*, u.full_name, u.status AS user_status, p.tone
             FROM support_tickets t
             LEFT JOIN users u ON u.id = t.user_id
             LEFT JOIN profiles p ON p.user_id = u.id
             ORDER BY COALESCE(t.last_at, t.created_at) DESC"
        );
        return array_map(static function ($t) {
            $flags = (int) (Db::fetch(
                "SELECT COUNT(*) c FROM reports WHERE target_type='user' AND target_id=?",
                [$t['user_id']]
            )['c'] ?? 0);
            return [
                'id'          => (int) $t['id'],
                'code'        => $t['code'] ?: ('T-' . $t['id']),
                'name'        => $t['full_name'] ?: 'Unknown',
                'initials'    => initials($t['full_name'] ?: '?'),
                'tone'        => $t['tone'] ?: 'a2',
                'subject'     => $t['subject'],
                'body'        => $t['body'] ?? '',
                'status'      => $t['status'],
                'user_status' => $t['user_status'] ?? '',
                'flags'       => $flags,
                'date'        => date('M j', strtotime($t['created_at']) ?: time()),
            ];
        }, $rows);
    }

    public static function ticketGet(int $id): array
    {
        $list = self::tickets();
        $card = null;
        foreach ($list as $t) {
            if ($t['id'] === $id) {
                $card = $t;
                break;
            }
        }
        if ($card === null) {
            throw new AppError('not_found', 'Ticket not found.', 404);
        }
        $msgs = Db::fetchAll(
            'SELECT m.*, u.full_name FROM ticket_messages m LEFT JOIN users u ON u.id=m.author_id WHERE m.ticket_id=? ORDER BY m.id',
            [$id]
        );
        $card['messages'] = array_map(static fn ($m) => [
            'admin' => (int) $m['from_admin'] === 1,
            'name'  => $m['full_name'] ?: 'Skilvi',
            'body'  => $m['body'],
            'time'  => date('M j, H:i', strtotime($m['created_at']) ?: time()),
        ], $msgs);
        return $card;
    }

    public static function ticketReply(int $adminId, string $key, string $body, string $ip): array
    {
        $t = Db::fetch('SELECT * FROM support_tickets WHERE id=? OR code=?', [$key, $key]);
        if ($t === null) {
            throw new AppError('not_found', 'Ticket not found.', 404);
        }
        $body = trim($body);
        if (mb_strlen($body) < 4) {
            throw new AppError('invalid', 'Write a reply.', 422);
        }
        $now = now_iso();
        Db::run(
            'INSERT INTO ticket_messages (ticket_id, author_id, body, from_admin, created_at) VALUES (?,?,?,1,?)',
            [$t['id'], $adminId, $body, $now]
        );
        Db::run("UPDATE support_tickets SET status='answered', last_at=? WHERE id=?", [$now, $t['id']]);
        if ($t['user_id']) {
            NotificationService::push((int) $t['user_id'], 'order', 'Support replied', mb_substr($body, 0, 120), 'help.html');
        }
        self::audit($adminId, 'support.reply', 'ticket:' . $t['id'], [], $ip);
        return self::ticketGet((int) $t['id']);
    }

    public static function audit(int $adminId, string $action, string $target, array $meta, string $ip = ''): void
    {
        Db::run(
            'INSERT INTO admin_audit (admin_id, action, target, meta, created_at, ip) VALUES (?,?,?,?,?,?)',
            [$adminId, $action, $target, json_encode($meta), now_iso(), $ip]
        );
    }

    /** @return list<array<string,mixed>> */
    private static function recentUsers(int $n): array
    {
        $rows = Db::fetchAll(
            "SELECT u.full_name, u.roles, u.created_at, u.phone, p.tone, p.city
             FROM users u LEFT JOIN profiles p ON p.user_id=u.id
             ORDER BY u.id DESC LIMIT $n"
        );
        return array_map(static fn ($r) => [
            'name'     => $r['full_name'],
            'initials' => initials($r['full_name']),
            'tone'     => $r['tone'] ?: 'a2',
            'sub'      => format_phone($r['phone']) . ' · ' . str_replace(',', ' · ', (string) $r['roles']) . ($r['city'] ? ' · ' . $r['city'] : ''),
            'when'     => ago($r['created_at']),
        ], $rows);
    }

    private static function needs(int $d, int $w, int $r): array
    {
        $out = [];
        if ($d) {
            $out[] = ['chip' => 'st-red', 'label' => 'Disputes', 'text' => $d . ' open case' . ($d === 1 ? '' : 's') . ' — escrow frozen until you resolve.', 'href' => 'disputes.html'];
        }
        if ($w) {
            $out[] = ['chip' => 'st-amber', 'label' => 'Withdrawals', 'text' => $w . ' payout' . ($w === 1 ? '' : 's') . ' waiting on review.', 'href' => 'withdrawals.html'];
        }
        if ($r) {
            $out[] = ['chip' => 'st-amber', 'label' => 'Reports', 'text' => $r . ' open report' . ($r === 1 ? '' : 's') . ' in the queue.', 'href' => 'reports.html'];
        }
        if (!$out) {
            $out[] = ['chip' => 'st-green', 'label' => 'Quiet', 'text' => 'No queues need you right now.', 'href' => 'audit-log.html'];
        }
        return $out;
    }

    private static function compactNaira(int $kobo): string
    {
        $n = $kobo / 100;
        if ($n >= 1000000) {
            return '₦' . rtrim(rtrim(number_format($n / 1000000, 2), '0'), '.') . 'M';
        }
        if ($n >= 1000) {
            return '₦' . rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k';
        }
        return ngn_fmt($kobo);
    }

    private static function ledger(int $orderId, string $account, ?int $userId, int $debit, int $credit, string $memo): void
    {
        $n = (int) (Db::fetch('SELECT COALESCE(MAX(entry_no),0) n FROM ledger')['n'] ?? 0) + 1;
        Db::run(
            'INSERT INTO ledger (entry_no, account, user_id, order_id, debit_kobo, credit_kobo, memo, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$n, $account, $userId, $orderId, $debit, $credit, $memo, now_iso()]
        );
    }
}
