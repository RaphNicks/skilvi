<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-admin-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('SQLITE_PATH=' . $tmp);
putenv('APP_ENV=dev');

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Schema::install();
App\Core\Seed::run();

function assert_true($c, string $msg): void
{
    if (!$c) {
        fwrite(STDERR, "FAIL $msg\n");
        exit(1);
    }
    echo "ok  $msg\n";
}

$admin   = (int) App\Models\User::findByPhone('2348000000001')['id'];
$chinedu = (int) App\Models\User::findByPhone('2348031112233')['id'];
$ifeanyi = (int) App\Models\User::findByPhone('2348010001028')['id'];
$tunde   = (int) App\Models\User::findByPhone('2348075550005')['id'];

$dash = App\Services\AdminService::dashboard();
assert_true(count($dash['kpis']) === 6, 'dashboard has 6 kpis');
assert_true(isset($dash['gmv'][0]['kobo']), 'gmv series present');

$users = App\Services\AdminService::users('Chinedu', 'worker', '');
assert_true(count($users) >= 1 && $users[0]['name'] === 'Chinedu Okafor', 'user search finds Chinedu');

try {
    App\Services\AdminService::userAction($admin, (string) $admin, 'suspend', 'testing self', '127.0.0.1');
    assert_true(false, 'cannot suspend self');
} catch (App\AppError $e) {
    assert_true($e->http === 403, 'self-suspend forbidden');
}

App\Services\AdminService::userAction($admin, (string) $ifeanyi, 'suspend', 'Test hold for review of account activity.', '127.0.0.1');
$ife = App\Models\User::find($ifeanyi);
assert_true($ife['status'] === 'suspended', 'ifeanyi suspended');
try {
    App\Services\AuthService::loginStart('2348010001028', 'password1', '127.0.0.1');
    assert_true(false, 'suspended cannot login');
} catch (App\AppError $e) {
    assert_true($e->http === 403, 'suspended login 403');
}
App\Services\AdminService::userAction($admin, (string) $ifeanyi, 'activate', '', '127.0.0.1');
$ife = App\Models\User::find($ifeanyi);
assert_true($ife['status'] === 'active', 'ifeanyi reactivated');

$orders = App\Services\AdminService::orders('funded', '');
assert_true(count($orders) >= 1, 'funded orders listed');
$or1019 = null;
foreach (App\Services\AdminService::orders('', '') as $o) {
    if ($o['id'] === 'OR-1019') {
        $or1019 = $o;
    }
}
assert_true($or1019 && $or1019['can_release'] === true, 'OR-1019 can force-release');

$before = App\Core\Db::fetch('SELECT pending_kobo, available_kobo FROM wallets WHERE user_id=?', [$tunde]);
try {
    App\Core\Db::run("UPDATE orders SET status='disputed' WHERE code='OR-1036'");
    App\Services\AdminService::orderAction($admin, 'OR-1036', 'release', 'Should not bypass the dispute queue at all.', '127.0.0.1');
    assert_true(false, 'disputed force-release blocked');
} catch (App\AppError $e) {
    assert_true($e->http === 409, 'disputed force-release 409');
    App\Core\Db::run("UPDATE orders SET status='in_progress' WHERE code='OR-1036'");
}

$rel = App\Services\AdminService::orderAction($admin, 'OR-1019', 'release', 'Force-release after client went silent on a funded job.', '127.0.0.1');
assert_true($rel['status'] === 'released', 'force-release settled the order');
$after = App\Core\Db::fetch('SELECT pending_kobo, available_kobo FROM wallets WHERE user_id=?', [$tunde]);
assert_true((int) $after['available_kobo'] > (int) $before['available_kobo'], 'worker wallet credited');

$pays = App\Services\AdminService::payments('', '', '');
assert_true(is_array($pays), 'payments list');

$vers = App\Services\AdminService::verifications();
$pending = array_values(array_filter($vers, static fn ($v) => $v['status'] === 'pending'));
assert_true(count($pending) >= 1, 'pending verification in queue');
App\Services\TrustService::review($admin, (string) $pending[0]['id'], 'approve', '');
$vf = App\Core\Db::fetch('SELECT verified FROM profiles WHERE user_id=?', [$tunde]);
assert_true((int) $vf['verified'] === 1, 'tunde badge on after approve');

$reps = App\Services\AdminService::reports();
assert_true(count($reps) >= 1, 'reports queue seeded');
App\Services\AdminService::reportAction($admin, $reps[0]['id'], 'dismiss', 'No evidence', '127.0.0.1');
$reps2 = App\Services\AdminService::reports();
assert_true($reps2[0]['status'] === 'dismissed', 'report dismissed');

$cats = App\Services\AdminService::categories();
assert_true(count($cats) >= 1 && count($cats[0]['subs']) >= 1, 'category tree');
$subId = (string) $cats[0]['subs'][0]['id'];
App\Services\AdminService::categoryAction($admin, $subId, 'archive', '127.0.0.1');
$cats2 = App\Services\AdminService::categories();
$found = false;
foreach ($cats2[0]['subs'] as $s) {
    if ((string) $s['id'] === $subId) {
        $found = $s['active'] === false;
    }
}
assert_true($found, 'sub-category archived');

$s = App\Services\AdminService::saveSettings($admin, ['fee_percent' => 12], '127.0.0.1');
assert_true($s['fee_percent'] === 12, 'fee percent saved');

$tickets = App\Services\AdminService::tickets();
assert_true(count($tickets) >= 1, 'support tickets');
$reply = App\Services\AdminService::ticketReply($admin, (string) $tickets[0]['id'], 'Only the paying client can approve on-site completion.', '127.0.0.1');
assert_true(count($reply['messages']) >= 2, 'support reply stored');

$log = App\Services\AdminService::auditLog();
assert_true(count($log) >= 3, 'audit log has staff actions');

$csv = App\Services\AdminService::analyticsCsv();
assert_true(str_starts_with($csv, 'metric,value'), 'csv export header');

$promos = App\Services\AdminService::promotions();
assert_true(isset($promos['items']), 'promotions payload');

echo "ALL ADMIN TESTS PASSED\n";
@unlink($tmp);
