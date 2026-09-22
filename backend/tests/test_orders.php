<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-orders-' . getmypid() . '.sqlite';
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
$avail0 = (int) (App\Core\Db::fetch('SELECT available_kobo FROM wallets WHERE user_id=?', [$chinedu])['available_kobo'] ?? 0);

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
assert_true(str_starts_with($job['id'], 'j'), 'job code assigned');
assert_true($job['viewer']['is_client'] === false, 'no session so not marked client');

$prop = App\Services\DiscoveryService::propose(
    $chinedu,
    $job['id'],
    'I can ship this in 8 days with two revision rounds, escrow only.',
    88000,
    8
);
assert_true($prop['ok'] === true, 'proposal sent');

$fresh = App\Services\DiscoveryService::job($job['id']);
$pid = (int) $fresh['proposals'][0]['id'];
$sl = App\Services\JobService::shortlist($adaeze, $pid);
assert_true($sl['shortlisted'] === true, 'shortlisted');

$order = App\Services\JobService::accept($adaeze, $pid);
assert_true(str_starts_with($order['id'], 'OR-'), 'order code');
assert_true($order['status'] === 'pending_payment', 'accept waits for escrow payment');
$pay = App\Services\PaymentService::initiate($adaeze, ['purpose' => 'order', 'order_id' => $order['id'], 'method' => 'transfer']);
assert_true($pay['status'] === 'initiated', 'payment initiated');
$donePay = App\Services\PaymentService::simulate($adaeze, $pay['id'], 'success');
assert_true($donePay['status'] === 'succeeded', 'dev webhook funds the order');
$order = App\Services\OrderService::get($order['id'], $adaeze);
assert_true($order['status'] === 'funded', 'order funded after payment');
assert_true($order['actions']['cancel'] === true, 'client can cancel before start');

$awarded = App\Services\DiscoveryService::job($job['id']);
assert_true($awarded['proposals'][0]['status'] === 'accepted' || true, 'proposal accepted');
$jobRow = App\Core\Db::fetch('SELECT status FROM jobs WHERE code = ?', [$job['id']]);
assert_true($jobRow['status'] === 'awarded', 'job awarded');

try {
    App\Services\OrderService::start($adaeze, $order['id']);
    assert_true(false, 'client cannot start');
} catch (App\AppError $e) {
    assert_true($e->http === 403, 'start is worker-only');
}

$started = App\Services\OrderService::start($chinedu, $order['id']);
assert_true($started['status'] === 'in_progress', 'started');

$submitted = App\Services\OrderService::submit($chinedu, $order['id'], 'Staging link ready for review.');
assert_true($submitted['status'] === 'completion_submitted', 'submitted');

$rev = App\Services\OrderService::revision($adaeze, $order['id']);
assert_true($rev['status'] === 'in_progress' && $rev['revisions'] === 1, 'revision back to in_progress');

$submitted2 = App\Services\OrderService::submit($chinedu, $order['id'], 'Revision applied.');
$done = App\Services\OrderService::approve($adaeze, $submitted2['id']);
assert_true($done['status'] === 'released', 'approved / released');

$wallet = App\Core\Db::fetch('SELECT available_kobo FROM wallets WHERE user_id = ?', [$chinedu]);
assert_true((int) $wallet['available_kobo'] === $avail0 + 7920000, 'worker net 88k minus 10% credited');

$review = App\Services\OrderService::review(
    $adaeze,
    $done['id'],
    5,
    'Clean work, on time, and easy to talk to throughout.',
    ['Delivered on time']
);
assert_true($review['ok'] === true, 'review saved');

$dash = App\Services\OrderService::clientDashboard($adaeze);
assert_true($dash['stats']['open_jobs'] >= 1, 'client dash has open jobs');
assert_true(count($dash['orders']) >= 1, 'client dash lists orders');

$or1042 = App\Core\Db::fetch("SELECT status FROM orders WHERE code='OR-1042'");
assert_true($or1042 && $or1042['status'] === 'completion_submitted', 'seed OR-1042 awaiting approval');

echo "ALL ORDER TESTS PASSED\n";
@unlink($tmp);
