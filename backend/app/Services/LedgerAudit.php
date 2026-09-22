<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;

/**
 * Reconstructable money checks. Read-only. Never mutates wallets.
 */
final class LedgerAudit
{
    /** @return array{ok:bool, issues:list<string>, stats:array<string,int>} */
    public static function run(): array
    {
        $issues = [];
        $neg = Db::fetchAll('SELECT user_id, available_kobo, pending_kobo FROM wallets WHERE available_kobo < 0 OR pending_kobo < 0');
        foreach ($neg as $w) {
            $issues[] = 'wallet #' . $w['user_id'] . ' is negative (available ' . $w['available_kobo'] . ', pending ' . $w['pending_kobo'] . ')';
        }

        $released = Db::fetchAll("SELECT id, code, amount_kobo, fee_kobo, worker_id FROM orders WHERE status = 'released'");
        $missingCredit = 0;
        foreach ($released as $o) {
            $row = Db::fetch(
                "SELECT COALESCE(SUM(credit_kobo),0) s FROM ledger WHERE order_id = ? AND account = 'worker' AND credit_kobo > 0",
                [$o['id']]
            );
            $net = (int) $o['amount_kobo'] - (int) $o['fee_kobo'];
            if ((int) ($row['s'] ?? 0) < $net) {
                $missingCredit++;
                $issues[] = $o['code'] . ' released but worker credit ' . (int) ($row['s'] ?? 0) . ' < net ' . $net;
            }
        }

        $open = Db::fetchAll("SELECT id, code, amount_kobo, worker_id FROM orders WHERE status IN ('funded','in_progress','completion_submitted','disputed')");
        foreach ($open as $o) {
            $esc = Db::fetch(
                "SELECT id FROM ledger WHERE order_id = ? AND account = 'escrow'",
                [$o['id']]
            );
            if ($esc === null) {
                $issues[] = $o['code'] . ' is held in escrow with no escrow ledger row';
            }
        }

        $pays = Db::fetchAll("SELECT code, amount_kobo FROM payments WHERE status = 'succeeded' AND amount_kobo <= 0");
        foreach ($pays as $p) {
            $issues[] = $p['code'] . ' succeeded at ₦0';
        }

        $dup = Db::fetchAll(
            "SELECT provider_ref, COUNT(*) c FROM payments WHERE provider_ref IS NOT NULL AND provider_ref != '' GROUP BY provider_ref HAVING c > 1"
        );
        foreach ($dup as $d) {
            $issues[] = 'duplicate provider_ref ' . $d['provider_ref'];
        }

        $stats = [
            'wallets'          => (int) (Db::fetch('SELECT COUNT(*) c FROM wallets')['c'] ?? 0),
            'released_orders'  => count($released),
            'open_escrow'      => count($open),
            'ledger_rows'      => (int) (Db::fetch('SELECT COUNT(*) c FROM ledger')['c'] ?? 0),
            'succeeded_pays'   => (int) (Db::fetch("SELECT COUNT(*) c FROM payments WHERE status='succeeded'")['c'] ?? 0),
            'issue_count'      => count($issues),
        ];
        return ['ok' => $issues === [], 'issues' => $issues, 'stats' => $stats];
    }
}
