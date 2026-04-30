<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Models\CashHandover;
use App\Models\OrderPartialPayment;
use App\Models\Shift;
use App\Models\ShiftPayout;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cashier shift sessions — admin surface for opening, closing, and
 * reviewing a shift's till reconciliation.
 *
 * The shift module is software-only: there's no kick-drawer hardware
 * on site. "Open shift" just declares opening cash + a session row;
 * "Close shift" recomputes expected cash from live sources and
 * persists the variance against actual_cash counted by the cashier.
 *
 * Branch scope: each cashier holds at most one open shift at a time,
 * and the index/show methods filter by the current admin's branch_id
 * so cashiers in branch A never see branch B's shifts.
 */
class ShiftController extends Controller
{
    /**
     * Pull the open shift for a specific cashier, or null. Used by
     * the order-placement flow to attribute a sale to a shift, and
     * by the open-shift modal to detect prior open sessions.
     */
    public static function currentFor(int $adminId): ?Shift
    {
        return Shift::query()
            ->where('opened_by_admin_id', $adminId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /** Shift list — open at top, then recently-closed for review. */
    public function index(Request $request): Renderable
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $base = Shift::query()
            ->with(['openedBy:id,f_name,l_name', 'closedBy:id,f_name,l_name', 'branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $open = (clone $base)
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->get();

        $recent = (clone $base)
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get();

        // My open shift, if any — drives the "Close my shift" CTA.
        $mine = $admin ? self::currentFor($admin->id) : null;

        return view('admin-views.shift.index', [
            'openShifts' => $open,
            'recent'     => $recent,
            'mine'       => $mine,
        ]);
    }

    /** Show a single shift with its orders, handovers, payouts. */
    public function show(int $id): Renderable|RedirectResponse
    {
        $admin = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $shift = Shift::query()
            ->with(['openedBy:id,f_name,l_name', 'closedBy:id,f_name,l_name', 'branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);

        if (!$shift) {
            return redirect()->route('admin.shifts.index')->with('error', 'Shift not found in your branch.');
        }

        // Live sources for the running till math.
        $orderIds = $shift->orders()->pluck('id');
        $cashSales = (float) OrderPartialPayment::query()
            ->where('paid_with', 'cash')
            ->whereIn('order_id', $orderIds)
            ->sum('paid_amount');
        $handovers = $shift->handovers()
            ->with('waiter:id,f_name,l_name')
            ->where('status', 'received')
            ->orderByDesc('received_at')
            ->get();
        $payouts = $shift->payouts()
            ->with('recordedBy:id,f_name,l_name')
            ->orderByDesc('created_at')
            ->get();
        $expected = (float) $shift->opening_cash + $cashSales + (float) $handovers->sum('total_cash') - (float) $payouts->sum('amount');

        return view('admin-views.shift.show', [
            'shift'        => $shift,
            'orderCount'   => $orderIds->count(),
            'cashSales'    => $cashSales,
            'handovers'    => $handovers,
            'payouts'      => $payouts,
            'expectedCash' => $expected,
        ]);
    }

    /** Open a shift for the current admin. Rejects if one is already open. */
    public function open(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin || !$admin->branch_id) {
            return back()->with('error', 'Your account is not assigned to a branch.');
        }

        $existing = self::currentFor($admin->id);
        if ($existing) {
            return back()->with('error', 'You already have an open shift (#' . $existing->id . '). Close it before opening a new one.');
        }

        $validated = $request->validate([
            'opening_cash' => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        $shift = Shift::create([
            'branch_id'          => $admin->branch_id,
            'opened_by_admin_id' => $admin->id,
            'opened_at'          => now(),
            'opening_cash'       => (float) ($validated['opening_cash'] ?? 0),
            'notes'              => $validated['notes'] ?? null,
            'status'             => 'open',
        ]);

        return redirect()->route('admin.shifts.show', ['id' => $shift->id])
            ->with('success', 'Shift opened — drawer is now live.');
    }

    /**
     * Close the shift. Requires actual_cash so we can persist a
     * variance figure that head office can audit. Once closed, the
     * row is read-only.
     */
    public function close(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        $shift = Shift::query()
            ->when($admin?->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);

        if (!$shift) {
            return back()->with('error', 'Shift not found in your branch.');
        }
        if ($shift->status !== 'open') {
            return back()->with('error', 'Shift is already closed.');
        }

        $validated = $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $expected = $shift->computeExpectedCash();
        $actual   = (float) $validated['actual_cash'];
        $variance = round($actual - $expected, 2);

        $shift->forceFill([
            'closed_by_admin_id' => $admin->id,
            'closed_at'          => now(),
            'expected_cash'      => $expected,
            'actual_cash'        => $actual,
            'variance'           => $variance,
            'notes'              => $validated['notes'] ?? $shift->notes,
            'status'             => 'closed',
        ])->save();

        $varianceTag = $variance == 0 ? 'No variance' : ($variance > 0 ? 'Surplus ' : 'Shortage ');
        return redirect()->route('admin.shifts.show', ['id' => $shift->id])
            ->with('success', 'Shift closed. ' . $varianceTag . ($variance != 0 ? \App\CentralLogics\Helpers::set_symbol(abs($variance)) : ''));
    }

    /**
     * Record a manual cash-out against the shift — refund handed
     * across, supplier petty cash, etc. Reduces the expected_cash so
     * the close-of-shift count reconciles.
     */
    public function payout(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        $shift = Shift::query()
            ->when($admin?->branch_id, fn ($q) => $q->where('branch_id', $admin->branch_id))
            ->find($id);

        if (!$shift) {
            return back()->with('error', 'Shift not found in your branch.');
        }
        if ($shift->status !== 'open') {
            return back()->with('error', 'Cannot record payouts on a closed shift.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        ShiftPayout::create([
            'shift_id'             => $shift->id,
            'amount'               => (float) $validated['amount'],
            'reason'               => $validated['reason'],
            'recorded_by_admin_id' => $admin->id,
        ]);

        return back()->with('success', 'Payout recorded · ৳' . number_format((float) $validated['amount'], 2));
    }
}
