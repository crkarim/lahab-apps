<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountTransaction;
use App\Models\CashAccount;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8.2 — Manual transaction entry against the accounts ledger.
 *
 * Three forms behind one controller, switched by ?type=in|out|transfer:
 *   - in        — money received into one account (sale, owner deposit, refund)
 *   - out       — money paid from one account (supplier, utility, fuel)
 *   - transfer  — between two accounts (Bank → bKash, Till → Safe).
 *                 Creates two paired rows in a DB transaction.
 *
 * Each direction captures charge + VAT + AIT separately so reports
 * can roll those columns up without re-parsing descriptions.
 *
 * Branch isolation: managers see/post only their branch's accounts +
 * HQ-wide accounts. Master Admin sees all.
 */
class AccountTransactionController extends Controller
{
    /** Recent ledger entries with filters. */
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $accounts = CashAccount::visibleTo($isMaster ? null : $branchId)->get();
        $accountIds = $accounts->pluck('id');

        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to')   ?? now()->endOfDay();
        $accountFilter = $request->input('account_id');
        $directionFilter = $request->input('direction');

        $rows = AccountTransaction::query()
            ->with(['account:id,name,color,type', 'recordedBy:id,f_name,l_name'])
            ->whereIn('account_id', $accountIds)
            ->whereBetween('transacted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($accountFilter, fn ($q) => $q->where('account_id', $accountFilter))
            ->when($directionFilter, fn ($q) => $q->where('direction', $directionFilter))
            ->orderByDesc('transacted_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $totals = [
            'in'      => (float) $rows->where('direction', 'in')->sum('amount'),
            'out'     => (float) $rows->where('direction', 'out')->sum('amount'),
            'charges' => (float) $rows->sum('charge'),
            'vat_in'  => (float) $rows->sum('vat_input'),
            'vat_out' => (float) $rows->sum('vat_output'),
        ];

        return view('admin-views.account-transaction.index', [
            'rows'             => $rows,
            'accounts'         => $accounts,
            'from'             => $from,
            'to'               => $to,
            'accountFilter'    => $accountFilter,
            'directionFilter'  => $directionFilter,
            'totals'           => $totals,
        ]);
    }

    /** Entry form — type=in/out/transfer. */
    public function create(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $type = $request->input('type', 'in');
        if (!in_array($type, ['in', 'out', 'transfer'], true)) $type = 'in';

        $accounts = CashAccount::visibleTo($isMaster ? null : $branchId)->get();

        return view('admin-views.account-transaction.create', [
            'type'     => $type,
            'accounts' => $accounts,
        ]);
    }

    /** Persist a manual entry (in/out) — single row. */
    public function store(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $validated = $request->validate([
            'direction'      => 'required|in:in,out',
            'account_id'     => 'required|integer|exists:cash_accounts,id',
            'amount'         => 'required|numeric|min:0.01',
            'charge'         => 'nullable|numeric|min:0',
            'vat_input'      => 'nullable|numeric|min:0',
            'vat_output'     => 'nullable|numeric|min:0',
            'tax_amount'     => 'nullable|numeric|min:0',
            'transacted_at'  => 'nullable|date',
            'description'    => 'required|string|max:1000',
        ]);

        $account = CashAccount::find($validated['account_id']);
        if (!$account) return back()->with('error', 'Account not found.');

        if (!$this->canPostTo($admin, $account)) {
            return back()->with('error', 'You can\'t post to that account from your branch.');
        }

        DB::transaction(function () use ($validated, $account, $admin) {
            $txn = AccountTransaction::create([
                'txn_no'              => AccountTransaction::nextTxnNo(),
                'account_id'          => $account->id,
                'direction'           => $validated['direction'],
                'amount'              => (float) $validated['amount'],
                'charge'              => (float) ($validated['charge'] ?? 0),
                'vat_input'           => (float) ($validated['vat_input'] ?? 0),
                'vat_output'          => (float) ($validated['vat_output'] ?? 0),
                'tax_amount'          => (float) ($validated['tax_amount'] ?? 0),
                'branch_id'           => $account->branch_id,
                'description'         => $validated['description'],
                'transacted_at'       => $validated['transacted_at'] ?? now(),
                'recorded_by_admin_id' => $admin->id,
            ]);
            $account->recomputeBalance();
        });

        return redirect()->route('admin.account-transactions.index')
            ->with('success', 'Transaction posted · ' . strtoupper($validated['direction']) . ' Tk ' . number_format((float) $validated['amount'], 2));
    }

    /**
     * Persist a transfer — two paired rows in a single DB transaction.
     * Charge belongs to the OUT leg by convention (where the cashout fee
     * actually hits). The IN leg shows the same gross amount minus
     * implied charge so the destination's balance increases by amount only.
     */
    public function storeTransfer(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $validated = $request->validate([
            'from_account_id' => 'required|integer|exists:cash_accounts,id|different:to_account_id',
            'to_account_id'   => 'required|integer|exists:cash_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'charge'          => 'nullable|numeric|min:0',
            'transacted_at'   => 'nullable|date',
            'description'     => 'required|string|max:1000',
        ]);

        $from = CashAccount::find($validated['from_account_id']);
        $to   = CashAccount::find($validated['to_account_id']);
        if (!$from || !$to) return back()->with('error', 'One of the accounts was not found.');
        if (!$this->canPostTo($admin, $from) || !$this->canPostTo($admin, $to)) {
            return back()->with('error', 'You can\'t post a transfer between those accounts from your branch.');
        }

        $when = $validated['transacted_at'] ?? now();
        $amount = (float) $validated['amount'];
        $charge = (float) ($validated['charge'] ?? 0);

        DB::transaction(function () use ($from, $to, $amount, $charge, $when, $validated, $admin) {
            // Insert OUT leg first; nextTxnNo() for the IN leg has to be
            // called AFTER OUT is persisted so the SELECT MAX() sees it.
            // Otherwise both legs collide on the unique txn_no constraint.
            $out = AccountTransaction::create([
                'txn_no'              => AccountTransaction::nextTxnNo(),
                'account_id'          => $from->id,
                'direction'           => 'out',
                'amount'              => $amount,
                'charge'              => $charge,
                'branch_id'           => $from->branch_id,
                'description'         => 'Transfer → ' . $to->name . ' · ' . $validated['description'],
                'transacted_at'       => $when,
                'recorded_by_admin_id' => $admin->id,
            ]);

            $in = AccountTransaction::create([
                'txn_no'              => AccountTransaction::nextTxnNo(),
                'account_id'          => $to->id,
                'direction'           => 'in',
                'amount'              => $amount,
                'charge'              => 0,
                'paired_txn_id'       => $out->id,
                'branch_id'           => $to->branch_id,
                'description'         => 'Transfer ← ' . $from->name . ' · ' . $validated['description'],
                'transacted_at'       => $when,
                'recorded_by_admin_id' => $admin->id,
            ]);

            $out->paired_txn_id = $in->id;
            $out->save();

            $from->recomputeBalance();
            $to->recomputeBalance();
        });

        return redirect()->route('admin.account-transactions.index')
            ->with('success', 'Transfer posted · ' . $from->name . ' → ' . $to->name . ' Tk ' . number_format($amount, 2)
                . ($charge > 0 ? ' (charge Tk ' . number_format($charge, 2) . ')' : ''));
    }

    /**
     * Delete a transaction — and its pair if it's part of a transfer.
     * Recomputes both affected accounts. Manual-entry rows only;
     * controllers that auto-post (POS/HRM) will set ref_type and we
     * refuse to delete those from this surface to avoid orphaning the
     * source record.
     */
    public function destroy(int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        $txn = AccountTransaction::find($id);
        if (!$txn) return back()->with('error', 'Transaction not found.');

        if (!empty($txn->ref_type)) {
            return back()->with('error', 'Auto-posted transactions can only be removed by reverting the source record (POS sale / payslip / bill).');
        }

        DB::transaction(function () use ($txn) {
            $affectedAccounts = [$txn->account_id];
            if ($txn->paired_txn_id) {
                $pair = AccountTransaction::find($txn->paired_txn_id);
                if ($pair) {
                    $affectedAccounts[] = $pair->account_id;
                    $pair->delete();
                }
            }
            $txn->delete();
            foreach (array_unique($affectedAccounts) as $aid) {
                CashAccount::find($aid)?->recomputeBalance();
            }
        });

        return back()->with('success', 'Transaction removed and balances recomputed.');
    }

    private function canPostTo($admin, CashAccount $account): bool
    {
        $isMaster = (int) $admin->admin_role_id === 1;
        if ($isMaster) return true;
        if (!$admin->branch_id) return false;
        // Branch managers can post to their branch's accounts + HQ-wide.
        return is_null($account->branch_id) || (int) $account->branch_id === (int) $admin->branch_id;
    }
}
