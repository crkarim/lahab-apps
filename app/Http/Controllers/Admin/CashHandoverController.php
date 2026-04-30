<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Models\CashHandover;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cashier-facing surface for the cash handover lifecycle.
 *
 * Pending list = handovers a waiter has submitted but no cashier has
 * acknowledged yet. Each row exposes a Receive button so the cashier
 * (or any branch admin) can ack the batch in one tap. Disputed status
 * is reachable from the row when the count doesn't match the claim.
 */
class CashHandoverController extends Controller
{
    /** Pending handovers + a recent-history strip + live drawer per waiter. */
    public function index(Request $request): Renderable
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $base = CashHandover::query()
            ->with(['waiter:id,f_name,l_name', 'cashier:id,f_name,l_name', 'branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $pending = (clone $base)
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->get();

        $recent = (clone $base)
            ->whereIn('status', ['received', 'disputed'])
            ->orderByDesc('received_at')
            ->limit(20)
            ->get();

        // Live drawer per waiter — sum of cash partial-payments that
        // haven't been submitted to a handover yet, grouped by the
        // admin who placed the order. Shows the cashier exactly which
        // waiter is holding what before any submission lands.
        $liveDrawers = \App\Models\OrderPartialPayment::query()
            ->whereNull('handover_id')
            ->where('paid_with', 'cash')
            ->whereHas('order', function ($q) use ($branchId) {
                $q->where('payment_status', 'paid');
                if ($branchId) $q->where('branch_id', $branchId);
            })
            ->join('orders', 'orders.id', '=', 'order_partial_payments.order_id')
            ->join('admins', 'admins.id', '=', 'orders.placed_by_admin_id')
            ->select(
                'admins.id as waiter_id',
                'admins.f_name',
                'admins.l_name',
                \DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                \DB::raw('SUM(order_partial_payments.paid_amount) as cash_total'),
            )
            ->groupBy('admins.id', 'admins.f_name', 'admins.l_name')
            ->orderByDesc('cash_total')
            ->get();

        return view('admin-views.cash-handover.index', [
            'pending'      => $pending,
            'recent'       => $recent,
            'liveDrawers'  => $liveDrawers,
        ]);
    }

    /** Cashier ack — moves pending → received. */
    public function receive(Request $request, int $id): RedirectResponse
    {
        $handover = $this->scoped($request, $id);
        if (!$handover) {
            return back()->with('error', 'Handover not found or already actioned.');
        }
        if ($handover->status !== 'pending') {
            return back()->with('error', 'Handover is no longer pending.');
        }

        // Attach to the cashier's open shift so the shift's cash
        // reconciliation includes this handover. Best-effort —
        // null is fine if no shift is open (warn-but-allow).
        $shiftId = null;
        try {
            $shiftId = \App\Http\Controllers\Admin\ShiftController::currentFor(auth('admin')->user()->id)?->id;
        } catch (\Throwable $e) { /* shifts module not migrated yet */ }

        $handover->update([
            'cashier_id'  => auth('admin')->user()->id,
            'shift_id'    => $shiftId,
            'received_at' => now(),
            'status'      => 'received',
        ]);

        // Tell the placing waiter their cash was settled — closes the
        // loop so they don't refresh the drawer wondering if the
        // handshake completed.
        \App\CentralLogics\WaiterPushHelper::pushHandoverReceived($handover->fresh());

        return back()->with('success', 'Handover received · ৳' . number_format($handover->total, 2) . ' added to drawer.');
    }

    /**
     * Cashier-initiated collection — pulls all unhandover'd cash from
     * one waiter directly into a `received` handover row, skipping the
     * "wait for waiter to tap Submit" step. Useful when:
     *   - shift is ending and a waiter forgot to submit
     *   - cashier wants the floor reconciled in real time
     *   - a waiter is mid-service and can't open the app
     *
     * The waiter's drawer immediately reads ৳0 because all rows now
     * have `handover_id` set; their app shows "submitted by cashier"
     * audit info on next refresh.
     */
    public function collectFromWaiter(Request $request, int $waiterId): RedirectResponse
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        // Find the waiter — must be in the same branch (or HQ can pick anyone).
        $waiter = \App\Model\Admin::query()
            ->where('id', $waiterId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->first();
        if (!$waiter) {
            return back()->with('error', 'Waiter not found in your branch.');
        }

        $note = trim((string) $request->input('notes', ''));
        $effectiveBranch = $branchId ?? $waiter->branch_id;

        $handover = \DB::transaction(function () use ($waiter, $admin, $effectiveBranch, $note) {
            // Lock waiter's unhandover'd cash rows.
            $rows = \App\Models\OrderPartialPayment::query()
                ->whereNull('handover_id')
                ->where('paid_with', 'cash')
                ->whereHas('order', function ($q) use ($waiter, $effectiveBranch) {
                    $q->where('placed_by_admin_id', $waiter->id);
                    if ($effectiveBranch) $q->where('branch_id', $effectiveBranch);
                })
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $cashTotal = (float) $rows->sum('paid_amount');
            $orderIds  = $rows->pluck('order_id')->unique();
            $tipMeta   = (float) \App\Model\Order::whereIn('id', $orderIds)->sum('tip_amount');

            // Direct collection: status = received in one shot. Both
            // submitted_at and received_at are now (the cashier did
            // both halves of the handshake).
            // Cashier-initiated collect — same shift attribution as
            // a regular receive(). The collect flow skips the waiter's
            // submit step but the cash still lands in this cashier's
            // till during their shift.
            $shiftId = null;
            try {
                $shiftId = \App\Http\Controllers\Admin\ShiftController::currentFor($admin->id)?->id;
            } catch (\Throwable $e) { /* shifts module not migrated yet */ }

            $h = CashHandover::create([
                'waiter_id'    => $waiter->id,
                'cashier_id'   => $admin->id,
                'shift_id'     => $shiftId,
                'branch_id'    => $effectiveBranch,
                'submitted_at' => now(),
                'received_at'  => now(),
                'total_cash'   => $cashTotal,
                'total_tips'   => $tipMeta,
                'status'       => 'received',
                'notes'        => $note !== '' ? $note : 'Collected by cashier',
            ]);

            \App\Models\OrderPartialPayment::whereIn('id', $rows->pluck('id'))
                ->update(['handover_id' => $h->id]);

            return $h;
        });

        if (!$handover) {
            return back()->with('error', 'Waiter has no unsubmitted cash on the floor.');
        }

        // Push to the waiter — they may not have asked for collection,
        // so without this they'd only learn about it on next refresh.
        \App\CentralLogics\WaiterPushHelper::pushHandoverCollected($handover);

        $name = trim(($waiter->f_name ?? '') . ' ' . ($waiter->l_name ?? '')) ?: 'waiter';
        return back()->with('success',
            'Collected ' . \App\CentralLogics\Helpers::set_symbol($handover->total_cash) .
            ' from ' . $name . ' · added to drawer.'
        );
    }

    /** Operator-flagged mismatch — moves pending → disputed for HQ review. */
    public function dispute(Request $request, int $id): RedirectResponse
    {
        $handover = $this->scoped($request, $id);
        if (!$handover) return back()->with('error', 'Handover not found.');

        $note = trim((string) $request->input('notes', ''));
        $handover->update([
            'cashier_id' => auth('admin')->user()->id,
            'status'     => 'disputed',
            'notes'      => $note !== '' ? $note : ($handover->notes ?? 'Disputed by cashier'),
        ]);
        return back()->with('warning', 'Handover marked as disputed.');
    }

    /** JSON shape for the waiter app's drawer screen. */
    public function pendingJson(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $rows = CashHandover::query()
            ->with(['waiter:id,f_name,l_name'])
            ->where('status', 'pending')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('submitted_at')
            ->get()
            ->map(function (CashHandover $h) {
                return [
                    'id'           => $h->id,
                    'waiter'       => $h->waiter
                        ? trim(($h->waiter->f_name ?? '') . ' ' . ($h->waiter->l_name ?? ''))
                        : 'Unknown',
                    'submitted_at' => $h->submitted_at?->toIso8601String(),
                    'submitted_human' => $h->submitted_at?->diffForHumans(),
                    'total_cash'   => (float) $h->total_cash,
                    'total_tips'   => (float) $h->total_tips,
                    'total'        => (float) $h->total,
                ];
            });

        return response()->json([
            'count' => $rows->count(),
            'rows'  => $rows,
        ]);
    }

    private function scoped(Request $request, int $id): ?CashHandover
    {
        $admin = auth('admin')->user();
        return CashHandover::query()
            ->where('id', $id)
            ->when($admin?->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->first();
    }
}
