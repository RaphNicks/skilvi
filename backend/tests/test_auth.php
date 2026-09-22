<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-test-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('SQLITE_PATH=' . $tmp);
putenv('APP_ENV=dev');

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Schema::install();
App\Core\Seed::run();
App\Core\Session::start();

function assert_true($c, string $msg): void
{
    if (!$c) {
        fwrite(STDERR, "FAIL $msg\n");
        exit(1);
    }
    echo "ok  $msg\n";
}

$u = App\Models\User::findByPhone('2348031112233');
assert_true($u !== null, 'seed chinedu exists');
assert_true(password_verify('password1', $u['password_hash']), 'seed password');

$ip = '127.0.0.1';
$login = App\Services\AuthService::loginStart('chinedu@okafordev.ng', 'password1', $ip);
assert_true(isset($login['dev_code']) && strlen($login['dev_code']) === 6, 'login sends otp');

try {
    App\Services\AuthService::verify('000000', 'login', $ip);
    assert_true(false, 'bad otp rejected');
} catch (App\AppError $e) {
    assert_true($e->errorCode === 'otp_invalid', 'bad otp error code');
}

$done = App\Services\AuthService::verify($login['dev_code'], 'login', $ip);
assert_true(isset($done['user']['id']), 'login verify establishes session');
assert_true(App\Core\Session::userId() === (int) $done['user']['id'], 'session user_id');
assert_true(in_array('worker', $done['user']['roles'], true), 'chinedu is worker');

$me = App\Services\AuthService::me();
assert_true($me['full_name'] === 'Chinedu Okafor', 'me()');

$updated = App\Services\AuthService::updateProfile((int) $me['id'], ['city' => 'Obio-Akpor', 'headline' => 'WP specialist']);
assert_true($updated['city'] === 'Obio-Akpor', 'profile update');

App\Services\AuthService::logout();
assert_true(App\Core\Session::userId() === null, 'logout');

// fresh session after destroy
App\Core\Session::start();

try {
    App\Services\AuthService::loginStart('chinedu@okafordev.ng', 'wrong-pass', $ip);
    assert_true(false, 'bad password rejected');
} catch (App\AppError $e) {
    assert_true($e->errorCode === 'credentials', 'bad password code');
}

$reg = App\Services\AuthService::registerStart(
    'Ngozi Bello',
    '',
    'ngozi@example.com',
    'password1',
    'both',
    $ip
);
assert_true(isset($reg['dev_code']), 'register otp');
$created = App\Services\AuthService::verify($reg['dev_code'], 'register', $ip);
assert_true($created['user']['full_name'] === 'Ngozi Bello', 'register creates user');
assert_true(in_array('client', $created['user']['roles'], true) && in_array('worker', $created['user']['roles'], true), 'both roles');

App\Services\AuthService::logout();
App\Core\Session::start();

$fp = App\Services\AuthService::forgot('ngozi@example.com', $ip);
assert_true(isset($fp['dev_code']), 'forgot otp');
$reset = App\Services\AuthService::resetPassword($fp['dev_code'], 'newpass12', $ip);
assert_true($reset['user']['full_name'] === 'Ngozi Bello', 'reset logs in');

App\Services\AuthService::logout();
App\Core\Session::start();
$again = App\Services\AuthService::loginStart('ngozi@example.com', 'newpass12', $ip);
assert_true(isset($again['dev_code']), 'login with new password + email id');

echo "ALL AUTH TESTS PASSED\n";
@unlink($tmp);
