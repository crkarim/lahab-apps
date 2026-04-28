<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Bridges the waiter Passport token to the same `branch_id` Config slot
 * the existing customer-facing API expects (set by BranchAdder for the
 * `branch-id` header). With this in place we can reuse ProductLogic /
 * CategoryLogic / Helpers::* unchanged — their `Config::get('branch_id')`
 * lookups silently resolve to the staff member's branch.
 */
class WaiterBranchScope
{
    public function handle(Request $request, Closure $next)
    {
        $admin = $request->user('waiter_api');
        if (!$admin || !$admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no_branch', 'message' => 'Your account is not assigned to a branch yet.']],
            ], 403);
        }

        Config::set('branch_id', $admin->branch_id);
        return $next($request);
    }
}
