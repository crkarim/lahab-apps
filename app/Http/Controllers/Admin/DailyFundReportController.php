<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountTransaction;
use App\Models\CashAccount;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * Phase 8.3 — Daily fund report.
 *
 * For a chosen date, per account:
 *   opening (start of day)
 *   + sum of inflows that day
 *   - sum of outflows that day
 *   - sum of charges that day
 *   = closing (end of day)
 *
 * Also surfaces the day's VAT in/out totals + total charges, so HR /
 * accounting can reconcile against bank statements at end-of-day.
 *
 * Query strategy: pull all transactions on the date in one query, then
 * group by account in PHP. Closing for date X is computed as opening +
 * day movements (no separate SUM-up-to-end-of-day query).
 *
 * Opening for the chosen date = account.opening_balance + sum of all
 * transaction impacts strictly before the chosen date's start. One
 * subquery per account is acceptable for the small N (typically 5-20
 * accounts).
 */
class DailyFundReportController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $date = $request->date('date') ?? now()->startOfDay();
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay   = $date->copy()->endOfDay();

        $accounts = CashAccount::visibleTo($isMaster ? null : $branchId)->get();

        // Pull every txn on the day in one go.
        $accountIds = $accounts->pluck('id');
        $dayTxns = AccountTransaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereBetween('transacted_at', [$startOfDay, $endOfDay])
            ->orderBy('transacted_at')
            ->get();

        $byAccount = $dayTxns->groupBy('account_id');

        $rows = [];
        $grand = ['opening' => 0, 'in' => 0, 'out' => 0, 'charge' => 0, 'vat_in' => 0, 'vat_out' => 0, 'closing' => 0];

        foreach ($accounts as $acc) {
            // Opening for this date = opening_balance + sum of impacts strictly before start of day.
            $beforeImpact = AccountTransaction::query()
                ->where('account_id', $acc->id)
                ->where('transacted_at', '<', $startOfDay)
                ->selectRaw("
                    SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) -
                    SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) -
                    SUM(charge) AS impact
                ")
                ->value('impact');
            $opening = round((float) $acc->opening_balance + (float) $beforeImpact, 2);

            $dayRows = $byAccount->get($acc->id, collect());
            $in     = round((float) $dayRows->where('direction', 'in')->sum('amount'), 2);
            $out    = round((float) $dayRows->where('direction', 'out')->sum('amount'), 2);
            $charge = round((float) $dayRows->sum('charge'), 2);
            $vatIn  = round((float) $dayRows->sum('vat_input'), 2);
            $vatOut = round((float) $dayRows->sum('vat_output'), 2);

            $closing = round($opening + $in - $out - $charge, 2);

            $rows[] = [
                'account' => $acc,
                'opening' => $opening,
                'in'      => $in,
                'out'     => $out,
                'charge'  => $charge,
                'vat_in'  => $vatIn,
                'vat_out' => $vatOut,
                'closing' => $closing,
                'movements' => $dayRows,
            ];

            $grand['opening'] += $opening;
            $grand['in']      += $in;
            $grand['out']     += $out;
            $grand['charge']  += $charge;
            $grand['vat_in']  += $vatIn;
            $grand['vat_out'] += $vatOut;
            $grand['closing'] += $closing;
        }

        return view('admin-views.daily-fund.index', [
            'date'    => $date,
            'rows'    => $rows,
            'grand'   => $grand,
            'txnsCount' => $dayTxns->count(),
        ]);
    }
}
