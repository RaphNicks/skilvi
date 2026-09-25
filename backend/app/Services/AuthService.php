<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\RateLimit;
use App\Core\Session;
use App\Models\Profile;
use App\Models\User;

final class AuthService
{
    public static function registerStart(string $name, string $phoneRaw, string $email, string $password, string $joinAs, string $ip): array
    {
        $fields = [];
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            $fields['full_name'] = 'Enter your full name.';
        }
        $phone = $phoneRaw !== '' ? normalize_phone($phoneRaw) : '';
        if ($phoneRaw !== '' && $phone === '') {
            $fields['phone'] = 'Use a Nigerian mobile, e.g. 0803 000 0000, or leave it blank.';
        }
        $email = strtolower(trim($email));
        $looksLikePhone = $email !== '' && !str_contains($email, '@') && preg_match('/^[0-9+\s().-]{7,}$/', $email);
        if ($looksLikePhone || ($email !== '' && !str_contains($email, '@'))) {
            $fields['email'] = 'Use an email like you@example.com — not a phone number.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fields['email'] = 'Enter a working email — we send your login code there.';
        }
        if (strlen($password) < 8) {
            $fields['password'] = 'Use at least 8 characters.';
        }
        $roles = self::rolesFromJoin($joinAs);
        if ($roles === '') {
            $fields['join_as'] = 'Choose Worker, Client, or Both.';
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        if ($phone !== '' && User::findByPhone($phone)) {
            throw new AppError('phone_taken', 'An account already uses this number. Log in instead.', 409);
        }
        if (User::findByEmail($email)) {
            throw new AppError('email_taken', 'That email is already on an account. Log in instead.', 409);
        }

        $rl = RateLimit::hit('register:ip:' . $ip, 5, 3600);
        if (!$rl['ok']) {
            throw new AppError('rate_limited', 'Too many accounts from this network. Try later.', 429);
        }

        Session::set('pending_register', [
            'full_name'     => $name,
            'phone'         => $phone,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'roles'         => $roles,
            'at'            => time(),
        ]);
        Session::claim($email, 'register');
        return OtpService::issue($email, 'register', $ip);
    }

    public static function loginStart(string $identifier, string $password, string $ip): array
    {
        $rl = RateLimit::hit('login:ip:' . $ip, 10, 15 * 60);
        if (!$rl['ok']) {
            throw new AppError('rate_limited', 'Too many login attempts. Try again shortly.', 429);
        }
        $user = User::findByIdentifier($identifier);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new AppError('credentials', 'Email or password is not right.', 401, [
                'identifier' => 'Check this email.',
                'password'   => 'Check this password.',
            ]);
        }
        if ($user['status'] !== 'active') {
            throw new AppError('suspended', 'This account is not active. Contact support.', 403);
        }
        // Always bind the next OTP to THIS account — never keep a previous login.
        Session::forgetUser();
        $dest = (string) ($user['email'] ?: $user['phone']);
        if ($dest === '' || str_starts_with($dest, 'e:')) {
            $dest = (string) $user['email'];
        }
        if ($dest === '') {
            throw new AppError('no_dest', 'This account has no email to send a code to.', 400);
        }
        Session::set('pending_login_id', (int) $user['id']);
        Session::claim($dest, 'login');
        return OtpService::issue($dest, 'login', $ip) + ['phone' => $dest];
    }

    public static function verify(string $code, string $purpose, string $ip): array
    {
        $claim = Session::get('otp_claim');
        if (!is_array($claim) || ($claim['purpose'] ?? '') !== $purpose) {
            throw new AppError('otp_session', 'Start this step again — your session expired.', 401);
        }
        $dest = (string) $claim['phone'];
        OtpService::verify($dest, $purpose, $code);

        if ($purpose === 'register') {
            $pending = Session::get('pending_register');
            if (!is_array($pending) || strcasecmp((string) ($pending['email'] ?? ''), $dest) !== 0) {
                throw new AppError('otp_session', 'Start registration again.', 401);
            }
            if (User::findByEmail((string) $pending['email'])) {
                throw new AppError('email_taken', 'That email is already on an account. Log in instead.', 409);
            }
            $id = User::create($pending);
            Session::remove('pending_register');
            Session::clearClaim();
            return self::establish($id);
        }

        if ($purpose === 'login') {
            $id = (int) Session::get('pending_login_id');
            $user = User::find($id);
            $match = $user && (
                strcasecmp((string) $user['email'], $dest) === 0
                || (string) $user['phone'] === $dest
            );
            if (!$match) {
                throw new AppError('otp_session', 'Start login again.', 401);
            }
            Session::remove('pending_login_id');
            Session::clearClaim();
            return self::establish($id);
        }

        if ($purpose === 'reset') {
            Session::set('reset_ok', ['phone' => $dest, 'at' => time()]);
            Session::clearClaim();
            return ['reset_ok' => true, 'phone_mask' => mask_dest($dest)];
        }

        throw new AppError('otp_purpose', 'Unknown verification step.', 400);
    }

    public static function resend(string $ip): array
    {
        $claim = Session::get('otp_claim');
        if (!is_array($claim)) {
            throw new AppError('otp_session', 'Start this step again.', 401);
        }
        return OtpService::issue((string) $claim['phone'], (string) $claim['purpose'], $ip);
    }

    public static function forgot(string $identifier, string $ip): array
    {
        $user = User::findByIdentifier($identifier);
        // Always look like success — no account enumeration.
        $echo = trim($identifier);
        $dest = $user ? (string) ($user['email'] ?: $user['phone']) : $echo;
        $mask = mask_dest($dest);
        if ($user !== null && $user['status'] === 'active' && $dest !== '') {
            Session::claim($dest, 'reset');
            $otp = OtpService::issue($dest, 'reset', $ip);
            return $otp + ['echo' => $mask];
        }
        return [
            'phone_mask' => $mask,
            'expires_in' => (int) Config::get('otp.ttl'),
            'purpose'    => 'reset',
            'echo'       => $mask,
        ];
    }

    public static function resetPassword(string $code, string $newPassword, string $ip): array
    {
        if (strlen($newPassword) < 8) {
            throw new AppError('invalid', 'Use at least 8 characters.', 422, ['password' => 'Use at least 8 characters.']);
        }
        $reset = Session::get('reset_ok');
        if (!is_array($reset) || (time() - (int) $reset['at']) > 900) {
            $claim = Session::get('otp_claim');
            if (!is_array($claim) || ($claim['purpose'] ?? '') !== 'reset') {
                throw new AppError('otp_session', 'Start the reset again.', 401);
            }
            OtpService::verify((string) $claim['phone'], 'reset', $code);
            $dest = (string) $claim['phone'];
            Session::clearClaim();
        } else {
            $dest = (string) $reset['phone'];
        }
        $user = str_contains($dest, '@')
            ? User::findByEmail($dest)
            : User::findByPhone($dest);
        if ($user === null) {
            throw new AppError('not_found', 'Account not found.', 404);
        }
        User::updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
        Session::remove('reset_ok');
        return self::establish((int) $user['id']);
    }

    public static function logout(): void
    {
        Session::logout();
    }

    public static function me(): array
    {
        $id = Session::userId();
        if ($id === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        $user = User::find($id);
        if ($user === null) {
            Session::logout();
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        return User::public($user);
    }

    public static function updateProfile(int $id, array $in): array
    {
        $name = trim((string) ($in['full_name'] ?? ''));
        if ($name !== '') {
            if (mb_strlen($name) < 2) {
                throw new AppError('invalid', 'Enter your full name.', 422, ['full_name' => 'Enter your full name.']);
            }
            User::updateName($id, $name);
        }
        $fields = [];
        foreach (['headline', 'bio', 'state', 'city', 'work_mode', 'skill'] as $k) {
            if (array_key_exists($k, $in)) {
                $fields[$k] = $in[$k] === '' ? null : (string) $in[$k];
            }
        }
        foreach (['notify_sms', 'notify_jobs', 'notify_marketing'] as $k) {
            if (array_key_exists($k, $in)) {
                $fields[$k] = !empty($in[$k]) ? 1 : 0;
            }
        }
        Profile::update($id, $fields);
        return User::public(User::find($id));
    }

    public static function updatePassword(int $id, string $current, string $new): void
    {
        $user = User::find($id);
        if ($user === null || !password_verify($current, $user['password_hash'])) {
            throw new AppError('credentials', 'Current password is not right.', 401);
        }
        if (strlen($new) < 8) {
            throw new AppError('invalid', 'Use at least 8 characters.', 422, ['password' => 'Use at least 8 characters.']);
        }
        User::updatePassword($id, password_hash($new, PASSWORD_DEFAULT));
    }

    public static function updateEmail(int $id, string $email): array
    {
        $email = trim($email);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AppError('invalid', 'That email does not look right.', 422, ['email' => 'That email does not look right.']);
        }
        if ($email !== '') {
            $other = User::findByEmail($email);
            if ($other && (int) $other['id'] !== $id) {
                throw new AppError('email_taken', 'That email is already on an account.', 409);
            }
        }
        User::updateEmail($id, $email !== '' ? $email : null);
        return User::public(User::find($id));
    }

    public static function updatePhone(int $id, string $raw): array
    {
        $raw = trim($raw);
        $user = User::find($id);
        if ($user === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        if ($raw === '') {
            $email = strtolower((string) ($user['email'] ?? ''));
            $placeholder = $email !== '' ? ('e:' . $email) : ('e:user' . $id);
            User::updatePhone($id, $placeholder);
            return User::public(User::find($id));
        }
        $phone = normalize_phone($raw);
        if ($phone === '') {
            throw new AppError('invalid', 'Use a Nigerian mobile, e.g. 0803 000 0000, or leave it blank.', 422, ['phone' => 'Use a Nigerian mobile, e.g. 0803 000 0000.']);
        }
        $other = User::findByPhone($phone);
        if ($other && (int) $other['id'] !== $id) {
            throw new AppError('phone_taken', 'An account already uses this number.', 409);
        }
        User::updatePhone($id, $phone);
        return User::public(User::find($id));
    }

    public static function export(int $id): array
    {
        $user = User::find($id);
        if ($user === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        $me = User::public($user);
        unset($me['phone']);
        $orders = \App\Core\Db::fetchAll(
            'SELECT code, title, status, amount_kobo, created_at FROM orders WHERE client_id = ? OR worker_id = ? ORDER BY id DESC LIMIT 200',
            [$id, $id]
        );
        $jobs = \App\Core\Db::fetchAll(
            'SELECT code, title, status, created_at FROM jobs WHERE client_id = ? ORDER BY id DESC LIMIT 200',
            [$id]
        );
        return [
            'exported_at' => now_iso(),
            'account'     => $me,
            'orders'      => $orders,
            'jobs'        => $jobs,
        ];
    }

    public static function consent(int $id): array
    {
        $user = User::find($id);
        if ($user === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        $me = User::public($user);
        $profile = \App\Models\Profile::forUser($id) ?? [];
        return [
            'updated_at' => $profile['updated_at'] ?? $user['updated_at'] ?? null,
            'items'      => [
                ['title' => 'Order updates (email)', 'on' => true, 'locked' => true, 'note' => 'Always on for money events.'],
                ['title' => 'Money alerts (SMS)', 'on' => !empty($me['notify_sms']), 'locked' => false],
                ['title' => 'New job alerts (email)', 'on' => !empty($me['notify_jobs']), 'locked' => false],
                ['title' => 'Marketing & tips (email)', 'on' => !empty($me['notify_marketing']), 'locked' => false],
            ],
        ];
    }

    public static function requestDeletion(int $id): array
    {
        \App\Core\Db::run(
            'INSERT INTO account_requests (user_id, kind, note, created_at) VALUES (?,?,?,?)',
            [$id, 'delete', 'NDPR deletion request', now_iso()]
        );
        return ['queued' => true, 'message' => 'Deletion request logged. We process it within 30 days, after any active escrow settles.'];
    }

    public static function deactivate(int $id): array
    {
        $open = (int) (\App\Core\Db::fetch(
            "SELECT COUNT(*) c FROM orders WHERE (client_id = ? OR worker_id = ?) AND status IN ('pending_payment','funded','in_progress','completion_submitted','disputed')",
            [$id, $id]
        )['c'] ?? 0);
        if ($open > 0) {
            throw new AppError('escrow_open', 'Finish or settle open orders before deactivating. Escrow must clear first.', 409);
        }
        User::setStatus($id, 'deactivated');
        Session::logout();
        return ['deactivated' => true];
    }

    public static function homeFor(array $me): string
    {
        $roles = $me['roles'] ?? [];
        if (in_array('admin', $roles, true)) {
            return '/admin/index.html';
        }
        if (in_array('client', $roles, true)) {
            return '/client-dashboard.html';
        }
        return '/worker-dashboard.html';
    }

    private static function establish(int $id): array
    {
        Session::login($id);
        User::touchLogin($id);
        $me = User::public(User::find($id));
        return ['user' => $me, 'redirect' => self::homeFor($me)];
    }

    private static function rolesFromJoin(string $joinAs): string
    {
        return match ($joinAs) {
            'worker' => 'worker',
            'client' => 'client',
            'both'   => 'client,worker',
            default  => '',
        };
    }
}
