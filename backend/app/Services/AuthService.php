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
    public static function registerStart(string $name, string $phoneRaw, string $email, string $password, string $joinAs, string $ip, array $extra = []): array
    {
        $fields = [];
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            $fields['full_name'] = 'Enter your full name.';
        }
        $countryIn = trim((string) ($extra['country'] ?? ''));
        $stateIn = trim((string) ($extra['state'] ?? ''));
        $cityIn = trim((string) ($extra['city'] ?? ''));
        $dob = trim((string) ($extra['dob'] ?? ''));
        $gender = strtolower(trim((string) ($extra['gender'] ?? '')));
        $heard = strtolower(trim((string) ($extra['heard_about'] ?? '')));
        $geo = GeoService::resolve($countryIn, $stateIn, $cityIn);
        if ($geo['country'] === null) {
            $fields['country'] = 'Pick your country.';
        }
        if ($geo['state'] === null) {
            $fields['state'] = 'Pick your state / region.';
        }
        if ($cityIn === '') {
            $fields['city'] = 'Pick your city.';
        }
        $iso2 = strtoupper((string) ($geo['country']['iso2'] ?? ''));
        $phone = '';
        if ($phoneRaw !== '') {
            if ($iso2 === 'NG' || $iso2 === '') {
                $phone = normalize_phone($phoneRaw);
                if ($phone === '') {
                    $fields['phone'] = 'Use a Nigerian mobile, e.g. 0803 000 0000, or leave it blank.';
                }
            } else {
                $digits = preg_replace('/\D/', '', $phoneRaw) ?? '';
                if (strlen($digits) < 7 || strlen($digits) > 15) {
                    $fields['phone'] = 'Enter a working mobile number, or leave it blank.';
                } else {
                    $phone = $digits;
                }
            }
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
        $needsDob = str_contains($roles, 'worker');
        if ($needsDob) {
            if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $fields['dob'] = 'Enter your date of birth.';
            } else {
                $born = \DateTimeImmutable::createFromFormat('Y-m-d', $dob);
                $cutoff = (new \DateTimeImmutable('today'))->modify('-16 years');
                $oldest = (new \DateTimeImmutable('today'))->modify('-120 years');
                if ($born === false || $born > $cutoff) {
                    $fields['dob'] = 'Workers must be 16 or older.';
                } elseif ($born < $oldest) {
                    $fields['dob'] = 'Enter a real date of birth.';
                }
            }
        } else {
            $dob = '';
        }
        if ($gender !== '' && !in_array($gender, ['female', 'male', 'prefer_not'], true)) {
            $fields['gender'] = 'Pick one of the listed options, or leave it blank.';
        }
        $heardOk = ['google', 'instagram', 'facebook', 'whatsapp', 'tiktok', 'friend', 'youtube', 'other'];
        if (!in_array($heard, $heardOk, true)) {
            $fields['heard_about'] = 'Tell us how you heard about Skilvi.';
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
        if (User::isBlocked($email, $phone)) {
            throw new AppError('blocked', self::blockedMessage(), 403, ['email' => self::blockedMessage()]);
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
            'country'       => (string) ($geo['country']['name'] ?? ''),
            'country_code'  => $iso2,
            'state'         => (string) ($geo['state']['name'] ?? $stateIn),
            'city'          => $cityIn,
            'dob'           => $dob !== '' ? $dob : null,
            'gender'        => $gender !== '' ? $gender : null,
            'heard_about'   => $heard,
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
            $msg = $user['status'] === 'banned'
                ? 'This account is banned and cannot be used.'
                : 'This account is not active. Contact support.';
            throw new AppError('suspended', $msg, 403);
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
            if (User::isBlocked((string) $pending['email'], (string) ($pending['phone'] ?? ''))) {
                throw new AppError('blocked', self::blockedMessage(), 403);
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
        \App\Core\Auth::revokeCurrent();
        Session::logout();
    }

    public static function me(): array
    {
        $id = \App\Core\Auth::resolvedId();
        if ($id === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        $user = User::find($id);
        if ($user === null) {
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
        foreach (['headline', 'bio', 'state', 'city', 'country', 'country_code', 'dob', 'gender', 'heard_about', 'work_mode', 'skill'] as $k) {
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
            if (User::isBlocked($email)) {
                throw new AppError('blocked', 'This email cannot be used.', 403, ['email' => 'This email cannot be used.']);
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

    public static function blockedMessage(): string
    {
        return 'This email cannot be used to open an account. Contact support@skilvi.ng for further assistance.';
    }

    /** @return array{filename:string,body:string} */
    public static function exportPdf(int $id): array
    {
        $pack = self::export($id);
        $me = is_array($pack['account'] ?? null) ? $pack['account'] : [];
        $rows = [
            ['Exported', (string) ($pack['exported_at'] ?? '')],
            ['Name', (string) ($me['name'] ?? $me['full_name'] ?? '—')],
            ['Email', (string) ($me['email'] ?? '—')],
            ['Role', (string) ($me['role'] ?? '—')],
            ['City', trim((string) ($me['city'] ?? '') . ' ' . (string) ($me['state'] ?? '')) ?: '—'],
        ];
        foreach (($pack['orders'] ?? []) as $i => $o) {
            if (!is_array($o) || $i >= 12) {
                break;
            }
            $rows[] = [
                'Order ' . (string) ($o['code'] ?? ($i + 1)),
                trim((string) ($o['title'] ?? '') . ' · ' . (string) ($o['status'] ?? '')),
            ];
        }
        foreach (($pack['jobs'] ?? []) as $i => $j) {
            if (!is_array($j) || $i >= 8) {
                break;
            }
            $rows[] = [
                'Job ' . (string) ($j['code'] ?? ($i + 1)),
                trim((string) ($j['title'] ?? '') . ' · ' . (string) ($j['status'] ?? '')),
            ];
        }
        $logo = rtrim((string) \App\Core\Config::get('frontend_root'), '/\\') . '/assets/img/skilvi-logo-word.png';
        $body = \App\Core\SimplePdf::receipt(
            'Your Skilvi data',
            $rows,
            'This is a summary of your Skilvi account under the NDPR. Download JSON from account settings for the full file. Skilvi. Built in Nigeria.',
            is_file($logo) ? $logo : null,
            'Data export'
        );
        return ['filename' => 'skilvi-data.pdf', 'body' => $body];
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

    public static function googleStart(string $next = ''): string
    {
        $state = bin2hex(random_bytes(16));
        Session::set('google_oauth', [
            'state' => $state,
            'next'  => self::safeNext($next),
            'at'    => time(),
        ]);
        return GoogleAuthService::authorizeUrl($state);
    }

    /** @return array{token:string,redirect:string,user:array<string,mixed>} */
    public static function googleFinish(string $code, string $state, string $ip): array
    {
        $sess = Session::get('google_oauth');
        Session::remove('google_oauth');
        if (!is_array($sess) || !hash_equals((string) ($sess['state'] ?? ''), $state)) {
            throw new AppError('google', 'Google sign-in expired. Try again.', 401);
        }
        if ((time() - (int) ($sess['at'] ?? 0)) > 600) {
            throw new AppError('google', 'Google sign-in expired. Try again.', 401);
        }
        $rl = RateLimit::hit('google:ip:' . $ip, 20, 3600);
        if (!$rl['ok']) {
            throw new AppError('rate_limited', 'Too many Google sign-in attempts. Try later.', 429);
        }
        $g = GoogleAuthService::userFromCode($code);
        if (User::isBlocked($g['email'])) {
            throw new AppError('blocked', self::blockedMessage(), 403);
        }
        $user = User::findByGoogleId($g['sub']);
        if ($user === null) {
            $user = User::findByEmail($g['email']);
            if ($user !== null) {
                if ($user['status'] !== 'active') {
                    $msg = $user['status'] === 'banned'
                        ? 'This account is banned and cannot be used.'
                        : 'This account is not active. Contact support.';
                    throw new AppError('suspended', $msg, 403);
                }
                User::setGoogleId((int) $user['id'], $g['sub'], true);
                $user = User::find((int) $user['id']);
            }
        }
        if ($user === null) {
            $id = User::create([
                'full_name'         => $g['name'],
                'email'             => $g['email'],
                'phone'             => '',
                'password_hash'     => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'roles'             => 'pending',
                'google_id'         => $g['sub'],
                'email_verified_at' => now_iso(),
            ]);
            $user = User::find($id);
        }
        if ($user === null) {
            throw new AppError('google', 'Could not open your account. Try again.', 500);
        }
        if ($user['status'] !== 'active') {
            $msg = $user['status'] === 'banned'
                ? 'This account is banned and cannot be used.'
                : 'This account is not active. Contact support.';
            throw new AppError('suspended', $msg, 403);
        }
        Session::forgetUser();
        $out = self::establish((int) $user['id']);
        $next = self::safeNext((string) ($sess['next'] ?? ''));
        if (!empty($out['user']['needs_profile'])) {
            if ($next !== '') {
                Session::set('google_next', $next);
            }
            $out['redirect'] = '/complete-profile.html';
        } elseif ($next !== '') {
            $out['redirect'] = $next;
        }
        return $out;
    }

    public static function completeProfile(int $userId, array $in): array
    {
        $user = User::find($userId);
        if ($user === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        $fields = [];
        $name = trim((string) ($in['full_name'] ?? $user['full_name'] ?? ''));
        if (mb_strlen($name) < 2) {
            $fields['full_name'] = 'Enter your full name.';
        }
        $joinAs = (string) ($in['join_as'] ?? '');
        $roles = self::rolesFromJoin($joinAs);
        if ($roles === '') {
            $fields['join_as'] = 'Choose Worker, Client, or Both.';
        }
        $countryIn = trim((string) ($in['country'] ?? ''));
        $stateIn = trim((string) ($in['state'] ?? ''));
        $cityIn = trim((string) ($in['city'] ?? ''));
        $geo = GeoService::resolve($countryIn, $stateIn, $cityIn);
        if ($geo['country'] === null) {
            $fields['country'] = 'Pick your country.';
        }
        if ($geo['state'] === null) {
            $fields['state'] = 'Pick your state / region.';
        }
        if ($cityIn === '') {
            $fields['city'] = 'Pick your city.';
        }
        $iso2 = strtoupper((string) ($geo['country']['iso2'] ?? ''));
        $phoneRaw = trim((string) ($in['phone'] ?? ''));
        $phone = '';
        if ($phoneRaw !== '') {
            if ($iso2 === 'NG' || $iso2 === '') {
                $phone = normalize_phone($phoneRaw);
                if ($phone === '') {
                    $fields['phone'] = 'Use a Nigerian mobile, e.g. 0803 000 0000, or leave it blank.';
                }
            } else {
                $digits = preg_replace('/\D/', '', $phoneRaw) ?? '';
                if (strlen($digits) < 7 || strlen($digits) > 15) {
                    $fields['phone'] = 'Enter a working mobile number, or leave it blank.';
                } else {
                    $phone = $digits;
                }
            }
        }
        $dob = trim((string) ($in['dob'] ?? ''));
        $needsDob = str_contains($roles, 'worker');
        if ($needsDob) {
            if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $fields['dob'] = 'Enter your date of birth.';
            } else {
                $born = \DateTimeImmutable::createFromFormat('Y-m-d', $dob);
                $cutoff = (new \DateTimeImmutable('today'))->modify('-16 years');
                $oldest = (new \DateTimeImmutable('today'))->modify('-120 years');
                if ($born === false || $born > $cutoff) {
                    $fields['dob'] = 'Workers must be 16 or older.';
                } elseif ($born < $oldest) {
                    $fields['dob'] = 'Enter a real date of birth.';
                }
            }
        } else {
            $dob = '';
        }
        $gender = strtolower(trim((string) ($in['gender'] ?? '')));
        if ($gender !== '' && !in_array($gender, ['female', 'male', 'prefer_not'], true)) {
            $fields['gender'] = 'Pick one of the listed options, or leave it blank.';
        }
        $heard = strtolower(trim((string) ($in['heard_about'] ?? '')));
        $heardOk = ['google', 'instagram', 'facebook', 'whatsapp', 'tiktok', 'friend', 'youtube', 'other'];
        if (!in_array($heard, $heardOk, true)) {
            $fields['heard_about'] = 'Tell us how you heard about Skilvi.';
        }
        if (empty($in['terms'])) {
            $fields['terms'] = 'Agree to the Terms and Privacy Policy to continue.';
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        if ($phone !== '') {
            $other = User::findByPhone($phone);
            if ($other && (int) $other['id'] !== $userId) {
                throw new AppError('phone_taken', 'An account already uses this number.', 409, ['phone' => 'An account already uses this number.']);
            }
        }
        User::updateName($userId, $name);
        User::updateRoles($userId, $roles);
        if ($phone !== '') {
            User::updatePhone($userId, $phone);
        }
        Profile::update($userId, [
            'country'      => (string) ($geo['country']['name'] ?? ''),
            'country_code' => $iso2,
            'state'        => (string) ($geo['state']['name'] ?? $stateIn),
            'city'         => $cityIn,
            'dob'          => $dob !== '' ? $dob : null,
            'gender'       => $gender !== '' ? $gender : null,
            'heard_about'  => $heard,
        ]);
        $out = self::establish($userId);
        $next = self::safeNext((string) Session::get('google_next'));
        Session::remove('google_next');
        if ($next !== '' && empty($out['user']['needs_profile'])) {
            $out['redirect'] = $next;
        }
        return $out;
    }

    public static function homeFor(array $me): string
    {
        if (!empty($me['needs_profile'])) {
            return '/complete-profile.html';
        }
        $roles = $me['roles'] ?? [];
        if (in_array('admin', $roles, true)) {
            return '/admin/index.html';
        }
        if (in_array('client', $roles, true)) {
            return '/client-dashboard.html';
        }
        return '/worker-dashboard.html';
    }

    private static function safeNext(string $next): string
    {
        $next = trim($next);
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '://') || str_contains($next, '\\')) {
            return '';
        }
        if (str_starts_with($next, '/admin')) {
            return '';
        }
        return $next;
    }

    private static function establish(int $id): array
    {
        Session::login($id);
        User::touchLogin($id);
        $me = User::public(User::find($id));
        return [
            'user'     => $me,
            'redirect' => self::homeFor($me),
            'token'    => \App\Core\Auth::issueToken($id),
        ];
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
