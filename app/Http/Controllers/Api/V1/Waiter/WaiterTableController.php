<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Model\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Waiter-app surface for the table grid. Returns every active table in
 * the authenticated waiter's branch, each annotated with whether it's
 * currently occupied + (when occupied) who owns it and how old the
 * order is. Source of truth = the same Order model the admin POS uses.
 *
 * "Occupied" = there is an open dine-in order on this table that has
 * not been paid AND has not been closed/canceled. Mirrors the admin
 * dashboard's `tables_in_use` definition so the two views agree.
 */
class WaiterTableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $tables = Table::query()
            ->where('branch_id', $admin->branch_id)
            ->where('is_active', 1)
            ->orderBy('zone')
            ->orderBy('number')
            ->get(['id', 'number', 'zone', 'capacity']);

        // One query for all open dine-in orders on this branch — pluck the
        // table_id so the per-row check below is O(1).
        $openOrders = Order::query()
            ->where('branch_id', $admin->branch_id)
            ->where('order_type', 'dine_in')
            ->where('payment_status', '!=', 'paid')
            ->whereNotIn('order_status', ['completed', 'canceled', 'failed'])
            ->whereNotNull('table_id')
            ->with('placedBy:id,f_name,l_name')
            ->get(['id', 'table_id', 'order_status', 'placed_by_admin_id', 'created_at'])
            ->groupBy('table_id');

        $payload = $tables->map(function ($t) use ($openOrders) {
            $rows = $openOrders->get($t->id);
            $row  = $rows?->first();
            return [
                'id'         => $t->id,
                'number'     => $t->number,
                'zone'       => $t->zone,
                'capacity'   => $t->capacity,
                'occupied'   => (bool) $row,
                'order_id'   => $row?->id,
                'order_age'  => $row?->created_at?->diffForHumans(),
                'owner_name' => $row?->placedBy
                    ? trim(($row->placedBy->f_name ?? '') . ' ' . ($row->placedBy->l_name ?? ''))
                    : null,
                'status'     => $row?->order_status,
            ];
        });

        return response()->json([
            'branch_id'   => $admin->branch_id,
            'branch_name' => $admin->branch?->name,
            'tables'      => $payload,
        ]);
    }
}
