<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\CashAccount;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 8.1 — Cash accounts (wallet) admin surface.
 *
 * Master Admin manages all accounts; branch managers see HQ-wide rows
 * + their own branch's rows. Editing branch_id requires Master Admin.
 *
 * Hard delete blocked once any transaction has posted to the account —
 * deactivate instead. Keeps the audit trail intact.
 */
class CashAccountController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $accounts = CashAccount::query()
            ->with(['branch:id,name'])
            ->withCount('transactions')
            ->when(!$isMaster && $branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);

        // Totals tile — sum current balance grouped by type, viewer scope.
        $totalsByType = $accounts->where('is_active', true)->groupBy('type')->map(fn ($g) => [
            'count'   => $g->count(),
            'balance' => (float) $g->sum('current_balance'),
        ]);

        return view('admin-views.cash-account.index', [
            'accounts'     => $accounts,
            'branches'     => $branches,
            'totalsByType' => $totalsByType,
            'isMaster'     => $isMaster,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $validated = $request->validate([
            'name'            => 'required|string|max:80',
            'code'            => 'required|string|max:32|alpha_dash',
            'type'            => 'required|in:cash,bank,mfs,cheque',
            'provider'        => 'nullable|string|max:60',
            'account_number'  => 'nullable|string|max:40',
            'branch_id'       => 'nullable|integer|exists:branches,id',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'color'           => 'nullable|string|max:16',
            'sort_order'      => 'nullable|integer',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $branchId = $validated['branch_id'] ?? null;
        if (!$isMaster) {
            // Branch managers can only create accounts in their own branch.
            $branchId = $admin?->branch_id;
        }

        // Code uniqueness scoped to branch (matches the migration's unique key).
        $exists = CashAccount::query()
            ->where('code', $validated['code'])
            ->where('branch_id', $branchId)
            ->exists();
        if ($exists) {
            return back()->with('error', 'An account with this code already exists in scope.');
        }

        $opening = (float) ($validated['opening_balance'] ?? 0);

        CashAccount::create([
            'name'            => $validated['name'],
            'code'            => $validated['code'],
            'type'            => $validated['type'],
            'provider'        => $validated['provider'] ?? null,
            'account_number'  => $validated['account_number'] ?? null,
            'branch_id'       => $branchId,
            'opening_balance' => $opening,
            'opening_date'    => $validated['opening_date'] ?? now()->toDateString(),
            'current_balance' => $opening, // no txns yet → opening = current
            'color'           => $validated['color'] ?: '#6A6A70',
            'sort_order'      => (int) ($validated['sort_order'] ?? 0),
            'notes'           => $validated['notes'] ?? null,
            'is_active'       => true,
        ]);

        return back()->with('success', 'Account created · ' . $validated['name']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $acc = CashAccount::find($id);
        if (!$acc) return back()->with('error', 'Account not found.');

        if (!$isMaster && $admin?->branch_id && $acc->branch_id !== $admin->branch_id) {
            return back()->with('error', 'You can only edit your branch\'s accounts.');
        }

        $validated = $request->validate([
            'name'            => 'required|string|max:80',
            'provider'        => 'nullable|string|max:60',
            'account_number'  => 'nullable|string|max:40',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'color'           => 'nullable|string|max:16',
            'sort_order'      => 'nullable|integer',
            'notes'           => 'nullable|string|max:1000',
            'is_active'       => 'nullable|boolean',
        ]);

        $oldOpening = (float) $acc->opening_balance;
        $newOpening = (float) ($validated['opening_balance'] ?? $oldOpening);

        $acc->forceFill([
            'name'            => $validated['name'],
            'provider'        => $validated['provider'] ?? null,
            'account_number'  => $validated['account_number'] ?? null,
            'opening_balance' => $newOpening,
            'opening_date'    => $validated['opening_date'] ?? $acc->opening_date,
            'color'           => $validated['color'] ?: $acc->color,
            'sort_order'      => (int) ($validated['sort_order'] ?? 0),
            'notes'           => $validated['notes'] ?? null,
            'is_active'       => (bool) ($validated['is_active'] ?? true),
        ])->save();

        // If opening changed, current_balance shifts by the delta — but
        // safer to just recompute from txns.
        if (abs($newOpening - $oldOpening) > 0.001) {
            $acc->recomputeBalance();
        }

        return back()->with('success', 'Account updated · ' . $acc->name);
    }

    /**
     * Hard-delete blocked once a single transaction has posted —
     * deactivate instead so historical reports still resolve names.
     */
    public function destroy(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $acc = CashAccount::withCount('transactions')->find($id);
        if (!$acc) return back()->with('error', 'Account not found.');

        if (!$isMaster && $admin?->branch_id && $acc->branch_id !== $admin->branch_id) {
            return back()->with('error', 'You can only delete your branch\'s accounts.');
        }

        if ($acc->transactions_count > 0) {
            $acc->is_active = false;
            $acc->save();
            return back()->with('success', $acc->name . ' deactivated (has ' . $acc->transactions_count . ' transaction(s) — cannot hard-delete).');
        }

        $acc->delete();
        return back()->with('success', 'Account deleted.');
    }

    /** Manual resync — drift safety. Recomputes current_balance from ledger. */
    public function recompute(int $id): RedirectResponse
    {
        $acc = CashAccount::find($id);
        if (!$acc) return back()->with('error', 'Account not found.');
        $before = (float) $acc->current_balance;
        $acc->recomputeBalance();
        $acc->refresh();
        $delta = round($acc->current_balance - $before, 2);
        $msg = 'Balance recomputed for ' . $acc->name . '. ';
        $msg .= abs($delta) < 0.005
            ? 'No drift — already in sync.'
            : 'Adjusted by Tk ' . number_format($delta, 2) . '.';
        return back()->with('success', $msg);
    }
}
