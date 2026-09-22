<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-wallet-' . getmypid() . '.sqlite';
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

$adaeze = (int) App\Models\User::findByPhone('2348052223344')['id'];
$chinedu = (int) App\Models\User::findByPhone('2348031112233')['id'];
$admin = (int) App\Models\User::findByPhone('2348000000001')['id'];

$job = App\Services\JobService::create($adaeze, [
    'title' => 'Need a product catalogue page',
    'description' => 'A simple catalogue with photos, prices, and a WhatsApp button. Mobile-first.',
    'category' => 'Web Development',
    'budget_naira' => 80000,
    'budget_type' => 'fixed',
    'work_mode' => 'remote',
    'state' => 'Lagos',
    'city' => 'Ikeja',
]);
App\Services\DiscoveryService::propose($chinedu, $job['id'], 'I can ship this in 7 days with two revision rounds, escrow only.', 80000, 7);
$fresh = App\Services\DiscoveryService::job($job['id']);
$order = App\Services\JobService::accept($adaeze, (int) $fresh['proposals'][0]['id']);
$pay = App\Services\PaymentService::initiate($adaeze, ['purpose' => 'order', 'order_id' => $order['id'], 'method' => 'transfer']);
App\Services\PaymentService::simulate($adaeze, $pay['id'], 'success');
App\Services\OrderService::start($chinedu, $order['id']);
App\Services\OrderService::submit($chinedu, $order['id'], 'Catalogue page is live. Two revision rounds included.');
App\Services\OrderService::approve($adaeze, $order['id']);

$w = App\Services\WalletService::get($chinedu);
assert_true($w['available_naira'] >= 72000, 'release credits worker net (90%)');
$before = $w['available_kobo'];

try {
    App\Core\Session::login($chinedu);
    App\Services\WalletService::start($chinedu, [
        'amount_naira' => 1000,
        'bank_name' => 'GTBank',
        'account_number' => '0123456789',
        'account_name' => 'Chinedu Okafor',
    ], '127.0.0.1');
    assert_true(false, 'below minimum rejected');
} catch (App\AppError $e) {
    assert_true($e->http === 422, 'min withdrawal ₦5,000');
}

App\Core\Session::login($chinedu);
$otp = App\Services\WalletService::start($chinedu, [
    'amount_naira' => 20000,
    'bank_name' => 'GTBank',
    'account_number' => '0123456789',
    'account_name' => 'Chinedu Okafor',
], '127.0.0.1');
assert_true(!empty($otp['dev_code']) && $otp['purpose'] === 'withdraw', 'withdraw OTP is not login');

$wd = App\Services\WalletService::confirm($chinedu, $otp['dev_code']);
assert_true($wd['status'] === 'pending' && $wd['amount_naira'] === 20000, 'withdrawal held as pending');
$after = App\Services\WalletService::get($chinedu);
assert_true($after['available_kobo'] === $before - 2000000, 'available drops on request');

$tx = App\Services\WalletService::transactions($chinedu);
assert_true(count($tx) >= 2, 'ledger has settlement + withdrawal');

App\Core\Session::login($admin);
$paid = App\Services\WalletService::adminAction($admin, (string) $wd['numeric_id'], 'paid');
assert_true($paid['status'] === 'paid', 'admin marks paid');
$final = App\Services\WalletService::get($chinedu);
assert_true($final['available_kobo'] === $after['available_kobo'], 'paid does not double-debit');

$svc = App\Services\ServiceCatalog::create($chinedu, [
    'title' => 'Landing page for restaurants',
    'description' => 'Figma to a fast one-page site with a contact form and menu section.',
    'category' => 'Web Development',
    'work_mode' => 'remote',
    'packages' => [
        ['name' => 'Starter', 'price_naira' => 45000, 'days' => 5, 'revisions' => 1],
        ['name' => 'Standard', 'price_naira' => 85000, 'days' => 10, 'revisions' => 2],
    ],
]);
assert_true($svc['live'] === true && str_starts_with($svc['id'], 's-'), 'new service goes live');
$paused = App\Services\ServiceCatalog::pause($chinedu, $svc['id']);
assert_true($paused['live'] === false, 'pause hides from search');
$again = App\Services\ServiceCatalog::update($chinedu, $svc['id'], [
    'title' => 'Landing page for restaurants and cafes',
    'description' => 'Figma to a fast one-page site with a contact form and menu section.',
    'category' => 'Web Development',
    'work_mode' => 'hybrid',
    'packages' => [
        ['name' => 'Standard', 'price_naira' => 90000, 'days' => 10, 'revisions' => 2],
    ],
]);
assert_true($again['mode'] === 'hybrid' && $again['packages'][0]['price_naira'] === 90000, 'worker can edit packages');

$me = App\Services\AuthService::updateProfile($chinedu, [
    'headline' => 'Frontend developer — WordPress and React',
    'work_mode' => 'remote',
]);
assert_true($me['headline'] === 'Frontend developer — WordPress and React', 'profile edit live');

$dash = App\Services\OrderService::workerDashboard($chinedu);
assert_true(($dash['profile_strength'] ?? 0) >= 50, 'profile strength on worker dashboard');

$againBal = App\Services\WalletService::get($chinedu);
App\Services\WalletService::syncFromOrders();
$idem = App\Services\WalletService::get($chinedu);
assert_true($idem['available_kobo'] === $againBal['available_kobo'], 'wallet sync is idempotent');

$adminQueue = App\Services\WalletService::adminList();
assert_true(count($adminQueue) >= 1 && $adminQueue[0]['status'] === 'paid', 'admin withdrawal queue');

$props = App\Services\OrderService::myProposals($chinedu);
$sent = array_values(array_filter($props, static fn ($p) => $p['status'] === 'sent'));
assert_true(count($sent) > 0, 'seeded sent proposal');
$wdProp = App\Services\JobService::withdrawProposal($chinedu, (int) $sent[0]['id']);
assert_true($wdProp['status'] === 'withdrawn', 'worker can withdraw a sent proposal');

echo "ALL WALLET TESTS PASSED\n";
@unlink($tmp);
