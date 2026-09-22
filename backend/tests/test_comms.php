<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/skilvi-comms-' . getmypid() . '.sqlite';
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

$adaeze  = (int) App\Models\User::findByPhone('2348052223344')['id'];
$chinedu = (int) App\Models\User::findByPhone('2348031112233')['id'];
$admin   = (int) App\Models\User::findByPhone('2348000000001')['id'];
$tunde   = (int) App\Models\User::findByPhone('2348075550005')['id'];

$threads = App\Services\MessageService::list($chinedu);
assert_true(count($threads) >= 1, 'seeded order thread listed');
$tid = (string) $threads[0]['id'];
$got = App\Services\MessageService::get($chinedu, $tid);
assert_true(count($got['messages']) >= 1, 'thread has messages');

$sent = App\Services\MessageService::send($chinedu, $tid, 'Pushed the hero swap — staging is updated.');
assert_true(count($sent['messages']) === count($got['messages']) + 1, 'message appended');

$notes = App\Services\NotificationService::list($adaeze);
assert_true($notes['unread'] >= 1, 'client got a message notification');
$badges = App\Services\NotificationService::badges($adaeze);
assert_true($badges['notifications'] >= 1, 'badge count tracks unread');
$cleared = App\Services\NotificationService::readAll($adaeze);
assert_true($cleared['unread'] === 0, 'read-all zeros unread');

$dsp = App\Services\DisputeService::open(
    $adaeze,
    'OR-1019',
    'Work not as described',
    'Wiring stopped after first-fix and the worker has not returned in ten days. Please freeze escrow.'
);
assert_true(str_starts_with($dsp['id'], 'DSP-'), 'dispute code assigned');
assert_true($dsp['status'] === 'open', 'dispute open');
$frozen = App\Services\OrderService::get('OR-1019', $adaeze);
assert_true($frozen['status'] === 'disputed', 'order frozen as disputed');
assert_true($frozen['actions']['approve'] === false, 'approve hidden while disputed');
assert_true($frozen['dispute']['id'] === $dsp['id'], 'order points at dispute');

try {
    App\Services\OrderService::start($tunde, 'OR-1019');
    assert_true(false, 'start blocked while disputed');
} catch (App\AppError $e) {
    assert_true($e->http === 409, 'start blocked 409 while disputed');
}

$reply = App\Services\DisputeService::reply($tunde, $dsp['id'], 'I was on site Tuesday — photos attached in the thread.');
assert_true(count($reply['messages']) >= 2, 'dispute reply stored');

$listed = App\Services\DisputeService::list($adaeze);
assert_true(count($listed) >= 1, 'party sees the case');

$adminQ = App\Services\DisputeService::adminList();
assert_true($adminQ[0]['status'] === 'open', 'admin queue surfaces open cases first');

$before = App\Core\Db::fetch('SELECT pending_kobo, available_kobo FROM wallets WHERE user_id=?', [$tunde]);
$resolved = App\Services\DisputeService::resolve(
    $admin,
    $dsp['id'],
    'split',
    60,
    'Worker evidenced first-fix on 3 of 5 rooms. 60/40 split per policy.'
);
assert_true($resolved['status'] === 'resolved', 'split closes the case');
assert_true($resolved['decision'] === 'split' && $resolved['worker_pct'] === 60, 'split recorded');
$ord = App\Core\Db::fetch("SELECT status FROM orders WHERE code='OR-1019'");
assert_true($ord['status'] === 'released', 'split with worker share marks order released');
$after = App\Core\Db::fetch('SELECT pending_kobo, available_kobo FROM wallets WHERE user_id=?', [$tunde]);
$gross = (int) round(45000000 * 0.6);
$fee = (int) round($gross * 0.10);
$net = $gross - $fee;
assert_true((int) $after['available_kobo'] === (int) $before['available_kobo'] + $net, 'worker received 60% net of fee');
assert_true((int) $after['pending_kobo'] === max(0, (int) $before['pending_kobo'] - 45000000), 'full escrow left pending');

$dsp2 = App\Services\DisputeService::open(
    $chinedu,
    'OR-1042',
    'Client unresponsive',
    'Delivery submitted days ago with no approval or revision request from the client.'
);
try {
    App\Services\OrderService::approve($adaeze, 'OR-1042');
    assert_true(false, 'approve blocked while disputed');
} catch (App\AppError $e) {
    assert_true($e->http === 409, 'approve blocked 409 while disputed');
}

$full = App\Services\DisputeService::resolve(
    $admin,
    $dsp2['id'],
    'release',
    100,
    'Work is complete on staging. Full release to the worker.'
);
assert_true($full['decision'] === 'release', 'full release decision');

App\Core\Db::run("UPDATE profiles SET promo = 1 WHERE user_id = ?", [$chinedu]);
App\Core\Db::run(
    "INSERT INTO promotions (user_id, plan, amount_kobo, status, starts_at, ends_at, created_at)
     VALUES (?, 'search', 250000, 'active', '2026-01-01 00:00:00', '2026-01-08 00:00:00', '2026-01-01 00:00:00')",
    [$chinedu]
);
App\Services\TrustService::expirePromos();
$pr = App\Core\Db::fetch('SELECT promo FROM profiles WHERE user_id=?', [$chinedu]);
$st = App\Core\Db::fetch("SELECT status FROM promotions WHERE user_id=? ORDER BY id DESC LIMIT 1", [$chinedu]);
assert_true((int) $pr['promo'] === 0, 'expired promo cleared from profile');
assert_true($st['status'] === 'expired', 'promotion row marked expired');

$ifeanyi = (int) App\Models\User::findByPhone('2348010001028')['id'];
App\Core\Db::run(
    "INSERT INTO verifications (user_id, status, amount_kobo, notes, created_at, updated_at)
     VALUES (?, 'pending', 500000, 'nins submitted', ?, ?)",
    [$ifeanyi, now_iso(), now_iso()]
);
$vid = (string) App\Core\Db::lastInsertId();
$ok = App\Services\TrustService::review($admin, $vid, 'approve', '');
assert_true($ok['status'] === 'approved', 'admin approved identity check');
$vf = App\Core\Db::fetch('SELECT verified FROM profiles WHERE user_id=?', [$ifeanyi]);
assert_true((int) $vf['verified'] === 1, 'profile verified flag set');
$note = App\Core\Db::fetch(
    "SELECT title FROM notifications WHERE user_id=? AND title LIKE 'Identity%' ORDER BY id DESC LIMIT 1",
    [$ifeanyi]
);
assert_true($note !== null, 'verification notice sent');
assert_true(!str_contains(strtolower($note['title']), 'certif'), 'badge copy does not say certified');

echo "ALL COMMS TESTS PASSED\n";
@unlink($tmp);
