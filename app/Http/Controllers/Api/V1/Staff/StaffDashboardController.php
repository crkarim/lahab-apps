<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpensePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/staff/dashboard/today
 *
 * Snapshot for directors / branch managers viewing the My Lahab app.
 * Read-only — never touches business data, only aggregates.
 *
 * Branch scoping:
 *   - Master Admin (role 1) sees everything (branch_id query param can
 *     narrow if they want).
 *   - Branch managers / staff with `management_dashboard` are pinned to
 *     their own branch; the branch_id query param is ignored for them
 *     so they can never peek at another branch's numbers.
 *
 * Permission gate enforced in the route group (module:management_dashboard).
 * If a staff somehow reaches it without the flag, the controller returns
 * a 403 belt-and-suspenders.
 */
class StaffDashboardController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $isMaster = (int) $admin->admin_role_id === 1;

        $allowed = $this->hasDashboardPermission($admin);
        if (! $allowed) {
            return response()->json(['errors' => [['code' => 'forbidden', 'message' => 'Not allowed.']]], 403);
        }

        $branchId = $isMaster
            ? ($request->query('branch_id') ? (int) $request->query('branch_id') : null)
            : ($admin->branch_id ?: null);

        $today      = now()->startOfDay();
        $tomorrow   = now()->endOfDay();

        // ── Sales: today's confirmed/in-progress/done revenue + count ──
        $excludedStatuses = ['failed', 'canceled', 'refunded', 'refund_requested'];
        $salesQ = DB::table('orders')
            ->whereBetween('created_at', [$today, $tomorrow])
            ->whereNotIn('order_status', $excludedStatuses);
        if ($branchId) $salesQ->where('branch_id', $branchId);

        $totalAmount = (float) (clone $salesQ)->sum('order_amount');
        $orderCount  = (int)   (clone $salesQ)->count();

        // Average order value — guards against /0.
        $avgOrder = $orderCount > 0 ? round($totalAmount / $orderCount, 2) : 0.0;

        // ── Cash position: current_balance per cash account ──
        $cashQ = CashAccount::query()->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
        if ($branchId) $cashQ->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
        $accounts = $cashQ->get(['id', 'name', 'type', 'provider', 'current_balance', 'color']);
        $cashTotal = (float) $accounts->sum('current_balance');

        // ── Today's expenses (paid + unpaid bill totals) ──
        $expQ = Expense::query()->whereDate('bill_date', now()->toDateString());
        if ($branchId) $expQ->where('branch_id', $branchId);
        $expenseTotal = (float) (clone $expQ)->sum('total');
        $expenseCount = (int)   (clone $expQ)->count();

        // Cash actually outflowed today via expense payments — the more
        // honest "spent today" number when bills span days.
        $paidTodayQ = ExpensePayment::query()->whereDate('paid_at', now()->toDateString());
        if ($branchId) {
            $paidTodayQ->whereExists(function ($q) use ($branchId) {
                $q->select(DB::raw(1))->from('expenses')
                  ->whereColumn('expenses.id', 'expense_payments.expense_id')
                  ->where('expenses.branch_id', $branchId);
            });
        }
        $expensePaidToday = (float) $paidTodayQ->sum('amount');

        // ── Top 5 products today by quantity ──
        $topProductsQ = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->whereBetween('o.created_at', [$today, $tomorrow])
            ->whereNotIn('o.order_status', $excludedStatuses)
            ->whereNotNull('od.product_id')
            ->select(
                'od.product_id',
                DB::raw('SUM(od.quantity) as qty'),
                DB::raw('SUM(od.quantity * od.price) as revenue'),
            )
            ->groupBy('od.product_id')
            ->orderByDesc('qty')
            ->limit(5);
        if ($branchId) $topProductsQ->where('o.branch_id', $branchId);
        $topRows = $topProductsQ->get();

        // Resolve product names in a single follow-up query.
        $productNames = $topRows->isEmpty()
            ? collect()
            : DB::table('products')
                ->whereIn('id', $topRows->pluck('product_id'))
                ->pluck('name', 'id');
        $topProducts = $topRows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'name'       => (string) ($productNames[$r->product_id] ?? '—'),
            'qty'        => (int) $r->qty,
            'revenue'    => round((float) $r->revenue, 2),
        ])->values();

        // Branch context for the header.
        $branchName = $branchId
            ? optional(Branch::find($branchId))->name
            : 'All branches';

        // ── Today's checklist performance ──
        $checklistStats = $this->checklistPerformance($branchId, $today);

        return response()->json([
            'as_of'        => now()->toIso8601String(),
            'branch_id'    => $branchId,
            'branch_name'  => $branchName,
            'sales' => [
                'amount'     => round($totalAmount, 2),
                'order_count'=> $orderCount,
                'avg_order'  => $avgOrder,
            ],
            'cash' => [
                'total'    => round($cashTotal, 2),
                'accounts' => $accounts->map(fn ($a) => [
                    'id'      => $a->id,
                    'name'    => $a->name,
                    'type'    => $a->type,
                    'provider'=> $a->provider,
                    'balance' => round((float) $a->current_balance, 2),
                    'color'   => $a->color,
                ])->values(),
            ],
            'expenses' => [
                'bills_today'   => $expenseCount,
                'bills_total'   => round($expenseTotal, 2),
                'paid_today'    => round($expensePaidToday, 2),
            ],
            'top_products'  => $topProducts,
            'checklists'    => $checklistStats,
        ]);
    }

    /**
     * Aggregate today's checklist performance for the dashboard:
     *   - total / completed runs
     *   - on-time vs late item completion rate
     *   - top staff by submissions today
     */
    private function checklistPerformance(?int $branchId, \Carbon\Carbon $today): array
    {
        $runQ = \Illuminate\Support\Facades\DB::table('checklist_runs')
            ->where('run_date', $today->toDateString())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $totalRuns     = (clone $runQ)->count();
        $completedRuns = (clone $runQ)->whereNotNull('completed_at')->count();

        $itemQ = \Illuminate\Support\Facades\DB::table('checklist_run_items as ri')
            ->join('checklist_runs as r', 'r.id', '=', 'ri.run_id')
            ->where('r.run_date', $today->toDateString())
            ->when($branchId, fn ($q) => $q->where('r.branch_id', $branchId));
        $totalItems   = (clone $itemQ)->count();
        $checkedItems = (clone $itemQ)->whereNotNull('ri.checked_at')->count();

        // On-time = checked_at ≤ scheduled_time (same day). Items without
        // a scheduled_time are excluded from the rate (no expectation).
        $scheduledChecked = (clone $itemQ)
            ->whereNotNull('ri.checked_at')
            ->whereNotNull('ri.scheduled_time')
            ->count();
        $onTimeChecked = (clone $itemQ)
            ->whereNotNull('ri.checked_at')
            ->whereNotNull('ri.scheduled_time')
            ->whereRaw('TIME(ri.checked_at) <= ri.scheduled_time')
            ->count();
        $onTimeRate = $scheduledChecked > 0 ? round(100 * $onTimeChecked / $scheduledChecked) : null;

        // Top submitters today (across all checklists in scope).
        $topSubmitters = \Illuminate\Support\Facades\DB::table('checklist_run_submissions as s')
            ->join('checklist_runs as r', 'r.id', '=', 's.run_id')
            ->where('r.run_date', $today->toDateString())
            ->when($branchId, fn ($q) => $q->where('r.branch_id', $branchId))
            ->select(
                's.admin_id',
                \Illuminate\Support\Facades\DB::raw('MIN(s.admin_name) as admin_name'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as submissions'),
            )
            ->groupBy('s.admin_id')
            ->orderByDesc('submissions')
            ->limit(5)
            ->get();

        return [
            'total_runs'      => $totalRuns,
            'completed_runs'  => $completedRuns,
            'total_items'     => $totalItems,
            'checked_items'   => $checkedItems,
            'on_time_rate'    => $onTimeRate,            // 0-100, or null if no scheduled items
            'top_submitters'  => $topSubmitters->map(fn ($s) => [
                'admin_id'    => (int) $s->admin_id,
                'name'        => (string) ($s->admin_name ?: '#' . $s->admin_id),
                'submissions' => (int) $s->submissions,
            ])->values(),
        ];
    }

    private function hasDashboardPermission($admin): bool
    {
        if ((int) $admin->admin_role_id === 1) return true;
        $admin->loadMissing('role');
        $raw = $admin->role?->module_access;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) && in_array('management_dashboard', $decoded, true);
    }
}
