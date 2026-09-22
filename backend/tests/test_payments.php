<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-pay-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('SQLITE_PATH=' . $tmp);
putenv('APP_ENV=dev');

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

$adaeze = (int) App\Models\User::findByPhone('2348052223344')['id'];
$chinedu = (int) App\Models\User::findByPhone('2348031112233')['id'];
$yusuf = (int) App\Models\User::findByPhone('2348100000010')['id']; // unverified painter

$job = App\Services\JobService::create($adaeze, [
    'title' => 'Need a product landing page for spices',
    'description' => 'One-page site from a Figma file, mobile-first, contact form, and a spice catalogue section.',
    'category' => 'Web Development',
    'budget_naira' => 90000,
    'budget_type' => 'fixed',
    'work_mode' => 'remote',
    'state' => 'Lagos',
    'city' => 'Ikeja',
]);
App\Services\DiscoveryService::propose($chinedu, $job['id'], 'I can ship this in 8 days with two revision rounds, escrow only.', 88000, 8);
$fresh = App\Services\DiscoveryService::job($job['id']);
$order = App\Services\JobService::accept($adaeze, (int) $fresh['proposals'][0]['id']);
assert_true($order['status'] === 'pending_payment', 'hire lands in checkout, not funded');

$pay = App\Services\PaymentService::initiate($adaeze, ['purpose' => 'order', 'order_id' => $order['id'], 'method' => 'transfer']);
assert_true(str_starts_with($pay['id'], 'PAY-') && $pay['virtual_account']['bank'] === 'GTBank', 'virtual account for transfer');
$again = App\Services\PaymentService::initiate($adaeze, ['purpose' => 'order', 'order_id' => $order['id'], 'method' => 'transfer']);
assert_true($again['id'] === $pay['id'], 're-initiate is idempotent while pending');

try {
    App\Services\PaymentService::webhookPaystack('{"event":"charge.success"}', 'bad');
    assert_true(false, 'bad sig rejected');
} catch (App\AppError $e) {
    assert_true($e->http === 403, 'bad webhook signature');
}

$funded = App\Services\PaymentService::simulate($adaeze, $pay['id'], 'success');
assert_true($funded['status'] === 'succeeded', 'simulate funds');
$replay = App\Services\PaymentService::simulate($adaeze, $pay['id'], 'success');
assert_true(!empty($replay['replayed']), 'duplicate webhook is a no-op');
$live = App\Services\OrderService::get($order['id'], $adaeze);
assert_true($live['status'] === 'funded', 'order funded from webhook');
$wallet = App\Core\Db::fetch('SELECT pending_kobo FROM wallets WHERE user_id=?', [$chinedu]);
$before = (int) $wallet['pending_kobo'];
assert_true($before >= 8800000, 'worker pending includes the new ₦88,000 escrow (have ' . $before . ')');

$svc = App\Services\OrderService::openFromService($adaeze, 's-w01', 'Standard');
assert_true($svc['status'] === 'pending_payment' && $svc['amount_naira'] === 85000, 'direct service hire Standard ₦85k');

$vpay = App\Services\PaymentService::initiate($yusuf, ['purpose' => 'verification', 'method' => 'ussd']);
assert_true($vpay['amount_naira'] === 5000, 'verification is ₦5,000');
App\Services\PaymentService::simulate($yusuf, $vpay['id'], 'success');
$sub = App\Services\TrustService::submit($yusuf, [
    'full_name' => 'Yusuf Garba',
    'id_type' => 'National ID (NIN)',
    'id_number' => '12345678901',
]);
assert_true($sub['status'] === 'pending', 'verification pending review — not a skill badge');

$ppay = App\Services\PaymentService::initiate($chinedu, ['purpose' => 'promotion', 'plan' => 'search', 'method' => 'card']);
assert_true($ppay['amount_naira'] === 2500, 'search boost ₦2,500');
App\Services\PaymentService::simulate($chinedu, $ppay['id'], 'success');
$promo = App\Services\TrustService::promotions($chinedu);
assert_true($promo['active']['plan'] === 'search', 'promo active');
$w = App\Core\Db::fetch('SELECT promo FROM profiles WHERE user_id=?', [$chinedu]);
assert_true((int) $w['promo'] === 1, 'profile promo flag — visibility only');

echo "ALL PAYMENT TESTS PASSED\n";
@unlink($tmp);
