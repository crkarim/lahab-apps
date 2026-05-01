<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 8.5d — Cash account list for the waiter app.
 *
 * Returns active accounts visible to the authenticated waiter's branch
 * (branch-scoped + HQ-wide). The Flutter app uses this to populate the
 * "specific account" picker in the checkout screen so floor staff can
 * say "this card payment landed in EBL, that one in DBBL."
 *
 * Output is grouped by `type` (cash / bank / mfs / cheque) so the app
 * can show one section per type without re-bucketing client-side.
 */
class WaiterCashAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        $accounts = CashAccount::query()
            ->where('is_active', true)
            ->where(function ($q) use ($admin) {
                $q->whereNull('branch_id')->orWhere('branch_id', $admin->branch_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'provider', 'account_number', 'color', 'branch_id']);

        // Group by type so the app shows one section per kind.
        $grouped = [];
        foreach ($accounts as $a) {
            $grouped[$a->type][] = [
                'id'             => (int) $a->id,
                'name'           => $a->name,
                'code'           => $a->code,
                'provider'       => $a->provider,
                'account_number' => $a->account_number,
                'color'          => $a->color,
                'is_branch'      => !is_null($a->branch_id),
            ];
        }

        return response()->json([
            'success'  => true,
            'count'    => $accounts->count(),
            'accounts' => $accounts->map(fn ($a) => [
                'id'             => (int) $a->id,
                'name'           => $a->name,
                'code'           => $a->code,
                'type'           => $a->type,
                'provider'       => $a->provider,
                'account_number' => $a->account_number,
                'color'          => $a->color,
                'is_branch'      => !is_null($a->branch_id),
            ])->all(),
            'by_type'  => $grouped,
        ]);
    }
}
