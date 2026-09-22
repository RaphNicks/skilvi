<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-hard-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('SQLITE_PATH=' . $tmp);
putenv('APP_ENV=dev');
putenv('APP_KEY=skilvi-test-key-phase8-hardening');

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Schema::install();
App\Core\Seed::run(true);

function assert_true($c, string $msg): void
{
    if (!$c) {
        fwrite(STDERR, "FAIL $msg\n");
        exit(1);
    }
    echo "ok  $msg\n";
}

$plain = '0123456789';
$enc = App\Core\Crypto::encrypt($plain);
assert_true(str_starts_with($enc, 'enc:v1:'), 'nuban encrypts with v1 prefix');
assert_true(App\Core\Crypto::decrypt($enc) === $plain, 'nuban decrypts');
assert_true(App\Core\Crypto::decrypt($plain) === $plain, 'legacy plaintext still reads');
assert_true(App\Core\Crypto::maskAccount($enc) === '····6789', 'mask uses last 4');

$audit = App\Services\LedgerAudit::run();
assert_true($audit['ok'] === true, 'seed ledger audit clean: ' . implode('; ', $audit['issues']));

$rl = App\Core\RateLimit::hit('t:bucket', 2, 60);
$rl = App\Core\RateLimit::hit('t:bucket', 2, 60);
$rl = App\Core\RateLimit::hit('t:bucket', 2, 60);
assert_true($rl['ok'] === false && $rl['wait'] > 0, 'rate limit trips on third hit');

$yusuf = (int) App\Models\User::findByPhone('2348100000010')['id'];
$_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'pay-once-abc';
$a = App\Services\PaymentService::initiate($yusuf, ['purpose' => 'verification', 'method' => 'ussd']);
$b = App\Services\PaymentService::initiate($yusuf, ['purpose' => 'verification', 'method' => 'card']);
assert_true($a['id'] === $b['id'], 'Idempotency-Key returns the same payment');
unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);

$adaeze = (int) App\Models\User::findByPhone('2348052223344')['id'];
try {
    App\Services\PaymentService::status($adaeze, $a['id']);
    assert_true(false, 'foreign payment 403');
} catch (App\AppError $e) {
    assert_true($e->http === 403, 'IDOR: another user cannot read a payment');
}

$ifeanyi = (int) App\Models\User::findByPhone('2348010001028')['id'];
App\Models\User::setStatus($ifeanyi, 'banned');
App\Core\Session::start();
try {
    App\Core\Session::set('user_id', $ifeanyi);
    App\Core\Auth::user();
    assert_true(false, 'banned auth');
} catch (App\AppError $e) {
    assert_true($e->http === 403, 'banned session cannot call Auth::user');
}

$tmpFile = sys_get_temp_dir() . '/skilvi-evil.php';
file_put_contents($tmpFile, '<?php echo 1;');
try {
    App\Services\UploadService::avatar($yusuf, [
        'error'    => UPLOAD_ERR_OK,
        'tmp_name' => $tmpFile,
        'size'     => filesize($tmpFile),
        'name'     => 'shell.php',
        'type'     => 'application/x-php',
    ]);
    assert_true(false, 'php upload blocked');
} catch (App\AppError $e) {
    assert_true($e->http === 422, 'non-image upload rejected');
}
@unlink($tmpFile);

$h = App\Core\Response::securityHeaders();
assert_true(isset($h['Content-Security-Policy']) && isset($h['X-Content-Type-Options']), 'security headers present');
assert_true(!isset($h['X-Frame-Options']), 'dev does not DENY frames (preview iframe)');

echo "ALL HARDENING TESTS PASSED\n";
@unlink($tmp);
